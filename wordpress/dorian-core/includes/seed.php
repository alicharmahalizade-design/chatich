<?php
if (!defined('ABSPATH')) exit;

/** Seeds the same sample data shown in the preview, and creates a booking page. */
class Dorian_Seed {

    public static function run() {
        if (get_option('dorian_seeded')) return;
        $existing = get_posts(array('post_type' => 'dorian_service', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids'));
        if ($existing) { update_option('dorian_seeded', 1); return; }

        Dorian_CPT::register();

        $groups = array(
            'hair'    => array('name' => 'مو و ریش', 'providers' => array('arman', 'ehsan'), 'services' => array(
                array('کوتاهی مو', 550000, 30), array('اصلاح ریش', 400000, 30), array('سشوار و حالت', 300000, 30), array('پکیج مو و ریش', 800000, 60))),
            'facial'  => array('name' => 'پوست و فیشیال', 'providers' => array('hamid'), 'services' => array(
                array('پاکسازی لایت', 2100000, 60), array('پاکسازی VIP', 2800000, 60), array('شمع صورت', 300000, 30))),
            'massage' => array('name' => 'ماساژ', 'providers' => array('ahmadreza'), 'services' => array(
                array('ماساژ ریلکسی', 1350000, 60), array('سنگ داغ', 1750000, 60), array('ماساژ سر و صورت', 580000, 30))),
        );
        $provInfo = array(
            'arman'     => array('آرمان کاراگاه', 'Hair Master', array('hair')),
            'ehsan'     => array('احسان حسن‌یاری', 'Hair Artist', array('hair')),
            'hamid'     => array('حمید جوهری', 'Facial Expert', array('facial')),
            'ahmadreza' => array('احمدرضا جلالی', 'Massage', array('massage')),
        );

        // groups (terms)
        $term = array();
        foreach ($groups as $k => $g) {
            $t = wp_insert_term($g['name'], 'dorian_group');
            if (is_wp_error($t)) { $ex = get_term_by('name', $g['name'], 'dorian_group'); $term[$k] = $ex ? (int) $ex->term_id : 0; }
            else $term[$k] = (int) $t['term_id'];
        }
        // providers
        $prov = array();
        foreach ($provInfo as $k => $p) {
            $pid = wp_insert_post(array('post_type' => 'dorian_provider', 'post_status' => 'publish', 'post_title' => $p[0]));
            if (is_wp_error($pid)) continue;
            update_post_meta($pid, '_dorian_role', $p[1]);
            update_post_meta($pid, '_dorian_phone', '');
            $tids = array(); foreach ($p[2] as $gk) $tids[] = $term[$gk];
            wp_set_post_terms($pid, $tids, 'dorian_group');
            $prov[$k] = $pid;
        }
        // services
        foreach ($groups as $k => $g) {
            $allowed = array(); foreach ($g['providers'] as $pk) if (isset($prov[$pk])) $allowed[] = $prov[$pk];
            foreach ($g['services'] as $s) {
                $sid = wp_insert_post(array('post_type' => 'dorian_service', 'post_status' => 'publish', 'post_title' => $s[0]));
                if (is_wp_error($sid)) continue;
                update_post_meta($sid, '_dorian_price', $s[1]);
                update_post_meta($sid, '_dorian_duration', $s[2]);
                update_post_meta($sid, '_dorian_providers', $allowed);
                wp_set_post_terms($sid, array($term[$k]), 'dorian_group');
            }
        }
        update_option('dorian_seeded', 1);
    }

    /** Create (once) a published page that hosts the booking form. */
    public static function ensure_page() {
        $id = (int) get_option('dorian_booking_page_id');
        if ($id && get_post_status($id)) return;
        foreach (get_posts(array('post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any')) as $pg) {
            if (has_shortcode($pg->post_content, 'dorian_booking')) { update_option('dorian_booking_page_id', $pg->ID); return; }
        }
        $pid = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'رزرو نوبت', 'post_content' => '[dorian_booking]'));
        if ($pid && !is_wp_error($pid)) update_option('dorian_booking_page_id', $pid);
    }
}
