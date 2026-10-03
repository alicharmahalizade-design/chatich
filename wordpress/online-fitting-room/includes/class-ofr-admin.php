<?php
/**
 * Settings page (WooCommerce → اتاق پُرُو آنلاین), connection test and the
 * per-product fields.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Admin {
	const GROUP = 'ofr_settings_group';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
		add_action( 'wp_ajax_ofr_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_fields' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( OFR_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function categories() {
		return array(
			'auto'       => __( 'تشخیص خودکار', 'online-fitting-room' ),
			'tops'       => __( 'بالاتنه', 'online-fitting-room' ),
			'bottoms'    => __( 'پایین‌تنه', 'online-fitting-room' ),
			'one-pieces' => __( 'یک‌تکه / پیراهن', 'online-fitting-room' ),
		);
	}

	public static function ip_sources() {
		return array(
			'remote_addr' => __( 'ندارد', 'online-fitting-room' ),
			'cloudflare'  => 'Cloudflare',
			'x_forwarded' => __( 'آروان‌کلاد / پروکسی دیگر (X-Forwarded-For)', 'online-fitting-room' ),
			'x_real_ip'   => 'Nginx (X-Real-IP)',
		);
	}

	public static function menu() {
		self::$hook = (string) add_submenu_page( 'woocommerce', __( 'اتاق پُرُو آنلاین', 'online-fitting-room' ), __( 'اتاق پُرُو آنلاین', 'online-fitting-room' ), 'manage_woocommerce', 'online-fitting-room', array( __CLASS__, 'page' ) );
	}

	/** Styles and script for the settings screen only. */
	public static function assets( $hook ) {
		if ( ! self::$hook || $hook !== self::$hook ) return;
		wp_enqueue_style( 'ofr-admin', plugins_url( 'assets/css/admin.css', OFR_FILE ), array(), Online_Fitting_Room::VERSION );
		wp_enqueue_script( 'ofr-admin', plugins_url( 'assets/js/admin.js', OFR_FILE ), array(), Online_Fitting_Room::VERSION, true );
		wp_localize_script( 'ofr-admin', 'OFRAdmin', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'ofr_test_connection' ),
			'checking' => __( 'در حال بررسی اتصال…', 'online-fitting-room' ),
			'invalid'  => __( 'پاسخ نامعتبر از سرور سایت دریافت شد.', 'online-fitting-room' ),
		) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=online-fitting-room' ) ) . '">' . esc_html__( 'تنظیمات', 'online-fitting-room' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting( self::GROUP, Online_Fitting_Room::OPTION_KEY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function dependency_notice() {
		if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'افزونه «اتاق پُرُو آنلاین» به ووکامرس نیاز دارد.', 'online-fitting-room' ) . '</p></div>';
		}
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array(); // options.php has already unslashed it.
		$old   = Online_Fitting_Room::settings();
		$api_key = $old['api_key'];
		if ( ! empty( $input['api_key_clear'] ) ) {
			$api_key = '';
		} elseif ( isset( $input['api_key'] ) && '' !== trim( $input['api_key'] ) ) {
			$entered = OFR_Api::clean_key( $input['api_key'] );
			if ( OFR_Api::is_key_id( $entered ) ) {
				// The key ID from the API dashboard is not a credential; keep the previous key.
				add_settings_error( Online_Fitting_Room::OPTION_KEY, 'ofr_key_id', __( 'مقداری که وارد کردید شناسه کلید است (با key_ شروع می‌شود) و ذخیره نشد. کلید خام را وارد کنید؛ با vton_live_ یا vton_test_ شروع می‌شود.', 'online-fitting-room' ) );
			} else {
				$api_key = $entered;
			}
		}
		// Checkboxes post "1"; stored settings (migrations) carry "yes"/"no".
		$on = function ( $key ) use ( $input ) {
			return ! empty( $input[ $key ] ) && 'no' !== $input[ $key ] ? 'yes' : 'no';
		};
		return array(
			'enabled'            => $on( 'enabled' ),
			'auto_display'       => $on( 'auto_display' ),
			'api_key'            => $api_key,
			'button_text'        => isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : $old['button_text'],
			'primary_color'      => sanitize_hex_color( $input['primary_color'] ?? '' ) ?: $old['primary_color'],
			'accent_color'       => sanitize_hex_color( $input['accent_color'] ?? '' ) ?: $old['accent_color'],
			'default_category'   => array_key_exists( $input['default_category'] ?? '', self::categories() ) ? $input['default_category'] : $old['default_category'],
			'require_login'      => $on( 'require_login' ),
			'limit_per_user_day' => min( 1000, absint( $input['limit_per_user_day'] ?? $old['limit_per_user_day'] ) ),
			'limit_site_day'     => min( 100000, absint( $input['limit_site_day'] ?? $old['limit_site_day'] ) ),
			'ip_source'          => array_key_exists( $input['ip_source'] ?? '', self::ip_sources() ) ? $input['ip_source'] : $old['ip_source'],
			'privacy_text'       => sanitize_textarea_field( $input['privacy_text'] ?? $old['privacy_text'] ),
		);
	}

	private static function name( $field ) {
		return esc_attr( Online_Fitting_Room::OPTION_KEY . '[' . $field . ']' );
	}

	private static function select( $field, $options, $value ) {
		echo '<select class="ofr-input" id="ofr-' . esc_attr( $field ) . '" name="' . self::name( $field ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in name().
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/** On/off switch backed by a real checkbox. */
	private static function toggle( $field, $checked, $label, $help = '' ) {
		echo '<label class="ofr-switch"><input type="checkbox" name="' . self::name( $field ) . '" value="1"' . checked( $checked, 'yes', false ) . '><span class="ofr-switch__track" aria-hidden="true"></span><span class="ofr-switch__text"><strong>' . esc_html( $label ) . '</strong>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in name().
		if ( $help ) echo '<small>' . esc_html( $help ) . '</small>';
		echo '</span></label>';
	}

	/** Small inline icons (stroke follows currentColor). */
	private static function icon( $name ) {
		$paths = array(
			'logo'   => '<path d="M12 3c-1.7 0-3 1.3-3 3h2a1 1 0 1 1 1.5.9c-.9.5-1.5 1.4-1.5 2.4V10L3.6 15.2A2 2 0 0 0 4.7 19h14.6a2 2 0 0 0 1.1-3.8L13 10v-.7"/>',
			'plug'   => '<path d="M9 2v6M15 2v6M6 8h12v3a6 6 0 0 1-12 0zM12 17v5"/>',
			'eye'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
			'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
			'alert'  => '<path d="M12 3l10 18H2zM12 10v5M12 18h.01"/>',
			'test'   => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
			'support' => '<path d="M4 13a8 8 0 0 1 16 0"/><path d="M4 13v3a2 2 0 0 0 2 2h1v-6H6a2 2 0 0 0-2 2M20 13v3a2 2 0 0 1-2 2h-1v-6h1a2 2 0 0 1 2 2"/><path d="M17 18c0 1.7-2.2 3-5 3"/>',
		);
		return '<svg class="ofr-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;
		$s          = Online_Fitting_Room::settings();
		$source     = OFR_Api::key_source();
		$last_error = get_option( 'ofr_last_error' );
		$used       = Online_Fitting_Room::instance()->usage_today();
		$warning    = OFR_Api::key_warning();
		$has_key    = '' !== OFR_Api::key();
		$recent_err = is_array( $last_error ) && $last_error['time'] > time() - DAY_IN_SECONDS;
		// phpcs:disable WordPress.Security.EscapeOutput -- icon() and name() return escaped/static markup.
		?>
		<div class="wrap ofr-app" dir="rtl">
			<header class="ofr-hero">
				<div class="ofr-hero__brand">
					<span class="ofr-logo"><?php echo self::icon( 'logo' ); ?></span>
					<div><strong><?php esc_html_e( 'اتاق پُرُو آنلاین', 'online-fitting-room' ); ?></strong><small><?php esc_html_e( 'پرو مجازی هوشمند برای ووکامرس', 'online-fitting-room' ); ?></small></div>
				</div>
				<nav class="ofr-hero__actions">
					<a class="ofr-btn ofr-btn--ghost" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'محصولات', 'online-fitting-room' ); ?></a>
					<button type="button" class="ofr-btn ofr-btn--primary" id="ofr-test"><?php echo self::icon( 'test' ); ?><?php esc_html_e( 'تست اتصال', 'online-fitting-room' ); ?></button>
				</nav>
			</header>

			<div class="ofr-main">
				<span class="ofr-eyebrow"><?php esc_html_e( 'مرکز مدیریت', 'online-fitting-room' ); ?></span>
				<h1 class="ofr-title"><?php esc_html_e( 'تنظیمات اتاق پُرُو', 'online-fitting-room' ); ?></h1>
				<p class="ofr-subtitle"><?php esc_html_e( 'اتصال به سرویس، نمایش دکمه پرو و محدودیت مصرف را از یک‌جا مدیریت کنید.', 'online-fitting-room' ); ?></p>
				<hr class="wp-header-end">
				<?php settings_errors(); // Pages outside the Settings menu must print "Settings saved" themselves. ?>

				<div class="ofr-alert ofr-alert--info" id="ofr-test-result" role="status" hidden></div>
				<?php if ( $warning ) : ?>
					<div class="ofr-alert"><?php echo self::icon( 'alert' ); ?><span><?php echo esc_html( $warning ); ?></span><a href="#ofr-api-key"><?php esc_html_e( 'اصلاح کلید', 'online-fitting-room' ); ?></a></div>
				<?php elseif ( ! $has_key ) : ?>
					<div class="ofr-alert"><?php echo self::icon( 'alert' ); ?><span><?php echo esc_html( __( 'هنوز کلید API ثبت نشده است؛ تا آن زمان پرو برای مشتری‌ها کار نمی‌کند.', 'online-fitting-room' ) . ' ' . OFR_Api::support_text() ); ?></span><a href="#ofr-api-key"><?php esc_html_e( 'ثبت کلید', 'online-fitting-room' ); ?></a></div>
				<?php endif; ?>

				<section class="ofr-stats" aria-label="<?php esc_attr_e( 'وضعیت', 'online-fitting-room' ); ?>">
					<div class="ofr-stat">
						<span class="ofr-stat__label"><?php esc_html_e( 'وضعیت افزونه', 'online-fitting-room' ); ?></span>
						<strong class="ofr-stat__value"><?php echo 'yes' === $s['enabled'] ? esc_html__( 'فعال', 'online-fitting-room' ) : esc_html__( 'غیرفعال', 'online-fitting-room' ); ?></strong>
						<span class="ofr-stat__note <?php echo 'yes' === $s['enabled'] ? 'is-ok' : 'is-warn'; ?>"><?php echo 'yes' === $s['auto_display'] ? esc_html__( 'نمایش خودکار در صفحه محصول', 'online-fitting-room' ) : esc_html__( 'فقط با ویجت یا شورت‌کد', 'online-fitting-room' ); ?></span>
					</div>
					<div class="ofr-stat">
						<span class="ofr-stat__label"><?php esc_html_e( 'کلید API', 'online-fitting-room' ); ?></span>
						<strong class="ofr-stat__value"><?php echo ! $has_key ? esc_html__( 'ثبت نشده', 'online-fitting-room' ) : ( $warning ? esc_html__( 'نیاز به بررسی', 'online-fitting-room' ) : esc_html__( 'ثبت شده', 'online-fitting-room' ) ); ?></strong>
						<span class="ofr-stat__note <?php echo $has_key && ! $warning ? 'is-ok' : 'is-warn'; ?>" dir="ltr"><?php echo $has_key ? esc_html( OFR_Api::masked_key() ) : '—'; ?></span>
					</div>
					<div class="ofr-stat">
						<span class="ofr-stat__label"><?php esc_html_e( 'پروهای امروز', 'online-fitting-room' ); ?></span>
						<strong class="ofr-stat__value"><?php echo esc_html( number_format_i18n( $used ) ); ?></strong>
						<span class="ofr-stat__note is-ok"><?php echo $s['limit_site_day'] > 0 ? esc_html( sprintf( /* translators: %s: daily site limit. */ __( 'از سقف %s', 'online-fitting-room' ), number_format_i18n( $s['limit_site_day'] ) ) ) : esc_html__( 'بدون سقف روزانه', 'online-fitting-room' ); ?></span>
					</div>
					<div class="ofr-stat">
						<span class="ofr-stat__label"><?php esc_html_e( 'آخرین خطای سرویس', 'online-fitting-room' ); ?></span>
						<strong class="ofr-stat__value"><?php echo is_array( $last_error ) ? esc_html( sprintf( /* translators: %s: time span such as "2 hours". */ __( '%s پیش', 'online-fitting-room' ), human_time_diff( $last_error['time'] ) ) ) : esc_html__( 'بدون خطا', 'online-fitting-room' ); ?></strong>
						<span class="ofr-stat__note <?php echo $recent_err ? 'is-warn' : 'is-ok'; ?>"><?php echo is_array( $last_error ) ? esc_html( $last_error['context'] ) : esc_html__( 'همه‌چیز عادی است', 'online-fitting-room' ); ?></span>
					</div>
				</section>

				<form method="post" action="options.php" class="ofr-form">
					<?php settings_fields( self::GROUP ); ?>
					<div class="ofr-grid">
						<section class="ofr-card" id="ofr-connection">
							<header class="ofr-card__head"><span class="ofr-card__icon"><?php echo self::icon( 'plug' ); ?></span><div><h2><?php esc_html_e( 'اتصال به سرویس', 'online-fitting-room' ); ?></h2><p><?php esc_html_e( 'کلید خام مربع API؛ هرگز به مرورگر مشتری ارسال نمی‌شود.', 'online-fitting-room' ); ?></p></div></header>
							<div class="ofr-field">
								<label class="ofr-label" for="ofr-api-key"><?php esc_html_e( 'کلید مربع API', 'online-fitting-room' ); ?></label>
								<?php if ( 'constant' === $source || 'env' === $source ) : ?>
									<p class="ofr-help"><?php esc_html_e( 'کلید در تنظیمات سرور تعریف شده است:', 'online-fitting-room' ); ?> <code dir="ltr"><?php echo esc_html( OFR_Api::masked_key() ); ?></code></p>
								<?php else : ?>
									<input id="ofr-api-key" class="ofr-input" type="password" autocomplete="new-password" dir="ltr" name="<?php echo self::name( 'api_key' ); ?>" placeholder="<?php echo esc_attr( $s['api_key'] ? __( 'برای حفظ کلید فعلی خالی بگذارید', 'online-fitting-room' ) : 'vton_live_...' ); ?>">
									<?php if ( $s['api_key'] ) : ?>
										<div class="ofr-keyrow"><span><?php esc_html_e( 'کلید فعلی:', 'online-fitting-room' ); ?> <code dir="ltr"><?php echo esc_html( OFR_Api::masked_key() ); ?></code></span><label class="ofr-check"><input type="checkbox" name="<?php echo self::name( 'api_key_clear' ); ?>" value="1"> <?php esc_html_e( 'حذف کلید', 'online-fitting-room' ); ?></label></div>
									<?php endif; ?>
								<?php endif; ?>
								<p class="ofr-hint"><?php echo self::icon( 'support' ); ?><span><?php echo esc_html( OFR_Api::support_text() ); ?></span></p>
							</div>
							<?php if ( is_array( $last_error ) ) : ?>
								<details class="ofr-details"><summary><?php esc_html_e( 'جزئیات آخرین خطای سرویس', 'online-fitting-room' ); ?></summary><code dir="ltr"><?php echo esc_html( wp_date( 'Y-m-d H:i', $last_error['time'] ) . ' — ' . $last_error['context'] . ' — HTTP ' . $last_error['code'] . "\n" . $last_error['detail'] ); ?></code></details>
							<?php endif; ?>
						</section>

						<section class="ofr-card">
							<header class="ofr-card__head"><span class="ofr-card__icon"><?php echo self::icon( 'shield' ); ?></span><div><h2><?php esc_html_e( 'محدودیت مصرف', 'online-fitting-room' ); ?></h2><p><?php esc_html_e( 'از اعتبار حساب مربع API در برابر استفاده بی‌رویه محافظت کنید.', 'online-fitting-room' ); ?></p></div></header>
							<div class="ofr-field"><?php self::toggle( 'require_login', $s['require_login'], __( 'فقط کاربران عضو', 'online-fitting-room' ), __( 'مهمان‌ها برای پرو باید وارد حساب کاربری شوند.', 'online-fitting-room' ) ); ?></div>
							<div class="ofr-row">
								<div class="ofr-field"><label class="ofr-label" for="ofr-limit-user"><?php esc_html_e( 'سقف روزانه هر کاربر', 'online-fitting-room' ); ?></label><input id="ofr-limit-user" class="ofr-input" type="number" min="0" max="1000" name="<?php echo self::name( 'limit_per_user_day' ); ?>" value="<?php echo esc_attr( $s['limit_per_user_day'] ); ?>"><p class="ofr-help"><?php esc_html_e( '۰ یعنی نامحدود', 'online-fitting-room' ); ?></p></div>
								<div class="ofr-field"><label class="ofr-label" for="ofr-limit-site"><?php esc_html_e( 'سقف روزانه کل سایت', 'online-fitting-room' ); ?></label><input id="ofr-limit-site" class="ofr-input" type="number" min="0" max="100000" name="<?php echo self::name( 'limit_site_day' ); ?>" value="<?php echo esc_attr( $s['limit_site_day'] ); ?>"><p class="ofr-help"><?php esc_html_e( '۰ یعنی نامحدود', 'online-fitting-room' ); ?></p></div>
							</div>
							<div class="ofr-field"><label class="ofr-label" for="ofr-ip_source"><?php esc_html_e( 'سایت پشت CDN', 'online-fitting-room' ); ?></label><?php self::select( 'ip_source', self::ip_sources(), $s['ip_source'] ); ?><p class="ofr-help"><?php esc_html_e( 'برای شناختن درست هر مشتری در سقف روزانه. اگر از Cloudflare یا آروان استفاده نمی‌کنید، «ندارد» بماند.', 'online-fitting-room' ); ?></p></div>
						</section>

						<section class="ofr-card ofr-card--wide">
							<header class="ofr-card__head"><span class="ofr-card__icon"><?php echo self::icon( 'eye' ); ?></span><div><h2><?php esc_html_e( 'نمایش در فروشگاه', 'online-fitting-room' ); ?></h2><p><?php esc_html_e( 'دکمه پرو و پنجره‌ای که مشتری می‌بیند.', 'online-fitting-room' ); ?></p></div></header>
							<div class="ofr-row">
								<div class="ofr-field"><?php self::toggle( 'enabled', $s['enabled'], __( 'اتاق پُرُو فعال باشد', 'online-fitting-room' ) ); ?></div>
								<div class="ofr-field"><?php self::toggle( 'auto_display', $s['auto_display'], __( 'نمایش خودکار در صفحه محصول', 'online-fitting-room' ), __( 'اگر از ویجت المنتور یا شورت‌کد [online_fitting_room] استفاده می‌کنید، خاموش کنید.', 'online-fitting-room' ) ); ?></div>
							</div>
							<div class="ofr-row">
								<div class="ofr-field"><label class="ofr-label" for="ofr-button-text"><?php esc_html_e( 'متن دکمه', 'online-fitting-room' ); ?></label><input id="ofr-button-text" class="ofr-input" name="<?php echo self::name( 'button_text' ); ?>" value="<?php echo esc_attr( $s['button_text'] ); ?>"></div>
								<div class="ofr-field"><label class="ofr-label" for="ofr-default_category"><?php esc_html_e( 'نوع لباس پیش‌فرض', 'online-fitting-room' ); ?></label><?php self::select( 'default_category', self::categories(), $s['default_category'] ); ?><p class="ofr-help"><?php esc_html_e( 'در ویرایش هر محصول قابل تغییر است.', 'online-fitting-room' ); ?></p></div>
								<div class="ofr-field"><span class="ofr-label"><?php esc_html_e( 'رنگ‌ها', 'online-fitting-room' ); ?></span><div class="ofr-colors"><label><input type="color" name="<?php echo self::name( 'primary_color' ); ?>" value="<?php echo esc_attr( $s['primary_color'] ); ?>"> <?php esc_html_e( 'اصلی', 'online-fitting-room' ); ?></label><label><input type="color" name="<?php echo self::name( 'accent_color' ); ?>" value="<?php echo esc_attr( $s['accent_color'] ); ?>"> <?php esc_html_e( 'تأکیدی', 'online-fitting-room' ); ?></label></div></div>
							</div>
							<div class="ofr-field"><label class="ofr-label" for="ofr-privacy"><?php esc_html_e( 'متن رضایت مشتری', 'online-fitting-room' ); ?></label><textarea id="ofr-privacy" class="ofr-input" rows="3" name="<?php echo self::name( 'privacy_text' ); ?>"><?php echo esc_textarea( $s['privacy_text'] ); ?></textarea></div>
						</section>
					</div>
					<div class="ofr-savebar"><span><?php esc_html_e( 'تغییرات پس از ذخیره روی فروشگاه اعمال می‌شوند.', 'online-fitting-room' ); ?></span><button type="submit" class="ofr-btn ofr-btn--primary"><?php esc_html_e( 'ذخیره تنظیمات', 'online-fitting-room' ); ?></button></div>
				</form>
			</div>
		</div>
		<?php
		// phpcs:enable
	}

	public static function ajax_test_connection() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'ofr_test_connection', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی مجاز نیست.', 'online-fitting-room' ) ), 403 );
		}
		$result = OFR_Api::test_connection();
		$result['ok'] ? wp_send_json_success( $result ) : wp_send_json_error( $result );
	}

	public static function product_fields() {
		woocommerce_wp_checkbox( array( 'id' => Online_Fitting_Room::META_ENABLED, 'label' => __( 'اتاق پُرُو آنلاین', 'online-fitting-room' ), 'description' => __( 'نمایش دکمه پرو برای این محصول', 'online-fitting-room' ), 'desc_tip' => true, 'value' => get_post_meta( get_the_ID(), Online_Fitting_Room::META_ENABLED, true ) ?: 'yes' ) );
		woocommerce_wp_select( array( 'id' => Online_Fitting_Room::META_CATEGORY, 'label' => __( 'نوع لباس پرو مجازی', 'online-fitting-room' ), 'options' => array( '' => __( 'تنظیم عمومی', 'online-fitting-room' ) ) + self::categories() ) );
	}

	public static function save_product_fields( $product_id ) {
		// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce verifies the product edit nonce before this hook.
		update_post_meta( $product_id, Online_Fitting_Room::META_ENABLED, isset( $_POST[ Online_Fitting_Room::META_ENABLED ] ) ? 'yes' : 'no' );
		$category = sanitize_text_field( wp_unslash( $_POST[ Online_Fitting_Room::META_CATEGORY ] ?? '' ) );
		// phpcs:enable
		update_post_meta( $product_id, Online_Fitting_Room::META_CATEGORY, array_key_exists( $category, self::categories() ) ? $category : '' );
	}
}
