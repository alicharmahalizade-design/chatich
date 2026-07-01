<?php
/**
 * Plugin Name: هسته دوریان (Dorian Core)
 * Description: هستهٔ دوریان + ماژولِ «رزرو دوریان»: نوبت‌دهی با تقویم شمسی، خدمات/قیمت/زمان، متخصص‌ها، پیامکِ فراز و پرداخت (WooCommerce). شورت‌کد و ویجت المنتور.
 * Version: 1.2.0
 * Author: Ronakads
 * Text Domain: dorian-core
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) exit;

define('DORIAN_VER', '1.2.0');
define('DORIAN_FILE', __FILE__);
define('DORIAN_DIR', plugin_dir_path(__FILE__));
define('DORIAN_URL', plugin_dir_url(__FILE__));
define('DORIAN_TABLE', 'dorian_bookings');

require_once DORIAN_DIR . 'includes/db.php';
require_once DORIAN_DIR . 'includes/cpt.php';
require_once DORIAN_DIR . 'includes/seed.php';
require_once DORIAN_DIR . 'includes/settings.php';
require_once DORIAN_DIR . 'includes/sms.php';
require_once DORIAN_DIR . 'includes/ajax.php';
require_once DORIAN_DIR . 'includes/frontend.php';
require_once DORIAN_DIR . 'includes/elementor.php';
require_once DORIAN_DIR . 'includes/panel.php';
require_once DORIAN_DIR . 'includes/manage.php';

/* ---------------- activation / deactivation ---------------- */
register_activation_hook(__FILE__, function () {
    Dorian_DB::install();
    Dorian_CPT::register();         // so rewrite rules include them
    Dorian_Seed::run();             // sample services/providers (like the preview)
    Dorian_Seed::ensure_page();     // a published "رزرو نوبت" page with the shortcode
    do_action('init');              // register provider slug rewrite rules before flushing
    flush_rewrite_rules();
});

/** URL of the booking page (for the theme's reserve buttons). */
function dorian_booking_url() {
    $id = (int) get_option('dorian_booking_page_id');
    if ($id && get_post_status($id) === 'publish') return get_permalink($id);
    return home_url('/');
}

/**
 * Self-heal: if the plugin was *updated* (not freshly activated) the activation
 * hook never fires, so the sample data / booking page can be missing. Seed it on
 * the first admin load instead. Both calls are guarded by options, so they run
 * at most once and are cheap afterwards.
 */
add_action('admin_init', function () {
    if (!get_option('dorian_seeded')) Dorian_Seed::run();
    Dorian_Seed::ensure_page(); // idempotent; also applies the Canvas (no header/footer) template
});
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
    wp_clear_scheduled_hook('dorian_send_reminder'); // clears all args? safer to leave individual events
});

/* ---------------- defaults ---------------- */
function dorian_settings() {
    $defaults = array(
        'work_start'     => '10:00',
        'work_end'       => '22:00',
        'slot_min'       => 30,
        'off_days'       => array(6),     // 0=Sat .. 6=Fri  (Friday off)
        'deposit_rate'   => 30,           // %
        'reminder_hours' => 3,
        'currency'       => 'تومان',
        'pay_mode'       => 'woocommerce', // woocommerce | none
        // management / reception oversight panel (dorianstudio.ir/<manage_slug>)
        'manage_slug'    => 'modir',
        'manage_pass'    => '',
        // FarazSMS / IPPanel
        'sms_enabled'    => 0,
        'sms_apikey'     => '',
        'sms_sender'     => '',
        'sms_base'       => 'https://api2.ippanel.com/api/v1',
        'sms_mode'       => 'message',     // message | pattern
        'sms_pattern_customer' => '',
        'sms_pattern_provider' => '',
        'sms_pattern_reminder' => '',
        // plain-message templates (placeholders: {name} {date} {time} {service} {provider})
        'tpl_customer'   => 'دوریان: {name} عزیز، نوبت شما برای {service} با {provider} در {date} ساعت {time} ثبت شد.',
        'tpl_provider'   => 'دوریان: نوبت جدید — {service} برای {name} ({phone}) در {date} ساعت {time}.',
        'tpl_reminder'   => 'دوریان: {name} عزیز، یادآوری نوبت شما فردا/امروز ساعت {time} ({service}).',
    );
    $saved = get_option('dorian_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
}

/* ---------------- reminder cron callback ---------------- */
add_action('dorian_send_reminder', function ($booking_id) {
    Dorian_DB::send_reminder((int) $booking_id);
});
