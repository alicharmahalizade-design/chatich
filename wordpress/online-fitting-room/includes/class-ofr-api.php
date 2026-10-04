<?php
/**
 * Try-on API client (مربع API): authentication, time budget, retries, garment
 * preparation and mapping upstream errors to customer-safe messages.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Api {
	const BASE              = 'https://vton-api.ayaitech.com';
	const GARMENT_MAX_BYTES = 8388608;  // API limit for the garment image: 8 MB.
	const PERSON_MAX_BYTES  = 26214400; // API limit for the person image: 25 MB.
	const ALLOWED_MIMES     = array( 'image/jpeg', 'image/png', 'image/webp' );

	const KEY_PREFIX = 'enc:v1:';

	private static $deadline = null;

	/**
	 * Service address. Hosts whose outbound traffic is filtered (e.g. inside
	 * Iran) can point it at a relay/reverse proxy: OFR_API_BASE in
	 * wp-config.php, the «آدرس relay» setting, or the ofr_api_base filter.
	 * Only https addresses are accepted.
	 */
	public static function base() {
		$base = defined( 'OFR_API_BASE' ) && is_string( OFR_API_BASE ) ? OFR_API_BASE : '';
		if ( '' === $base && class_exists( 'Online_Fitting_Room' ) ) {
			$base = (string) ( Online_Fitting_Room::settings()['api_base'] ?? '' );
		}
		$base = (string) apply_filters( 'ofr_api_base', $base ?: self::BASE );
		return self::valid_base( $base ) ? untrailingslashit( $base ) : self::BASE;
	}

	public static function valid_base( $url ) {
		$parts = wp_parse_url( (string) $url );
		return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && ! empty( $parts['host'] ) && empty( $parts['query'] ) && empty( $parts['user'] );
	}

	public static function uses_relay() {
		return self::base() !== self::BASE;
	}

	/* ---------- API key at rest ---------- */

	private static function secret() {
		return hash( 'sha256', 'ofr-api-key|' . wp_salt( 'auth' ), true );
	}

	/**
	 * The key saved from the settings page is encrypted with the site's own
	 * salts (wp-config.php), so a database dump or backup alone does not reveal
	 * it. Keys defined in wp-config.php or the environment are never stored.
	 */
	public static function encrypt_key( $key ) {
		if ( '' === $key ) {
			return '';
		}
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return self::KEY_PREFIX . 's.' . base64_encode( $nonce . sodium_crypto_secretbox( $key, $nonce, self::secret() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = random_bytes( 12 );
			$tag = '';
			$ct  = openssl_encrypt( $key, 'aes-256-gcm', self::secret(), OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
			if ( false !== $ct ) {
				return self::KEY_PREFIX . 'g.' . base64_encode( $iv . $tag . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}
		return $key; // No cipher on this server: stored as before.
	}

	/** @return string|false The plain key, or false when it cannot be decrypted (the salts in wp-config.php changed). */
	public static function decrypt_key( $stored ) {
		$stored = (string) $stored;
		if ( 0 !== strpos( $stored, self::KEY_PREFIX ) ) {
			return $stored;
		}
		$alg  = substr( $stored, strlen( self::KEY_PREFIX ), 1 );
		$data = base64_decode( substr( $stored, strlen( self::KEY_PREFIX ) + 2 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $data ) {
			return false;
		}
		if ( 's' === $alg && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			return sodium_crypto_secretbox_open( substr( $data, $n ), substr( $data, 0, $n ), self::secret() );
		}
		if ( 'g' === $alg && function_exists( 'openssl_decrypt' ) ) {
			return openssl_decrypt( substr( $data, 28 ), 'aes-256-gcm', self::secret(), OPENSSL_RAW_DATA, substr( $data, 0, 12 ), substr( $data, 12, 16 ) );
		}
		return false;
	}

	/** True when a key is saved but can no longer be decrypted. */
	public static function key_unreadable() {
		$stored = (string) ( Online_Fitting_Room::settings()['api_key'] ?? '' );
		return '' !== $stored && false === self::decrypt_key( $stored );
	}

	/**
	 * Keys pasted from a dashboard often carry invisible characters (RTL marks,
	 * zero-width joiners, non-breaking spaces), line breaks, quotes or a
	 * "Bearer " prefix. API keys are plain ASCII, so everything else goes.
	 */
	public static function clean_key( $key ) {
		$key = preg_replace( '/[^\x21-\x7E]/', '', (string) $key ); // Drops every non-ASCII byte, space and control character.
		$key = trim( $key, "\"'`" );
		return preg_replace( '/^Bearer(?=vton_|.{20,})/i', '', $key );
	}

	/** @return array { key, source } — source is 'constant', 'env', 'settings' or ''. */
	private static function resolve() {
		foreach ( array( 'OFR_API_KEY', 'VTON_API_KEY' ) as $constant ) {
			$value = defined( $constant ) && is_string( constant( $constant ) ) ? self::clean_key( constant( $constant ) ) : '';
			if ( '' !== $value ) {
				return array(
					'key'    => $value,
					'source' => 'constant',
				);
			}
		}
		$environment = getenv( 'VTON_API_KEY' );
		$environment = is_string( $environment ) ? self::clean_key( $environment ) : '';
		if ( '' !== $environment ) {
			return array(
				'key'    => $environment,
				'source' => 'env',
			);
		}
		$stored = self::decrypt_key( Online_Fitting_Room::settings()['api_key'] ?? '' );
		$stored = false === $stored ? '' : self::clean_key( $stored );
		return array(
			'key'    => $stored,
			'source' => '' !== $stored ? 'settings' : '',
		);
	}

	/** Key lookup order: wp-config constant, server environment, settings page. */
	public static function key() {
		return self::resolve()['key'];
	}

	public static function key_source() {
		return self::resolve()['source'];
	}

	public static function masked_key() {
		$key = self::key();
		if ( '' === $key ) {
			return '';
		}
		return strlen( $key ) <= 12 ? substr( $key, 0, 3 ) . '…' : substr( $key, 0, 10 ) . '…' . substr( $key, -4 );
	}

	/** API dashboards list keys by an ID such as key_01ABC…; only the raw secret authenticates. */
	public static function is_key_id( $key ) {
		return 0 === strpos( (string) $key, 'key_' );
	}

	/** Where store owners get their key: one sentence reused wherever the key is mentioned. */
	public static function support_text() {
		return __( 'برای دریافت کلید مربع API با پشتیبانی محصول در راست‌چین در ارتباط باشید.', 'online-fitting-room' );
	}

	/** Admin hint for keys that are probably not the raw secret. */
	public static function key_warning() {
		$key = self::key();
		if ( '' === $key ) {
			return '';
		}
		if ( self::is_key_id( $key ) ) {
			return __( 'این مقدار شناسه کلید است (با key_ شروع می‌شود)، نه خود کلید؛ کلید اصلی با vton_live_ شروع می‌شود.', 'online-fitting-room' ) . ' ' . self::support_text();
		}
		if ( 0 !== strpos( $key, 'vton_' ) ) {
			return __( 'کلید ذخیره‌شده با vton_ شروع نمی‌شود. اگر «تست اتصال» خطا داد، مطمئن شوید کلید را کامل وارد کرده‌اید.', 'online-fitting-room' ) . ' ' . self::support_text();
		}
		return '';
	}

	/**
	 * Every AJAX request gets a wall-clock budget below PHP's max_execution_time,
	 * so a slow upstream call ends with a clean JSON error instead of a fatal
	 * "Maximum execution time exceeded" (and an empty response) on shared hosts.
	 */
	public static function start_budget() {
		if ( null !== self::$deadline ) {
			return;
		}
		if ( function_exists( 'set_time_limit' ) && false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 90 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- some hosts forbid it; the budget below still applies.
		}
		$max            = (int) ini_get( 'max_execution_time' );
		$budget         = $max <= 0 ? 85 : max( 10, $max - 5 );
		$started        = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
		self::$deadline = $started + $budget;
	}

	public static function remaining() {
		self::start_budget();
		return self::$deadline - microtime( true );
	}

	/**
	 * Ways to reach the API, tried in order until one gets an HTTP answer:
	 *  - compat:    HTTP/1.1, no compression, one request per connection (works
	 *               around the API's proxy closing HTTP/2 or compressed responses early);
	 *  - compat_v4: the same over IPv4 only (broken IPv6 routes on some hosts);
	 *  - default:   cURL's own negotiation, IPv4 only.
	 * The profile that last worked is tried first. POST retries are safe: the
	 * idempotency key and body do not change.
	 */
	const TRANSPORTS = array( 'compat', 'compat_v4', 'default' );

	private static $transport = 'compat';

	/** Transport errors worth another attempt: the connection dropped or the TLS handshake failed. */
	private static function is_transient( $error ) {
		$message = $error->get_error_message();
		return (bool) preg_match( '/cURL error (18|35|52|55|56)\b|closed abruptly|connection reset|empty reply|unexpected eof/i', $message );
	}

	private static function transports() {
		$last  = get_option( 'ofr_transport' );
		$order = self::TRANSPORTS;
		if ( in_array( $last, $order, true ) ) {
			$order = array_values( array_unique( array_merge( array( $last ), $order ) ) );
		}
		return $order;
	}

	/** Unauthenticated, low-level request with the transport fallbacks (also used to test reachability). */
	private static function send( $url, $args, $max_timeout ) {
		$response = null;
		foreach ( self::transports() as $i => $transport ) {
			$timeout = min( $max_timeout, (int) floor( self::remaining() ) - 2 );
			if ( $timeout < 5 ) {
				$response = $response ?: new WP_Error( 'ofr_time_budget', 'Not enough PHP execution time left for the API request.' );
				break;
			}
			if ( $i > 0 ) {
				usleep( 300000 );
			}
			$attempt            = $args;
			$attempt['timeout'] = $timeout;
			if ( 'default' !== $transport ) {
				$attempt['httpversion'] = '1.1';
				$attempt['decompress']  = false;
				$attempt['headers']     = array_merge(
					array(
						'Accept-Encoding' => 'identity',
						'Connection'      => 'close',
					),
					$attempt['headers'] ?? array()
				);
			}
			self::$transport = $transport;
			add_action( 'http_api_curl', array( __CLASS__, 'configure_curl' ), 10, 3 );
			$response = wp_remote_request( $url, $attempt );
			remove_action( 'http_api_curl', array( __CLASS__, 'configure_curl' ), 10 );
			if ( ! is_wp_error( $response ) ) {
				if ( get_option( 'ofr_transport' ) !== $transport ) {
					update_option( 'ofr_transport', $transport, false );
				}
				break;
			}
			if ( ! self::is_transient( $response ) ) {
				break;
			}
		}
		return $response;
	}

	public static function request( $method, $path, $args = array(), $max_timeout = 45 ) {
		$key = self::key();
		if ( '' === $key ) {
			return new WP_Error( 'ofr_no_key', 'No API key is configured.' );
		}
		$args['method']      = $method;
		$args['redirection'] = 0;
		// "Expect:" stops cURL's 100-continue handshake on uploads, which some proxies mishandle.
		$args['headers'] = array_merge(
			array(
				'Authorization' => 'Bearer ' . $key,
				'Accept'        => 'application/json',
				'Expect'        => '',
			),
			$args['headers'] ?? array()
		);
		$response        = self::send( self::base() . $path, $args, $max_timeout );
		$code            = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			self::remember_success();
		}
		return $response;
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt -- adjusts the handle WordPress itself created (http_api_curl), for the transport fallbacks.
	public static function configure_curl( $handle, $request, $url ) {
		if ( 0 !== strpos( $url, self::base() ) ) {
			return;
		}
		if ( 'compat' !== self::$transport && defined( 'CURLOPT_IPRESOLVE' ) ) {
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		}
		if ( 'default' === self::$transport ) {
			return;
		}
		if ( defined( 'CURLOPT_HTTP_VERSION' ) && defined( 'CURL_HTTP_VERSION_1_1' ) ) {
			curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		}
		if ( defined( 'CURLOPT_ENCODING' ) ) {
			curl_setopt( $handle, CURLOPT_ENCODING, 'identity' );
		}
	}
	// phpcs:enable

	/** Admin explanation of a transport error, by cURL error number. */
	private static function network_message( WP_Error $error ) {
		preg_match( '/cURL error (\d+)/', $error->get_error_message(), $m );
		$code = (int) ( $m[1] ?? 0 );
		$host = __( 'مربع API', 'online-fitting-room' );
		switch ( true ) {
			case 6 === $code:
				/* translators: %s: host name. */
				return sprintf( __( 'سرور سایت آدرس %s را پیدا نکرد (خطای DNS). از پشتیبانی هاست بخواهید DNS سرور را بررسی کند.', 'online-fitting-room' ), $host );
			case 7 === $code:
				/* translators: %s: host name. */
				return sprintf( __( 'هاست اجازه اتصال به %s را نمی‌دهد. از پشتیبانی هاست بخواهید اتصال خروجی به این دامنه روی پورت ۴۴۳ (HTTPS) را باز کند.', 'online-fitting-room' ), $host );
			case 28 === $code:
				/* translators: %s: host name. */
				return sprintf( __( 'از %s در زمان مجاز پاسخی نیامد. ممکن است فایروال هاست اتصال را بی‌صدا مسدود کرده باشد یا مربع API در دسترس نباشد.', 'online-fitting-room' ), $host );
			case in_array( $code, array( 35, 60, 77 ), true ):
				return __( 'ارتباط امن (SSL) با مربع API برقرار نشد. معمولاً نسخه OpenSSL/cURL یا گواهی‌های ریشه هاست قدیمی است؛ از پشتیبانی هاست بخواهید آن‌ها را به‌روز کند.', 'online-fitting-room' );
			case in_array( $code, array( 18, 52, 55, 56 ), true ):
				/* translators: %s: host name. */
				return sprintf( __( 'اتصال به %s برقرار شد ولی پیش از رسیدن پاسخ قطع شد؛ افزونه چند روش اتصال را امتحان کرد. این معمولاً کار فایروال یا فیلترینگ مسیر شبکه هاست است (مثلاً هاست داخل ایران و سرویس خارج از ایران). از پشتیبانی هاست بپرسید آیا اتصال HTTPS به این دامنه مسدود یا محدود می‌شود؛ اگر هاست مشکلی نمی‌بیند، موضوع را با پشتیبانی محصول در راست‌چین مطرح کنید.', 'online-fitting-room' ), $host );
			default:
				return __( 'سرور سایت به مربع API وصل نشد.', 'online-fitting-room' );
		}
	}

	/**
	 * @param array $person  { bytes, mime }
	 * @param array $garment { path, mime }
	 * @param array $fields  Extra form fields (category, mode).
	 */
	public static function create_tryon( $person, $garment, $fields, $idempotency_key, $reference ) {
		$garment_bytes = file_get_contents( $garment['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $garment_bytes ) {
			return new WP_Error( 'ofr_garment_read', 'Could not read the garment image.' );
		}
		$boundary = '----OnlineFittingRoom' . wp_generate_password( 24, false, false );
		$body     = self::file_part( $boundary, 'person_image', 'person.' . self::extension( $person['mime'] ), $person['mime'], $person['bytes'] );
		$body    .= self::file_part( $boundary, 'garment_image', 'garment.' . self::extension( $garment['mime'] ), $garment['mime'], $garment_bytes );
		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . "\r\n" . 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n" . $value . "\r\n";
		}
		$body .= '--' . $boundary . "--\r\n";
		return self::request(
			'POST',
			'/v1/try-ons',
			array(
				'headers' => array(
					'Idempotency-Key'       => $idempotency_key,
					'X-Client-Reference-Id' => $reference,
					'Content-Type'          => 'multipart/form-data; boundary=' . $boundary,
					'Content-Length'        => (string) strlen( $body ),
				),
				'body'    => $body,
			)
		);
	}

	public static function get_tryon( $prediction_id ) {
		return self::request( 'GET', '/v1/try-ons/' . rawurlencode( $prediction_id ), array(), 20 );
	}

	public static function get_result( $prediction_id ) {
		return self::request( 'GET', '/v1/try-ons/' . rawurlencode( $prediction_id ) . '/result', array(), 40 );
	}

	/** Short fingerprint, so remembered facts are only trusted for the key they were made with. */
	private static function key_fingerprint() {
		return substr( hash( 'sha256', self::key() ), 0, 16 );
	}

	/** Called after every successful authenticated call: proof that the current key works. */
	private static function remember_success() {
		$last = get_option( 'ofr_last_ok' );
		if ( ! is_array( $last ) || self::key_fingerprint() !== $last['key'] || $last['time'] < time() - MINUTE_IN_SECONDS ) {
			update_option(
				'ofr_last_ok',
				array(
					'time' => time(),
					'key'  => self::key_fingerprint(),
				),
				false
			);
		}
	}

	/** The newest real try-on, which the connection test can look up for free. */
	public static function remember_prediction( $prediction_id ) {
		update_option(
			'ofr_last_prediction',
			array(
				'id'  => $prediction_id,
				'key' => self::key_fingerprint(),
			),
			false
		);
	}

	/**
	 * Free check that sends only requests real try-ons send:
	 * 1. Reachability: the public documentation page, with no key.
	 * 2. Key: the status of this store's latest real try-on (costs nothing).
	 *    The service cuts the connection when asked about a try-on that does
	 *    not exist, so no made-up lookup is ever sent.
	 * 3. Without such a try-on: the time the current key last succeeded, or a
	 *    note that the first real try-on will confirm it.
	 *
	 * @return array { ok: bool, message: string }
	 */
	public static function test_connection() {
		$key_id = self::is_key_id( self::key() );
		$reach  = self::send(
			self::base() . '/developer/docs',
			array(
				'method'      => 'GET',
				'redirection' => 0,
			),
			15
		);
		if ( is_wp_error( $reach ) ) {
			self::log_error( 'connection test (reachability)', 0, $reach->get_error_message() );
			$message = self::network_message( $reach ) . ' — ' . $reach->get_error_message();
			return array(
				'ok'      => false,
				'message' => $key_id ? self::key_warning() . ' ' . $message : $message,
			);
		}
		if ( '' === self::key() ) {
			return array(
				'ok'      => false,
				'message' => __( 'سرور مربع API در دسترس است، ولی هیچ کلید API تنظیم نشده است.', 'online-fitting-room' ),
			);
		}
		if ( $key_id ) {
			return array(
				'ok'      => false,
				'message' => __( 'سرور مربع API در دسترس است، ولی کلید ثبت‌شده قابل استفاده نیست.', 'online-fitting-room' ) . ' ' . self::key_warning(),
			);
		}

		$fingerprint = self::key_fingerprint();
		$prediction  = get_option( 'ofr_last_prediction' );
		if ( is_array( $prediction ) && $prediction['key'] === $fingerprint && '' !== (string) $prediction['id'] ) {
			$response = self::get_tryon( $prediction['id'] );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				self::clear_test_error();
				return array(
					'ok'      => true,
					'message' => __( 'اتصال برقرار است و مربع API کلید را پذیرفت.', 'online-fitting-room' ),
				);
			}
			if ( 401 === $code || 403 === $code ) {
				return array(
					'ok'      => false,
					'message' => __( 'سرور مربع API در دسترس است، ولی کلید را نپذیرفت. کلید را کامل و بدون تغییر وارد کنید.', 'online-fitting-room' ) . ' ' . self::support_text(),
				);
			}
			if ( 402 === $code ) {
				return array(
					'ok'      => false,
					'message' => __( 'کلید معتبر است ولی اعتبار حساب مربع API تمام شده است.', 'online-fitting-room' ),
				);
			}
			// The try-on may have expired on the service side; fall back to what is already known.
			delete_option( 'ofr_last_prediction' );
		}

		$last_ok = get_option( 'ofr_last_ok' );
		self::clear_test_error();
		if ( is_array( $last_ok ) && $last_ok['key'] === $fingerprint ) {
			return array(
				'ok'      => true,
				/* translators: %s: time span such as "2 hours". */
				'message' => sprintf( __( 'سرور مربع API در دسترس است و آخرین درخواست با این کلید %s پیش موفق بوده است.', 'online-fitting-room' ), human_time_diff( $last_ok['time'] ) ),
			);
		}
		return array(
			'ok'      => true,
			'message' => __( 'سرور مربع API در دسترس است و کلید ثبت شده. کلید با اولین پرو واقعی تأیید می‌شود؛ پس از آن، این تست وضعیت کلید را هم بررسی می‌کند.', 'online-fitting-room' ),
		);
	}

	/** Errors produced by earlier versions of this test are not service errors; drop them once the test passes. */
	private static function clear_test_error() {
		$last = get_option( 'ofr_last_error' );
		if ( is_array( $last ) && 0 === strpos( (string) $last['context'], 'connection test' ) ) {
			delete_option( 'ofr_last_error' );
		}
	}

	/**
	 * Picks a garment file the API accepts (JPG/PNG/WEBP, ≤ 8 MB): the full image
	 * when possible, otherwise the largest generated size that fits, otherwise a
	 * converted JPEG copy in a temporary file (HEIC, AVIF, TIFF, … featured images).
	 *
	 * @return array|WP_Error { path, mime, temp: bool }
	 */
	public static function garment_file( $attachment_id ) {
		$main = get_attached_file( $attachment_id );
		if ( ! $main || ! is_readable( $main ) ) {
			return new WP_Error( 'ofr_garment_missing', 'Garment image file not found on the server.' );
		}
		$candidates = array( $main );
		$original   = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $attachment_id ) : '';
		if ( $original && $original !== $main ) {
			array_unshift( $candidates, $original );
		}
		$meta  = wp_get_attachment_metadata( $attachment_id );
		$sizes = is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : array();
		uasort(
			$sizes,
			function ( $a, $b ) {
				return ( (int) $b['width'] * (int) $b['height'] ) <=> ( (int) $a['width'] * (int) $a['height'] );
			}
		);
		foreach ( $sizes as $size ) {
			if ( min( (int) $size['width'], (int) $size['height'] ) >= 512 ) {
				$candidates[] = path_join( dirname( $main ), $size['file'] );
			}
		}
		foreach ( $candidates as $path ) {
			$mime = wp_check_filetype( $path )['type'];
			if ( is_readable( $path ) && in_array( $mime, self::ALLOWED_MIMES, true ) && filesize( $path ) <= self::GARMENT_MAX_BYTES ) {
				return array(
					'path' => $path,
					'mime' => $mime,
					'temp' => false,
				);
			}
		}
		$converted = OFR_Image::normalize( $main, basename( $main ), 2048, self::GARMENT_MAX_BYTES );
		if ( is_wp_error( $converted ) ) {
			return $converted;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php'; // wp_tempnam() is not loaded on the front end or in AJAX.
		$temp = wp_tempnam( 'ofr-garment' );
		if ( ! $temp || false === file_put_contents( $temp, $converted['bytes'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- a temporary file next to the upload; WP_Filesystem may need credentials on the front end.
			return new WP_Error( 'ofr_garment_write', 'Could not write the converted garment image.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}       return array(
			'path' => $temp,
			'mime' => $converted['mime'],
			'temp' => true,
		);
	}

	public static function extension( $mime ) {
		return array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		)[ $mime ] ?? 'jpg';
	}

	private static function file_part( $boundary, $field, $filename, $mime, $bytes ) {
		return '--' . $boundary . "\r\n" .
			'Content-Disposition: form-data; name="' . $field . '"; filename="' . $filename . '"' . "\r\n" .
			'Content-Type: ' . $mime . "\r\n\r\n" . $bytes . "\r\n";
	}

	/** Upstream detail for the admin: the log table, WooCommerce logs, error_log under WP_DEBUG and the settings page. */
	public static function log_error( $context, $code, $detail ) {
		$detail = is_string( $detail ) ? $detail : wp_json_encode( $detail );
		update_option(
			'ofr_last_error',
			array(
				'time'    => time(),
				'context' => $context,
				'code'    => (int) $code,
				'detail'  => mb_substr( (string) $detail, 0, 500 ),
			),
			false
		);
		if ( class_exists( 'OFR_Log' ) ) {
			OFR_Log::error( $context, $code, $detail );
		}
		do_action( 'ofr_service_error', $context, (int) $code, $detail );
	}

	/** Extracts the API's error text (FastAPI-style detail, message or error). */
	public static function error_detail( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return mb_substr( wp_strip_all_tags( wp_remote_retrieve_body( $response ) ), 0, 300 );
		}
		return $data['detail'] ?? $data['message'] ?? $data['error'] ?? $data;
	}

	/**
	 * Customer-safe message and the status we return to the browser. Upstream
	 * wording, vendor name and transport errors are never shown to shoppers.
	 *
	 * @return array { message, status }
	 */
	public static function customer_error( $code ) {
		switch ( true ) {
			case 0 === $code:
				return array(
					'message' => __( 'ارتباط با سرویس پردازش تصویر برقرار نشد. چند لحظه دیگر دوباره تلاش کنید.', 'online-fitting-room' ),
					'status'  => 502,
				);
			case in_array( $code, array( 400, 415, 422 ), true ):
				return array(
					'message' => __( 'این عکس قابل پردازش نیست. لطفاً یک عکس تمام‌قد، روبه‌رو و با نور کافی انتخاب کنید.', 'online-fitting-room' ),
					'status'  => 422,
				);
			case 413 === $code:
				return array(
					'message' => __( 'حجم تصویر بیشتر از حد مجاز است.', 'online-fitting-room' ),
					'status'  => 413,
				);
			case 402 === $code:
				return array(
					'message' => __( 'ظرفیت پرو مجازی فروشگاه فعلاً به پایان رسیده است. لطفاً بعداً دوباره سر بزنید.', 'online-fitting-room' ),
					'status'  => 503,
				);
			case 429 === $code:
				return array(
					'message' => __( 'سرویس پرو در حال حاضر شلوغ است؛ چند لحظه دیگر دوباره تلاش کنید.', 'online-fitting-room' ),
					'status'  => 503,
				);
			case 404 === $code:
				return array(
					'message' => __( 'این پردازش پیدا نشد؛ لطفاً دوباره تلاش کنید.', 'online-fitting-room' ),
					'status'  => 410,
				);
			default: // 401/403 (bad key), 5xx and anything unexpected.
				return array(
					'message' => __( 'سرویس پرو مجازی موقتاً در دسترس نیست. لطفاً کمی بعد دوباره تلاش کنید.', 'online-fitting-room' ),
					'status'  => 503,
				);
		}
	}
}
