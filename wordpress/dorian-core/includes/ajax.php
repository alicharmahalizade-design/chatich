<?php
if (!defined('ABSPATH')) exit;

class Dorian_Ajax {

    public static function boot() {
        foreach (array('slots', 'firstfree', 'book', 'checkcode') as $a) {
            add_action('wp_ajax_dorian_' . $a, array(__CLASS__, $a));
            add_action('wp_ajax_nopriv_dorian_' . $a, array(__CLASS__, $a));
        }
        // mark booking confirmed once its deposit order is paid
        add_action('woocommerce_order_status_processing', array(__CLASS__, 'order_paid'));
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'order_paid'));
    }

    protected static function verify() {
        check_ajax_referer('dorian_booking', 'nonce');
    }

    protected static function provider_ids() {
        $p = isset($_POST['providers']) ? (array) $_POST['providers'] : array();
        return array_values(array_unique(array_filter(array_map('intval', $p))));
    }

    /** price/duration of the chosen services FOR a specific provider */
    protected static function calc($sub_ids, $pid) {
        $svcs = function_exists('dorian_provider_services') ? dorian_provider_services($pid) : array();
        $price = 0; $dur = 0; $names = array();
        foreach ($sub_ids as $sid) {
            $sid = (int) $sid;
            if (isset($svcs[$sid])) {
                $price += (int) $svcs[$sid]['price'];
                $dur   += (int) $svcs[$sid]['dur'];
                $names[] = $svcs[$sid]['name'];
            }
        }
        return array('price' => $price, 'dur' => max(15, $dur), 'names' => implode('، ', $names));
    }

    /** the single chosen provider */
    protected static function pid() { $p = self::provider_ids(); return $p ? (int) $p[0] : 0; }

    public static function slots() {
        self::verify();
        $pid = self::pid();
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $sub_ids = isset($_POST['subs']) ? array_map('intval', (array) $_POST['subs']) : array();
        if (!$pid || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) wp_send_json_error('bad request');
        $c = self::calc($sub_ids, $pid);
        wp_send_json_success(dorian_provider_slots($pid, $date, $c['dur']));
    }

    public static function firstfree() {
        self::verify();
        $pid = self::pid();
        $sub_ids = isset($_POST['subs']) ? array_map('intval', (array) $_POST['subs']) : array();
        if (!$pid) wp_send_json_error('none');
        $c = self::calc($sub_ids, $pid);
        for ($d = 0; $d < 90; $d++) {
            $ymd = date('Y-m-d', strtotime("+$d day", current_time('timestamp')));
            foreach (dorian_provider_slots($pid, $ymd, $c['dur']) as $slot) {
                if ($slot['free']) wp_send_json_success(array('date' => $ymd, 'time' => $slot['t']));
            }
        }
        wp_send_json_error('none');
    }

    public static function book() {
        self::verify();
        $providers = self::provider_ids();
        $sub_ids = isset($_POST['subs']) ? array_map('intval', (array) $_POST['subs']) : array();
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $weeks = isset($_POST['weeks']) ? (int) $_POST['weeks'] : 0;
        $name  = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone = isset($_POST['phone']) ? preg_replace('/\D/', '', $_POST['phone']) : '';

        $pid = $providers ? (int) $providers[0] : 0;
        if (!$sub_ids || !$pid) wp_send_json_error('خدمت/متخصص ناقص است.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) wp_send_json_error('تاریخ/ساعت نامعتبر است.');
        if (!$name || !preg_match('/^09\d{9}$/', $phone)) wp_send_json_error('نام و موبایل معتبر وارد کنید.');

        $c = self::calc($sub_ids, $pid);

        // server-side re-check: the requested start must still be free for the provider
        $free = false;
        foreach (dorian_provider_slots($pid, $date, $c['dur']) as $slot) {
            if ($slot['t'] === $time) { $free = $slot['free']; break; }
        }
        if (!$free) wp_send_json_error('این زمان دیگر خالی نیست. لطفاً زمان دیگری انتخاب کنید.');
        $providers = array($pid);

        $s = dorian_settings();
        // how much is paid online: the full price, or a deposit percentage
        if (isset($s['pay_amount']) && $s['pay_amount'] === 'deposit') {
            $payable = (int) round($c['price'] * ((int) $s['deposit_rate']) / 100 / 1000) * 1000;
        } else {
            $payable = (int) $c['price'];
        }

        // credit code: holders skip online payment and pay at the salon
        $code_in = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $code_valid = ($code_in !== '' && self::code_is_valid($code_in, $s));
        if ($code_valid) $payable = 0;

        $start = $date . ' ' . $time . ':00';

        $id = Dorian_DB::insert(array(
            'created_at'     => current_time('mysql'),
            'status'         => $code_valid ? 'at_salon' : 'confirmed',
            'customer_name'  => $name,
            'customer_phone' => $phone,
            'provider_ids'   => implode(',', $providers),
            'service_ids'    => implode(',', array_map('intval', $sub_ids)),
            'service_names'  => $c['names'],
            'start_dt'       => $start,
            'duration_min'   => $c['dur'],
            'recurrence_weeks' => max(0, $weeks),
            'total_price'    => $c['price'],
            'deposit_price'  => $payable,
            'credit_code'    => $code_valid ? $code_in : null,
        ));
        if (!$id) wp_send_json_error('ثبت نوبت ناموفق بود.');

        $booking = Dorian_DB::get($id);

        // SMS to customer + providers
        Dorian_SMS::send_booking($booking, $providers);

        // schedule reminder
        $rh = (int) $s['reminder_hours'];
        $remind_at = strtotime($start) - $rh * 3600;
        if ($remind_at > time()) wp_schedule_single_event($remind_at, 'dorian_send_reminder', array($id));

        // payment (skipped entirely when a valid credit code was used)
        if (!$code_valid && $s['pay_mode'] === 'woocommerce' && class_exists('WooCommerce') && $payable > 0) {
            $url = self::wc_payment_order($booking, $payable);
            if ($url) wp_send_json_success(array('redirect' => $url, 'id' => $id));
        }
        wp_send_json_success(array('id' => $id, 'event' => self::gcal($booking), 'atSalon' => $code_valid));
    }

    /** Validate a credit code on demand (so the UI can switch to "pay at salon"). */
    public static function checkcode() {
        self::verify();
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $s = dorian_settings();
        if ($code !== '' && self::code_is_valid($code, $s)) {
            wp_send_json_success(array('valid' => true));
        }
        wp_send_json_error('invalid');
    }

    /** True when $code matches one of the admin's credit codes (case-insensitive). */
    protected static function code_is_valid($code, $s) {
        $raw = isset($s['credit_codes']) ? (string) $s['credit_codes'] : '';
        $list = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)));
        if (!$list) return false;
        $code = function_exists('mb_strtolower') ? mb_strtolower(trim($code)) : strtolower(trim($code));
        foreach ($list as $valid) {
            $v = function_exists('mb_strtolower') ? mb_strtolower($valid) : strtolower($valid);
            if ($v === $code) return true;
        }
        return false;
    }

    protected static function wc_payment_order($booking, $amount) {
        try {
            $order = wc_create_order();
            $fee = new WC_Order_Item_Fee();
            $fee->set_name('پرداخت نوبت دوریان #' . $booking->id);
            $fee->set_amount($amount);
            $fee->set_total($amount);
            $order->add_item($fee);
            $order->set_address(array('first_name' => $booking->customer_name, 'phone' => $booking->customer_phone), 'billing');
            $order->update_meta_data('_dorian_booking_id', $booking->id);
            $order->calculate_totals();
            $order->update_status('pending', 'Dorian booking payment');
            $order->save();
            return $order->get_checkout_payment_url();
        } catch (Exception $e) {
            error_log('[Dorian WC] ' . $e->getMessage());
            return '';
        }
    }

    public static function order_paid($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        $bid = (int) $order->get_meta('_dorian_booking_id');
        if ($bid) {
            global $wpdb;
            $wpdb->update(Dorian_DB::table(), array('status' => 'paid'), array('id' => $bid));
        }
    }

    protected static function gcal($b) {
        $start = gmdate('Ymd\THis', strtotime($b->start_dt) - (int) (get_option('gmt_offset') * 3600));
        $end = gmdate('Ymd\THis', strtotime($b->start_dt) + $b->duration_min * 60 - (int) (get_option('gmt_offset') * 3600));
        return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode('نوبت دوریان — ' . $b->service_names)
            . '&dates=' . $start . 'Z/' . $end . 'Z';
    }
}
Dorian_Ajax::boot();
