<?php
/**
 * Credit balance and alerts (email and SMS).
 *
 * Balance: read from the service (GET /v1/account by default; the path is the
 * ofr_balance_path filter) or, when the service does not report it, estimated
 * locally: credits the admin bought minus try-ons started since then.
 *
 * Alerts: low balance (once per crossing of the threshold), credit exhausted
 * (HTTP 402 from the service), service outage (repeated failures) and an
 * optional daily report. Each goes by email and/or SMS through an Iranian SMS
 * provider or any webhook.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Alerts {
	const DAILY_HOOK = 'ofr_daily_report';

	public static function init() {
		add_action( 'ofr_tryon_started', array( __CLASS__, 'after_start' ) );
		add_action( 'ofr_service_error', array( __CLASS__, 'service_error' ), 10, 2 );
		add_action( OFR_Storage::CLEANUP_HOOK, array( __CLASS__, 'hourly' ) );
		add_action( self::DAILY_HOOK, array( __CLASS__, 'daily_report' ) );
		add_action( 'wp_ajax_ofr_test_alert', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_ofr_refresh_balance', array( __CLASS__, 'ajax_refresh' ) );
	}

	public static function providers() {
		return array(
			'none'        => __( 'بدون پیامک', 'online-fitting-room' ),
			'kavenegar'   => __( 'کاوه‌نگار', 'online-fitting-room' ),
			'smsir'       => __( 'اس‌ام‌اس دات آی‌آر (SMS.ir)', 'online-fitting-room' ),
			'melipayamak' => __( 'ملی‌پیامک', 'online-fitting-room' ),
			'ippanel'     => __( 'آی‌پی‌پنل / فراز اس‌ام‌اس', 'online-fitting-room' ),
			'webhook'     => __( 'وب‌هوک دلخواه (JSON)', 'online-fitting-room' ),
		);
	}

	public static function events() {
		return array(
			'low'    => __( 'کم شدن اعتبار', 'online-fitting-room' ),
			'empty'  => __( 'تمام شدن اعتبار', 'online-fitting-room' ),
			'outage' => __( 'قطعی سرویس پرو', 'online-fitting-room' ),
			'daily'  => __( 'گزارش روزانه', 'online-fitting-room' ),
		);
	}

	public static function schedule() {
		if ( wp_next_scheduled( self::DAILY_HOOK ) ) {
			return;
		}
		// 21:00 site time, the end of a shopping day.
		$tz   = wp_timezone();
		$next = new DateTime( 'today 21:00', $tz );
		if ( $next->getTimestamp() <= time() ) {
			$next->modify( '+1 day' );
		}
		wp_schedule_event( $next->getTimestamp(), 'daily', self::DAILY_HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
	}

	/* ---------- Balance ---------- */

	/**
	 * @return array { value: int|null, source: api|manual|'', time: int, error: string }
	 */
	public static function balance( $refresh = false ) {
		$s = Online_Fitting_Room::settings();
		if ( 'manual' === $s['credit_mode'] ) {
			$bought = (int) $s['credits_bought'];
			if ( $bought <= 0 ) {
				return array(
					'value'  => null,
					'source' => 'manual',
					'time'   => time(),
					'error'  => '',
				);
			}
			return array(
				'value'  => max( 0, $bought - self::used_since( (int) get_option( 'ofr_credit_epoch', 0 ) ) ),
				'source' => 'manual',
				'time'   => time(),
				'error'  => '',
			);
		}
		$cached = get_option( 'ofr_balance' );
		if ( ! $refresh && is_array( $cached ) && $cached['time'] > time() - 15 * MINUTE_IN_SECONDS ) {
			return $cached;
		}
		$fresh = self::fetch_balance();
		// Keep the last known value when the service is unreachable for a moment.
		if ( null === $fresh['value'] && is_array( $cached ) && null !== $cached['value'] ) {
			$fresh['value'] = $cached['value'];
		}
		update_option( 'ofr_balance', $fresh, false );
		return $fresh;
	}

	/** Try-ons started since a moment (each one uses a credit). */
	private static function used_since( $time ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . OFR_DB::table( 'events' ) . " WHERE type = 'start' AND created >= %d", $time ) );
	}

	private static function fetch_balance() {
		if ( '' === OFR_Api::key() ) {
			return array(
				'value'  => null,
				'source' => 'api',
				'time'   => time(),
				'error'  => __( 'کلید API ثبت نشده است.', 'online-fitting-room' ),
			);
		}
		OFR_Api::start_budget();
		$response = OFR_Api::request( 'GET', (string) apply_filters( 'ofr_balance_path', '/v1/account' ), array(), 15 );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'value'  => null,
				'source' => 'api',
				'time'   => time(),
				'error'  => is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code,
			);
		}
		$value = self::find_number( json_decode( wp_remote_retrieve_body( $response ), true ) );
		return array(
			'value'  => $value,
			'source' => 'api',
			'time'   => time(),
			'error'  => null === $value ? __( 'پاسخ سرویس شامل مقدار اعتبار نبود.', 'online-fitting-room' ) : '',
		);
	}

	/** First balance-like number in a JSON answer ({balance}, {credits}, {data:{remaining}}, …). */
	public static function find_number( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}
		foreach ( array( 'credits_remaining', 'remaining_credits', 'balance', 'credits', 'credit', 'remaining' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) {
				return (int) floor( (float) $data[ $key ] );
			}
		}
		foreach ( array( 'data', 'account', 'wallet', 'usage' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				$found = self::find_number( $data[ $key ] );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/** Called when the admin changes «اعتبار خریداری‌شده»: counting starts again from now. */
	public static function reset_manual() {
		update_option( 'ofr_credit_epoch', time(), false );
		delete_option( 'ofr_alert_low_sent' );
	}

	/* ---------- Triggers ---------- */

	public static function after_start() {
		$s = Online_Fitting_Room::settings();
		if ( 'manual' !== $s['credit_mode'] ) {
			// The cached API value goes down with each start until the next refresh.
			$cached = get_option( 'ofr_balance' );
			if ( is_array( $cached ) && null !== $cached['value'] ) {
				$cached['value'] = max( 0, (int) $cached['value'] - 1 );
				update_option( 'ofr_balance', $cached, false );
			}
		}
		self::check_low();
	}

	public static function check_low() {
		$s       = Online_Fitting_Room::settings();
		$balance = self::balance();
		if ( null === $balance['value'] ) {
			return;
		}
		$threshold = (int) $s['credits_threshold'];
		if ( $balance['value'] > $threshold ) {
			delete_option( 'ofr_alert_low_sent' ); // Re-arm after a top-up.
			return;
		}
		if ( get_option( 'ofr_alert_low_sent' ) ) {
			return;
		}
		update_option( 'ofr_alert_low_sent', time(), false );
		/* translators: 1: remaining credits, 2: site name. */
		self::notify( 'low', sprintf( __( 'اتاق پرو %2$s: اعتبار سرویس رو به پایان است؛ %1$s پرو باقی مانده. برای شارژ با پشتیبانی تماس بگیرید.', 'online-fitting-room' ), number_format_i18n( $balance['value'] ), self::site() ) );
	}

	public static function service_error( $context, $code ) {
		if ( 402 === (int) $code && self::throttle( 'empty', 6 * HOUR_IN_SECONDS ) ) {
			$cached = get_option( 'ofr_balance' );
			if ( is_array( $cached ) ) {
				$cached['value'] = 0;
				update_option( 'ofr_balance', $cached, false );
			}
			/* translators: %s: site name. */
			self::notify( 'empty', sprintf( __( 'اتاق پرو %s: اعتبار سرویس تمام شده و مشتری‌ها نمی‌توانند پرو کنند. لطفاً حساب را شارژ کنید.', 'online-fitting-room' ), self::site() ) );
			return;
		}
		$transport = 0 === (int) $code || (int) $code >= 500;
		if ( $transport && 0 === strpos( (string) $context, 'start' ) && OFR_Log::count_since( 15 * MINUTE_IN_SECONDS, 'start' ) >= 5 && self::throttle( 'outage', 3 * HOUR_IN_SECONDS ) ) {
			/* translators: %s: site name. */
			self::notify( 'outage', sprintf( __( 'اتاق پرو %s: چند پروی پشت‌سرهم به سرویس نرسید. اتصال هاست و وضعیت سرویس را بررسی کنید (تنظیمات ← گزارش خطا).', 'online-fitting-room' ), self::site() ) );
		}
	}

	public static function hourly() {
		if ( 'api' === Online_Fitting_Room::settings()['credit_mode'] ) {
			self::balance( true );
		}
		self::check_low();
	}

	public static function daily_report() {
		$sum     = OFR_Stats::summary( 1 );
		$balance = self::balance();
		/* translators: 1: site name, 2: try-ons, 3: add-to-carts, 4: orders. */
		$text = sprintf( __( 'گزارش امروز اتاق پرو %1$s: %2$s پرو، %3$s افزودن به سبد پس از پرو، %4$s سفارش.', 'online-fitting-room' ), self::site(), number_format_i18n( $sum['successes'] ), number_format_i18n( $sum['carts'] ), number_format_i18n( $sum['orders'] ) );
		if ( null !== $balance['value'] ) {
			/* translators: %s: remaining credits. */
			$text .= ' ' . sprintf( __( 'اعتبار باقی‌مانده: %s.', 'online-fitting-room' ), number_format_i18n( $balance['value'] ) );
		}
		self::notify( 'daily', $text );
	}

	/** True (and remembers now) when $event was not sent within $seconds. */
	private static function throttle( $event, $seconds ) {
		$last = (array) get_option( 'ofr_alert_times', array() );
		if ( ! empty( $last[ $event ] ) && $last[ $event ] > time() - $seconds ) {
			return false;
		}
		$last[ $event ] = time();
		update_option( 'ofr_alert_times', $last, false );
		return true;
	}

	private static function site() {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/* ---------- Delivery ---------- */

	/** Sends an alert on every channel enabled for $event; returns per-channel results. */
	public static function notify( $event, $text ) {
		$s       = Online_Fitting_Room::settings();
		$enabled = array_map( 'trim', explode( ',', (string) $s['alert_events'] ) );
		if ( ! in_array( $event, $enabled, true ) ) {
			return array();
		}
		$text    = (string) apply_filters( 'ofr_alert_text', $text, $event );
		$results = array();
		if ( 'yes' === $s['alert_email'] ) {
			$results['email'] = self::send_email( $text );
		}
		if ( 'none' !== $s['sms_provider'] ) {
			$results['sms'] = self::send_sms( $text );
		}
		self::remember( $event, $text, $results );
		do_action( 'ofr_alert_sent', $event, $text, $results );
		return $results;
	}

	public static function email_recipients() {
		$list = Online_Fitting_Room::settings()['alert_emails'];
		$list = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', (string) $list ) ), 'is_email' );
		return $list ?: array( get_option( 'admin_email' ) );
	}

	public static function send_email( $text ) {
		/* translators: %s: site name. */
		$ok = wp_mail( self::email_recipients(), sprintf( __( '[%s] اتاق پُرُو آنلاین', 'online-fitting-room' ), self::site() ), $text );
		return array(
			'ok'     => (bool) $ok,
			'detail' => $ok ? '' : __( 'ارسال ایمیل انجام نشد (تنظیمات ایمیل سایت را بررسی کنید).', 'online-fitting-room' ),
		);
	}

	/** Persian/Arabic digits to Latin, spaces and dashes removed; +98/0098 kept as 0. */
	public static function normalize_mobile( $number ) {
		$number = strtr(
			(string) $number,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
		$number = preg_replace( '/[^\d+]/', '', $number );
		$number = preg_replace( '/^(\+98|0098)/', '0', $number );
		if ( preg_match( '/^9\d{9}$/', $number ) ) {
			$number = '0' . $number;
		}
		return $number;
	}

	public static function sms_recipients() {
		$list = preg_split( '/[\s,;]+/', (string) Online_Fitting_Room::settings()['sms_recipients'] );
		return array_values(
			array_filter(
				array_map( array( __CLASS__, 'normalize_mobile' ), $list ),
				function ( $n ) {
					return (bool) preg_match( '/^0\d{10}$/', $n );
				}
			)
		);
	}

	public static function send_sms( $text ) {
		$s          = Online_Fitting_Room::settings();
		$recipients = self::sms_recipients();
		$key        = OFR_Api::decrypt_key( $s['sms_key'] );
		if ( ! $recipients ) {
			return array(
				'ok'     => false,
				'detail' => __( 'شماره موبایل گیرنده ثبت نشده است.', 'online-fitting-room' ),
			);
		}
		if ( 'webhook' !== $s['sms_provider'] && ! $key ) {
			return array(
				'ok'     => false,
				'detail' => __( 'کلید API پنل پیامک ثبت نشده است.', 'online-fitting-room' ),
			);
		}
		$sender = trim( (string) $s['sms_sender'] );
		$json   = function ( $url, $body, $headers = array() ) {
			return wp_remote_post(
				$url,
				array(
					'timeout' => 15,
					'headers' => array_merge(
						array(
							'Content-Type' => 'application/json',
							'Accept'       => 'application/json',
						),
						$headers
					),
					'body'    => wp_json_encode( $body ),
				)
			);
		};
		switch ( $s['sms_provider'] ) {
			case 'kavenegar':
				$url      = 'https://api.kavenegar.com/v1/' . rawurlencode( $key ) . '/sms/send.json';
				$response = wp_remote_post(
					$url,
					array(
						'timeout' => 15,
						'body'    => array_filter(
							array(
								'receptor' => implode( ',', $recipients ),
								'sender'   => $sender,
								'message'  => $text,
							)
						),
					)
				);
				$ok       = function ( $data ) {
					return 200 === (int) ( $data['return']['status'] ?? 0 );
				};
				break;
			case 'smsir':
				$response = $json(
					'https://api.sms.ir/v1/send/bulk',
					array(
						'lineNumber'  => (int) preg_replace( '/\D/', '', $sender ),
						'messageText' => $text,
						'mobiles'     => $recipients,
					),
					array( 'X-API-KEY' => $key )
				);
				$ok       = function ( $data ) {
					return 1 === (int) ( $data['status'] ?? 0 );
				};
				break;
			case 'melipayamak':
				$response = null;
				foreach ( $recipients as $to ) {
					$response = $json(
						'https://console.melipayamak.com/api/send/simple/' . rawurlencode( $key ),
						array(
							'from' => $sender,
							'to'   => $to,
							'text' => $text,
						)
					);
				}
				$ok = function ( $data ) {
					return ! empty( $data['recId'] ) || ( isset( $data['status'] ) && false !== stripos( (string) $data['status'], 'success' ) ) || ( isset( $data['status'] ) && 'ارسال موفق بود' === $data['status'] );
				};
				break;
			case 'ippanel':
				$response = $json(
					'https://api2.ippanel.com/api/v1/sms/send/webservice/single',
					array(
						'recipient' => array_map(
							function ( $n ) {
								return '+98' . substr( $n, 1 );
							},
							$recipients
						),
						'sender'    => $sender,
						'message'   => $text,
					),
					array( 'apikey' => $key )
				);
				$ok       = function ( $data ) {
					return 'OK' === strtoupper( (string) ( $data['status'] ?? '' ) );
				};
				break;
			case 'webhook':
				$url = (string) $s['sms_webhook'];
				if ( ! wp_http_validate_url( $url ) ) {
					return array(
						'ok'     => false,
						'detail' => __( 'آدرس وب‌هوک معتبر نیست.', 'online-fitting-room' ),
					);
				}
				$response = $json(
					$url,
					array(
						'message'    => $text,
						'recipients' => $recipients,
						'site'       => home_url(),
					),
					$key ? array( 'Authorization' => 'Bearer ' . $key ) : array()
				);
				$ok       = function () {
					return true;
				};
				break;
			default:
				return array(
					'ok'     => false,
					'detail' => '',
				);
		}
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'detail' => $response->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$good = $code >= 200 && $code < 300 && $ok( is_array( $data ) ? $data : array() );
		if ( ! $good ) {
			OFR_Log::error( 'sms ' . $s['sms_provider'], $code, mb_substr( wp_remote_retrieve_body( $response ), 0, 400 ) );
		}
		return array(
			'ok'     => $good,
			'detail' => $good ? '' : 'HTTP ' . $code . ' ' . mb_substr( wp_strip_all_tags( wp_remote_retrieve_body( $response ) ), 0, 200 ),
		);
	}

	private static function remember( $event, $text, $results ) {
		$log = (array) get_option( 'ofr_alert_log', array() );
		array_unshift(
			$log,
			array(
				'time'    => time(),
				'event'   => $event,
				'text'    => $text,
				'results' => $results,
			)
		);
		update_option( 'ofr_alert_log', array_slice( $log, 0, 20 ), false );
	}

	/* ---------- Admin actions ---------- */

	private static function guard() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'ofr_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی مجاز نیست.', 'online-fitting-room' ) ), 403 );
		}
	}

	public static function ajax_test() {
		self::guard();
		$channel = 'sms' === ( $_POST['channel'] ?? '' ) ? 'sms' : 'email'; // phpcs:ignore WordPress.Security.NonceVerification -- guard() checks it.
		/* translators: %s: site name. */
		$text   = sprintf( __( 'پیام آزمایشی اتاق پُرُو آنلاین از %s — اعلان‌ها درست تنظیم شده‌اند.', 'online-fitting-room' ), self::site() );
		$result = 'sms' === $channel ? self::send_sms( $text ) : self::send_email( $text );
		self::remember( 'test', $text, array( $channel => $result ) );
		$result['ok']
			? wp_send_json_success( array( 'message' => 'sms' === $channel ? __( 'پیامک آزمایشی ارسال شد.', 'online-fitting-room' ) : __( 'ایمیل آزمایشی ارسال شد.', 'online-fitting-room' ) ) )
			: wp_send_json_error( array( 'message' => $result['detail'] ) );
	}

	public static function ajax_refresh() {
		self::guard();
		$balance = self::balance( true );
		null === $balance['value']
			? wp_send_json_error( array( 'message' => $balance['error'] ?: __( 'اعتبار مشخص نیست.', 'online-fitting-room' ) ) )
			: wp_send_json_success(
				array(
					/* translators: %s: remaining credits. */
					'message' => sprintf( __( 'اعتبار باقی‌مانده: %s پرو', 'online-fitting-room' ), number_format_i18n( $balance['value'] ) ),
					'value'   => $balance['value'],
				)
			);
	}
}
