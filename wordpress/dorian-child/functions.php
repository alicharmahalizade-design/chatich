<?php
/**
 * Dorian Landing (Child) — functions
 *
 * Loads the landing's styles/scripts ONLY on the "Dorian Landing" page template,
 * so the rest of the site (and the parent theme) is untouched.
 */

if (!defined('ABSPATH')) exit;

/** Make sure WordPress prints a <title> (the template no longer hard-codes one). */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
});

/**
 * Enqueue the Dorian assets only on the landing template.
 * Order matters: gsap -> ScrollTrigger -> lenis -> app.js -> custom.js (all in footer).
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_page_template('template-dorian.php')) {
        return;
    }

    $uri = get_stylesheet_directory_uri();
    $dir = get_stylesheet_directory();
    // file-mtime versions so updates bust the cache automatically
    $v = function ($rel) use ($dir) {
        $path = $dir . $rel;
        return file_exists($path) ? filemtime($path) : '1.0.0';
    };

    /* ---- styles ---- */
    wp_enqueue_style(
        'dorian-fonts',
        'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Mr+Dafoe&family=Pinyon+Script&display=swap',
        array(),
        null
    );
    wp_enqueue_style('dorian-site', $uri . '/assets/css/site.css', array(), $v('/assets/css/site.css'));
    wp_enqueue_style('dorian-custom', $uri . '/assets/css/custom.css', array('dorian-site'), $v('/assets/css/custom.css'));
    // bulletproof the handwriting tagline font against cache-plugin CSS stripping:
    // absolute @font-face + forced family (survives WP Rocket "Remove Unused CSS").
    wp_add_inline_style('dorian-custom',
        "@font-face{font-family:'Mr Dafoe';src:url('" . $uri . "/assets/fonts/MrDafoe.woff2') format('woff2');font-weight:400;font-display:swap}"
        . ".hero__tag.hero__tagline{font-family:'Mr Dafoe',cursive !important}"
    );

    /* ---- scripts (footer, dependency-ordered) ---- */
    wp_enqueue_script('dorian-gsap', $uri . '/assets/vendor/gsap.min.js', array(), $v('/assets/vendor/gsap.min.js'), true);
    wp_enqueue_script('dorian-st', $uri . '/assets/vendor/ScrollTrigger.min.js', array('dorian-gsap'), $v('/assets/vendor/ScrollTrigger.min.js'), true);
    wp_enqueue_script('dorian-lenis', $uri . '/assets/vendor/lenis.min.js', array(), $v('/assets/vendor/lenis.min.js'), true);
    wp_enqueue_script('dorian-app', $uri . '/assets/js/app.js', array('dorian-gsap', 'dorian-st', 'dorian-lenis'), $v('/assets/js/app.js'), true);
    wp_enqueue_script('dorian-custom', $uri . '/assets/js/custom.js', array('dorian-app'), $v('/assets/js/custom.js'), true);

    /* WooCommerce AJAX add-to-cart + live cart fragments for the products section.
       (this template is standalone, so Woo's scripts aren't auto-enqueued here) */
    if (class_exists('WooCommerce')) {
        wp_enqueue_script('wc-add-to-cart');
        wp_enqueue_script('wc-cart-fragments');
    }
}, 20);

/**
 * Keep the floating cart's count in sync after an AJAX add-to-cart.
 * (registered whenever WooCommerce is active, since fragment refreshes are AJAX)
 */
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    if (function_exists('WC') && WC()->cart) {
        ob_start();
        echo '<span class="dorian-cart__count">' . esc_html(WC()->cart->get_cart_contents_count()) . '</span>';
        $fragments['span.dorian-cart__count'] = ob_get_clean();

        // refresh the mini-cart drawer contents too
        ob_start();
        echo '<div class="widget_shopping_cart_content">';
        woocommerce_mini_cart();
        echo '</div>';
        $fragments['div.widget_shopping_cart_content'] = ob_get_clean();
    }
    return $fragments;
});

/* =========================================================================
   Dorian theme options — edit (almost) everything without code.
   ========================================================================= */

/* ---- WordPress-native editable menu in the header ---- */
add_action('after_setup_theme', function () {
    register_nav_menus(array('dorian_primary' => 'منوی اصلی دوریان (هدر)'));
});
/* make in-page (#hash) menu links smooth-scroll via the existing engine */
add_filter('nav_menu_link_attributes', function ($atts, $item) {
    $url = isset($atts['href']) ? $atts['href'] : '';
    $pos = strpos($url, '#');
    if ($pos !== false) { $atts['href'] = substr($url, $pos); $atts['data-go'] = '0'; }
    return $atts;
}, 10, 2);

/* ---- content options (one array option) ---- */
function dorian_opts_defaults() {
    return array(
        // brand colors
        'color_blue'  => '#0066B3', 'color_gold'  => '#C9A227', 'color_cream' => '#DFD2BF',
        // hero
        'hero_eyebrow'  => "Gentlemen's Studio · Ahvaz",
        'hero_tagline'  => 'Let us enchant you, in a good way',
        'hero_sub'      => 'دامادسرا و آرایشگاه تخصصی آقایان',
        'hero_cta1'     => 'رزرو نوبت',
        'hero_cta2'     => 'گشتی در مجموعه',
        'hero_portrait' => '',
        // reservation / contact
        'reserve_heading' => 'نوبت خود را رزرو کنید',
        'reserve_text'    => 'برای رزرو نوبت یا مشاوره‌ی تخصصی داماد با ما در تماس باشید.',
        'phone1_label' => 'تماس', 'phone1' => '0916 792 1710',
        'phone2_label' => 'تماس', 'phone2' => '0916 792 1310',
        'phone3_label' => 'مشاوره داماد', 'phone3' => '0916 555 5532',
        'address'      => 'اهواز، کیان‌آباد، نبش خیابان سوم، وهابی',
        // social
        'instagram_url' => 'https://instagram.com/dorianstudio.ir',
        'website_url'   => 'https://dorianstudio.ir',
        // footer
        'footer_copy'   => '© 2026 Designed by Ronakads | All rights reserved for Dorianstudio',
    );
}
function dorian_opts() {
    $s = get_option('dorian_opts', array());
    return wp_parse_args(is_array($s) ? $s : array(), dorian_opts_defaults());
}
/** A content option, falling back to a default. */
function dorian_opt($key, $fallback = '') {
    $o = dorian_opts();
    return (isset($o[$key]) && $o[$key] !== '') ? $o[$key] : $fallback;
}
function dorian_opts_sanitize($in) {
    $out = array();
    foreach (dorian_opts_defaults() as $k => $v) {
        if (!isset($in[$k])) { $out[$k] = ''; continue; }
        if (strpos($k, 'color_') === 0)      $out[$k] = sanitize_hex_color($in[$k]);
        elseif (strpos($k, '_url') !== false || $k === 'hero_portrait') $out[$k] = esc_url_raw($in[$k]);
        else                                  $out[$k] = sanitize_text_field($in[$k]);
    }
    return $out;
}

/* ---- editable button / gift-card links (kept as individual options) ---- */
function dorian_link_fields() {
    return array(
        'dorian_reserve_url'  => 'لینک دکمه‌های «رزرو نوبت»',
        'dorian_tour_url'     => 'لینک دکمهٔ «گشتی در مجموعه»',
        'dorian_gift_cta_url' => 'لینک دکمهٔ «هدیه بدهید»',
        'dorian_gift_url_1'   => 'لینک گیفت‌کارت ۱ (یک میلیون)',
        'dorian_gift_url_2'   => 'لینک گیفت‌کارت ۲ (دو میلیون)',
        'dorian_gift_url_3'   => 'لینک گیفت‌کارت ۳ (پنج میلیون)',
        'dorian_gift_url_4'   => 'لینک گیفت‌کارت ۴ (ده میلیون)',
    );
}
function dorian_link($key, $fallback = '') { $url = get_option($key); return $url ? $url : $fallback; }
function dorian_reserve_link() {
    $url = get_option('dorian_reserve_url');
    if ($url) return $url;
    if (function_exists('dorian_booking_url')) return dorian_booking_url();
    return home_url('/');
}
function dorian_giftcard($front, $back, $alt, $url) {
    $tag = $url ? 'a' : 'div';
    $href = $url ? ' href="' . esc_url($url) . '"' : '';
    echo '<' . $tag . ' class="giftcard"' . $href . '><div class="giftcard__inner">'
        . '<div class="giftcard__face giftcard__front"><img src="' . esc_url($front) . '" alt="' . esc_attr($alt) . '" loading="lazy"></div>'
        . '<div class="giftcard__face giftcard__back"><img src="' . esc_url($back) . '" alt="' . esc_attr($alt) . '" loading="lazy"></div>'
        . '</div></' . $tag . '>';
}

/* ---- register + admin page ---- */
add_action('admin_menu', function () {
    add_theme_page('تنظیمات قالب دوریان', 'تنظیمات دوریان', 'manage_options', 'dorian-theme', 'dorian_theme_settings_page');
});
add_action('admin_init', function () {
    register_setting('dorian_theme_group', 'dorian_opts', array('sanitize_callback' => 'dorian_opts_sanitize'));
    foreach (array_keys(dorian_link_fields()) as $key) {
        register_setting('dorian_theme_group', $key, array('sanitize_callback' => 'esc_url_raw'));
    }
    wp_enqueue_media();
});

/* inject brand colors on the landing template */
add_action('wp_head', function () {
    if (!is_page_template('template-dorian.php')) return;
    $o = dorian_opts();
    $b = $o['color_blue']; $g = $o['color_gold'];
    echo "<style id=\"dorian-brand\">:root{--blue:$b;--blue-bright:$b;--blue-deep:$b;--gold:$g;--gold-2:$g;--gold-1:$g;}</style>\n";
}, 99);

function dorian_field($key, $label, $type = 'text') {
    $o = dorian_opts();
    $val = isset($o[$key]) ? $o[$key] : '';
    echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
    if ($type === 'textarea') {
        echo '<textarea name="dorian_opts[' . esc_attr($key) . ']" rows="2" style="width:520px">' . esc_textarea($val) . '</textarea>';
    } elseif ($type === 'color') {
        echo '<input type="text" class="dorian-color" name="dorian_opts[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" style="width:120px">';
    } elseif ($type === 'image') {
        echo '<input type="url" class="dorian-img-url" name="dorian_opts[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" style="width:420px">'
            . ' <button type="button" class="button dorian-img-pick">انتخاب تصویر</button>';
    } else {
        echo '<input type="text" name="dorian_opts[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" style="width:520px">';
    }
    echo '</td></tr>';
}

function dorian_theme_settings_page() {
    $auto = function_exists('dorian_booking_url') ? dorian_booking_url() : '';
    ?>
    <div class="wrap"><h1>تنظیمات قالب دوریان</h1>
    <form method="post" action="options.php"><?php settings_fields('dorian_theme_group'); ?>

      <h2>منو</h2>
      <p class="description">منوی هدر را از <a href="<?php echo esc_url(admin_url('nav-menus.php')); ?>"><strong>نمایش → فهرست‌ها (Menus)</strong></a> بسازید و در محلِ «منوی اصلی دوریان (هدر)» قرار دهید. برای لینکِ بخش‌های همین صفحه، در «پیوندهای سفارشی» از <code>#story</code>، <code>#floors</code>، <code>#services</code>، <code>#team</code>، <code>#gift</code> استفاده کنید. اگر منویی نسازید، منوی پیش‌فرض نمایش داده می‌شود.</p>

      <h2>هیرو</h2>
      <table class="form-table"><?php
        dorian_field('hero_eyebrow', 'متن بالای عنوان (انگلیسی)');
        dorian_field('hero_tagline', 'شعار دست‌نویس');
        dorian_field('hero_sub', 'زیرعنوان');
        dorian_field('hero_cta1', 'متن دکمهٔ اول (رزرو)');
        dorian_field('hero_cta2', 'متن دکمهٔ دوم');
        dorian_field('hero_portrait', 'تصویر پرتره', 'image');
      ?></table>

      <h2>تماس و رزرو</h2>
      <table class="form-table"><?php
        dorian_field('reserve_heading', 'عنوان بخش رزرو');
        dorian_field('reserve_text', 'متن بخش رزرو', 'textarea');
        dorian_field('phone1_label', 'برچسب تلفن ۱'); dorian_field('phone1', 'شمارهٔ تلفن ۱');
        dorian_field('phone2_label', 'برچسب تلفن ۲'); dorian_field('phone2', 'شمارهٔ تلفن ۲');
        dorian_field('phone3_label', 'برچسب تلفن ۳'); dorian_field('phone3', 'شمارهٔ تلفن ۳');
        dorian_field('address', 'آدرس', 'textarea');
      ?></table>

      <h2>شبکه‌های اجتماعی</h2>
      <table class="form-table"><?php
        dorian_field('instagram_url', 'اینستاگرام');
        dorian_field('website_url', 'وب‌سایت');
      ?></table>

      <h2>رنگ‌های برند</h2>
      <table class="form-table"><?php
        dorian_field('color_blue', 'آبی برند', 'color');
        dorian_field('color_gold', 'طلایی برند', 'color');
      ?></table>

      <h2>فوتر</h2>
      <table class="form-table"><?php
        dorian_field('footer_copy', 'متن کپی‌رایت', 'textarea');
      ?></table>

      <h2>لینک دکمه‌ها و گیفت‌کارت‌ها</h2>
      <table class="form-table">
        <?php foreach (dorian_link_fields() as $key => $label) :
          $ph = ($key === 'dorian_reserve_url') ? $auto : ''; ?>
        <tr><th scope="row"><?php echo esc_html($label); ?></th><td>
          <input type="url" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr(get_option($key, '')); ?>" style="width:480px" placeholder="<?php echo esc_attr($ph); ?>">
        </td></tr>
        <?php endforeach; ?>
      </table>

    <?php submit_button(); ?>
    </form></div>
    <script>
    jQuery(function($){
      $('.dorian-img-pick').on('click', function(e){
        e.preventDefault(); var btn=$(this), frame=wp.media({title:'انتخاب تصویر', multiple:false});
        frame.on('select', function(){ var u=frame.state().get('selection').first().toJSON().url; btn.prev('.dorian-img-url').val(u); });
        frame.open();
      });
    });
    </script>
    <?php
}
