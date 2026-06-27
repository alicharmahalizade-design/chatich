<?php
if (!defined('ABSPATH')) exit;

class Dorian_DB {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . DORIAN_TABLE;
    }

    public static function install() {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
            customer_name VARCHAR(120) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            provider_ids VARCHAR(190) NOT NULL,
            service_ids VARCHAR(190) NOT NULL,
            service_names TEXT NULL,
            start_dt DATETIME NOT NULL,
            duration_min INT NOT NULL,
            recurrence_weeks INT NOT NULL DEFAULT 0,
            total_price BIGINT NOT NULL DEFAULT 0,
            deposit_price BIGINT NOT NULL DEFAULT 0,
            reminded TINYINT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY start_dt (start_dt)
        ) $charset;");
    }

    /** bookings for a set of providers on a given Y-m-d date */
    public static function bookings_for($provider_ids, $date_ymd) {
        global $wpdb;
        $provider_ids = array_map('intval', (array) $provider_ids);
        if (!$provider_ids) return array();
        $table = self::table();
        $like = array();
        foreach ($provider_ids as $pid) {
            $like[] = $wpdb->prepare("FIND_IN_SET(%d, provider_ids)", $pid);
        }
        $where = '(' . implode(' OR ', $like) . ')';
        $sql = $wpdb->prepare(
            "SELECT start_dt, duration_min, provider_ids FROM $table
             WHERE DATE(start_dt)=%s AND status<>'cancelled' AND $where",
            $date_ymd
        );
        return $wpdb->get_results($sql);
    }

    /** returns array of "HH:MM" => bool free, for the slot grid of a date */
    public static function slots($provider_ids, $date_ymd, $duration_min) {
        $s = dorian_settings();
        $step = max(15, (int) $s['slot_min']);
        $dur  = max($step, (int) $duration_min);
        list($sh, $sm) = array_map('intval', explode(':', $s['work_start']));
        list($eh, $em) = array_map('intval', explode(':', $s['work_end']));
        $start = $sh * 60 + $sm;
        $end   = $eh * 60 + $em;

        $busy = array(); // list of [from,to] minutes for chosen providers
        foreach (self::bookings_for($provider_ids, $date_ymd) as $b) {
            $t = strtotime($b->start_dt);
            $from = (int) date('G', $t) * 60 + (int) date('i', $t);
            $busy[] = array($from, $from + (int) $b->duration_min);
        }

        $now_min = -1;
        if ($date_ymd === current_time('Y-m-d')) {
            $now_min = (int) current_time('G') * 60 + (int) current_time('i');
        }

        $out = array();
        for ($m = $start; $m + $dur <= $end + 1; $m += $step) {
            $free = true;
            if ($m <= $now_min) $free = false;
            foreach ($busy as $bb) {
                if ($m < $bb[1] && ($m + $dur) > $bb[0]) { $free = false; break; }
            }
            $out[] = array('t' => sprintf('%02d:%02d', intdiv($m, 60), $m % 60), 'free' => $free);
        }
        return $out;
    }

    /** scan forward up to N days for the first free slot (Gregorian) */
    public static function first_free($provider_ids, $duration_min) {
        $s = dorian_settings();
        $off = array_map('intval', (array) $s['off_days']); // 0=Sat..6=Fri (our weekday convention)
        for ($d = 0; $d < 90; $d++) {
            $ts = strtotime("+$d day", current_time('timestamp'));
            $ymd = date('Y-m-d', $ts);
            // PHP date('w'): 0=Sun..6=Sat ; convert to our 0=Sat..6=Fri
            $our = ((int) date('w', $ts) + 1) % 7;
            if (in_array($our, $off, true)) continue;
            foreach (self::slots($provider_ids, $ymd, $duration_min) as $slot) {
                if ($slot['free']) return array('date' => $ymd, 'time' => $slot['t']);
            }
        }
        return null;
    }

    public static function insert($data) {
        global $wpdb;
        $wpdb->insert(self::table(), $data);
        return (int) $wpdb->insert_id;
    }

    public static function get($id) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
    }

    public static function send_reminder($id) {
        $b = self::get($id);
        if (!$b || $b->reminded || $b->status === 'cancelled') return;
        Dorian_SMS::send_reminder($b);
        global $wpdb;
        $wpdb->update(self::table(), array('reminded' => 1), array('id' => $id));
    }
}
