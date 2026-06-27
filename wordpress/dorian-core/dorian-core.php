<?php
/**
 * Plugin Name: هسته دوریان (Dorian Core)
 * Description: هستهٔ دوریان + ماژولِ «رزرو دوریان»: نوبت‌دهی با تقویم شمسی، خدمات/قیمت/زمان، متخصص‌ها، پیامکِ فراز و پرداخت (WooCommerce). شورت‌کد و ویجت المنتور.
 * Version: 1.0.0
 * Author: Ronakads
 * Text Domain: dorian-core
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) exit;

define('DORIAN_VER', '1.0.0');
define('DORIAN_FILE', __FILE__);
define('DORIAN_DIR', plugin_dir_path(__FILE__));
define('DORIAN_URL', plugin_dir_url(__FILE__));
define('DORIAN_TABLE', 'dorian_bookings');

require_once DORIAN_DIR . 'includes/db.php';
require_once DORIAN_DIR . 'includes/cpt.php';
require_once DORIAN_DIR . 'includes/settings.php';
require_once DORIAN_DIR . 'includes/sms.php';
require_once DORIAN_DIR . 'includes/ajax.php';
require_once DORIAN_DIR . 'includes/frontend.php';
require_once DORIAN_DIR . 'includes/elementor.php';

/* ---------------- activation / deactivation ---------------- */
register_activation_hook(__FILE__, function () {
    Dorian_DB::install();
    Dorian_CPT::register();         // so rewrite rules include them
    flush_rewrite_rules();
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
