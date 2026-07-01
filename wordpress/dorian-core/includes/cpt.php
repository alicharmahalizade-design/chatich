<?php
if (!defined('ABSPATH')) exit;

class Dorian_CPT {

    public static function boot() {
        add_action('init', array(__CLASS__, 'register'));
        add_action('add_meta_boxes', array(__CLASS__, 'meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save'), 10, 2);
    }

    public static function register() {
        register_taxonomy('dorian_group', array('dorian_service', 'dorian_provider'), array(
            'labels' => array('name' => 'گروه خدمت', 'singular_name' => 'گروه', 'add_new_item' => 'افزودن گروه'),
            'public' => false, 'show_ui' => true, 'hierarchical' => true, 'show_admin_column' => true,
        ));

        register_post_type('dorian_service', array(
            'labels' => array(
                'name' => 'خدمات (رزرو)', 'singular_name' => 'خدمت',
                'add_new' => 'افزودن خدمت', 'add_new_item' => 'افزودن خدمت جدید', 'edit_item' => 'ویرایش خدمت',
                'menu_name' => 'رزرو دوریان',
            ),
            'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-calendar-alt',
            'supports' => array('title'), 'taxonomies' => array('dorian_group'), 'menu_position' => 26,
        ));

        register_post_type('dorian_provider', array(
            'labels' => array(
                'name' => 'متخصص‌ها', 'singular_name' => 'متخصص',
                'add_new' => 'افزودن متخصص', 'add_new_item' => 'افزودن متخصص', 'edit_item' => 'ویرایش متخصص',
            ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=dorian_service',
            'supports' => array('title', 'thumbnail'), 'taxonomies' => array('dorian_group'),
        ));
    }

    public static function meta_boxes() {
        add_meta_box('dorian_service_meta', 'مشخصات خدمت (قیمت و زمان)', array(__CLASS__, 'service_box'), 'dorian_service', 'normal', 'high');
        add_meta_box('dorian_provider_meta', 'مشخصات متخصص (پیامک)', array(__CLASS__, 'provider_box'), 'dorian_provider', 'normal', 'high');
    }

    public static function service_box($post) {
        wp_nonce_field('dorian_meta', 'dorian_nonce');
        $price = (int) get_post_meta($post->ID, '_dorian_price', true);
        $dur   = (int) get_post_meta($post->ID, '_dorian_duration', true);
        $provs = (array) get_post_meta($post->ID, '_dorian_providers', true);
        echo '<p><label>قیمت (تومان):<br><input type="number" name="dorian_price" value="' . esc_attr($price) . '" style="width:220px"></label></p>';
        echo '<p><label>مدت (دقیقه، مضربِ طول اسلات):<br><input type="number" name="dorian_duration" value="' . esc_attr($dur ?: 30) . '" step="15" style="width:220px"></label></p>';
        echo '<p><strong>متخصصانِ ارائه‌دهندهٔ این خدمت:</strong></p>';
        $all = get_posts(array('post_type' => 'dorian_provider', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        if (!$all) {
            echo '<em>ابتدا از منوی «متخصص‌ها» چند متخصص اضافه کنید.</em>';
        } else {
            foreach ($all as $p) {
                $checked = in_array($p->ID, array_map('intval', $provs), true) ? 'checked' : '';
                echo '<label style="display:inline-block;margin:0 0 6px 14px"><input type="checkbox" name="dorian_providers[]" value="' . $p->ID . '" ' . $checked . '> ' . esc_html($p->post_title) . '</label>';
            }
        }
        echo '<p style="color:#777">نکته: «گروهِ خدمت» را در باکس کناری انتخاب کنید (مثلاً «مو و ریش»). خدماتِ هم‌گروه با هم نمایش داده می‌شوند.</p>';
    }

    public static function provider_box($post) {
        wp_nonce_field('dorian_meta', 'dorian_nonce');
        $role  = get_post_meta($post->ID, '_dorian_role', true);
        $phone = get_post_meta($post->ID, '_dorian_phone', true);
        $slug  = get_post_meta($post->ID, '_dorian_slug', true);
        $pass  = get_post_meta($post->ID, '_dorian_pass', true);
        echo '<p><label>نقش / تخصص (مثلاً Hair Master):<br><input type="text" name="dorian_role" value="' . esc_attr($role) . '" style="width:320px"></label></p>';
        echo '<p><label>شمارهٔ موبایل (برای پیامکِ نوبت‌ها):<br><input type="text" name="dorian_phone" value="' . esc_attr($phone) . '" placeholder="09xxxxxxxxx" style="width:220px"></label></p>';
        echo '<hr><p><strong>پنل اختصاصی متخصص</strong></p>';
        echo '<p><label>آدرس پنل (اسلاگ لاتین، مثلاً arman):<br><input type="text" name="dorian_slug" value="' . esc_attr($slug) . '" placeholder="arman" style="width:220px"></label>'
           . ($slug ? ' <span style="color:#777">آدرس: <code>' . esc_html(home_url('/' . $slug)) . '</code></span>' : '') . '</p>';
        echo '<p><label>رمز ورود به پنل:<br><input type="text" name="dorian_pass" value="' . esc_attr($pass) . '" style="width:220px"></label> <span style="color:#777">این رمز را به متخصص بدهید.</span></p>';
        echo '<p style="color:#777">«تصویر شاخص» = عکسِ متخصص. «گروه» در باکس کناری = تخصص‌هایی که انجام می‌دهد. ساعت کاری و مدت خدمات را خودِ متخصص از پنلش تنظیم می‌کند.</p>';
    }

    public static function save($post_id, $post) {
        if (!isset($_POST['dorian_nonce']) || !wp_verify_nonce($_POST['dorian_nonce'], 'dorian_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if ($post->post_type === 'dorian_service') {
            update_post_meta($post_id, '_dorian_price', isset($_POST['dorian_price']) ? (int) $_POST['dorian_price'] : 0);
            update_post_meta($post_id, '_dorian_duration', isset($_POST['dorian_duration']) ? max(15, (int) $_POST['dorian_duration']) : 30);
            $provs = isset($_POST['dorian_providers']) ? array_map('intval', (array) $_POST['dorian_providers']) : array();
            update_post_meta($post_id, '_dorian_providers', $provs);
        }
        if ($post->post_type === 'dorian_provider') {
            update_post_meta($post_id, '_dorian_slug', isset($_POST['dorian_slug']) ? sanitize_title($_POST['dorian_slug']) : '');
            update_post_meta($post_id, '_dorian_pass', isset($_POST['dorian_pass']) ? sanitize_text_field($_POST['dorian_pass']) : '');
            update_post_meta($post_id, '_dorian_role', isset($_POST['dorian_role']) ? sanitize_text_field($_POST['dorian_role']) : '');
            update_post_meta($post_id, '_dorian_phone', isset($_POST['dorian_phone']) ? preg_replace('/\D/', '', $_POST['dorian_phone']) : '');
        }
    }

    /** Build the data the frontend form needs (groups → services, providers). */
    public static function form_data() {
        $services = array();
        $groups = get_terms(array('taxonomy' => 'dorian_group', 'hide_empty' => false));
        if (is_wp_error($groups)) $groups = array();

        // map: provider id -> group ids
        $prov_groups = array();
        $providers = array();
        foreach (get_posts(array('post_type' => 'dorian_provider', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC')) as $p) {
            $gids = wp_get_post_terms($p->ID, 'dorian_group', array('fields' => 'ids'));
            $prov_groups[$p->ID] = array_map('intval', $gids);
            $img = get_the_post_thumbnail_url($p->ID, 'medium');
            $providers[] = array(
                'id'       => (string) $p->ID,
                'name'     => $p->post_title,
                'role'     => (string) get_post_meta($p->ID, '_dorian_role', true),
                'photo'    => $img ? $img : DORIAN_URL . 'assets/avatar.svg',
                'services' => array_map('strval', $prov_groups[$p->ID]),
            );
        }

        foreach ($groups as $g) {
            $subs = array();
            $items = get_posts(array(
                'post_type' => 'dorian_service', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC',
                'tax_query' => array(array('taxonomy' => 'dorian_group', 'field' => 'term_id', 'terms' => $g->term_id)),
            ));
            foreach ($items as $it) {
                $subs[] = array(
                    'id'    => (string) $it->ID,
                    'name'  => $it->post_title,
                    'price' => (int) get_post_meta($it->ID, '_dorian_price', true),
                    'dur'   => (int) get_post_meta($it->ID, '_dorian_duration', true) ?: 30,
                );
            }
            if ($subs) {
                $services[] = array(
                    'id'   => (string) $g->term_id,
                    'name' => $g->name,
                    'icon' => 'M4 14c4-6 12-6 16 0M12 7a2 2 0 100-4 2 2 0 000 4z',
                    'subs' => $subs,
                );
            }
        }
        return array('services' => $services, 'providers' => $providers);
    }
}
Dorian_CPT::boot();
