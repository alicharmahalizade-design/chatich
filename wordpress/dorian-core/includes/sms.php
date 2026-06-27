<?php
if (!defined('ABSPATH')) exit;

/** Gregorian timestamp -> Jalali "روزِ هفته d ماه Y" + separate pieces */
function dorian_jalali($ts) {
    $gy = (int) date('Y', $ts); $gm = (int) date('n', $ts); $gd = (int) date('j', $ts);
    $g_d_m = array(0,31,59,90,120,151,181,212,243,273,304,334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053)); $days %= 12053;
    $jy += 4 * intdiv($days, 1461); $days %= 1461;
    if ($days > 365) { $jy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
    if ($days < 186) { $jm = 1 + intdiv($days, 31); $jd = 1 + ($days % 31); }
    else { $jm = 7 + intdiv($days - 186, 30); $jd = 1 + (($days - 186) % 30); }
    $months = array('فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند');
    $weekdays = array('یک‌شنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه','شنبه'); // date('w') 0=Sun
    $wd = $weekdays[(int) date('w', $ts)];
    $fa = function ($n) { return strtr($n, '0123456789', '۰۱۲۳۴۵۶۷۸۹'); };
    return array(
        'full' => $wd . ' ' . $fa($jd) . ' ' . $months[$jm - 1] . ' ' . $fa($jy),
        'time' => $fa(date('H:i', $ts)),
    );
}

class Dorian_SMS {

    public static function send_booking($booking, $provider_ids) {
        $s = dorian_settings();
        if (empty($s['sms_enabled'])) return;
        $j = dorian_jalali(strtotime($booking->start_dt));
        $vars = array(
            '{name}' => $booking->customer_name, '{phone}' => $booking->customer_phone,
            '{date}' => $j['full'], '{time}' => $j['time'],
            '{service}' => $booking->service_names, '{provider}' => self::provider_names($provider_ids),
        );
        // customer
        self::dispatch($booking->customer_phone, $vars, $s['tpl_customer'], $s['sms_pattern_customer']);
        // each provider on that booking
        foreach ((array) $provider_ids as $pid) {
            $ph = get_post_meta((int) $pid, '_dorian_phone', true);
            if ($ph) self::dispatch($ph, $vars, $s['tpl_provider'], $s['sms_pattern_provider']);
        }
    }

    public static function send_reminder($booking) {
        $s = dorian_settings();
        if (empty($s['sms_enabled'])) return;
        $j = dorian_jalali(strtotime($booking->start_dt));
        $vars = array(
            '{name}' => $booking->customer_name, '{phone}' => $booking->customer_phone,
            '{date}' => $j['full'], '{time}' => $j['time'],
            '{service}' => $booking->service_names, '{provider}' => '',
        );
        self::dispatch($booking->customer_phone, $vars, $s['tpl_reminder'], $s['sms_pattern_reminder']);
    }

    protected static function provider_names($ids) {
        $names = array();
        foreach ((array) $ids as $id) { $t = get_the_title((int) $id); if ($t) $names[] = $t; }
        return implode('، ', $names);
    }

    protected static function dispatch($phone, $vars, $template, $pattern_code) {
        $s = dorian_settings();
        $phone = preg_replace('/\D/', '', $phone);
        if (!$phone) return;
        $base = rtrim($s['sms_base'], '/');
        $headers = array('apikey' => $s['sms_apikey'], 'Content-Type' => 'application/json', 'Accept' => 'application/json');

        if ($s['sms_mode'] === 'pattern' && $pattern_code) {
            // strip braces from keys for pattern variables
            $variable = array();
            foreach ($vars as $k => $v) $variable[trim($k, '{}')] = $v;
            $body = array('code' => $pattern_code, 'sender' => $s['sms_sender'], 'recipient' => $phone, 'variable' => $variable);
            $url = $base . '/sms/pattern/normal/send';
        } else {
            $message = strtr($template, $vars);
            $body = array('recipient' => array($phone), 'sender' => $s['sms_sender'], 'message' => $message);
            $url = $base . '/sms/send/webservice/single';
        }

        $res = wp_remote_post($url, array('headers' => $headers, 'body' => wp_json_encode($body), 'timeout' => 15));
        if (is_wp_error($res)) {
            error_log('[Dorian SMS] ' . $res->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) error_log('[Dorian SMS] HTTP ' . $code . ' — ' . wp_remote_retrieve_body($res));
        }
    }
}
