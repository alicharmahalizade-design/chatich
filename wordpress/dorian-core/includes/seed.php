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
        // name, role, [group keys], panel slug, panel passcode
        $provInfo = array(
            'arman'     => array('آرمان کاراگاه', 'Hair Master', array('hair'), 'arman', 'arman1234'),
            'ehsan'     => array('احسان حسن‌یاری', 'Hair Artist', array('hair'), 'ehsan', 'ehsan1234'),
            'hamid'     => array('حمید جوهری', 'Facial Expert', array('facial'), 'hamid', 'hamid1234'),
            'ahmadreza' => array('احمدرضا جلالی', 'Massage', array('massage'), 'ahmadreza', 'ahmadreza1234'),
        );

        // default weekly working hours: Sat..Thu two ranges, Friday (6) closed
        $defHours = array();
        for ($w = 0; $w < 6; $w++) $defHours[$w] = array('12:00-15:00', '17:00-21:00');
        $defHours[6] = array();

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
            update_post_meta($pid, '_dorian_slug', $p[3]);
            update_post_meta($pid, '_dorian_pass', $p[4]);
            update_post_meta($pid, '_dorian_hours', $defHours);
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

    /** Create (once) a published page that hosts the booking form, full-screen. */
    public static function ensure_page() {
        $id = (int) get_option('dorian_booking_page_id');
        if (!$id || !get_post_status($id)) {
            $id = 0;
            foreach (get_posts(array('post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any')) as $pg) {
                if (has_shortcode($pg->post_content, 'dorian_booking')) { $id = $pg->ID; break; }
            }
            if (!$id) {
                $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'رزرو نوبت', 'post_content' => '[dorian_booking]'));
            }
            if ($id && !is_wp_error($id)) update_option('dorian_booking_page_id', (int) $id);
        }
        if ($id && !is_wp_error($id)) self::canvas_template((int) $id);
    }

    /** Make the booking page render with no theme header/footer (Elementor Canvas). */
    protected static function canvas_template($id) {
        $tpl = defined('ELEMENTOR_VERSION') ? 'elementor_canvas' : '';
        if ($tpl && get_post_meta($id, '_wp_page_template', true) !== $tpl) {
            update_post_meta($id, '_wp_page_template', $tpl);
        }
    }
}
