<?php
/**
 * Site Health (Tools → Site Health) checks for what usually breaks a try-on
 * silently: a web server that refuses ordinary photo uploads, a results folder
 * the public can open, no encryption, a stopped cleanup, visitor IPs hidden
 * behind a CDN, and no route from the host to the try-on service.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Health {
	const PROBE_BYTES = 1200000; // A bit over Nginx's default 1 MB body limit.

	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_ajax_nopriv_ofr_probe', array( __CLASS__, 'ajax_probe' ) );
		add_action( 'wp_ajax_ofr_probe', array( __CLASS__, 'ajax_probe' ) );
	}

	private static function label() {
		return array(
			'label' => __( 'اتاق پُرُو آنلاین', 'online-fitting-room' ),
			'color' => 'orange',
		);
	}

	private static function result( $test, $status, $label, $description, $actions = '' ) {
		return array(
			'label'       => $label,
			'status'      => $status, // good, recommended or critical.
			'badge'       => self::label(),
			'description' => '<p>' . $description . '</p>',
			'actions'     => $actions,
			'test'        => 'ofr_' . $test,
		);
	}

	public static function tests( $tests ) {
		$direct = array(
			'encryption' => __( 'رمزنگاری تصاویر نتیجه پرو', 'online-fitting-room' ),
			'cleanup'    => __( 'پاک‌سازی خودکار نتایج پرو', 'online-fitting-room' ),
			'proxy'      => __( 'تشخیص IP مشتری برای سقف مصرف', 'online-fitting-room' ),
			'counters'   => __( 'جدول شمارنده‌های سقف مصرف', 'online-fitting-room' ),
			'imagick'    => __( 'فرمت‌های عکسی که سرور تبدیل می‌کند', 'online-fitting-room' ),
		);
		foreach ( $direct as $name => $label ) {
			$tests['direct'][ 'ofr_' . $name ] = array(
				'label' => $label,
				'test'  => array( __CLASS__, 'test_' . $name ),
			);
		}
		$async = array(
			'upload'     => __( 'حد حجم آپلود وب‌سرور برای عکس پرو', 'online-fitting-room' ),
			'private'    => __( 'دسترسی مستقیم به پوشه نتایج پرو', 'online-fitting-room' ),
			'connection' => __( 'اتصال هاست به مربع API', 'online-fitting-room' ),
		);
		foreach ( $async as $name => $label ) {
			$tests['async'][ 'ofr_' . $name ] = array(
				'label'             => $label,
				'test'              => rest_url( 'ofr/v1/health/' . $name ),
				'has_rest'          => true,
				'async_direct_test' => array( __CLASS__, 'test_' . $name ),
			);
		}
		return $tests;
	}

	public static function routes() {
		foreach ( array( 'upload', 'private', 'connection' ) as $name ) {
			register_rest_route(
				'ofr/v1',
				'/health/' . $name,
				array(
					'methods'             => 'GET',
					'callback'            => function () use ( $name ) {
						return rest_ensure_response( call_user_func( array( __CLASS__, 'test_' . $name ) ) );
					},
					'permission_callback' => function () {
						return current_user_can( 'view_site_health_checks' );
					},
				)
			);
		}
	}

	/* ---------- Direct tests ---------- */

	public static function test_encryption() {
		$method = OFR_Storage::encryption();
		if ( $method ) {
			return self::result(
				'encryption',
				'good',
				__( 'تصاویر نتیجه پرو رمزنگاری‌شده ذخیره می‌شوند', 'online-fitting-room' ),
				/* translators: %s: libsodium or OpenSSL. */
				sprintf( __( 'هر تصویر با کلیدی ساخته‌شده از توکن خصوصی همان مشتری (%s) رمز می‌شود و حتی با دسترسی مستقیم به فایل قابل دیدن نیست.', 'online-fitting-room' ), 'sodium' === $method ? 'libsodium' : 'OpenSSL AES-256-GCM' )
			);
		}
		return self::result(
			'encryption',
			'critical',
			__( 'تصاویر نتیجه پرو بدون رمزنگاری ذخیره می‌شوند', 'online-fitting-room' ),
			__( 'نه افزونه sodium و نه OpenSSL (AES-256-GCM) روی PHP این هاست فعال است. از پشتیبانی هاست بخواهید یکی از آن‌ها را فعال کند.', 'online-fitting-room' )
		);
	}

	public static function test_cleanup() {
		$last = (int) get_option( 'ofr_last_cleanup', 0 );
		if ( $last > time() - 3 * HOUR_IN_SECONDS ) {
			return self::result(
				'cleanup',
				'good',
				__( 'نتایج پرو به‌موقع پاک می‌شوند', 'online-fitting-room' ),
				/* translators: %s: time span such as "20 minutes". */
				sprintf( __( 'آخرین پاک‌سازی %s پیش انجام شد. هیچ نتیجه‌ای بیشتر از ۲۴ ساعت نگه داشته نمی‌شود.', 'online-fitting-room' ), human_time_diff( $last ?: time() ) )
			);
		}
		return self::result(
			'cleanup',
			'recommended',
			__( 'پاک‌سازی ساعتی نتایج پرو عقب افتاده است', 'online-fitting-room' ),
			__( 'WP-Cron اجرا نمی‌شود (یا سایت بازدید کمی داشته است). افزونه هنگام هر پرو و هر بار باز شدن پنجره هم پاک‌سازی می‌کند و فایل منقضی را هرگز نمایش نمی‌دهد، ولی برای پاک شدن دقیق، یک Cron واقعی سرور برای wp-cron.php تنظیم کنید.', 'online-fitting-room' )
		);
	}

	public static function test_proxy() {
		$seen = get_option( 'ofr_client_seen' );
		$s    = Online_Fitting_Room::settings();
		if ( ! is_array( $seen ) ) {
			return self::result(
				'proxy',
				'good',
				__( 'تشخیص IP مشتری فعال است', 'online-fitting-room' ),
				__( 'هنوز مشتری‌ای پنجره پرو را باز نکرده است؛ پس از اولین بازدید، این بررسی وضعیت واقعی را نشان می‌دهد.', 'online-fitting-room' )
			);
		}
		$names = array(
			'cloudflare' => 'Cloudflare',
			'arvan'      => __( 'آروان‌کلاد', 'online-fitting-room' ),
			'custom'     => __( 'پروکسی تعریف‌شده', 'online-fitting-room' ),
			'private'    => __( 'پروکسی داخلی سرور', 'online-fitting-room' ),
		);
		switch ( $seen['hint'] ) {
			case 'unresolved':
				return self::result(
					'proxy',
					'critical',
					__( 'همه مشتری‌ها با IP شبکه CDN شناخته می‌شوند', 'online-fitting-room' ),
					/* translators: %s: CDN name. */
					sprintf( __( 'سایت پشت %s است ولی IP واقعی مشتری در درخواست پیدا نشد؛ در این حالت سقف روزانه هر کاربر بین همه مشتری‌ها مشترک می‌شود. «تشخیص IP» را روی «خودکار» بگذارید و مطمئن شوید CDN هدر X-Forwarded-For یا CF-Connecting-IP را می‌فرستد.', 'online-fitting-room' ), $names[ $seen['proxy'] ] ?? $seen['proxy'] ),
					'<p><a href="' . esc_url( admin_url( 'admin.php?page=online-fitting-room#ofr-limits' ) ) . '">' . esc_html__( 'تنظیمات محدودیت مصرف', 'online-fitting-room' ) . '</a></p>'
				);
			case 'unknown_cdn':
				return self::result(
					'proxy',
					'recommended',
					__( 'هدر CDN از آدرسی ناشناخته رسید', 'online-fitting-room' ),
					__( 'درخواست‌ها هدر IP یک CDN دارند ولی از آدرس‌های شناخته‌شده آن نمی‌آیند (مثلاً فهرست IPهای آروان هنوز دانلود نشده یا هاست به آن دسترسی ندارد). بازه IPهای CDN خود را در «پروکسی‌های مورد اعتماد» وارد کنید.', 'online-fitting-room' ),
					'<p><a href="' . esc_url( admin_url( 'admin.php?page=online-fitting-room#ofr-limits' ) ) . '">' . esc_html__( 'تنظیمات محدودیت مصرف', 'online-fitting-room' ) . '</a></p>'
				);
			case 'spoofable':
				return self::result(
					'proxy',
					'critical',
					__( 'گزینه Cloudflare انتخاب شده ولی سایت پشت Cloudflare نیست', 'online-fitting-room' ),
					__( 'در این حالت هر کسی می‌تواند با یک هدر ساختگی IP خودش را عوض کند و سقف روزانه را دور بزند. «تشخیص IP» را روی «خودکار» بگذارید.', 'online-fitting-room' )
				);
		}
		$via = $seen['via'] ? ( $names[ $seen['via'] ] ?? $seen['via'] ) : '';
		return self::result(
			'proxy',
			'good',
			__( 'IP واقعی مشتری درست تشخیص داده می‌شود', 'online-fitting-room' ),
			/* translators: %s: CDN name. */
			$via ? sprintf( __( 'درخواست‌ها از طریق %s می‌رسند و IP واقعی هر مشتری از هدر معتبر آن خوانده می‌شود.', 'online-fitting-room' ), $via ) : ( 'auto' === $s['ip_source'] ? __( 'سایت مستقیم (بدون CDN) در دسترس است.', 'online-fitting-room' ) : __( 'روش تشخیص IP دستی انتخاب شده است.', 'online-fitting-room' ) )
		);
	}

	public static function test_counters() {
		global $wpdb;
		$table = OFR_Quota::table();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $found === $table ) {
			return self::result(
				'counters',
				'good',
				__( 'سقف مصرف به‌صورت اتمیک شمرده می‌شود', 'online-fitting-room' ),
				__( 'حتی صدها درخواست هم‌زمان نمی‌توانند از سقف روزانه عبور کنند.', 'online-fitting-room' )
			);
		}
		OFR_Quota::install();
		return self::result(
			'counters',
			'critical',
			__( 'جدول شمارنده‌های اتاق پُرُو ساخته نشده است', 'online-fitting-room' ),
			__( 'افزونه دوباره تلاش کرد جدول را بسازد. اگر این پیام باقی ماند، کاربر پایگاه داده اجازه CREATE TABLE ندارد؛ تا آن زمان شمارش غیراتمیک (قدیمی) استفاده می‌شود.', 'online-fitting-room' )
		);
	}

	/**
	 * Which photo formats the server can convert when the shopper's browser
	 * cannot (HEIC in Chrome, TIFF, RAW, …). JPG/PNG/WEBP always work.
	 */
	public static function test_imagick() {
		$all     = array( 'heic', 'avif', 'tiff', 'raw', 'psd', 'jxl', 'bmp', 'gif' );
		$can     = OFR_Image::server_formats();
		$missing = array_values( array_diff( $all, $can ) );
		$labels  = function ( $formats ) {
			return implode( '، ', array_map( array( 'OFR_Image', 'label' ), $formats ) );
		};
		$engine  = class_exists( 'Imagick' ) ? 'Imagick' : ( function_exists( 'imagecreatetruecolor' ) ? 'GD' : '' );
		if ( ! $missing ) {
			return self::result(
				'imagick',
				'good',
				__( 'سرور همه فرمت‌های عکس را تبدیل می‌کند', 'online-fitting-room' ),
				/* translators: %s: engine name. */
				sprintf( __( 'موتور تبدیل: %s.', 'online-fitting-room' ), $engine )
			);
		}
		$important = array_intersect( $missing, array( 'heic', 'avif' ) );
		return self::result(
			'imagick',
			$important ? 'recommended' : 'good',
			$important ? __( 'سرور بعضی عکس‌های گوشی را تبدیل نمی‌کند', 'online-fitting-room' ) : __( 'سرور فرمت‌های رایج عکس را تبدیل می‌کند', 'online-fitting-room' ),
			/* translators: 1: engine, 2: supported formats, 3: missing formats. */
			sprintf( __( 'موتور تبدیل: %1$s. قابل تبدیل روی سرور: %2$s. ناموجود: %3$s. اغلب مرورگرها این فرمت‌ها را خودشان تبدیل می‌کنند؛ برای پوشش کامل (مثلاً HEIC در کروم ویندوز وقتی مبدل مرورگر بارگذاری نشود) از هاست بخواهید Imagick با libheif را فعال کند.', 'online-fitting-room' ), $engine ?: '—', $can ? $labels( $can ) : '—', $labels( $missing ) )
		);
	}

	/* ---------- Loopback tests ---------- */

	private static function probe_token() {
		$token = wp_generate_password( 24, false, false );
		set_transient( 'ofr_probe_' . $token, 1, 2 * MINUTE_IN_SECONDS );
		return $token;
	}

	/** Answers the upload probe: echoes the size it received, nothing else. */
	public static function ajax_probe() {
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a one-time token instead.
		if ( ! preg_match( '/^[A-Za-z0-9]{24}$/', $token ) || ! get_transient( 'ofr_probe_' . $token ) ) {
			wp_send_json_error( null, 403 );
		}
		delete_transient( 'ofr_probe_' . $token );
		wp_send_json_success( array( 'received' => isset( $_FILES['probe']['size'] ) ? (int) $_FILES['probe']['size'] : 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification -- the one-time token above replaces a nonce.
	}

	public static function test_upload() {
		$boundary = 'ofrprobe' . wp_generate_password( 16, false, false );
		$body     = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"action\"\r\n\r\nofr_probe\r\n";
		$body    .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"token\"\r\n\r\n" . self::probe_token() . "\r\n";
		$body    .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"probe\"; filename=\"probe.bin\"\r\nContent-Type: application/octet-stream\r\n\r\n" . str_repeat( 'x', self::PROBE_BYTES ) . "\r\n--" . $boundary . "--\r\n";
		$response = wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 20,
				'sslverify' => false, // Loopback to this very site.
				'headers'   => array(
					'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
					'Expect'       => '',
				),
				'body'      => $body,
			)
		);
		$size     = size_format( self::PROBE_BYTES );
		if ( is_wp_error( $response ) ) {
			return self::result(
				'upload',
				'recommended',
				__( 'حد حجم آپلود وب‌سرور بررسی نشد', 'online-fitting-room' ),
				/* translators: %s: error message. */
				sprintf( __( 'درخواست آزمایشی سایت به خودش انجام نشد (%s). افزونه عکس‌ها را زیر ۹۰۰ کیلوبایت ارسال می‌کند و در صورت خطای ۴۱۳ خودکار نسخه سبک‌تری می‌فرستد.', 'online-fitting-room' ), esc_html( $response->get_error_message() ) )
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 === $code && ! empty( $data['success'] ) && (int) ( $data['data']['received'] ?? 0 ) >= self::PROBE_BYTES ) {
			return self::result(
				'upload',
				'good',
				__( 'وب‌سرور عکس‌های پرو را بدون مشکل می‌پذیرد', 'online-fitting-room' ),
				/* translators: %s: file size. */
				sprintf( __( 'یک فایل آزمایشی %s با موفقیت دریافت شد.', 'online-fitting-room' ), $size )
			);
		}
		if ( 413 === $code ) {
			return self::result(
				'upload',
				'recommended',
				__( 'وب‌سرور آپلودهای بزرگ‌تر از ۱ مگابایت را رد می‌کند', 'online-fitting-room' ),
				__( 'افزونه برای همین عکس‌ها را زیر ۹۰۰ کیلوبایت فشرده می‌کند و در صورت نیاز خودکار نسخه سبک‌تری می‌فرستد، ولی عکس‌هایی که مرورگر نمی‌تواند باز کند (مثل RAW) رد می‌شوند. اگر سرور Nginx است، در تنظیمات آن مقدار زیر را اضافه کنید (یا از پشتیبانی هاست بخواهید):', 'online-fitting-room' ) . '</p><p><code dir="ltr">client_max_body_size 32M;</code>'
			);
		}
		return self::result(
			'upload',
			'recommended',
			__( 'حد حجم آپلود وب‌سرور بررسی نشد', 'online-fitting-room' ),
			/* translators: %d: HTTP status code. */
			sprintf( __( 'درخواست آزمایشی با پاسخ HTTP %d روبه‌رو شد (احتمالاً فایروال یا افزونه امنیتی). اگر مشتری‌ها هنگام ارسال عکس خطا می‌بینند، این مورد را بررسی کنید.', 'online-fitting-room' ), $code )
		);
	}

	public static function test_private() {
		if ( OFR_Storage::is_custom_dir() ) {
			return self::result(
				'private',
				'good',
				__( 'پوشه نتایج پرو خارج از دسترس وب است', 'online-fitting-room' ),
				__( 'مسیر پوشه نتایج با OFR_RESULTS_DIR یا فیلتر ofr_results_dir تعیین شده است.', 'online-fitting-room' )
			);
		}
		$dir = OFR_Storage::ensure_dir();
		$url = OFR_Storage::url();
		if ( '' === $dir || '' === $url ) {
			return self::result(
				'private',
				'critical',
				__( 'پوشه نتایج پرو قابل ساخت نیست', 'online-fitting-room' ),
				__( 'پوشه uploads قابل نوشتن نیست؛ پرو مجازی نمی‌تواند نتیجه را ذخیره کند.', 'online-fitting-room' )
			);
		}
		$name   = 'probe-' . wp_generate_password( 16, false, false ) . '.txt';
		$secret = wp_generate_password( 20, false, false );
		file_put_contents( $dir . '/' . $name, $secret ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$response = wp_remote_get(
			$url . '/' . $name,
			array(
				'timeout'     => 10,
				'sslverify'   => false,
				'redirection' => 0,
			)
		);
		wp_delete_file( $dir . '/' . $name );
		$open = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) && false !== strpos( wp_remote_retrieve_body( $response ), $secret );
		if ( ! $open ) {
			return self::result(
				'private',
				'good',
				__( 'پوشه نتایج پرو از وب قابل دسترسی نیست', 'online-fitting-room' ),
				__( 'یک فایل آزمایشی در پوشه نتایج از طریق آدرس وب باز نشد.', 'online-fitting-room' )
			);
		}
		$snippet = '<p><code dir="ltr">location ^~ ' . esc_html( trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) . ' { deny all; }</code></p>'
			. '<p>' . esc_html__( 'یا پوشه را بیرون از public_html ببرید، با افزودن این خط به wp-config.php:', 'online-fitting-room' ) . '</p>'
			. '<p><code dir="ltr">define( \'OFR_RESULTS_DIR\', dirname( ABSPATH ) . \'/ofr-results\' );</code></p>';
		if ( OFR_Storage::encryption() ) {
			return self::result(
				'private',
				'recommended',
				__( 'پوشه نتایج پرو از وب باز می‌شود (فایل‌ها رمزنگاری‌شده‌اند)', 'online-fitting-room' ),
				__( 'وب‌سرور (معمولاً Nginx) فایل .htaccess را نادیده می‌گیرد. چون هر تصویر با کلید خصوصی همان مشتری رمز شده، فایل‌ها حتی با باز شدن هم قابل دیدن نیستند؛ ولی برای اطمینان کامل، دسترسی را در وب‌سرور ببندید:', 'online-fitting-room' ) . '</p>' . $snippet . '<p>'
			);
		}
		return self::result(
			'private',
			'critical',
			__( 'تصاویر نتیجه پرو از وب قابل دسترسی‌اند', 'online-fitting-room' ),
			__( 'وب‌سرور .htaccess را نادیده می‌گیرد و رمزنگاری هم روی این هاست در دسترس نیست. دسترسی را در وب‌سرور ببندید:', 'online-fitting-room' ) . '</p>' . $snippet . '<p>'
		);
	}

	public static function test_connection() {
		OFR_Api::start_budget();
		$result = OFR_Api::test_connection();
		return self::result(
			'connection',
			$result['ok'] ? 'good' : 'critical',
			$result['ok'] ? __( 'هاست به مربع API دسترسی دارد', 'online-fitting-room' ) : __( 'اتصال هاست به مربع API برقرار نیست', 'online-fitting-room' ),
			esc_html( $result['message'] )
		);
	}
}
