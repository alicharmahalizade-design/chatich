<?php
/**
 * Plugin Name: اتاق پُرُو آنلاین
 * Description: اتاق پرو مجازی هوشمند برای ووکامرس؛ مشتری عکس خودش را بارگذاری می‌کند و در چند ثانیه همان لباس را روی تصویر خودش می‌بیند. همراه با ویجت المنتور، پشتیبانی از همه فرمت‌های عکس و حفظ حریم خصوصی مشتری.
 * Version: 1.7.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * Elementor tested up to: 4.0
 * Author: مربع استودیو
 * Text Domain: online-fitting-room
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'OFR_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-ofr-image.php';
require_once __DIR__ . '/includes/class-ofr-api.php';
require_once __DIR__ . '/includes/class-ofr-quota.php';
require_once __DIR__ . '/includes/class-ofr-client.php';
require_once __DIR__ . '/includes/class-ofr-captcha.php';
require_once __DIR__ . '/includes/class-ofr-storage.php';
require_once __DIR__ . '/includes/class-ofr-health.php';
require_once __DIR__ . '/includes/class-ofr-admin.php';

final class Online_Fitting_Room {
	const VERSION       = '1.7.0';
	const DB_VERSION    = '5';
	const OPTION_KEY    = 'ofr_settings';
	const NONCE_ACTION  = 'ofr_tryon';
	const META_ENABLED  = '_ofr_enabled';
	const META_CATEGORY = '_ofr_category';
	const BURST_LIMIT   = 8;   // Attempts per client…
	const BURST_WINDOW  = 600; // …per 10 minutes.

	/**
	 * Largest photo the browser uploads. Nginx refuses bodies over 1 MB unless
	 * configured otherwise (client_max_body_size), so photos are encoded to stay
	 * below that with room for the rest of the form.
	 */
	const UPLOAD_TARGET = 900 * 1024;

	private static $instance = null;
	private $rendered = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		OFR_Admin::init();
		OFR_Health::init();
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_migrate' ), 5 ); // Front end too: the counters table must exist before the first try-on.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_button' ), 35 );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_button_after_cart' ) );
		add_action( 'woocommerce_before_single_product_summary', array( $this, 'render_button_gallery' ), 25 );
		add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'loop_badge' ), 11 );
		add_filter( 'render_block', array( $this, 'block_badge' ), 10, 3 );
		add_action( 'wp_footer', array( $this, 'render_modal' ) );
		add_shortcode( 'online_fitting_room', array( $this, 'shortcode' ) );
		add_shortcode( 'webich_tryon', array( $this, 'shortcode' ) ); // Legacy name from Webich Smart Try-On.
		foreach ( array( 'session', 'start', 'status', 'result' ) as $action ) {
			add_action( 'wp_ajax_ofr_' . $action, array( $this, 'ajax_' . $action ) );
			add_action( 'wp_ajax_nopriv_ofr_' . $action, array( $this, 'ajax_' . $action ) );
		}
		add_action( OFR_Storage::CLEANUP_HOOK, array( 'OFR_Storage', 'cleanup' ) );
		add_action( OFR_Client::REFRESH_HOOK, array( 'OFR_Client', 'refresh_ranges' ) );
		add_action( 'ofr_delete_result', array( 'OFR_Storage', 'delete_legacy_result' ) ); // Events queued by 1.2.0.
		add_action( 'webich_tryon_delete_result', array( 'OFR_Storage', 'delete_legacy_result' ) ); // Events queued by the legacy plugin.
		add_action( 'elementor/elements/categories_registered', array( $this, 'elementor_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'elementor_widgets' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'elementor_preview_styles' ) );
	}

	public static function defaults() {
		return array(
			'enabled'            => 'yes',
			'auto_display'       => 'yes',
			'api_key'            => '',
			'button_text'        => __( 'پرو با هوش مصنوعی', 'online-fitting-room' ),
			'primary_color'      => '#111827',
			'accent_color'       => '#ff5c35',
			'font'               => 'theme',
			'theme_mode'         => 'light',
			'modal_title'        => __( 'قبل از خرید، تنت ببین', 'online-fitting-room' ),
			'modal_subtitle'     => __( 'عکس خودت را بده؛ هوش مصنوعی همین لباس را روی تصویرت شبیه‌سازی می‌کند.', 'online-fitting-room' ),
			'button_position'    => 'summary',
			'sticky_mobile'      => 'yes',
			'loop_badge'         => 'no',
			'photo_guide'        => 'yes',
			'remember_photo'     => 'yes',
			'watermark'          => 'yes',
			'default_category'   => 'auto',
			'require_login'      => 'no',
			'limit_per_user_day' => 5,
			'limit_guest_day'    => 3,
			'limit_site_day'     => 200,
			'ip_source'          => 'auto',
			'trusted_proxies'    => '',
			'captcha'            => 'pow',
			'turnstile_site'     => '',
			'turnstile_secret'   => '',
			'privacy_text'       => self::default_privacy_text(),
		);
	}

	public static function default_privacy_text() {
		return __( 'عکس من فقط برای ساخت تصویر پرو به سرویس پردازش ارسال می‌شود و روی سایت ذخیره نمی‌شود. تصویر نتیجه به‌صورت خصوصی نگه داشته و حداکثر پس از ۲۴ ساعت حذف می‌شود.', 'online-fitting-room' ) . ' {privacy}';
	}

	public static function settings() {
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/**
	 * Largest original photo accepted (only photos the browser cannot shrink,
	 * such as camera RAW, arrive this big): 25 MB, or less if PHP allows less.
	 */
	public static function max_upload_bytes() {
		return (int) min( 25 * MB_IN_BYTES, wp_max_upload_size() );
	}

	public static function max_dimension() {
		return max( 512, (int) apply_filters( 'ofr_max_dimension', 2048 ) );
	}

	public static function upload_target() {
		return max( 200 * 1024, min( self::max_upload_bytes(), (int) apply_filters( 'ofr_upload_target_bytes', self::UPLOAD_TARGET ) ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'online-fitting-room', false, dirname( plugin_basename( OFR_FILE ) ) . '/languages' );
	}

	/**
	 * DB 1: copy settings and rename product meta of the former "Webich Smart
	 * Try-On" plugin. DB 2: replace the old, inaccurate privacy text and make
	 * sure the cleanup job is scheduled. DB 3: retention is fixed at 24 hours,
	 * so the {hours} placeholder in a saved consent text becomes the number.
	 * DB 4: atomic counters table; "no CDN" becomes automatic detection, which
	 * only trusts proxy headers from verified CDN addresses and is therefore
	 * safe for every site; weekly refresh of the CDN address lists.
	 */
	public function maybe_migrate() {
		$version = (string) get_option( 'ofr_db_version', '0' );
		if ( self::DB_VERSION === $version ) return;
		// Only one request runs the migration.
		if ( get_transient( 'ofr_migrating' ) ) return;
		set_transient( 'ofr_migrating', 1, MINUTE_IN_SECONDS );
		global $wpdb;
		if ( version_compare( $version, '1', '<' ) ) {
			$legacy = get_option( 'webich_smart_tryon_settings' );
			if ( is_array( $legacy ) && false === get_option( self::OPTION_KEY ) ) {
				update_option( self::OPTION_KEY, $legacy );
			}
			$wpdb->update( $wpdb->postmeta, array( 'meta_key' => self::META_ENABLED ), array( 'meta_key' => '_webich_tryon_enabled' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery
			$wpdb->update( $wpdb->postmeta, array( 'meta_key' => self::META_CATEGORY ), array( 'meta_key' => '_webich_tryon_category' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery
		}
		if ( version_compare( $version, '2', '<' ) ) {
			$stored = get_option( self::OPTION_KEY );
			if ( is_array( $stored ) && isset( $stored['privacy_text'] ) && 'تصویر شما فقط برای ساخت نتیجه ارسال می‌شود و در رسانه‌های سایت ذخیره نخواهد شد.' === $stored['privacy_text'] ) {
				$stored['privacy_text'] = self::default_privacy_text();
				update_option( self::OPTION_KEY, $stored );
			}
		}
		if ( version_compare( $version, '3', '<' ) ) {
			$stored = get_option( self::OPTION_KEY );
			if ( is_array( $stored ) && isset( $stored['privacy_text'] ) && false !== strpos( $stored['privacy_text'], '{hours}' ) ) {
				$stored['privacy_text'] = str_replace( '{hours}', number_format_i18n( OFR_Storage::TTL_HOURS ), $stored['privacy_text'] );
				update_option( self::OPTION_KEY, $stored );
			}
		}
		if ( version_compare( $version, '4', '<' ) ) {
			OFR_Quota::install();
			$stored = get_option( self::OPTION_KEY );
			if ( is_array( $stored ) && ( $stored['ip_source'] ?? '' ) === 'remote_addr' ) {
				$stored['ip_source'] = 'auto';
				update_option( self::OPTION_KEY, $stored );
			}
			wp_schedule_single_event( time() + 30, OFR_Client::REFRESH_HOOK );
		}
		if ( version_compare( $version, '5', '<' ) ) {
			// Guests get their own daily limit; existing stores keep today's behaviour (same as members).
			$stored = get_option( self::OPTION_KEY );
			if ( is_array( $stored ) ) {
				if ( ! isset( $stored['limit_guest_day'] ) ) $stored['limit_guest_day'] = (int) ( $stored['limit_per_user_day'] ?? 5 );
				$old_default = 'عکس من فقط برای ساخت تصویر پرو به سرویس پردازش ارسال می‌شود و روی سایت ذخیره نمی‌شود. تصویر نتیجه به‌صورت خصوصی نگه داشته و حداکثر پس از ۲۴ ساعت حذف می‌شود.';
				if ( ( $stored['privacy_text'] ?? '' ) === $old_default ) $stored['privacy_text'] = self::default_privacy_text();
				update_option( self::OPTION_KEY, $stored );
			}
		}
		OFR_Storage::schedule();
		OFR_Client::schedule();
		update_option( 'ofr_db_version', self::DB_VERSION );
		delete_transient( 'ofr_migrating' );
	}

	public static function activate() {
		OFR_Quota::install();
		OFR_Storage::schedule();
		OFR_Client::schedule();
	}

	/**
	 * Nothing the plugin made about shoppers outlives it: deactivating deletes
	 * every result file and job right away, since the hourly cleanup that keeps
	 * the 24-hour promise stops with the plugin.
	 */
	public static function deactivate() {
		OFR_Storage::unschedule();
		OFR_Client::unschedule();
		OFR_Storage::remove_all();
	}

	/* ---------- Elementor ---------- */

	public function elementor_category( $elements_manager ) {
		$elements_manager->add_category( 'online-fitting-room', array( 'title' => __( 'اتاق پُرُو آنلاین', 'online-fitting-room' ), 'icon' => 'eicon-person' ) );
	}

	public function elementor_widgets( $widgets_manager ) {
		require_once __DIR__ . '/includes/class-ofr-elementor-widget.php';
		$widgets_manager->register( new OFR_Elementor_Widget() );
	}

	public function elementor_preview_styles() {
		$this->register_assets();
		wp_enqueue_style( 'online-fitting-room' );
	}

	/* ---------- Front end ---------- */

	private function content_has_shortcode() {
		$content = get_post_field( 'post_content', get_queried_object_id() );
		return is_string( $content ) && ( has_shortcode( $content, 'online_fitting_room' ) || has_shortcode( $content, 'webich_tryon' ) );
	}

	public function is_supported_product( $product_id ) {
		$s = self::settings();
		return 'yes' === $s['enabled'] && $product_id && class_exists( 'WooCommerce' ) && 'product' === get_post_type( $product_id ) && 'no' !== get_post_meta( $product_id, self::META_ENABLED, true ) && 'publish' === get_post_status( $product_id ) && has_post_thumbnail( $product_id );
	}

	/**
	 * The product in the current context: the queried post on a product page,
	 * or the global WooCommerce product inside loops and Elementor templates.
	 */
	public function current_product_id() {
		$id = get_the_ID();
		if ( $id && 'product' === get_post_type( $id ) ) return (int) $id;
		global $product;
		return ( is_object( $product ) && is_a( $product, 'WC_Product' ) ) ? (int) $product->get_id() : 0;
	}

	/** The bundled Vazirmatn (SIL OFL), only for Persian/Arabic characters; Latin falls back to the system font. */
	public static function font_face_css() {
		return '@font-face{font-family:"OFR Vazirmatn";src:url("' . esc_url( plugins_url( 'assets/fonts/vazirmatn.woff2', OFR_FILE ) ) . '") format("woff2");font-weight:100 900;font-display:swap;unicode-range:U+0600-06FF,U+0750-077F,U+08A0-08FF,U+FB50-FDFF,U+FE70-FEFF,U+200C-200F}';
	}

	/**
	 * Assets are cache-safe: the page carries no nonce or per-visitor data; the
	 * script fetches them from ofr_session when the modal opens.
	 */
	public function register_assets() {
		if ( wp_script_is( 'online-fitting-room', 'registered' ) ) return;
		$s = self::settings();
		wp_register_style( 'online-fitting-room', plugins_url( 'assets/css/tryon.css', OFR_FILE ), array(), self::VERSION );
		wp_register_script( 'online-fitting-room', plugins_url( 'assets/js/tryon.js', OFR_FILE ), array(), self::VERSION, true );
		wp_localize_script( 'online-fitting-room', 'OnlineFittingRoom', array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'wcAddToCart'   => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
			'cartUrl'       => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'maxBytes'      => self::max_upload_bytes(),
			'maxDimension'  => self::max_dimension(),
			'uploadTarget'  => self::upload_target(),
			// Formats the browser cannot decode are sent as-is when the server can convert them.
			'serverFormats' => OFR_Image::server_formats(),
			'heicDecoder'   => plugins_url( 'assets/js/vendor/heic2any.min.js', OFR_FILE ),
			'rememberPhoto' => 'yes' === $s['remember_photo'],
			'watermark'     => 'yes' === $s['watermark'] ? array( 'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), 'logo' => self::brand_logo() ) : false,
			'i18n'          => array(
				'network'      => __( 'ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی و دوباره تلاش کنید.', 'online-fitting-room' ),
				'timeout'      => __( 'پردازش بیش از حد معمول طول کشید. دوباره تلاش کنید.', 'online-fitting-room' ),
				'invalid'      => __( 'فایل انتخاب‌شده تصویر نیست یا خراب است؛ لطفاً عکس دیگری انتخاب کنید.', 'online-fitting-room' ),
				/* translators: %s: image format name, e.g. HEIC or TIFF. */
				'unsupported'  => __( 'باز کردن عکس‌های %s در این مرورگر و روی این سایت ممکن نیست؛ لطفاً عکس را با فرمت JPG انتخاب کنید.', 'online-fitting-room' ),
				'reading'      => __( 'در حال آماده‌سازی عکس…', 'online-fitting-room' ),
				'noPreview'    => __( 'پیش‌نمایش این فرمت در مرورگر شما ممکن نیست؛ عکس هنگام ساخت پرو روی سرور تبدیل می‌شود.', 'online-fitting-room' ),
				/* translators: %s: maximum file size, e.g. 8 MB. */
				'large'        => sprintf( __( 'حجم این عکس بیشتر از حد مجاز (%s) است و مرورگر نمی‌تواند آن را کوچک کند؛ لطفاً عکس کوچک‌تری انتخاب کنید.', 'online-fitting-room' ), size_format( self::max_upload_bytes() ) ),
				'tooLarge'     => __( 'حجم عکس برای سرور سایت زیاد است. لطفاً عکس را با فرمت JPG یا با کیفیت کمتر انتخاب کنید.', 'online-fitting-room' ),
				'retrying'     => __( 'در حال ارسال نسخه سبک‌تر عکس…', 'online-fitting-room' ),
				'blocked'      => __( 'درخواست توسط فایروال سایت متوقف شد. چند دقیقه بعد دوباره تلاش کنید.', 'online-fitting-room' ),
				'serverError'  => __( 'سرور سایت با خطا روبه‌رو شد. چند لحظه دیگر دوباره تلاش کنید.', 'online-fitting-room' ),
				'busy'         => __( 'تعداد درخواست‌ها زیاد است؛ چند دقیقه بعد دوباره تلاش کنید.', 'online-fitting-room' ),
				'preparing'    => __( 'در حال آماده‌سازی…', 'online-fitting-room' ),
				'securing'     => __( 'در حال تأیید امنیتی…', 'online-fitting-room' ),
				'captcha'      => __( 'لطفاً ابتدا تأیید امنیتی بالا را کامل کنید.', 'online-fitting-room' ),
				'uploading'    => __( 'در حال ارسال عکس…', 'online-fitting-room' ),
				'queued'       => __( 'در صف پردازش…', 'online-fitting-room' ),
				'processing'   => __( 'هوش مصنوعی در حال پوشاندن لباس است…', 'online-fitting-room' ),
				/* translators: %s: number of try-ons left today. */
				'remaining'    => __( 'امروز %s پرو دیگر می‌توانید انجام دهید.', 'online-fitting-room' ),
				'addToCart'    => __( 'افزودن به سبد خرید', 'online-fitting-room' ),
				'chooseOption' => __( 'انتخاب سایز / رنگ و خرید', 'online-fitting-room' ),
				'viewProduct'  => __( 'مشاهده محصول', 'online-fitting-room' ),
				'adding'       => __( 'در حال افزودن…', 'online-fitting-room' ),
				'added'        => __( '✓ به سبد خرید اضافه شد.', 'online-fitting-room' ),
				'viewCart'     => __( 'مشاهده سبد خرید', 'online-fitting-room' ),
				'drop'         => __( 'عکس را اینجا رها کنید', 'online-fitting-room' ),
				'notImage'     => __( 'فقط فایل تصویر را می‌توانید اینجا رها کنید.', 'online-fitting-room' ),
				/* translators: %s: product name. */
				'bgWorking'    => __( 'پرو «%s» در حال آماده شدن است…', 'online-fitting-room' ),
				/* translators: %s: product name. */
				'bgReady'      => __( 'پرو «%s» آماده شد.', 'online-fitting-room' ),
				'bgFailed'     => __( 'ساخت تصویر پرو انجام نشد.', 'online-fitting-room' ),
				'view'         => __( 'مشاهده', 'online-fitting-room' ),
				'retry'        => __( 'تلاش دوباره', 'online-fitting-room' ),
				/* translators: %s: seconds. */
				'eta'          => __( 'حدود %s ثانیه دیگر…', 'online-fitting-room' ),
				'etaLong'      => __( 'کمی بیشتر از معمول طول کشید؛ تقریباً تمام است…', 'online-fitting-room' ),
				/* translators: %s: number of try-ons. */
				'compare'      => __( 'مقایسه پروها (%s)', 'online-fitting-room' ),
				'viewProductShort' => __( 'مشاهده', 'online-fitting-room' ),
				'shareText'    => __( 'این لباس را در اتاق پُرُو آنلاین روی خودم امتحان کردم:', 'online-fitting-room' ),
				'shareFailed'  => __( 'اشتراک‌گذاری انجام نشد؛ تصویر دانلود شد.', 'online-fitting-room' ),
				'outOfStock'   => __( 'ناموجود', 'online-fitting-room' ),
				'savedPhoto'   => __( 'عکس ذخیره‌شده روی این دستگاه', 'online-fitting-room' ),
				'forgotten'    => __( 'عکس از این دستگاه حذف شد.', 'online-fitting-room' ),
			),
		) );
		$css = ':root{--ofr-primary:' . esc_attr( $s['primary_color'] ) . ';--ofr-accent:' . esc_attr( $s['accent_color'] ) . ';}';
		if ( 'vazirmatn' === $s['font'] ) {
			$css .= self::font_face_css() . ':root{--ofr-font:"OFR Vazirmatn",Tahoma,sans-serif}';
		}
		wp_add_inline_style( 'online-fitting-room', $css );
	}

	/**
	 * Loaded in <head> only where a button will certainly be shown (a supported
	 * product page with automatic display, or a page whose content has the
	 * shortcode). Widgets and shortcodes elsewhere load the assets themselves
	 * while rendering, so pages without a button stay untouched.
	 */
	public function enqueue_assets() {
		if ( 'yes' !== self::settings()['enabled'] ) return;
		$product_page = function_exists( 'is_product' ) && is_product() && 'yes' === self::settings()['auto_display'] && $this->is_supported_product( get_queried_object_id() );
		if ( $product_page || $this->content_has_shortcode() ) {
			$this->enqueue_frontend();
		} elseif ( 'yes' === self::settings()['loop_badge'] && $this->shows_product_grid() ) {
			// Badges load the script while rendering; the style goes in <head> so cards never flash unstyled.
			$this->register_assets();
			wp_enqueue_style( 'online-fitting-room' );
		}
	}

	private function shows_product_grid() {
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) return true;
		$post = get_post( get_queried_object_id() );
		if ( ! $post ) return false;
		foreach ( array( 'woocommerce/product-collection', 'woocommerce/handpicked-products', 'woocommerce/product-new', 'woocommerce/product-on-sale', 'woocommerce/product-best-sellers', 'woocommerce/product-category' ) as $block ) {
			if ( has_block( $block, $post ) ) return true;
		}
		return has_shortcode( $post->post_content, 'products' );
	}

	/** Store logo for the watermark on shared images: the custom logo, else the site icon. */
	public static function brand_logo() {
		$logo = get_theme_mod( 'custom_logo' );
		$url  = $logo ? wp_get_attachment_image_url( $logo, 'medium' ) : '';
		return $url ?: (string) get_site_icon_url( 192 );
	}

	/**
	 * Safe to call while rendering (shortcodes, Elementor, page builders):
	 * late styles and footer scripts are still printed by wp_footer.
	 */
	public function enqueue_frontend() {
		$this->register_assets();
		wp_enqueue_style( 'online-fitting-room' );
		wp_enqueue_script( 'online-fitting-room' );
	}

	/**
	 * Price as plain text. get_price_html() also carries screen-reader sentences
	 * ("Original price was: …") that would show up as clutter once tags are
	 * stripped, so those are removed and a sale price is sent as two values.
	 */
	public static function clean_price_html( $html ) {
		$html = preg_replace( '#<(span|div)[^>]*class="[^"]*screen-reader-text[^"]*"[^>]*>.*?</\1>#is', '', (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/** @return array { price: string, regular: string } — regular is set only for a product on sale. */
	public static function price_parts( $product ) {
		if ( ! $product->is_type( array( 'simple', 'external', 'variation' ) ) || '' === (string) $product->get_price() ) {
			return array( 'price' => self::clean_price_html( $product->get_price_html() ), 'regular' => '' );
		}
		$price   = self::clean_price_html( wc_price( wc_get_price_to_display( $product ) ) );
		$regular = '';
		if ( $product->is_on_sale() && '' !== (string) $product->get_regular_price() ) {
			$regular = self::clean_price_html( wc_price( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ) ) );
		}
		return array( 'price' => $price, 'regular' => $regular );
	}

	private function product_payload( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) return array( 'id' => $product_id, 'title' => get_the_title( $product_id ), 'price' => '', 'regular' => '', 'image' => get_the_post_thumbnail_url( $product_id, 'large' ), 'type' => '', 'ajaxAdd' => false, 'url' => get_permalink( $product_id ) );
		$price = self::price_parts( $product );
		return array(
			'id'      => $product_id,
			'title'   => $product->get_name(),
			'price'   => $price['price'],
			'regular' => $price['regular'],
			'image'   => get_the_post_thumbnail_url( $product_id, 'large' ),
			'type'    => $product->get_type(),
			// Simple products can be added straight from the modal anywhere on the site.
			'ajaxAdd' => $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock(),
			'url'     => $product->get_permalink(),
		);
	}

	public function render_button() {
		$this->render_at( 'summary' );
	}

	public function render_button_after_cart() {
		$this->render_at( 'after_cart' );
	}

	public function render_button_gallery() {
		$this->render_at( 'gallery' );
	}

	/** Automatic button at the position chosen in the settings (summary, under the add-to-cart form, or on the gallery). */
	private function render_at( $position ) {
		$s = self::settings();
		if ( 'no' === $s['auto_display'] || $s['button_position'] !== $position ) return;
		$product_id = get_the_ID();
		if ( ! $this->is_supported_product( $product_id ) ) return;
		$html = $this->button_html( $product_id, array( 'class' => 'ofr-open--' . $position ) );
		// On the gallery the script moves the button over the main image; without a gallery it simply stays here.
		echo 'gallery' === $position ? '<div class="ofr-gallery-slot" data-ofr-gallery>' . $html . '</div>' : $html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in button_html().
	}

	/**
	 * @param int   $product_id Product to try on.
	 * @param array $args {
	 *     @type string $text          Button label; defaults to the global setting.
	 *     @type string $icon_html     Trusted icon markup; defaults to the ✦ glyph, '' hides it.
	 *     @type string $icon_position 'before' or 'after' the label.
	 *     @type string $class         Extra CSS classes.
	 *     @type bool   $preview       Editor preview: inert button, no assets.
	 * }
	 */
	public function button_html( $product_id, $args = array() ) {
		$s    = self::settings();
		$args = wp_parse_args( $args, array( 'text' => '', 'icon_html' => null, 'icon_position' => 'before', 'class' => '', 'preview' => false ) );
		$text = '' !== trim( $args['text'] ) ? $args['text'] : $s['button_text'];
		$icon = null === $args['icon_html'] ? '✦' : $args['icon_html'];
		$icon = '' === $icon ? '' : '<span class="ofr-open__icon" aria-hidden="true">' . $icon . '</span>';
		$label = '<span class="ofr-open__text">' . esc_html( $text ) . '</span>';
		$data  = '';
		if ( ! $args['preview'] ) {
			$this->enqueue_frontend();
			$data = ' aria-haspopup="dialog" data-product="' . esc_attr( wp_json_encode( $this->product_payload( $product_id ) ) ) . '"';
		}
		$class = trim( 'ofr-open ' . $args['class'] );
		return '<button type="button" class="' . esc_attr( $class ) . '"' . $data . '>' . ( 'after' === $args['icon_position'] ? $label . $icon : $icon . $label ) . '</button>';
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'product_id' => $this->current_product_id(), 'text' => '' ), $atts, 'online_fitting_room' );
		$id = absint( $atts['product_id'] );
		if ( ! $this->is_supported_product( $id ) ) return '';
		return $this->button_html( $id, array( 'text' => sanitize_text_field( $atts['text'] ) ) );
	}

	/* ---------- «قابل پرو» badge on product cards ---------- */

	/** The badge opens the try-on right from the shop page, without visiting the product. */
	private function badge_html( $product_id ) {
		if ( 'yes' !== self::settings()['loop_badge'] || ! $this->is_supported_product( $product_id ) ) return '';
		$this->enqueue_frontend();
		/* translators: %s: product name. */
		$label = sprintf( __( 'پرو مجازی %s', 'online-fitting-room' ), get_the_title( $product_id ) );
		return '<span class="ofr-badge" role="button" tabindex="0" aria-haspopup="dialog" aria-label="' . esc_attr( $label ) . '" data-ofr-open data-product="' . esc_attr( wp_json_encode( $this->product_payload( $product_id ) ) ) . '"><span aria-hidden="true">✦</span> ' . esc_html__( 'قابل پرو', 'online-fitting-room' ) . '</span>';
	}

	/** Classic shop loops (shortcodes, archive templates). */
	public function loop_badge() {
		// Block themes run classic loop hooks from inside render_block for compatibility; block_badge() covers those cards.
		if ( doing_filter( 'render_block' ) ) return;
		global $product;
		echo $this->badge_html( is_object( $product ) && is_a( $product, 'WC_Product' ) ? $product->get_id() : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in badge_html().
	}

	/** Block-based product grids (Product Collection and friends): added to the product image block. */
	public function block_badge( $content, $block, $instance = null ) {
		if ( 'woocommerce/product-image' !== ( $block['blockName'] ?? '' ) || 'yes' !== self::settings()['loop_badge'] ) return $content;
		$id    = ( $instance instanceof WP_Block && isset( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : 0;
		$badge = $id ? $this->badge_html( $id ) : '';
		if ( '' === $badge ) return $content;
		$pos = strrpos( $content, '</div>' );
		return false === $pos ? $content . $badge : substr_replace( $content, $badge, $pos, 0 );
	}

	/* ---------- Colour swatches inside the modal ---------- */

	/**
	 * One swatch per distinct variation image (usually one per colour), so the
	 * shopper can try other colours with the same photo. The label is made of
	 * the attributes all variations sharing that image have in common
	 * (e.g. «مشکی» rather than «مشکی / Large»).
	 *
	 * @return array[] { id, thumb, image, label, attrs, price, regular, inStock }
	 */
	public static function swatches( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) return array();
		$groups = array();
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || 'publish' !== $variation->get_status() || ! $variation->get_image_id() ) continue;
			$groups[ $variation->get_image_id() ][] = $variation;
			if ( count( $groups ) > 24 ) break;
		}
		if ( count( $groups ) < 2 ) return array();
		$out = array();
		foreach ( $groups as $image_id => $variations ) {
			$common = $variations[0]->get_attributes();
			foreach ( $variations as $variation ) {
				$common = array_intersect_assoc( $common, $variation->get_attributes() );
			}
			$common = array_filter( $common, 'strlen' );
			$names  = array();
			foreach ( $common as $taxonomy => $value ) {
				$term    = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', $value, $taxonomy ) : null;
				$names[] = $term ? $term->name : $value;
			}
			// Prefer a variation that is in stock as the representative.
			$pick = $variations[0];
			foreach ( $variations as $variation ) {
				if ( $variation->is_in_stock() ) {
					$pick = $variation;
					break;
				}
			}
			$price = self::price_parts( $pick );
			$attrs = array();
			foreach ( $common as $taxonomy => $value ) {
				$attrs[ 'attribute_' . $taxonomy ] = $value;
			}
			$out[] = array(
				'id'      => $pick->get_id(),
				'thumb'   => wp_get_attachment_image_url( $image_id, 'thumbnail' ),
				'image'   => wp_get_attachment_image_url( $image_id, 'large' ),
				'label'   => $names ? implode( ' / ', $names ) : $pick->get_name(),
				'attrs'   => (object) $attrs,
				'price'   => $price['price'],
				'regular' => $price['regular'],
				'inStock' => $pick->is_in_stock(),
			);
		}
		return $out;
	}

	/* ---------- Modal ---------- */

	/** Consent text with optional links; {privacy} becomes a link to the privacy policy page. */
	public static function consent_html( $text ) {
		$text   = str_replace( '{hours}', number_format_i18n( OFR_Storage::TTL_HOURS ), (string) $text );
		$policy = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
		$link   = $policy ? '<a href="' . esc_url( $policy ) . '">' . esc_html__( 'سیاست حریم خصوصی', 'online-fitting-room' ) . '</a>' : '';
		$html   = wp_kses( str_replace( '{privacy}', $link, $text ), self::consent_tags() );
		// Links open in a new tab so the photo picked in the modal is not lost.
		return preg_replace( '/<a\s(?![^>]*\btarget=)/i', '<a target="_blank" rel="noopener" ', $html );
	}

	public static function consent_tags() {
		return array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'b' => array(), 'em' => array(), 'br' => array() );
	}

	/** Mobile bar that keeps the button at hand once the page has scrolled past it. */
	private function sticky_html() {
		$s  = self::settings();
		$id = function_exists( 'is_product' ) && is_product() ? get_queried_object_id() : 0;
		if ( 'yes' !== $s['sticky_mobile'] || ! $id || ! $this->is_supported_product( $id ) ) return '';
		$payload = $this->product_payload( $id );
		$thumb   = get_the_post_thumbnail_url( $id, 'thumbnail' );
		return '<div class="ofr-sticky" data-ofr-sticky hidden dir="rtl" data-theme="' . esc_attr( $s['theme_mode'] ) . '">'
			. ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="" width="44" height="44">' : '' )
			. '<span class="ofr-sticky__text"><strong>' . esc_html( $payload['title'] ) . '</strong><small>' . esc_html( $payload['price'] ) . '</small></span>'
			. $this->button_html( $id, array( 'class' => 'ofr-open--sticky' ) )
			. '</div>';
	}

	public function render_modal() {
		if ( $this->rendered || ! wp_script_is( 'online-fitting-room', 'enqueued' ) ) return;
		$this->rendered = true;
		$s      = self::settings();
		$theme  = esc_attr( $s['theme_mode'] );
		$guide  = 'yes' === $s['photo_guide'];
		$sticky = $this->sticky_html();
		?>
		<div class="ofr" hidden dir="rtl" data-theme="<?php echo $theme; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>">
			<div class="ofr__backdrop" data-ofr-close></div>
			<section class="ofr__dialog" role="dialog" aria-modal="true" aria-labelledby="ofr-title" tabindex="-1">
				<button type="button" class="ofr__close" data-ofr-close aria-label="<?php esc_attr_e( 'بستن', 'online-fitting-room' ); ?>">×</button>
				<header class="ofr__header"><span class="ofr__eyebrow"><?php esc_html_e( 'اتاق پُرُو آنلاین', 'online-fitting-room' ); ?></span><h2 id="ofr-title"><?php echo esc_html( $s['modal_title'] ); ?></h2><?php if ( '' !== trim( $s['modal_subtitle'] ) ) : ?><p><?php echo esc_html( $s['modal_subtitle'] ); ?></p><?php endif; ?></header>
				<ol class="ofr__progress" aria-label="<?php esc_attr_e( 'مراحل پرو', 'online-fitting-room' ); ?>"><li class="is-active" aria-current="step"><i>۱</i><span class="ofr__sr"><?php esc_html_e( 'انتخاب عکس', 'online-fitting-room' ); ?></span></li><li><i>۲</i><span class="ofr__sr"><?php esc_html_e( 'پردازش', 'online-fitting-room' ); ?></span></li><li><i>۳</i><span class="ofr__sr"><?php esc_html_e( 'نتیجه', 'online-fitting-room' ); ?></span></li></ol>
				<div class="ofr__body">
					<div class="ofr__stage ofr__login" data-stage="login">
						<h3 tabindex="-1"><?php esc_html_e( 'برای استفاده از اتاق پُرُو وارد حساب خود شوید', 'online-fitting-room' ); ?></h3>
						<p><?php esc_html_e( 'پرو مجازی فقط برای کاربران عضو فعال است.', 'online-fitting-room' ); ?></p>
						<a class="ofr__primary" data-login href="#"><?php esc_html_e( 'ورود / ثبت‌نام', 'online-fitting-room' ); ?></a>
					</div>

					<div class="ofr__stage is-active" data-stage="upload">
						<div class="ofr__product"><img data-product-image alt=""><div><small><?php esc_html_e( 'لباس انتخابی', 'online-fitting-room' ); ?></small><strong data-product-title></strong><span class="ofr__price"><del data-product-regular hidden></del> <span data-product-price></span></span></div></div>
						<div class="ofr__swatches" data-swatches hidden><span class="ofr__swatches-label"><?php esc_html_e( 'رنگ برای پرو:', 'online-fitting-room' ); ?></span><div class="ofr__swatch-list" role="radiogroup" aria-label="<?php esc_attr_e( 'رنگ برای پرو', 'online-fitting-room' ); ?>"></div></div>
						<div class="ofr__pick<?php echo $guide ? ' has-guide' : ''; ?>">
							<?php if ( $guide ) : ?>
								<figure class="ofr__guide" data-guide>
									<button type="button" class="ofr__guide-zoom" data-zoom-guide aria-label="<?php esc_attr_e( 'نمایش بزرگ راهنمای عکس', 'online-fitting-room' ); ?>">
										<img src="<?php echo esc_url( plugins_url( 'assets/img/photo-guide-small.webp', OFR_FILE ) ); ?>" data-full="<?php echo esc_url( plugins_url( 'assets/img/photo-guide.webp', OFR_FILE ) ); ?>" width="420" height="560" loading="lazy" decoding="async" alt="<?php esc_attr_e( 'راهنمای عکس مناسب: روبه‌دوربین بایستید، نور کافی و یکنواخت، دست‌ها کمی از بدن فاصله داشته باشد، پس‌زمینه ساده، تمام بدن داخل کادر.', 'online-fitting-room' ); ?>">
										<span class="ofr__guide-zoom-icon" aria-hidden="true">⤢</span>
									</button>
									<figcaption><?php esc_html_e( 'نمونه عکس مناسب', 'online-fitting-room' ); ?></figcaption>
								</figure>
							<?php endif; ?>
							<div class="ofr__pick-main">
								<label class="ofr__drop" data-drop><input type="file" accept="image/*" data-avatar><span class="ofr__drop-icon" aria-hidden="true">＋</span><strong><?php esc_html_e( 'عکس تمام‌قد خودت را انتخاب کن', 'online-fitting-room' ); ?></strong><em><?php esc_html_e( 'هر فرمت عکسی (JPG، HEIC آیفون، PNG، WEBP، AVIF و…)', 'online-fitting-room' ); ?></em><em class="ofr__drop-hint"><?php esc_html_e( 'یا عکس را اینجا بکشید و رها کنید (Ctrl+V هم کار می‌کند)', 'online-fitting-room' ); ?></em></label>
								<div class="ofr__preview" hidden><img data-preview alt="<?php esc_attr_e( 'پیش‌نمایش تصویر', 'online-fitting-room' ); ?>"><span class="ofr__saved-badge" data-saved-badge hidden><?php esc_html_e( 'عکس ذخیره‌شده روی این دستگاه', 'online-fitting-room' ); ?></span><p class="ofr__preview-note" data-preview-note hidden></p><div class="ofr__preview-actions"><button type="button" data-change><?php esc_html_e( 'تغییر عکس', 'online-fitting-room' ); ?></button><button type="button" data-forget hidden><?php esc_html_e( 'حذف از این دستگاه', 'online-fitting-room' ); ?></button></div></div>
							</div>
						</div>
						<?php if ( 'yes' === $s['remember_photo'] ) : ?>
							<label class="ofr__check"><input type="checkbox" data-remember><span><?php esc_html_e( 'عکسم را روی همین دستگاه نگه دار تا برای لباس‌های دیگر دوباره آپلود نکنم (فقط در مرورگر خودم ذخیره می‌شود).', 'online-fitting-room' ); ?></span></label>
						<?php endif; ?>
						<label class="ofr__check ofr__consent"><input type="checkbox" data-consent><span><?php echo self::consent_html( $s['privacy_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses() in consent_html(). ?></span></label>
						<div class="ofr__captcha" data-captcha hidden></div>
						<div class="ofr__error" role="alert" hidden><span data-error-text></span><a class="ofr__upsell" data-upsell href="#" hidden></a></div>
						<p class="ofr__remaining" data-remaining hidden></p>
						<button type="button" class="ofr__primary" data-start disabled><?php esc_html_e( 'ساخت تصویر پرو', 'online-fitting-room' ); ?> <b aria-hidden="true">←</b></button>
					</div>

					<div class="ofr__stage ofr__working" data-stage="working">
						<div class="ofr__scan" data-scan>
							<div class="ofr__scan-photo"><img data-scan-photo alt=""><span class="ofr__scan-line" aria-hidden="true"></span><span class="ofr__scan-grid" aria-hidden="true"></span></div>
							<span class="ofr__scan-plus" aria-hidden="true">+</span>
							<div class="ofr__scan-garment"><img data-scan-garment alt=""></div>
						</div>
						<div class="ofr__orb" data-orb aria-hidden="true" hidden><span></span></div>
						<h3 tabindex="-1"><?php esc_html_e( 'داریم استایل جدیدت را می‌سازیم', 'online-fitting-room' ); ?></h3>
						<p data-status-text role="status" aria-live="polite"></p>
						<div class="ofr__meter" data-meter role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e( 'پیشرفت تخمینی', 'online-fitting-room' ); ?>"><span></span></div>
						<small data-eta><?php esc_html_e( 'این مرحله معمولاً چند ثانیه زمان می‌برد.', 'online-fitting-room' ); ?></small>
						<button type="button" class="ofr__background" data-background><?php esc_html_e( 'بستن و ادامه خرید — وقتی آماده شد خبرتان می‌کنیم', 'online-fitting-room' ); ?></button>
					</div>

					<div class="ofr__stage ofr__result" data-stage="result">
						<div class="ofr__compare" data-compare-view>
							<img class="ofr__compare-after" data-result alt="<?php esc_attr_e( 'نتیجه پرو مجازی', 'online-fitting-room' ); ?>">
							<div class="ofr__compare-before" data-before-wrap hidden><img data-before alt="<?php esc_attr_e( 'عکس شما پیش از پرو', 'online-fitting-room' ); ?>"></div>
							<span class="ofr__compare-handle" data-handle hidden aria-hidden="true"><i>‹ ›</i></span>
							<input type="range" class="ofr__compare-range" data-compare min="0" max="100" value="50" dir="ltr" hidden aria-label="<?php esc_attr_e( 'مقایسه قبل و بعد', 'online-fitting-room' ); ?>">
							<span class="ofr__compare-tag is-before" data-tag-before hidden><?php esc_html_e( 'قبل', 'online-fitting-room' ); ?></span><span class="ofr__compare-tag is-after" data-tag-after hidden><?php esc_html_e( 'بعد', 'online-fitting-room' ); ?></span>
							<button type="button" class="ofr__fullscreen" data-zoom-result aria-label="<?php esc_attr_e( 'نمایش تمام‌صفحه و بزرگ‌نمایی', 'online-fitting-room' ); ?>">⤢</button>
						</div>
						<div class="ofr__result-side">
							<span class="ofr__success"><?php esc_html_e( '✓ آماده شد', 'online-fitting-room' ); ?></span>
							<h3 tabindex="-1"><?php esc_html_e( 'این استایل چطور شد؟', 'online-fitting-room' ); ?></h3>
							<p data-result-product></p>
							<div class="ofr__swatches is-compact" data-result-swatches hidden><span class="ofr__swatches-label"><?php esc_html_e( 'امتحان با رنگ دیگر:', 'online-fitting-room' ); ?></span><div class="ofr__swatch-list"></div></div>
							<a class="ofr__primary" data-cart href="#"></a>
							<p class="ofr__cart-note" data-cart-note role="status" hidden></p>
							<div class="ofr__row">
								<a class="ofr__secondary" data-download href="#"><?php esc_html_e( 'دانلود', 'online-fitting-room' ); ?></a>
								<button type="button" class="ofr__secondary" data-share hidden><?php esc_html_e( 'اشتراک‌گذاری', 'online-fitting-room' ); ?></button>
							</div>
							<div class="ofr__links">
								<button type="button" data-retry><?php esc_html_e( 'امتحان دوباره با همین عکس', 'online-fitting-room' ); ?></button>
								<button type="button" data-again><?php esc_html_e( 'پرو با عکس دیگر', 'online-fitting-room' ); ?></button>
								<button type="button" data-open-compare hidden></button>
							</div>
						</div>
					</div>

					<div class="ofr__stage ofr__gallery" data-stage="compare">
						<h3 tabindex="-1"><?php esc_html_e( 'مقایسه پروهای شما', 'online-fitting-room' ); ?></h3>
						<div class="ofr__gallery-grid" data-gallery></div>
						<button type="button" class="ofr__secondary" data-back-result><?php esc_html_e( 'بازگشت به نتیجه', 'online-fitting-room' ); ?></button>
					</div>
				</div>
				<div class="ofr__dropzone" data-dropzone hidden aria-hidden="true"><span></span></div>
			</section>
			<div class="ofr__viewer" data-viewer hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'نمایش بزرگ', 'online-fitting-room' ); ?>" tabindex="-1">
				<img data-viewer-img alt="">
				<button type="button" class="ofr__viewer-close" data-viewer-close aria-label="<?php esc_attr_e( 'بستن', 'online-fitting-room' ); ?>">×</button>
				<p class="ofr__viewer-hint"><?php esc_html_e( 'برای بزرگ‌نمایی دو انگشت را باز کنید یا دوبار بزنید', 'online-fitting-room' ); ?></p>
			</div>
		</div>
		<div class="ofr-toast" data-ofr-toast hidden dir="rtl" role="status" aria-live="polite" data-theme="<?php echo $theme; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>"><span class="ofr-toast__icon" aria-hidden="true"></span><span class="ofr-toast__text" data-toast-text></span><button type="button" class="ofr-toast__action" data-toast-action hidden></button><button type="button" class="ofr-toast__close" data-toast-close aria-label="<?php esc_attr_e( 'بستن', 'online-fitting-room' ); ?>">×</button></div>
		<?php
		echo $sticky; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in sticky_html().
	}

	/* ---------- Usage limits ---------- */

	/**
	 * Every limit as an atomic bucket (see OFR_Quota). Burst buckets count every
	 * attempt; daily buckets count try-ons the service accepted.
	 *
	 * @return array { burst: array[], day: array[] }
	 */
	private function buckets() {
		$s       = self::settings();
		$keys    = OFR_Client::keys();
		$times   = OFR_Client::shared_multiplier();
		$day     = wp_date( 'Ymd' );
		$per_day = (int) ( is_user_logged_in() ? $s['limit_per_user_day'] : $s['limit_guest_day'] );
		$burst   = max( 1, (int) apply_filters( 'ofr_burst_limit', self::BURST_LIMIT ) );
		$window  = (int) floor( time() / self::BURST_WINDOW );
		$out     = array(
			'burst' => array( array( 'name' => "burst|{$keys['primary']}|{$window}", 'limit' => $burst, 'ttl' => self::BURST_WINDOW + MINUTE_IN_SECONDS, 'scope' => 'burst' ) ),
			'day'   => array(
				array( 'name' => "day|site|{$day}", 'limit' => (int) $s['limit_site_day'], 'ttl' => 2 * DAY_IN_SECONDS, 'scope' => 'site' ),
				array( 'name' => "day|{$keys['primary']}|{$day}", 'limit' => $per_day, 'ttl' => 2 * DAY_IN_SECONDS, 'scope' => 'user' ),
			),
		);
		foreach ( $keys['shared'] as $key ) {
			$out['burst'][] = array( 'name' => "burst|{$key}|{$window}", 'limit' => $burst * $times, 'ttl' => self::BURST_WINDOW + MINUTE_IN_SECONDS, 'scope' => 'burst' );
			$out['day'][]   = array( 'name' => "day|{$key}|{$day}", 'limit' => $per_day * $times, 'ttl' => 2 * DAY_IN_SECONDS, 'scope' => 'user' );
		}
		return $out;
	}

	public function usage_today() {
		return OFR_Quota::get( 'day|site|' . wp_date( 'Ymd' ) );
	}

	/** @return int|null Try-ons this visitor may still start today, null when unlimited. */
	private function remaining_today() {
		$left = array();
		foreach ( $this->buckets()['day'] as $bucket ) {
			if ( $bucket['limit'] > 0 ) $left[] = $bucket['limit'] - OFR_Quota::get( $bucket['name'] );
		}
		return $left ? max( 0, min( $left ) ) : null;
	}

	private function login_required() {
		return 'yes' === self::settings()['require_login'] && ! is_user_logged_in();
	}

	private function limit_message( $scope ) {
		switch ( $scope ) {
			case 'site':
				return __( 'ظرفیت پرو مجازی امروز به پایان رسیده است. لطفاً فردا دوباره سر بزنید.', 'online-fitting-room' );
			case 'user':
				return __( 'سهم پرو امروز شما تمام شده است. فردا دوباره امتحان کنید.', 'online-fitting-room' );
		}
		return __( 'تعداد درخواست‌ها زیاد است؛ چند دقیقه بعد دوباره تلاش کنید.', 'online-fitting-room' );
	}

	/**
	 * When a guest has used up the guest allowance and members get more, say so
	 * (with a login link) instead of a dead end.
	 */
	private function upsell( $scope ) {
		$s = self::settings();
		if ( 'user' !== $scope || is_user_logged_in() ) return array();
		$member = (int) $s['limit_per_user_day'];
		if ( 0 !== $member && $member <= (int) $s['limit_guest_day'] ) return array();
		return array(
			'upsell'  => 0 === $member ? __( 'ورود / ثبت‌نام برای پرو بیشتر', 'online-fitting-room' )
				/* translators: %s: number of try-ons. */
				: sprintf( __( 'ورود / ثبت‌نام و دریافت %s پرو در روز', 'online-fitting-room' ), number_format_i18n( $member ) ),
			'message' => __( 'سهم پرو امروز مهمان‌ها تمام شده است، ولی اعضای فروشگاه پرو بیشتری دارند.', 'online-fitting-room' ),
		);
	}

	/** Shown to guests under the remaining count when members get more try-ons. */
	private function member_hint() {
		$s = self::settings();
		if ( is_user_logged_in() || (int) $s['limit_guest_day'] === 0 ) return '';
		$member = (int) $s['limit_per_user_day'];
		if ( 0 === $member ) return __( 'با ورود به حساب، پرو بیشتری در اختیار دارید.', 'online-fitting-room' );
		/* translators: %s: number of daily try-ons for members. */
		return $member > (int) $s['limit_guest_day'] ? sprintf( __( 'با ورود به حساب، %s پرو در روز.', 'online-fitting-room' ), number_format_i18n( $member ) ) : '';
	}

	/** JSON error that also hands the browser its next security challenge. */
	private function fail( $message, $status, $extra = array() ) {
		wp_send_json_error( array_merge( array( 'message' => $message, 'captcha' => OFR_Captcha::client_config() ), $extra ), $status );
	}

	/* ---------- AJAX ---------- */

	private function verify_ajax() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'نشست شما منقضی شده؛ دوباره تلاش کنید.', 'online-fitting-room' ), 'nonce' => true ), 403 );
		}
	}

	/** Fresh, never-cached per-visitor state, requested when the modal opens. */
	public function ajax_session() {
		nocache_headers();
		OFR_Client::ensure_guest_cookie();
		OFR_Client::observe();
		OFR_Storage::maybe_cleanup();
		$return     = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return'] ?? '' ) ), home_url( '/' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- this endpoint issues the nonce.
		$product_id = absint( $_POST['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- read-only data.
		$login  = function_exists( 'wc_get_page_permalink' ) && wc_get_page_id( 'myaccount' ) > 0 ? add_query_arg( 'redirect_to', rawurlencode( $return ), wc_get_page_permalink( 'myaccount' ) ) : wp_login_url( $return );
		wp_send_json_success( array(
			'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
			'loginRequired' => $this->login_required(),
			'loginUrl'      => $login,
			'remaining'     => $this->remaining_today(),
			'captcha'       => OFR_Captcha::client_config(),
			'memberHint'    => $this->member_hint(),
			'swatches'      => $product_id && $this->is_supported_product( $product_id ) ? self::swatches( $product_id ) : array(),
		) );
	}

	public function ajax_start() {
		OFR_Api::start_budget();
		$this->verify_ajax();
		$product_id = absint( $_POST['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- verified above.
		if ( ! $this->is_supported_product( $product_id ) ) $this->fail( __( 'این محصول برای پرو مجازی آماده نیست.', 'online-fitting-room' ), 400 );
		if ( $this->login_required() ) {
			$this->fail( __( 'برای استفاده از اتاق پُرُو ابتدا وارد حساب کاربری خود شوید.', 'online-fitting-room' ), 401, array( 'login' => true ) );
		}

		$buckets = $this->buckets();
		// Every attempt counts towards the 10-minute burst limit, taken atomically before any work is done.
		$burst = OFR_Quota::reserve( $buckets['burst'] );
		if ( ! $burst['ok'] ) $this->fail( $this->limit_message( 'burst' ), 429 );

		$captcha = OFR_Captcha::verify( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- verified above; sanitised per field.
		if ( is_wp_error( $captcha ) ) $this->fail( $captcha->get_error_message(), 403, array( 'captchaFailed' => true ) );

		// Fast answer when today's capacity is already used up (the reservation below is the real guard).
		$s = self::settings();
		if ( $s['limit_site_day'] > 0 && $this->usage_today() >= $s['limit_site_day'] ) $this->fail( $this->limit_message( 'site' ), 429 );

		if ( '' === OFR_Api::key() ) {
			OFR_Api::log_error( 'start', 0, 'No API key configured.' );
			$this->fail( OFR_Api::customer_error( 503 )['message'], 503 );
		}

		// phpcs:disable WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- verified above; the upload is validated below.
		if ( empty( $_FILES['avatar'] ) || UPLOAD_ERR_OK !== (int) $_FILES['avatar']['error'] ) {
			$code = isset( $_FILES['avatar']['error'] ) ? (int) $_FILES['avatar']['error'] : 0;
			$big  = in_array( $code, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true );
			$this->fail( $big ? __( 'حجم تصویر بیشتر از حد مجاز سرور است.', 'online-fitting-room' ) : __( 'آپلود تصویر کامل نشد؛ دوباره تلاش کنید.', 'online-fitting-room' ), $big ? 413 : 400 );
		}
		$file = $_FILES['avatar'];
		// phpcs:enable
		if ( (int) $file['size'] > self::max_upload_bytes() ) $this->fail( __( 'حجم تصویر بیشتر از حد مجاز است.', 'online-fitting-room' ), 413 );
		// Any supported format (HEIC, AVIF, TIFF, RAW, …) is converted here when the browser could not; metadata is always removed.
		$person = OFR_Image::normalize( $file['tmp_name'], sanitize_file_name( $file['name'] ), self::max_dimension(), OFR_Api::PERSON_MAX_BYTES );
		if ( is_wp_error( $person ) ) {
			$format = $person->get_error_data()['format'] ?? '';
			if ( 'ofr_unsupported_on_server' === $person->get_error_code() ) {
				/* translators: %s: image format name, e.g. HEIC or TIFF. */
				$message = sprintf( __( 'تبدیل عکس‌های %s روی این سایت ممکن نیست؛ لطفاً عکس را با فرمت JPG انتخاب کنید.', 'online-fitting-room' ), OFR_Image::label( $format ) );
			} else {
				$message = __( 'فایل انتخاب‌شده تصویر نیست یا خراب است؛ لطفاً عکس دیگری انتخاب کنید.', 'online-fitting-room' );
			}
			if ( 'ofr_convert_failed' === $person->get_error_code() ) OFR_Api::log_error( 'person image (' . OFR_Image::label( $format ) . ')', 0, $person->get_error_message() );
			$this->fail( $message, 415 );
		}

		// A selected variation with its own image (e.g. another colour) is tried on instead of the main image.
		$variation_id = absint( $_POST['variation_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$image_id     = (int) get_post_thumbnail_id( $product_id );
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && $variation->is_type( 'variation' ) && (int) $variation->get_parent_id() === $product_id && $variation->get_image_id() ) {
				$image_id = (int) $variation->get_image_id();
			} else {
				$variation_id = 0;
			}
		}
		$garment = OFR_Api::garment_file( $image_id );
		if ( is_wp_error( $garment ) ) {
			OFR_Api::log_error( 'garment image #' . $image_id, 0, $garment->get_error_message() );
			$this->fail( __( 'تصویر این لباس قابل استفاده نیست؛ لطفاً به فروشگاه اطلاع دهید.', 'online-fitting-room' ), 500 );
		}

		// The daily allowance is reserved atomically right before the paid call and given back if it fails.
		$day = OFR_Quota::reserve( $buckets['day'] );
		if ( ! $day['ok'] ) {
			if ( $garment['temp'] ) wp_delete_file( $garment['path'] );
			$this->fail( $this->limit_message( $day['scope'] ), 429, $this->upsell( $day['scope'] ) );
		}

		$category = get_post_meta( $product_id, self::META_CATEGORY, true ) ?: $s['default_category'];
		// Only a specific garment type is sent; "auto" leaves detection to the API.
		$fields = 'auto' !== $category ? array( 'category' => $category ) : array();
		$fields = (array) apply_filters( 'ofr_api_fields', $fields, $product_id, $variation_id );

		$response = OFR_Api::create_tryon(
			array( 'bytes' => $person['bytes'], 'mime' => $person['mime'] ),
			$garment,
			$fields,
			'ofr-' . $product_id . '-' . wp_generate_uuid4(),
			'wc-product-' . ( $variation_id ?: $product_id )
		);
		if ( $garment['temp'] ) wp_delete_file( $garment['path'] );

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $data['id'] ) || ! is_string( $data['id'] ) ) {
			OFR_Quota::release( $day['taken'] );
			OFR_Api::log_error( 'start', $code, OFR_Api::error_detail( $response ) );
			$error = OFR_Api::customer_error( $code >= 200 && $code < 300 ? 502 : $code );
			$this->fail( $error['message'], $error['status'] );
		}

		// If the try-on fails later, the shopper's own allowance comes back (the site total stays: the service may have charged).
		$refund = array_values( array_filter( $day['taken'], function ( $name ) {
			return 0 !== strpos( $name, 'day|site|' );
		} ) );
		$token = OFR_Storage::create_job( array( 'prediction' => sanitize_text_field( $data['id'] ), 'product' => $product_id, 'variation' => $variation_id, 'refund' => $refund ) );
		OFR_Api::remember_prediction( sanitize_text_field( $data['id'] ) );
		do_action( 'ofr_tryon_started', $product_id, $variation_id );
		wp_send_json_success( array( 'job' => $token, 'retryAfter' => $this->retry_after( $response ), 'remaining' => $this->remaining_today(), 'captcha' => OFR_Captcha::client_config() ) );
	}

	/** Gives the shopper's daily try-on back once, when a started try-on ends without a result. */
	private function refund( $token, $job ) {
		if ( ! empty( $job['refunded'] ) || empty( $job['refund'] ) ) return;
		OFR_Quota::release( (array) $job['refund'] );
		$job['refunded'] = true;
		OFR_Storage::save_job( $token, $job );
	}

	/** Ends a job with an error the browser will not retry. */
	private function terminal( $token, $job, $message, $status, $reason ) {
		$this->refund( $token, $job );
		do_action( 'ofr_tryon_failed', (int) $job['product'], (int) $job['variation'], $reason );
		wp_send_json_error( array( 'message' => $message, 'terminal' => true ), $status );
	}

	public function ajax_status() {
		OFR_Api::start_budget();
		$this->verify_ajax();
		$token = sanitize_text_field( wp_unslash( $_POST['job'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- verified above.
		$job   = OFR_Storage::get_job( $token );
		if ( ! $job ) wp_send_json_error( array( 'message' => __( 'این پرو منقضی شده است؛ دوباره تلاش کنید.', 'online-fitting-room' ), 'terminal' => true ), 410 );
		// Finished jobs are answered from the stored file: no new download, no new upstream call.
		if ( $job['file'] && OFR_Storage::path( $job['file'] ) ) $this->send_result( $token );
		if ( ++$job['polls'] > OFR_Storage::MAX_POLLS ) $this->terminal( $token, $job, __( 'پردازش بیش از حد معمول طول کشید. دوباره تلاش کنید.', 'online-fitting-room' ), 408, 'timeout' );
		OFR_Storage::save_job( $token, $job );

		$response = OFR_Api::get_tryon( $job['prediction'] );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			OFR_Api::log_error( 'status', $code, OFR_Api::error_detail( $response ) );
			// Transport hiccups, rate limits and 5xx are retried by the browser; anything else ends the job.
			if ( 0 === $code || $code >= 500 || 429 === $code ) wp_send_json_success( array( 'status' => 'processing', 'retryAfter' => 5 ) );
			$error = OFR_Api::customer_error( $code );
			$this->terminal( $token, $job, $error['message'], $error['status'], 'http_' . $code );
		}
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = sanitize_key( $data['status'] ?? '' );
		if ( 'needs_review' === $status ) {
			$this->terminal( $token, $job, __( 'این تصویر برای بررسی بیشتر متوقف شد. لطفاً عکس دیگری امتحان کنید.', 'online-fitting-room' ), 422, 'needs_review' );
		}
		if ( in_array( $status, array( 'failed', 'canceled', 'cancelled' ), true ) ) {
			OFR_Api::log_error( 'try-on ' . $status, 200, $data['error'] ?? '' );
			$this->terminal( $token, $job, __( 'ساخت تصویر ناموفق بود. لطفاً با یک عکس تمام‌قد و روبه‌رو دوباره امتحان کنید.', 'online-fitting-room' ), 422, $status );
		}
		if ( 'succeeded' !== $status ) {
			wp_send_json_success( array( 'status' => $status ?: 'processing', 'retryAfter' => $this->retry_after( $response ) ) );
		}
		if ( ! OFR_Storage::lock( $token ) ) wp_send_json_success( array( 'status' => 'processing', 'retryAfter' => 2 ) );
		$this->download_result( $token, $job, $data );
		OFR_Storage::unlock( $token );
		do_action( 'ofr_tryon_succeeded', (int) $job['product'], (int) $job['variation'] );
		$this->send_result( $token );
	}

	private function send_result( $token ) {
		$url = add_query_arg( array( 'action' => 'ofr_result', 't' => $token ), admin_url( 'admin-ajax.php' ) );
		wp_send_json_success( array( 'status' => 'succeeded', 'output' => $url, 'download' => add_query_arg( 'download', '1', $url ) ) );
	}

	private function download_result( $token, $job, $data ) {
		$fail = function ( $detail, $code = 0 ) use ( $token, $job ) {
			OFR_Storage::unlock( $token );
			OFR_Api::log_error( 'result', $code, $detail );
			$this->terminal( $token, $job, __( 'دریافت تصویر نهایی ناموفق بود؛ دوباره تلاش کنید.', 'online-fitting-room' ), 502, 'download' );
		};
		$response = OFR_Api::get_result( $job['prediction'] );
		if ( is_wp_error( $response ) ) $fail( $response->get_error_message() );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) $fail( OFR_Api::error_detail( $response ), $code );
		$bytes = wp_remote_retrieve_body( $response );
		$mime  = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
		if ( ! in_array( $mime, array( 'image/png', 'image/jpeg' ), true ) ) $fail( 'Unexpected result content type: ' . $mime, $code );
		$expected = strtolower( sanitize_text_field( $data['result']['sha256'] ?? '' ) );
		if ( $expected && ! hash_equals( $expected, hash( 'sha256', $bytes ) ) ) $fail( 'Result SHA-256 mismatch.', $code );
		$name = OFR_Storage::save( $bytes, $mime, $token );
		if ( ! $name ) $fail( 'Could not write the result to ' . OFR_Storage::dir(), $code );
		$job['file'] = $name;
		$job['mime'] = $mime;
		OFR_Storage::save_job( $token, $job );
	}

	/**
	 * Streams a private result to whoever holds its job token (the <img> and
	 * the download link in the modal), decrypting it with that token. Never
	 * cached, never indexed.
	 */
	public function ajax_result() {
		$token = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- the job token is the credential.
		$job   = OFR_Storage::get_job( $token );
		$bytes = $job ? OFR_Storage::read( $job['file'], $token ) : false;
		if ( false === $bytes || '' === $bytes ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$download = ! empty( $_GET['download'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$jpeg     = 'image/jpeg' === $job['mime'];
		nocache_headers();
		header( 'Content-Type: ' . ( $jpeg ? 'image/jpeg' : 'image/png' ) );
		header( 'Content-Length: ' . strlen( $bytes ) );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="fitting-room-' . absint( $job['product'] ) . ( $jpeg ? '.jpg' : '.png' ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'Cross-Origin-Resource-Policy: same-origin' );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput -- binary image.
		exit;
	}

	private function retry_after( $response ) {
		$value = absint( wp_remote_retrieve_header( $response, 'retry-after' ) );
		return min( 30, max( 2, $value ?: 2 ) );
	}
}

add_action( 'plugins_loaded', array( 'Online_Fitting_Room', 'instance' ) );
register_activation_hook( __FILE__, array( 'Online_Fitting_Room', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Online_Fitting_Room', 'deactivate' ) );

// The plugin stores nothing in orders, so it is compatible with HPOS and the cart/checkout blocks.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OFR_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', OFR_FILE, true );
	}
} );
