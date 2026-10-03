<?php
/**
 * Plugin Name: اتاق پُرُو آنلاین
 * Description: اتاق پرو مجازی هوشمند برای ووکامرس؛ مشتری عکس خودش را بارگذاری می‌کند و در چند ثانیه همان لباس را روی تصویر خودش می‌بیند. همراه با ویجت المنتور، پشتیبانی از همه فرمت‌های عکس و حفظ حریم خصوصی مشتری.
 * Version: 1.5.4
 * Requires at least: 6.2
 * Requires PHP: 7.4
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
require_once __DIR__ . '/includes/class-ofr-storage.php';
require_once __DIR__ . '/includes/class-ofr-admin.php';

final class Online_Fitting_Room {
	const VERSION       = '1.5.4';
	const DB_VERSION    = '3';
	const OPTION_KEY    = 'ofr_settings';
	const NONCE_ACTION  = 'ofr_tryon';
	const META_ENABLED  = '_ofr_enabled';
	const META_CATEGORY = '_ofr_category';
	const BURST_LIMIT   = 8;   // Attempts per client…
	const BURST_WINDOW  = 600; // …per 10 minutes.

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
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_migrate' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_button' ), 35 );
		add_action( 'wp_footer', array( $this, 'render_modal' ) );
		add_shortcode( 'online_fitting_room', array( $this, 'shortcode' ) );
		add_shortcode( 'webich_tryon', array( $this, 'shortcode' ) ); // Legacy name from Webich Smart Try-On.
		foreach ( array( 'session', 'start', 'status', 'result' ) as $action ) {
			add_action( 'wp_ajax_ofr_' . $action, array( $this, 'ajax_' . $action ) );
			add_action( 'wp_ajax_nopriv_ofr_' . $action, array( $this, 'ajax_' . $action ) );
		}
		add_action( OFR_Storage::CLEANUP_HOOK, array( 'OFR_Storage', 'cleanup' ) );
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
			'default_category'   => 'auto',
			'require_login'      => 'no',
			'limit_per_user_day' => 5,
			'limit_site_day'     => 200,
			'ip_source'          => 'remote_addr',
			'privacy_text'       => self::default_privacy_text(),
		);
	}

	public static function default_privacy_text() {
		return __( 'عکس من فقط برای ساخت تصویر پرو به سرویس پردازش ارسال می‌شود و روی سایت ذخیره نمی‌شود. تصویر نتیجه به‌صورت خصوصی نگه داشته و حداکثر پس از ۲۴ ساعت حذف می‌شود.', 'online-fitting-room' );
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

	public function load_textdomain() {
		load_plugin_textdomain( 'online-fitting-room', false, dirname( plugin_basename( OFR_FILE ) ) . '/languages' );
	}

	/**
	 * DB 1: copy settings and rename product meta of the former "Webich Smart
	 * Try-On" plugin. DB 2: replace the old, inaccurate privacy text and make
	 * sure the cleanup job is scheduled. DB 3: retention is fixed at 24 hours,
	 * so the {hours} placeholder in a saved consent text becomes the number.
	 */
	public function maybe_migrate() {
		$version = (string) get_option( 'ofr_db_version', '0' );
		if ( self::DB_VERSION === $version ) return;
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
		OFR_Storage::schedule();
		update_option( 'ofr_db_version', self::DB_VERSION );
	}

	public static function activate() {
		OFR_Storage::schedule();
	}

	public static function deactivate() {
		OFR_Storage::unschedule();
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
		return has_shortcode( $content, 'online_fitting_room' ) || has_shortcode( $content, 'webich_tryon' );
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
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'wcAddToCart'  => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
			'cartUrl'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'maxBytes'     => self::max_upload_bytes(),
			'maxDimension' => self::max_dimension(),
			// Formats the browser cannot decode are sent as-is when the server can convert them.
			'serverFormats' => OFR_Image::server_formats(),
			'heicDecoder'  => plugins_url( 'assets/js/vendor/heic2any.min.js', OFR_FILE ),
			'i18n'         => array(
				'network'      => __( 'ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی و دوباره تلاش کنید.', 'online-fitting-room' ),
				'timeout'      => __( 'پردازش بیش از حد معمول طول کشید. دوباره تلاش کنید.', 'online-fitting-room' ),
				'invalid'      => __( 'فایل انتخاب‌شده تصویر نیست یا خراب است؛ لطفاً عکس دیگری انتخاب کنید.', 'online-fitting-room' ),
				/* translators: %s: image format name, e.g. HEIC or TIFF. */
				'unsupported'  => __( 'باز کردن عکس‌های %s در این مرورگر و روی این سایت ممکن نیست؛ لطفاً عکس را با فرمت JPG انتخاب کنید.', 'online-fitting-room' ),
				'reading'      => __( 'در حال آماده‌سازی عکس…', 'online-fitting-room' ),
				'noPreview'    => __( 'پیش‌نمایش این فرمت در مرورگر شما ممکن نیست؛ عکس هنگام ساخت پرو روی سرور تبدیل می‌شود.', 'online-fitting-room' ),
				/* translators: %s: maximum file size, e.g. 8 MB. */
				'large'        => sprintf( __( 'حجم این عکس بیشتر از حد مجاز (%s) است و مرورگر نمی‌تواند آن را کوچک کند؛ لطفاً عکس کوچک‌تری انتخاب کنید.', 'online-fitting-room' ), size_format( self::max_upload_bytes() ) ),
				'preparing'    => __( 'در حال آماده‌سازی…', 'online-fitting-room' ),
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
			),
		) );
		wp_add_inline_style( 'online-fitting-room', ':root{--ofr-primary:' . esc_attr( $s['primary_color'] ) . ';--ofr-accent:' . esc_attr( $s['accent_color'] ) . ';}' );
	}

	public function enqueue_assets() {
		if ( ( function_exists( 'is_product' ) && is_product() ) || $this->content_has_shortcode() ) {
			$this->enqueue_frontend();
		}
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

	private function product_payload( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) return array( 'id' => $product_id, 'title' => get_the_title( $product_id ), 'price' => '', 'image' => get_the_post_thumbnail_url( $product_id, 'large' ), 'type' => '', 'ajaxAdd' => false, 'url' => get_permalink( $product_id ) );
		return array(
			'id'      => $product_id,
			'title'   => $product->get_name(),
			'price'   => wp_strip_all_tags( $product->get_price_html() ),
			'image'   => get_the_post_thumbnail_url( $product_id, 'large' ),
			'type'    => $product->get_type(),
			// Simple products can be added straight from the modal anywhere on the site.
			'ajaxAdd' => $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock(),
			'url'     => $product->get_permalink(),
		);
	}

	public function render_button() {
		if ( 'no' === self::settings()['auto_display'] ) return;
		$product_id = get_the_ID();
		if ( ! $this->is_supported_product( $product_id ) ) return;
		echo $this->button_html( $product_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in button_html().
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

	public function render_modal() {
		if ( $this->rendered || ! wp_script_is( 'online-fitting-room', 'enqueued' ) ) return;
		$this->rendered = true;
		$s       = self::settings();
		$privacy = str_replace( '{hours}', number_format_i18n( OFR_Storage::TTL_HOURS ), $s['privacy_text'] );
		?>
		<div class="ofr" hidden dir="rtl">
			<div class="ofr__backdrop" data-ofr-close></div>
			<section class="ofr__dialog" role="dialog" aria-modal="true" aria-labelledby="ofr-title" tabindex="-1">
				<button type="button" class="ofr__close" data-ofr-close aria-label="<?php esc_attr_e( 'بستن', 'online-fitting-room' ); ?>">×</button>
				<header class="ofr__header"><span class="ofr__eyebrow"><?php esc_html_e( 'اتاق پُرُو آنلاین', 'online-fitting-room' ); ?></span><h2 id="ofr-title"><?php esc_html_e( 'قبل از خرید، تنت ببین', 'online-fitting-room' ); ?></h2><p><?php esc_html_e( 'عکس خودت را بده؛ هوش مصنوعی همین لباس را روی تصویرت شبیه‌سازی می‌کند.', 'online-fitting-room' ); ?></p></header>
				<ol class="ofr__progress" aria-label="<?php esc_attr_e( 'مراحل پرو', 'online-fitting-room' ); ?>"><li class="is-active" aria-current="step"><i>۱</i><span class="ofr__sr"><?php esc_html_e( 'انتخاب عکس', 'online-fitting-room' ); ?></span></li><li><i>۲</i><span class="ofr__sr"><?php esc_html_e( 'پردازش', 'online-fitting-room' ); ?></span></li><li><i>۳</i><span class="ofr__sr"><?php esc_html_e( 'نتیجه', 'online-fitting-room' ); ?></span></li></ol>
				<div class="ofr__body">
					<div class="ofr__stage ofr__login" data-stage="login">
						<h3 tabindex="-1"><?php esc_html_e( 'برای استفاده از اتاق پُرُو وارد حساب خود شوید', 'online-fitting-room' ); ?></h3>
						<p><?php esc_html_e( 'پرو مجازی فقط برای کاربران عضو فعال است.', 'online-fitting-room' ); ?></p>
						<a class="ofr__primary" data-login href="#"><?php esc_html_e( 'ورود / ثبت‌نام', 'online-fitting-room' ); ?></a>
					</div>
					<div class="ofr__stage is-active" data-stage="upload">
						<div class="ofr__product"><img data-product-image alt=""><div><small><?php esc_html_e( 'لباس انتخابی', 'online-fitting-room' ); ?></small><strong data-product-title></strong><span data-product-price></span></div></div>
						<label class="ofr__drop"><input type="file" accept="image/*" data-avatar><span class="ofr__drop-icon" aria-hidden="true">＋</span><strong><?php esc_html_e( 'عکس تمام‌قد خودت را انتخاب کن', 'online-fitting-room' ); ?></strong><em><?php esc_html_e( 'هر فرمت عکسی (JPG، HEIC آیفون، PNG، WEBP، AVIF و…) — روبه‌دوربین و با نور کافی', 'online-fitting-room' ); ?></em></label>
						<div class="ofr__preview" hidden><img data-preview alt="<?php esc_attr_e( 'پیش‌نمایش تصویر', 'online-fitting-room' ); ?>"><p class="ofr__preview-note" data-preview-note hidden></p><button type="button" data-change><?php esc_html_e( 'تغییر عکس', 'online-fitting-room' ); ?></button></div>
						<label class="ofr__consent"><input type="checkbox" data-consent><span><?php echo esc_html( $privacy ); ?></span></label>
						<p class="ofr__error" role="alert" hidden></p>
						<p class="ofr__remaining" data-remaining hidden></p>
						<button type="button" class="ofr__primary" data-start disabled><?php esc_html_e( 'ساخت تصویر پرو', 'online-fitting-room' ); ?> <b aria-hidden="true">←</b></button>
					</div>
					<div class="ofr__stage ofr__working" data-stage="working"><div class="ofr__orb" aria-hidden="true"><span></span></div><h3 tabindex="-1"><?php esc_html_e( 'داریم استایل جدیدت را می‌سازیم', 'online-fitting-room' ); ?></h3><p data-status-text role="status" aria-live="polite"></p><div class="ofr__meter" aria-hidden="true"><span></span></div><small><?php esc_html_e( 'این مرحله معمولاً چند ثانیه زمان می‌برد.', 'online-fitting-room' ); ?></small></div>
					<div class="ofr__stage ofr__result" data-stage="result"><div class="ofr__result-image"><img data-result alt="<?php esc_attr_e( 'نتیجه پرو مجازی', 'online-fitting-room' ); ?>"></div><div><span class="ofr__success"><?php esc_html_e( '✓ آماده شد', 'online-fitting-room' ); ?></span><h3 tabindex="-1"><?php esc_html_e( 'این استایل چطور شد؟', 'online-fitting-room' ); ?></h3><p data-result-product></p><a class="ofr__primary" data-cart href="#"></a><p class="ofr__cart-note" data-cart-note role="status" hidden></p><a class="ofr__secondary" data-download href="#"><?php esc_html_e( 'دانلود تصویر', 'online-fitting-room' ); ?></a><button type="button" class="ofr__again" data-again><?php esc_html_e( 'پرو با عکس دیگر', 'online-fitting-room' ); ?></button></div></div>
				</div>
			</section>
		</div>
		<?php
	}

	/* ---------- Usage limits ---------- */

	/**
	 * The visitor's IP. Proxy headers are trusted only when the admin picked
	 * that proxy, since any client can send them.
	 */
	private function client_ip() {
		$headers = array( 'cloudflare' => 'HTTP_CF_CONNECTING_IP', 'x_real_ip' => 'HTTP_X_REAL_IP', 'x_forwarded' => 'HTTP_X_FORWARDED_FOR' );
		$source  = self::settings()['ip_source'];
		$ip      = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( isset( $headers[ $source ] ) && ! empty( $_SERVER[ $headers[ $source ] ] ) ) {
			$list = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $headers[ $source ] ] ) ) ) );
			// X-Forwarded-For: the right-most entry is the one our own proxy appended.
			$candidate = 'x_forwarded' === $source ? end( $list ) : $list[0];
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) $ip = $candidate;
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
	}

	private function client_key() {
		return is_user_logged_in() ? 'user:' . get_current_user_id() : 'ip:' . $this->client_ip();
	}

	private function day_key( $scope ) {
		return 'ofr_day_' . md5( $scope . '|' . wp_date( 'Ymd' ) );
	}

	private function bump( $key, $ttl ) {
		set_transient( $key, (int) get_transient( $key ) + 1, $ttl );
	}

	public function usage_today() {
		return (int) get_transient( $this->day_key( 'site' ) );
	}

	/** @return int|null Try-ons this visitor may still start today, null when unlimited. */
	private function remaining_today() {
		$s    = self::settings();
		$left = array();
		if ( $s['limit_per_user_day'] > 0 ) $left[] = $s['limit_per_user_day'] - (int) get_transient( $this->day_key( $this->client_key() ) );
		if ( $s['limit_site_day'] > 0 ) $left[] = $s['limit_site_day'] - $this->usage_today();
		return $left ? max( 0, min( $left ) ) : null;
	}

	private function login_required() {
		return 'yes' === self::settings()['require_login'] && ! is_user_logged_in();
	}

	private function enforce_limits() {
		if ( $this->login_required() ) {
			wp_send_json_error( array( 'message' => __( 'برای استفاده از اتاق پُرُو ابتدا وارد حساب کاربری خود شوید.', 'online-fitting-room' ), 'login' => true ), 401 );
		}
		$s = self::settings();
		if ( $s['limit_site_day'] > 0 && $this->usage_today() >= $s['limit_site_day'] ) {
			wp_send_json_error( array( 'message' => __( 'ظرفیت پرو مجازی امروز به پایان رسیده است. لطفاً فردا دوباره سر بزنید.', 'online-fitting-room' ) ), 429 );
		}
		if ( $s['limit_per_user_day'] > 0 && (int) get_transient( $this->day_key( $this->client_key() ) ) >= $s['limit_per_user_day'] ) {
			wp_send_json_error( array( 'message' => __( 'سهم پرو امروز شما تمام شده است. فردا دوباره امتحان کنید.', 'online-fitting-room' ) ), 429 );
		}
		$burst = 'ofr_burst_' . md5( $this->client_key() );
		if ( (int) get_transient( $burst ) >= (int) apply_filters( 'ofr_burst_limit', self::BURST_LIMIT ) ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست‌ها زیاد است؛ چند دقیقه بعد دوباره تلاش کنید.', 'online-fitting-room' ) ), 429 );
		}
		$this->bump( $burst, self::BURST_WINDOW );
	}

	/* ---------- AJAX ---------- */

	private function verify_ajax() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'نشست شما منقضی شده؛ دوباره تلاش کنید.', 'online-fitting-room' ), 'nonce' => true ), 403 );
		}
	}

	/** Fresh, never-cached per-visitor state, requested when the modal opens. */
	public function ajax_session() {
		$return = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return'] ?? '' ) ), home_url( '/' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- this endpoint issues the nonce.
		$login  = function_exists( 'wc_get_page_permalink' ) && wc_get_page_id( 'myaccount' ) > 0 ? add_query_arg( 'redirect_to', rawurlencode( $return ), wc_get_page_permalink( 'myaccount' ) ) : wp_login_url( $return );
		wp_send_json_success( array(
			'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
			'loginRequired' => $this->login_required(),
			'loginUrl'      => $login,
			'remaining'     => $this->remaining_today(),
		) );
	}

	public function ajax_start() {
		OFR_Api::start_budget();
		$this->verify_ajax();
		$product_id = absint( $_POST['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- verified above.
		if ( ! $this->is_supported_product( $product_id ) ) wp_send_json_error( array( 'message' => __( 'این محصول برای پرو مجازی آماده نیست.', 'online-fitting-room' ) ), 400 );
		$this->enforce_limits();
		if ( '' === OFR_Api::key() ) {
			OFR_Api::log_error( 'start', 0, 'No API key configured.' );
			wp_send_json_error( array( 'message' => OFR_Api::customer_error( 503 )['message'] ), 503 );
		}

		// phpcs:disable WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- verified above; the upload is validated below.
		if ( empty( $_FILES['avatar'] ) || UPLOAD_ERR_OK !== (int) $_FILES['avatar']['error'] ) {
			$code = isset( $_FILES['avatar']['error'] ) ? (int) $_FILES['avatar']['error'] : 0;
			$msg  = in_array( $code, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? __( 'حجم تصویر بیشتر از حد مجاز سرور است.', 'online-fitting-room' ) : __( 'آپلود تصویر کامل نشد؛ دوباره تلاش کنید.', 'online-fitting-room' );
			wp_send_json_error( array( 'message' => $msg ), 400 );
		}
		$file = $_FILES['avatar'];
		// phpcs:enable
		if ( (int) $file['size'] > self::max_upload_bytes() ) wp_send_json_error( array( 'message' => __( 'حجم تصویر بیشتر از حد مجاز است.', 'online-fitting-room' ) ), 413 );
		// Any supported format (HEIC, AVIF, TIFF, RAW, …) is converted here when the browser could not.
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
			wp_send_json_error( array( 'message' => $message ), 415 );
		}
		$bytes = $person['bytes'];
		$mime  = $person['mime'];

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
			wp_send_json_error( array( 'message' => __( 'تصویر این لباس قابل استفاده نیست؛ لطفاً به فروشگاه اطلاع دهید.', 'online-fitting-room' ) ), 500 );
		}

		$s        = self::settings();
		$category = get_post_meta( $product_id, self::META_CATEGORY, true ) ?: $s['default_category'];
		// Only a specific garment type is sent; "auto" leaves detection to the API.
		$fields = 'auto' !== $category ? array( 'category' => $category ) : array();
		$fields = (array) apply_filters( 'ofr_api_fields', $fields, $product_id, $variation_id );

		$response = OFR_Api::create_tryon(
			array( 'bytes' => $bytes, 'mime' => $mime ),
			$garment,
			$fields,
			'ofr-' . $product_id . '-' . wp_generate_uuid4(),
			'wc-product-' . ( $variation_id ?: $product_id )
		);
		if ( $garment['temp'] ) wp_delete_file( $garment['path'] );

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $data['id'] ) || ! is_string( $data['id'] ) ) {
			OFR_Api::log_error( 'start', $code, OFR_Api::error_detail( $response ) );
			$error = OFR_Api::customer_error( $code >= 200 && $code < 300 ? 502 : $code );
			wp_send_json_error( array( 'message' => $error['message'] ), $error['status'] );
		}

		$token = OFR_Storage::create_job( array( 'prediction' => sanitize_text_field( $data['id'] ), 'product' => $product_id, 'variation' => $variation_id ) );
		OFR_Api::remember_prediction( sanitize_text_field( $data['id'] ) );
		$this->bump( $this->day_key( 'site' ), DAY_IN_SECONDS );
		$this->bump( $this->day_key( $this->client_key() ), DAY_IN_SECONDS );
		wp_send_json_success( array( 'job' => $token, 'retryAfter' => $this->retry_after( $response ), 'remaining' => $this->remaining_today() ) );
	}

	public function ajax_status() {
		OFR_Api::start_budget();
		$this->verify_ajax();
		$token = sanitize_text_field( wp_unslash( $_POST['job'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- verified above.
		$job   = OFR_Storage::get_job( $token );
		if ( ! $job ) wp_send_json_error( array( 'message' => __( 'این پرو منقضی شده است؛ دوباره تلاش کنید.', 'online-fitting-room' ), 'terminal' => true ), 410 );
		// Finished jobs are answered from the stored file: no new download, no new upstream call.
		if ( $job['file'] && OFR_Storage::path( $job['file'] ) ) $this->send_result( $token );
		if ( ++$job['polls'] > OFR_Storage::MAX_POLLS ) wp_send_json_error( array( 'message' => __( 'پردازش بیش از حد معمول طول کشید. دوباره تلاش کنید.', 'online-fitting-room' ), 'terminal' => true ), 408 );
		OFR_Storage::save_job( $token, $job );

		$response = OFR_Api::get_tryon( $job['prediction'] );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			OFR_Api::log_error( 'status', $code, OFR_Api::error_detail( $response ) );
			// Transport hiccups, rate limits and 5xx are retried by the browser; anything else ends the job.
			if ( 0 === $code || $code >= 500 || 429 === $code ) wp_send_json_success( array( 'status' => 'processing', 'retryAfter' => 5 ) );
			$error = OFR_Api::customer_error( $code );
			wp_send_json_error( array( 'message' => $error['message'], 'terminal' => true ), $error['status'] );
		}
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = sanitize_key( $data['status'] ?? '' );
		if ( 'needs_review' === $status ) {
			wp_send_json_error( array( 'message' => __( 'این تصویر برای بررسی بیشتر متوقف شد. لطفاً عکس دیگری امتحان کنید.', 'online-fitting-room' ), 'terminal' => true ), 422 );
		}
		if ( in_array( $status, array( 'failed', 'canceled', 'cancelled' ), true ) ) {
			OFR_Api::log_error( 'try-on ' . $status, 200, $data['error'] ?? '' );
			wp_send_json_error( array( 'message' => __( 'ساخت تصویر ناموفق بود. لطفاً با یک عکس تمام‌قد و روبه‌رو دوباره امتحان کنید.', 'online-fitting-room' ), 'terminal' => true ), 422 );
		}
		if ( 'succeeded' !== $status ) {
			wp_send_json_success( array( 'status' => $status ?: 'processing', 'retryAfter' => $this->retry_after( $response ) ) );
		}
		if ( ! OFR_Storage::lock( $token ) ) wp_send_json_success( array( 'status' => 'processing', 'retryAfter' => 2 ) );
		$this->download_result( $token, $job, $data );
		OFR_Storage::unlock( $token );
		$this->send_result( $token );
	}

	private function send_result( $token ) {
		$url = add_query_arg( array( 'action' => 'ofr_result', 't' => $token ), admin_url( 'admin-ajax.php' ) );
		wp_send_json_success( array( 'status' => 'succeeded', 'output' => $url, 'download' => add_query_arg( 'download', '1', $url ) ) );
	}

	private function download_result( $token, $job, $data ) {
		$fail = function ( $detail, $code = 0 ) use ( $token ) {
			OFR_Storage::unlock( $token );
			OFR_Api::log_error( 'result', $code, $detail );
			wp_send_json_error( array( 'message' => __( 'دریافت تصویر نهایی ناموفق بود؛ دوباره تلاش کنید.', 'online-fitting-room' ), 'terminal' => true ), 502 );
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
		$name = OFR_Storage::save( $bytes, $mime );
		if ( ! $name ) $fail( 'Could not write the result to ' . OFR_Storage::dir(), $code );
		$job['file'] = $name;
		$job['mime'] = $mime;
		OFR_Storage::save_job( $token, $job );
	}

	/**
	 * Streams a private result to whoever holds its job token (the <img> and
	 * the download link in the modal). Never cached, never indexed.
	 */
	public function ajax_result() {
		$job  = OFR_Storage::get_job( sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- the job token is the credential.
		$path = $job ? OFR_Storage::path( $job['file'] ) : '';
		if ( ! $path ) {
			status_header( 404 );
			exit;
		}
		$download = ! empty( $_GET['download'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$jpeg     = 'image/jpeg' === $job['mime'];
		nocache_headers();
		header( 'Content-Type: ' . ( $jpeg ? 'image/jpeg' : 'image/png' ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="fitting-room-' . absint( $job['product'] ) . ( $jpeg ? '.jpg' : '.png' ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
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
