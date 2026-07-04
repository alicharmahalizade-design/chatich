<?php
/**
 * Dorian Landing (Child) — functions
 *
 * Loads the landing's styles/scripts ONLY on the "Dorian Landing" page template,
 * so the rest of the site (and the parent theme) is untouched.
 */

if (!defined('ABSPATH')) exit;

/** Theme supports: title tag, featured images, HTML5, WooCommerce + gallery. */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    if (function_exists('add_image_size')) {
        add_image_size('dorian-card', 720, 480, true);
    }
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
 * Internal (non-landing) pages: the shared "Dorian chrome" styles/scripts.
 * Loads the same design tokens as the landing (site.css) + a light internal layer,
 * so About / Contact / Blog / single-product all match the home page — without the
 * heavy GSAP/Lenis cinematics. The landing template opts out (it enqueues its own).
 */
add_action('wp_enqueue_scripts', function () {
    if (is_page_template('template-dorian.php')) {
        return; // landing handles itself above
    }
    // don't touch the standalone booking page (Canvas template with its own chrome)
    if (class_exists('Dorian_Frontend') && Dorian_Frontend::is_booking_view()) {
        return;
    }

    $uri = get_stylesheet_directory_uri();
    $dir = get_stylesheet_directory();
    $v = function ($rel) use ($dir) {
        $path = $dir . $rel;
        return file_exists($path) ? filemtime($path) : '1.0.0';
    };

    // distinct handle so the plugin's globally-registered "dorian-fonts" (Space
    // Grotesk only) doesn't shadow this fuller family list on internal pages
    wp_enqueue_style(
        'dorian-fonts-internal',
        'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Mr+Dafoe&family=Pinyon+Script&display=swap',
        array(),
        null
    );
    wp_enqueue_style('dorian-site', $uri . '/assets/css/site.css', array(), $v('/assets/css/site.css'));
    wp_enqueue_style('dorian-internal', $uri . '/assets/css/internal.css', array('dorian-site'), $v('/assets/css/internal.css'));
    // self-hosted Pinar so the Persian body font survives cache-plugin CSS stripping
    wp_add_inline_style('dorian-internal',
        "@font-face{font-family:'Pinar';src:url('" . $uri . "/assets/fonts/Pinar-VF.woff2') format('woff2');font-weight:100 900;font-display:swap}"
    );

    wp_enqueue_script('dorian-internal', $uri . '/assets/js/internal.js', array(), $v('/assets/js/internal.js'), true);

    if (comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
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
        'color_navy'  => '#081726', 'color_ink'   => '#15110A',
        // typography
        'font_base'      => '17', // px, body base size on internal pages
        'font_scale'     => 'comfort', // compact | comfort | roomy — heading scale
        'anim_enabled'   => '1',       // reveal-on-scroll for internal pages
        // hero
        'hero_eyebrow'  => "Gentlemen's Studio · Ahvaz",
        'hero_tagline'  => 'Let us enchant you, in a good way',
        'hero_sub'      => 'دامادسرا و آرایشگاه تخصصی آقایان',
        'hero_cta1'     => 'رزرو نوبت',
        'hero_cta2'     => 'گشتی در مجموعه',
        'hero_portrait' => '',
        // header (chrome, applies to internal pages)
        'header_sticky'  => '1',
        'header_cta_show'=> '1',
        'header_cta_text'=> 'رزرو نوبت',
        'header_logo'    => '', // falls back to assets/img/logo.png
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
        'telegram_url'  => '',
        'whatsapp_url'  => '',
        'aparat_url'    => '',
        // footer
        'footer_about'  => 'دوریان؛ مجموعه‌ی لاکچری دامادسرا و آرایشگاه تخصصی آقایان در اهواز — سه طبقه خدمات حرفه‌ای مو، پوست، ماساژ و مراقبت شخصی.',
        'footer_links'  => "درباره ما|/about\nتماس با ما|/contact\nرزرو نوبت|",
        'footer_copy'   => '© 2026 Designed by Ronakads | All rights reserved for Dorianstudio',
        // about page
        'about_eyebrow' => 'The Story',
        'about_heading' => 'دوریان؛ هنرِ ماندگاری',
        'about_intro'   => 'مجموعه‌ی تخصصی دوریان؛ جایی که آراستگیِ مردانه به یک آیین بدل می‌شود.',
        'about_body'    => "نام «دوریان» از قوم باستانی یونان برگرفته شده؛ مردمانی که به نظم، انضباط و سبک زندگی متمایزشان شناخته می‌شدند و یادآور رمان «دوریان گری»؛ روایتِ جوانی که زمان بر چهره‌اش اثری نمی‌گذاشت.\n\nمجموعه‌ی تخصصی دوریان در بهمن‌ماه ۱۴۰۴ در اهواز افتتاح شد؛ فضایی آرام و دور از هیاهوی روزمره، در بیش از ۳۰۰ متر مربع و سه طبقه‌ی مجزا که هرکدام با رویکردی هدفمند طراحی شده‌اند.",
        'about_image'   => '',
        'about_stat1_n' => '1404', 'about_stat1_l' => 'سال تأسیس',
        'about_stat2_n' => '300m²', 'about_stat2_l' => 'فضای مجموعه',
        'about_stat3_n' => '3', 'about_stat3_l' => 'طبقه‌ی تخصصی',
        'about_values'  => "اصالت|هر خدمت با وسواسِ یک صنعتگر ارائه می‌شود.\nآرامش|فضایی دور از هیاهو، تنها برای شما.\nتخصص|تیمی از بهترین متخصصانِ مو، پوست و آرامش.",
        // contact page
        'contact_eyebrow' => 'Get in touch',
        'contact_heading' => 'با دوریان در تماس باشید',
        'contact_intro'   => 'برای رزرو نوبت، مشاوره‌ی تخصصی داماد یا هر پرسشی، از راه‌های زیر با ما در ارتباط باشید.',
        'contact_hours'   => "شنبه تا پنجشنبه: ۱۰:۰۰ تا ۲۲:۰۰\nجمعه: تعطیل",
        'contact_map'     => '',
        'contact_email'   => '',
        'contact_form_show' => '1',
        // blog
        'blog_eyebrow'  => 'Journal',
        'blog_heading'  => 'مقالات دوریان',
        'blog_intro'    => 'یادداشت‌ها و راهنماهایی دربارهٔ آراستگی، مراقبت و سبکِ زندگیِ مردانه.',
        'blog_excerpt'  => '32', // words
        'blog_cols'     => '3',  // 2 | 3
        // product
        'product_related' => '1',
        // advanced
        'custom_css'    => '',
        'custom_js'     => '',
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
    // multiline plain-text fields (newlines preserved)
    $textareas = array(
        'reserve_text', 'address', 'footer_copy', 'footer_about', 'footer_links',
        'about_intro', 'about_body', 'about_values', 'contact_intro', 'contact_hours', 'blog_intro',
    );
    $out = array();
    foreach (dorian_opts_defaults() as $k => $v) {
        if (!isset($in[$k])) { $out[$k] = ''; continue; }
        if ($k === 'custom_css') {
            $out[$k] = preg_replace('#</?\s*(style|script)[^>]*>#i', '', (string) $in[$k]); // raw CSS, no tag breakout
        } elseif ($k === 'custom_js') {
            $out[$k] = preg_replace('#</?\s*script[^>]*>#i', '', (string) $in[$k]); // raw JS, no tag breakout
        } elseif ($k === 'contact_map') {
            // allow a single <iframe> embed (e.g. Google/Neshan maps) or a bare URL
            $out[$k] = trim(wp_kses((string) $in[$k], array('iframe' => array(
                'src' => array(), 'width' => array(), 'height' => array(), 'style' => array(),
                'frameborder' => array(), 'allowfullscreen' => array(), 'loading' => array(),
                'referrerpolicy' => array(), 'title' => array(),
            ))));
        } elseif (strpos($k, 'color_') === 0) {
            $out[$k] = sanitize_hex_color($in[$k]);
        } elseif (strpos($k, '_url') !== false || $k === 'hero_portrait' || $k === 'about_image' || $k === 'header_logo') {
            $out[$k] = esc_url_raw($in[$k]);
        } elseif (in_array($k, $textareas, true)) {
            $out[$k] = sanitize_textarea_field($in[$k]);
        } else {
            $out[$k] = sanitize_text_field($in[$k]);
        }
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

/* inject brand colors + typography + custom CSS — sitewide (landing keeps its
   original "flatten shades" behavior for backward-compatible gradients). */
add_action('wp_head', function () {
    $o = dorian_opts();
    $b = $o['color_blue']; $g = $o['color_gold'];
    $landing = is_page_template('template-dorian.php');
    echo "<style id=\"dorian-brand\">:root{";
    if ($landing) {
        echo "--blue:$b;--blue-bright:$b;--blue-deep:$b;--gold:$g;--gold-2:$g;--gold-1:$g;";
    } else {
        // internal pages keep the rich palette but re-anchor the brand hues
        echo "--blue:$b;--gold-2:$g;";
        if (!empty($o['color_navy'])) echo "--navy:" . $o['color_navy'] . ";";
        if (!empty($o['color_ink']))  echo "--ink:" . $o['color_ink'] . ";";
        $base = (int) $o['font_base']; if ($base < 13 || $base > 22) $base = 17;
        echo "--dorian-fs:{$base}px;";
    }
    echo "}</style>\n";
    if (!empty($o['custom_css'])) {
        echo "<style id=\"dorian-custom-css\">\n" . $o['custom_css'] . "\n</style>\n";
    }
}, 99);

/* custom JS — sitewide, printed just before </body> */
add_action('wp_footer', function () {
    $js = dorian_opt('custom_js');
    if ($js !== '') echo "<script id=\"dorian-custom-js\">\n" . $js . "\n</script>\n";
}, 99);

/* body classes that drive the internal typography scale + reveal animations */
add_filter('body_class', function ($classes) {
    if (is_page_template('template-dorian.php')) return $classes;
    $o = dorian_opts();
    $scale = in_array($o['font_scale'], array('compact', 'comfort', 'roomy'), true) ? $o['font_scale'] : 'comfort';
    $classes[] = 'dorian-inner';
    $classes[] = 'dorian-scale-' . $scale;
    if (!empty($o['anim_enabled'])) $classes[] = 'dorian-anim';
    return $classes;
});

function dorian_field($key, $label, $type = 'text', $extra = array()) {
    $o = dorian_opts();
    $val = isset($o[$key]) ? $o[$key] : '';
    $name = 'dorian_opts[' . esc_attr($key) . ']';
    echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
    if ($type === 'textarea') {
        $rows = isset($extra['rows']) ? (int) $extra['rows'] : 3;
        echo '<textarea name="' . $name . '" rows="' . $rows . '" style="width:100%;max-width:640px">' . esc_textarea($val) . '</textarea>';
    } elseif ($type === 'color') {
        echo '<input type="text" class="dorian-color" name="' . $name . '" value="' . esc_attr($val) . '" style="width:120px">';
    } elseif ($type === 'image') {
        echo '<input type="url" class="dorian-img-url" name="' . $name . '" value="' . esc_attr($val) . '" style="width:420px">'
            . ' <button type="button" class="button dorian-img-pick">انتخاب تصویر</button>';
    } elseif ($type === 'checkbox') {
        echo '<label><input type="checkbox" name="' . $name . '" value="1"' . checked($val, '1', false) . '> '
            . esc_html(isset($extra['hint']) ? $extra['hint'] : 'فعال') . '</label>';
    } elseif ($type === 'select') {
        echo '<select name="' . $name . '" style="min-width:220px">';
        foreach ((array) $extra as $ov => $ol) {
            echo '<option value="' . esc_attr($ov) . '"' . selected($val, $ov, false) . '>' . esc_html($ol) . '</option>';
        }
        echo '</select>';
    } elseif ($type === 'number') {
        $min = isset($extra['min']) ? ' min="' . (int) $extra['min'] . '"' : '';
        $max = isset($extra['max']) ? ' max="' . (int) $extra['max'] . '"' : '';
        echo '<input type="number"' . $min . $max . ' name="' . $name . '" value="' . esc_attr($val) . '" style="width:120px">';
    } else {
        echo '<input type="text" name="' . $name . '" value="' . esc_attr($val) . '" style="width:100%;max-width:640px">';
    }
    if (!empty($extra['desc'])) echo '<p class="description">' . esc_html($extra['desc']) . '</p>';
    echo '</td></tr>';
}

/* =========================================================================
   Shared "chrome" helpers used by header.php / footer.php / page templates.
   ========================================================================= */

/** URL of the brand logo (settings override → bundled default). */
function dorian_logo_url() {
    $u = dorian_opt('header_logo');
    return $u ? $u : get_stylesheet_directory_uri() . '/assets/img/logo.png';
}

/** All configured social links as [label => [url, svg_path]]. */
function dorian_social_links() {
    $out = array();
    $map = array(
        'instagram_url' => array('Instagram', 'M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.3 1 .4 2.2.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .3-2.2.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.3-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.3 2.2-.4C8.4 2.2 8.8 2.2 12 2.2zm0 4.9A4.9 4.9 0 1017 12a4.9 4.9 0 00-5-4.9zm0 8.1A3.2 3.2 0 1112 8.8a3.2 3.2 0 010 6.4zm6.3-8.3a1.15 1.15 0 11-1.15-1.15 1.15 1.15 0 011.15 1.15z'),
        'telegram_url'  => array('Telegram', 'M21.9 4.3l-3 14.1c-.2 1-.8 1.2-1.7.8l-4.6-3.4-2.2 2.1c-.3.3-.5.5-1 .5l.3-4.8L18.4 5c.4-.3-.1-.5-.6-.2L6.5 12 2 10.6c-1-.3-1-1 .2-1.5l18.2-7c.8-.3 1.5.2 1.5 1.2z'),
        'whatsapp_url'  => array('WhatsApp', 'M12 2a10 10 0 00-8.5 15.2L2 22l4.9-1.3A10 10 0 1012 2zm0 1.8a8.2 8.2 0 016.9 12.6l-.3.4.7 2.6-2.7-.7-.4.2A8.2 8.2 0 1112 3.8zm-3 3.6c-.2 0-.5.1-.7.3-.3.3-.9.9-.9 2.1s.9 2.5 1 2.6c.1.2 1.8 2.9 4.5 3.9 2.2.9 2.7.7 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2-.1-.1-.3-.2-.6-.4-.3-.2-1.5-.8-1.8-.8-.2-.1-.4-.1-.6.1-.2.3-.6.8-.8 1-.1.1-.3.2-.5.1-.3-.1-1.1-.4-2-1.3-.8-.7-1.3-1.5-1.4-1.8-.1-.2 0-.4.1-.5l.4-.5c.1-.2.2-.3.2-.5.1-.2 0-.4 0-.5 0-.1-.6-1.5-.8-2-.2-.5-.4-.4-.6-.4z'),
        'aparat_url'    => array('Aparat', 'M12 2a10 10 0 100 20 10 10 0 000-20zm0 4.2a1.9 1.9 0 110 3.8 1.9 1.9 0 010-3.8zm6 3.3a1.9 1.9 0 110 3.8 1.9 1.9 0 010-3.8zm-3.5 5.3a1.9 1.9 0 110 3.8 1.9 1.9 0 010-3.8zm-8.5-5.3a1.9 1.9 0 110 3.8 1.9 1.9 0 010-3.8zm3.5 5.3a1.9 1.9 0 110 3.8 1.9 1.9 0 010-3.8z'),
        'website_url'   => array('Website', 'M12 2a10 10 0 100 20 10 10 0 000-20zm0 1.8c1.3 0 3.1 2.3 3.6 6.4H8.4C8.9 6.1 10.7 3.8 12 3.8zM4 12c0-.7.1-1.4.2-2h3.3c-.1.6-.1 1.3-.1 2s0 1.4.1 2H4.2c-.1-.6-.2-1.3-.2-2zm.9 4h2.9c.4 2 .9 3.5 1.5 4.4A8.2 8.2 0 014.9 16zM7.8 8H4.9a8.2 8.2 0 014.4-4.4C8.7 4.5 8.2 6 7.8 8zm4.2 12.2c-1.3 0-3.1-2.3-3.6-6.2h7.2c-.5 3.9-2.3 6.2-3.6 6.2zM9.3 12c0-.7 0-1.4.1-2h5.2c.1.6.1 1.3.1 2s0 1.4-.1 2H9.4c-.1-.6-.1-1.3-.1-2zm6.9 8.4c.6-.9 1.1-2.4 1.5-4.4h2.9a8.2 8.2 0 01-4.4 4.4zM16.2 8c-.4-2-.9-3.5-1.5-4.4A8.2 8.2 0 0119.1 8zm.4 6c.1-.6.1-1.3.1-2s0-1.4-.1-2h3.3c.1.6.2 1.3.2 2s-.1 1.4-.2 2z'),
    );
    foreach ($map as $key => $meta) {
        $url = dorian_opt($key);
        if ($url) $out[$meta[0]] = array($url, $meta[1]);
    }
    return $out;
}

/** Echo the social icon row (reuses the landing's .foot__soc styling). */
function dorian_social_row() {
    $links = dorian_social_links();
    if (!$links) return;
    echo '<div class="foot__soc">';
    foreach ($links as $label => $meta) {
        echo '<a href="' . esc_url($meta[0]) . '" target="_blank" rel="noopener" aria-label="' . esc_attr($label) . '">'
            . '<svg viewBox="0 0 24 24"><path d="' . esc_attr($meta[1]) . '"/></svg></a>';
    }
    echo '</div>';
}

/**
 * The page title band shown under the header on internal pages.
 * $crumbs: array of [label => url|null] for a breadcrumb trail (optional).
 */
function dorian_page_hero($title, $eyebrow = '', $sub = '', $crumbs = array()) {
    echo '<section class="pagehero"><div class="wrap pagehero__inner">';
    if ($crumbs) {
        echo '<nav class="crumbs" aria-label="مسیر">';
        $last = count($crumbs) - 1; $i = 0;
        foreach ($crumbs as $label => $url) {
            if ($url && $i !== $last) echo '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a><span class="sep">/</span>';
            else echo '<span class="here">' . esc_html($label) . '</span>';
            $i++;
        }
        echo '</nav>';
    }
    if ($eyebrow) echo '<span class="eyebrow c">' . esc_html($eyebrow) . '</span>';
    echo '<h1 class="pagehero__title">' . wp_kses_post($title) . '</h1>';
    echo '<div class="ds"><i></i><em></em><i></i></div>';
    if ($sub) echo '<p class="pagehero__sub">' . wp_kses_post($sub) . '</p>';
    echo '</div></section>';
}

/** One article card for blog/archive grids. */
function dorian_post_card() {
    $words = (int) dorian_opt('blog_excerpt', 32); if ($words < 8) $words = 32;
    $cats  = get_the_category();
    $cat   = $cats ? $cats[0] : null;
    echo '<article class="post-card">';
    echo '<a class="post-card__media" href="' . esc_url(get_permalink()) . '">';
    if (has_post_thumbnail()) {
        the_post_thumbnail('dorian-card', array('loading' => 'lazy', 'alt' => esc_attr(get_the_title())));
    } else {
        echo '<span class="post-card__ph" aria-hidden="true"><img src="' . esc_url(get_stylesheet_directory_uri() . '/assets/img/badge.png') . '" alt=""></span>';
    }
    if ($cat) echo '<span class="post-card__cat">' . esc_html($cat->name) . '</span>';
    echo '</a>';
    echo '<div class="post-card__body">';
    echo '<h3 class="post-card__title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
    echo '<p class="post-card__excerpt">' . esc_html(wp_trim_words(get_the_excerpt(), $words, '…')) . '</p>';
    echo '<div class="post-card__foot"><span class="post-card__date lat">' . esc_html(get_the_date()) . '</span>';
    echo '<a class="post-card__more" href="' . esc_url(get_permalink()) . '">ادامه مطلب <span aria-hidden="true">←</span></a></div>';
    echo '</div></article>';
}

/** Themed pagination for archive/blog templates. */
function dorian_pagination() {
    $links = paginate_links(array('type' => 'array', 'prev_text' => '→ قبلی', 'next_text' => 'بعدی ←', 'mid_size' => 1));
    if (!$links) return;
    echo '<nav class="pager" aria-label="صفحه‌بندی"><ul>';
    foreach ($links as $l) echo '<li>' . str_replace('page-numbers', 'pager__link', $l) . '</li>';
    echo '</ul></nav>';
}

/* ---- Contact form: send via wp_mail, redirect back with a status flag ---- */
add_action('admin_post_nopriv_dorian_contact', 'dorian_handle_contact');
add_action('admin_post_dorian_contact', 'dorian_handle_contact');
function dorian_handle_contact() {
    $back = wp_get_referer() ? wp_get_referer() : home_url('/');
    if (!isset($_POST['dorian_contact_nonce']) || !wp_verify_nonce($_POST['dorian_contact_nonce'], 'dorian_contact')) {
        wp_safe_redirect(add_query_arg('dsent', 'err', $back)); exit;
    }
    if (!empty($_POST['dorian_hp'])) { // honeypot filled → silently pretend success
        wp_safe_redirect(add_query_arg('dsent', 'ok', $back)); exit;
    }
    $name  = sanitize_text_field($_POST['d_name'] ?? '');
    $phone = sanitize_text_field($_POST['d_phone'] ?? '');
    $email = sanitize_email($_POST['d_email'] ?? '');
    $msg   = sanitize_textarea_field($_POST['d_message'] ?? '');
    if ($name === '' || $msg === '') {
        wp_safe_redirect(add_query_arg('dsent', 'err', $back)); exit;
    }
    $to = dorian_opt('contact_email');
    if (!$to || !is_email($to)) $to = get_option('admin_email');
    $subject = 'پیام جدید از فرم تماس دوریان — ' . $name;
    $body  = "نام: $name\n";
    if ($phone) $body .= "تلفن: $phone\n";
    if ($email) $body .= "ایمیل: $email\n";
    $body .= "\nپیام:\n$msg\n";
    $headers = array();
    if ($email && is_email($email)) $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
    $ok = wp_mail($to, $subject, $body, $headers);
    wp_safe_redirect(add_query_arg('dsent', $ok ? 'ok' : 'err', $back)); exit;
}

function dorian_theme_settings_page() {
    $auto = function_exists('dorian_booking_url') ? dorian_booking_url() : '';
    $tabs = array(
        'general' => 'عمومی',
        'brand'   => 'رنگ‌ها',
        'type'    => 'تایپوگرافی',
        'header'  => 'هدر',
        'footer'  => 'فوتر',
        'social'  => 'شبکه‌ها',
        'about'   => 'درباره ما',
        'contact' => 'تماس با ما',
        'blog'    => 'مقالات',
        'product' => 'محصول',
        'links'   => 'لینک‌ها',
        'code'    => 'کد سفارشی',
        'team'    => 'تیم',
    );
    ?>
    <div class="wrap dorian-settings"><h1>تنظیمات قالب دوریان</h1>
    <p class="description" style="margin:6px 0 14px">پنل پیشرفتهٔ قالب — تقریباً هر چیزی را بدون کدنویسی تغییر دهید. تنظیمات در تب‌های زیر دسته‌بندی شده‌اند.</p>

    <h2 class="nav-tab-wrapper" id="dtabs">
      <?php foreach ($tabs as $id => $label): ?>
        <a href="#<?php echo esc_attr($id); ?>" class="nav-tab dtab-link" data-tab="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></a>
      <?php endforeach; ?>
    </h2>

    <form method="post" action="options.php"><?php settings_fields('dorian_theme_group'); ?>

      <div class="dtab" id="dtab-general">
        <h2>هیرو (صفحهٔ اصلی)</h2>
        <table class="form-table"><?php
          dorian_field('hero_eyebrow', 'متن بالای عنوان (انگلیسی)');
          dorian_field('hero_tagline', 'شعار دست‌نویس');
          dorian_field('hero_sub', 'زیرعنوان');
          dorian_field('hero_cta1', 'متن دکمهٔ اول (رزرو)');
          dorian_field('hero_cta2', 'متن دکمهٔ دوم');
          dorian_field('hero_portrait', 'تصویر پرتره', 'image');
        ?></table>
        <h2>تماس و رزرو (بخش رزرو صفحهٔ اصلی)</h2>
        <table class="form-table"><?php
          dorian_field('reserve_heading', 'عنوان بخش رزرو');
          dorian_field('reserve_text', 'متن بخش رزرو', 'textarea');
          dorian_field('phone1_label', 'برچسب تلفن ۱'); dorian_field('phone1', 'شمارهٔ تلفن ۱');
          dorian_field('phone2_label', 'برچسب تلفن ۲'); dorian_field('phone2', 'شمارهٔ تلفن ۲');
          dorian_field('phone3_label', 'برچسب تلفن ۳'); dorian_field('phone3', 'شمارهٔ تلفن ۳');
          dorian_field('address', 'آدرس', 'textarea');
        ?></table>
      </div>

      <div class="dtab" id="dtab-brand">
        <h2>رنگ‌های برند</h2>
        <p class="description">این رنگ‌ها هم روی صفحهٔ اصلی و هم روی همهٔ صفحات داخلی اعمال می‌شوند.</p>
        <table class="form-table"><?php
          dorian_field('color_blue', 'آبی برند', 'color');
          dorian_field('color_gold', 'طلایی برند', 'color');
          dorian_field('color_navy', 'سرمه‌ای (پس‌زمینهٔ تیره)', 'color');
          dorian_field('color_ink', 'مشکی متن', 'color');
        ?></table>
      </div>

      <div class="dtab" id="dtab-type">
        <h2>تایپوگرافی و حرکت (صفحات داخلی)</h2>
        <table class="form-table"><?php
          dorian_field('font_base', 'اندازهٔ فونت پایه (px)', 'number', array('min' => 13, 'max' => 22, 'desc' => 'بین ۱۳ تا ۲۲ — پیش‌فرض ۱۷'));
          dorian_field('font_scale', 'مقیاس سرتیترها', 'select', array('compact' => 'فشرده', 'comfort' => 'متعادل', 'roomy' => 'بزرگ'));
          dorian_field('anim_enabled', 'انیمیشن ظاهرشدن هنگام اسکرول', 'checkbox', array('hint' => 'فعال باشد'));
        ?></table>
      </div>

      <div class="dtab" id="dtab-header">
        <h2>هدر (نوار بالای صفحات داخلی)</h2>
        <p class="description">منوی هدر را از <a href="<?php echo esc_url(admin_url('nav-menus.php')); ?>"><strong>نمایش → فهرست‌ها</strong></a> بسازید و در محلِ «منوی اصلی دوریان (هدر)» قرار دهید. اگر منویی نسازید، منوی پیش‌فرض نمایش داده می‌شود.</p>
        <table class="form-table"><?php
          dorian_field('header_logo', 'لوگو (اگر خالی بماند، لوگوی پیش‌فرض)', 'image');
          dorian_field('header_sticky', 'هدر چسبان هنگام اسکرول', 'checkbox', array('hint' => 'چسبان باشد'));
          dorian_field('header_cta_show', 'نمایش دکمهٔ رزرو در هدر', 'checkbox', array('hint' => 'نمایش داده شود'));
          dorian_field('header_cta_text', 'متن دکمهٔ هدر');
        ?></table>
      </div>

      <div class="dtab" id="dtab-footer">
        <h2>فوتر</h2>
        <table class="form-table"><?php
          dorian_field('footer_about', 'متن کوتاه دربارهٔ برند (ستون اول فوتر)', 'textarea');
          dorian_field('footer_links', 'لینک‌های سریع (هر خط: «برچسب|آدرس»)', 'textarea', array('rows' => 5, 'desc' => 'مثال: درباره ما|/about — اگر آدرس خالی باشد، به لینک رزرو می‌رود.'));
          dorian_field('footer_copy', 'متن کپی‌رایت', 'textarea');
        ?></table>
      </div>

      <div class="dtab" id="dtab-social">
        <h2>شبکه‌های اجتماعی</h2>
        <p class="description">هر کدام را که خالی بگذارید نمایش داده نمی‌شود.</p>
        <table class="form-table"><?php
          dorian_field('instagram_url', 'اینستاگرام');
          dorian_field('telegram_url', 'تلگرام');
          dorian_field('whatsapp_url', 'واتساپ');
          dorian_field('aparat_url', 'آپارات');
          dorian_field('website_url', 'وب‌سایت');
        ?></table>
      </div>

      <div class="dtab" id="dtab-about">
        <h2>صفحهٔ «درباره ما»</h2>
        <p class="description">یک برگه بسازید و قالبِ «درباره ما — Dorian» را برایش انتخاب کنید. محتوای زیر روی همان برگه نمایش داده می‌شود.</p>
        <table class="form-table"><?php
          dorian_field('about_eyebrow', 'متن بالای عنوان (انگلیسی)');
          dorian_field('about_heading', 'عنوان اصلی');
          dorian_field('about_intro', 'مقدمه', 'textarea');
          dorian_field('about_body', 'متن اصلی (هر پاراگراف با یک خط خالی جدا شود)', 'textarea', array('rows' => 8));
          dorian_field('about_image', 'تصویر', 'image');
          dorian_field('about_stat1_n', 'آمار ۱ — عدد'); dorian_field('about_stat1_l', 'آمار ۱ — برچسب');
          dorian_field('about_stat2_n', 'آمار ۲ — عدد'); dorian_field('about_stat2_l', 'آمار ۲ — برچسب');
          dorian_field('about_stat3_n', 'آمار ۳ — عدد'); dorian_field('about_stat3_l', 'آمار ۳ — برچسب');
          dorian_field('about_values', 'ارزش‌ها (هر خط: «عنوان|توضیح»)', 'textarea', array('rows' => 5));
        ?></table>
      </div>

      <div class="dtab" id="dtab-contact">
        <h2>صفحهٔ «تماس با ما»</h2>
        <p class="description">یک برگه بسازید و قالبِ «تماس با ما — Dorian» را برایش انتخاب کنید. تلفن‌ها و آدرس از تبِ «عمومی» خوانده می‌شوند.</p>
        <table class="form-table"><?php
          dorian_field('contact_eyebrow', 'متن بالای عنوان (انگلیسی)');
          dorian_field('contact_heading', 'عنوان اصلی');
          dorian_field('contact_intro', 'مقدمه', 'textarea');
          dorian_field('contact_hours', 'ساعات کاری (هر خط یک مورد)', 'textarea');
          dorian_field('contact_map', 'کد امبد نقشه (iframe) یا آدرس', 'textarea', array('rows' => 4, 'desc' => 'کد iframe نقشهٔ گوگل/نشان را اینجا بگذارید.'));
          dorian_field('contact_email', 'ایمیل گیرندهٔ فرم تماس', 'text', array('desc' => 'اگر خالی بماند به ایمیل مدیر سایت ارسال می‌شود.'));
          dorian_field('contact_form_show', 'نمایش فرم تماس', 'checkbox', array('hint' => 'نمایش داده شود'));
        ?></table>
      </div>

      <div class="dtab" id="dtab-blog">
        <h2>مقالات (وبلاگ)</h2>
        <table class="form-table"><?php
          dorian_field('blog_eyebrow', 'متن بالای عنوان (انگلیسی)');
          dorian_field('blog_heading', 'عنوان صفحهٔ مقالات');
          dorian_field('blog_intro', 'مقدمهٔ صفحهٔ مقالات', 'textarea');
          dorian_field('blog_excerpt', 'طول خلاصه (تعداد کلمه)', 'number', array('min' => 8, 'max' => 80));
          dorian_field('blog_cols', 'تعداد ستون کارت‌ها', 'select', array('2' => 'دو ستون', '3' => 'سه ستون'));
        ?></table>
      </div>

      <div class="dtab" id="dtab-product">
        <h2>محصول (ووکامرس)</h2>
        <table class="form-table"><?php
          dorian_field('product_related', 'نمایش «محصولات مرتبط» در صفحهٔ محصول', 'checkbox', array('hint' => 'نمایش داده شود'));
        ?></table>
      </div>

      <div class="dtab" id="dtab-links">
        <h2>لینک دکمه‌ها و گیفت‌کارت‌ها</h2>
        <table class="form-table">
          <?php foreach (dorian_link_fields() as $key => $label) :
            $ph = ($key === 'dorian_reserve_url') ? $auto : ''; ?>
          <tr><th scope="row"><?php echo esc_html($label); ?></th><td>
            <input type="url" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr(get_option($key, '')); ?>" style="width:480px" placeholder="<?php echo esc_attr($ph); ?>">
          </td></tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div class="dtab" id="dtab-code">
        <h2>CSS سفارشی</h2>
        <p class="description">هر استایلِ دلخواهی را اینجا وارد کنید؛ روی <strong>کل سایت</strong> اعمال می‌شود و بر بقیهٔ استایل‌ها اولویت دارد.</p>
        <textarea name="dorian_opts[custom_css]" rows="10" dir="ltr" style="width:100%;max-width:900px;font-family:monospace" placeholder=".post-card{border-radius:20px}"><?php echo esc_textarea(dorian_opt('custom_css')); ?></textarea>
        <h2>JavaScript سفارشی</h2>
        <p class="description">کدِ JS دلخواه (بدون تگ <code>&lt;script&gt;</code>) که در انتهای همهٔ صفحات اجرا می‌شود.</p>
        <textarea name="dorian_opts[custom_js]" rows="8" dir="ltr" style="width:100%;max-width:900px;font-family:monospace" placeholder="console.log('Dorian');"><?php echo esc_textarea(dorian_opt('custom_js')); ?></textarea>
      </div>

    <?php submit_button('ذخیرهٔ تنظیمات', 'primary', 'submit', true, array('id' => 'dorian-main-submit')); ?>
    </form>

    <div class="dtab" id="dtab-team">
    <hr style="margin:14px 0 22px">
    <h2>شخصیت‌های دوریان (تیم)</h2>
    <p class="description">اعضای تیم را اینجا اضافه، ویرایش یا حذف کنید. ترتیبِ نمایش با عددِ «ترتیب» تعیین می‌شود (کوچک‌تر = جلوتر). «تخصص‌ها» را در هر خط یک مورد بنویسید. اگر همه را حذف کنید، تیمِ پیش‌فرض نمایش داده می‌شود.</p>
    <?php if (isset($_GET['chars']) && $_GET['chars'] === 'saved') echo '<div class="notice notice-success is-dismissible"><p>شخصیت‌ها ذخیره شد.</p></div>'; ?>
    <?php $chars = get_posts(array('post_type' => 'dorian_character', 'numberposts' => -1, 'orderby' => 'menu_order date', 'order' => 'ASC', 'post_status' => 'any')); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dchars">
      <input type="hidden" name="action" value="dorian_save_chars">
      <?php wp_nonce_field('dorian_save_chars'); ?>
      <div id="dchar-rows">
        <?php $idx = 0; foreach ($chars as $c) dorian_char_row($idx++, $c); ?>
      </div>
      <p><button type="button" class="button" id="dchar-add">+ افزودن شخصیت</button></p>
      <?php submit_button('ذخیرهٔ شخصیت‌ها'); ?>
    </form>
    <script type="text/template" id="dchar-tpl"><?php dorian_char_row('__IDX__', null); ?></script>
    </div><!-- /#dtab-team -->
    </div><!-- /.wrap -->
    <style>
      .dorian-settings .dchar-row.to-del{opacity:.45;text-decoration:line-through}
      .dorian-settings .dtab{display:none}
      .dorian-settings .dtab.is-active{display:block}
      .dorian-settings .dtab-link{cursor:pointer}
    </style>
    <script>
    jQuery(function($){
      // ---- tab switching (remembers last tab via the URL hash) ----
      function activate(tab){
        if(!tab || !$('#dtab-'+tab).length) tab = 'general';
        $('.dtab').removeClass('is-active');
        $('#dtab-'+tab).addClass('is-active');
        $('.dtab-link').removeClass('nav-tab-active');
        $('.dtab-link[data-tab="'+tab+'"]').addClass('nav-tab-active');
        // the settings form has one shared submit; hide it on the team tab (own submit)
        $('#dorian-main-submit').closest('p.submit').toggle(tab !== 'team');
      }
      $('.dtab-link').on('click', function(e){
        e.preventDefault(); var tab=$(this).data('tab');
        history.replaceState(null,'','#'+tab); activate(tab);
      });
      activate((location.hash||'').replace('#',''));

      // ---- media picker (delegated so cloned rows work too) ----
      $(document).on('click', '.dorian-img-pick', function(e){
        e.preventDefault(); var btn=$(this), frame=wp.media({title:'انتخاب تصویر', multiple:false});
        frame.on('select', function(){ var u=frame.state().get('selection').first().toJSON().url; btn.prev('.dorian-img-url').val(u); });
        frame.open();
      });
      // ---- characters repeater ----
      var i = <?php echo (int) count($chars); ?>;
      $('#dchar-add').on('click', function(){
        $('#dchar-rows').append($('#dchar-tpl').html().replace(/__IDX__/g, i++));
      });
      $('#dchar-rows').on('click', '.dchar-del', function(){
        var row=$(this).closest('.dchar-row');
        if (row.find('.dchar-id').val()){
          var f=row.find('.dchar-delflag');
          if (f.val()==='1'){ f.val(''); row.removeClass('to-del'); $(this).text('حذف'); }
          else { f.val('1'); row.addClass('to-del'); $(this).text('لغو حذف'); }
        } else { row.remove(); }
      });
    });
    </script>
    <?php
}

/** One editable character row inside the theme-settings team manager. */
function dorian_char_row($idx, $c) {
    $id    = $c ? $c->ID : '';
    $title = $c ? $c->post_title : '';
    $order = $c ? (int) $c->menu_order : 0;
    $role  = $c ? get_post_meta($c->ID, '_role', true) : '';
    $en    = $c ? get_post_meta($c->ID, '_name_en', true) : '';
    $photo = $c ? get_post_meta($c->ID, '_photo', true) : '';
    if ($c && !$photo) { $th = get_the_post_thumbnail_url($c->ID, 'medium'); if ($th) $photo = $th; }
    $skills = $c ? get_post_meta($c->ID, '_skills', true) : '';
    $n = 'chars[' . $idx . ']';
    ?>
    <div class="dchar-row" style="border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;margin-bottom:12px;background:#fff">
      <input type="hidden" class="dchar-id" name="<?php echo $n; ?>[id]" value="<?php echo esc_attr($id); ?>">
      <input type="hidden" class="dchar-delflag" name="<?php echo $n; ?>[del]" value="">
      <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start">
        <label>نام فارسی<br><input type="text" name="<?php echo $n; ?>[title]" value="<?php echo esc_attr($title); ?>" style="width:180px"></label>
        <label>نام انگلیسی<br><input type="text" name="<?php echo $n; ?>[name_en]" value="<?php echo esc_attr($en); ?>" style="width:180px"></label>
        <label>نقش/تخصص (EN)<br><input type="text" name="<?php echo $n; ?>[role]" value="<?php echo esc_attr($role); ?>" style="width:170px"></label>
        <label>ترتیب<br><input type="number" name="<?php echo $n; ?>[order]" value="<?php echo esc_attr($order); ?>" style="width:70px"></label>
      </div>
      <p style="margin:10px 0 0"><label>تصویر<br><input type="url" class="dorian-img-url" name="<?php echo $n; ?>[photo]" value="<?php echo esc_attr($photo); ?>" style="width:420px"></label> <button type="button" class="button dorian-img-pick">انتخاب تصویر</button></p>
      <p style="margin:10px 0 0"><label>تخصص‌ها (هر خط یک مورد)<br><textarea name="<?php echo $n; ?>[skills]" rows="4" style="width:420px"><?php echo esc_textarea($skills); ?></textarea></label></p>
      <p style="margin:10px 0 0"><button type="button" class="button-link-delete dchar-del" style="color:#b32d2e">حذف</button></p>
    </div>
    <?php
}

/** Save/create/delete team characters submitted from the theme-settings manager. */
add_action('admin_post_dorian_save_chars', function () {
    if (!current_user_can('manage_options')) wp_die('عدم دسترسی');
    check_admin_referer('dorian_save_chars');
    $rows = isset($_POST['chars']) && is_array($_POST['chars']) ? $_POST['chars'] : array();
    foreach ($rows as $row) {
        $id    = isset($row['id']) ? (int) $row['id'] : 0;
        $title = sanitize_text_field($row['title'] ?? '');
        if (!empty($row['del'])) { if ($id) wp_delete_post($id, true); continue; }
        if (!$id && $title === '') continue; // skip empty new rows
        $data = array(
            'post_type'   => 'dorian_character',
            'post_status' => 'publish',
            'post_title'  => $title,
            'menu_order'  => (int) ($row['order'] ?? 0),
        );
        if ($id) { $data['ID'] = $id; wp_update_post($data); }
        else { $id = wp_insert_post($data); }
        if ($id && !is_wp_error($id)) {
            update_post_meta($id, '_role', sanitize_text_field($row['role'] ?? ''));
            update_post_meta($id, '_name_en', sanitize_text_field($row['name_en'] ?? ''));
            update_post_meta($id, '_photo', esc_url_raw($row['photo'] ?? ''));
            update_post_meta($id, '_skills', sanitize_textarea_field($row['skills'] ?? ''));
        }
    }
    wp_safe_redirect(add_query_arg(array('page' => 'dorian-theme', 'chars' => 'saved'), admin_url('themes.php')));
    exit;
});

/* =========================================================================
   شخصیت‌های دوریان (تیم) — افزودن/حذف/ویرایش از پیشخان
   ========================================================================= */
add_action('init', function () {
    register_post_type('dorian_character', array(
        'labels' => array(
            'name' => 'شخصیت‌های دوریان', 'singular_name' => 'شخصیت',
            'add_new' => 'افزودن شخصیت', 'add_new_item' => 'افزودن شخصیت جدید', 'edit_item' => 'ویرایش شخصیت',
            'menu_name' => 'شخصیت‌های دوریان',
        ),
        // managed from «نمایش → تنظیمات دوریان»; no separate top-level menu
        'public' => false, 'show_ui' => true, 'show_in_menu' => false,
        'supports' => array('title', 'thumbnail', 'page-attributes'),
    ));
});
add_action('add_meta_boxes', function () {
    add_meta_box('dorian_char_meta', 'مشخصات شخصیت', 'dorian_char_box', 'dorian_character', 'normal', 'high');
});
function dorian_char_box($post) {
    wp_nonce_field('dorian_char', 'dorian_char_nonce');
    $role  = get_post_meta($post->ID, '_role', true);
    $en    = get_post_meta($post->ID, '_name_en', true);
    $photo = get_post_meta($post->ID, '_photo', true);
    $skills= get_post_meta($post->ID, '_skills', true);
    echo '<p><label>نقش/تخصص (انگلیسی، مثل Hair Artist):<br><input type="text" name="dorian_char_role" value="' . esc_attr($role) . '" style="width:320px"></label></p>';
    echo '<p><label>نام انگلیسی:<br><input type="text" name="dorian_char_en" value="' . esc_attr($en) . '" style="width:320px"></label></p>';
    echo '<p><label>تصویر (آدرس):<br><input type="url" class="dorian-img-url" name="dorian_char_photo" value="' . esc_attr($photo) . '" style="width:420px"> <button type="button" class="button dorian-img-pick">انتخاب تصویر</button></label>'
       . '<br><span style="color:#777">یا از «تصویر شاخص» در ستون کناری استفاده کنید.</span></p>';
    echo '<p><label>تخصص‌ها (هر خط یک مورد):<br><textarea name="dorian_char_skills" rows="6" style="width:420px">' . esc_textarea($skills) . '</textarea></label></p>';
    echo '<p style="color:#777">ترتیب نمایش با «ترتیب» در باکسِ «ویژگی‌های» کناری تعیین می‌شود (عدد کوچک‌تر = جلوتر).</p>';
}
add_action('save_post_dorian_character', function ($post_id) {
    if (!isset($_POST['dorian_char_nonce']) || !wp_verify_nonce($_POST['dorian_char_nonce'], 'dorian_char')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    update_post_meta($post_id, '_role', sanitize_text_field($_POST['dorian_char_role'] ?? ''));
    update_post_meta($post_id, '_name_en', sanitize_text_field($_POST['dorian_char_en'] ?? ''));
    update_post_meta($post_id, '_photo', esc_url_raw($_POST['dorian_char_photo'] ?? ''));
    update_post_meta($post_id, '_skills', sanitize_textarea_field($_POST['dorian_char_skills'] ?? ''));
});

/** Render the team cards from the CPT; returns false when there are none. */
function dorian_render_characters() {
    $items = get_posts(array('post_type' => 'dorian_character', 'numberposts' => -1, 'orderby' => 'menu_order date', 'order' => 'ASC', 'post_status' => 'publish'));
    if (!$items) return false;
    foreach ($items as $p) {
        $role  = get_post_meta($p->ID, '_role', true);
        $en    = get_post_meta($p->ID, '_name_en', true);
        $photo = get_the_post_thumbnail_url($p->ID, 'medium');
        if (!$photo) $photo = get_post_meta($p->ID, '_photo', true);
        $skills = array_filter(array_map('trim', explode("\n", (string) get_post_meta($p->ID, '_skills', true))));
        echo '<article class="tcard"><div class="tcard__inner">'
           . '<span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span>'
           . '<span class="tcard__role lat">' . esc_html($role) . '</span>'
           . '<div class="tcard__photo"><img src="' . esc_url($photo) . '" alt="' . esc_attr($p->post_title) . '" loading="lazy"></div>'
           . '<div class="tcard__name"><div class="fa">' . esc_html($p->post_title) . '</div><div class="en lat">' . esc_html($en) . '</div></div>'
           . '<div class="tcard__skills"><div class="h">Specialties</div><ul>';
        foreach ($skills as $sk) echo '<li>' . esc_html($sk) . '</li>';
        echo '</ul></div></div></article>';
    }
    return true;
}

/** Seed the current 9 members once, so they can be edited/deleted individually. */
add_action('admin_init', function () {
    if (get_option('dorian_chars_seeded')) return;
    if (get_posts(array('post_type' => 'dorian_character', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids'))) {
        update_option('dorian_chars_seeded', 1); return;
    }
    $uri = get_stylesheet_directory_uri() . '/assets/img/';
    $team = array(
        array('آرمان کاراگاه', 'Arman Karagah', 'Hair Master', 'team-01.jpg', "کوتاهی مو و ریش\nسشوار و استایل مو\nاکستنشن طبیعی مو\nپروتز مو\nکراتین مو\nگریم تخصصی داماد"),
        array('احسان حسن‌یاری', 'Ehsan Hasanyari', 'Hair Artist', 'team-02.jpg', "کوتاهی مو و ریش\nسشوار و استایل مو\nشمع و ماسک صورت\nمانیکور و پدیکور\nگریم داماد"),
        array('ارسلان پورفضل', 'Arsalan Pourfazl', 'Hair Artist', 'team-03.jpg', "کوتاهی مو و ریش\nسشوار و استایل مو\nشمع و ماسک صورت"),
        array('اشکان محمدی', 'Ashkan Mohammadi', 'Hair Artist', 'team-04.jpg', "کوتاهی مو و ریش\nسشوار و استایل مو\nماسک صورت"),
        array('حسین لویمی', 'Hossein Loyami', 'Hair Artist', 'team-05.jpg', "کوتاهی مو و ریش\nسشوار و استایل مو\nشمع و ماسک صورت"),
        array('مجتبی میراحمدی', 'Mojtaba Mirahmadi', 'Hair Artist', 'team-06.jpg', "کوتاهی مو و ریش\nسشوار و استایل مو\nشمع و ماسک صورت"),
        array('احمد سرخه', 'Ahmad Sorkheh', 'Hair Artist', 'team-07.jpg', "کوتاهی مو و ریش\nسشوار و استایل مو\nشمع و ماسک صورت\nرنگ مو\nکراتین مو"),
        array('احمدرضا جلالی', 'Ahmadreza Jalali', 'Massage', 'team-08.jpg', "ماساژ درمانی\nماساژ ریلکسی"),
        array('حمید جوهری', 'Hamid Johari', 'Facial', 'team-09.jpg', "پاکسازی تخصصی\nرنگ مو و ریش\nویتامینه مو\nمانیکور و پدیکور\nشمع صورت"),
    );
    $i = 0;
    foreach ($team as $t) {
        $pid = wp_insert_post(array('post_type' => 'dorian_character', 'post_status' => 'publish', 'post_title' => $t[0], 'menu_order' => $i++));
        if (is_wp_error($pid)) continue;
        update_post_meta($pid, '_name_en', $t[1]);
        update_post_meta($pid, '_role', $t[2]);
        update_post_meta($pid, '_photo', $uri . $t[3]);
        update_post_meta($pid, '_skills', $t[4]);
    }
    update_option('dorian_chars_seeded', 1);
});

/* =========================================================================
   WooCommerce tweaks for the Dorian single-product page (تک‌محصولی)
   ========================================================================= */
add_action('after_setup_theme', function () {
    if (!class_exists('WooCommerce')) return;
    // toggle related products from the theme panel
    if (dorian_opt('product_related') !== '1') {
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
    }
    // give the shop a comfortable 3-up grid by default
    add_filter('loop_shop_columns', function () { return 3; });
    // a trust / benefits strip inside the product summary, after the add-to-cart
    add_action('woocommerce_single_product_summary', 'dorian_product_benefits', 35);
});

/** Trust / benefits strip shown under the add-to-cart on single products. */
function dorian_product_benefits() {
    $ic = array(
        // shield-check
        '<svg viewBox="0 0 24 24"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>',
        // truck
        '<svg viewBox="0 0 24 24"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>',
        // chat
        '<svg viewBox="0 0 24 24"><path d="M4 5h16v11H9l-4 3v-3H4z"/></svg>',
    );
    $items = array(
        array('اصالت کالا', 'ضمانتِ اصل‌بودن همهٔ محصولات'),
        array('ارسال سریع', 'بسته‌بندیِ ویژه به سراسر کشور'),
        array('مشاورهٔ رایگان', 'راهنماییِ تخصصی پیش از خرید'),
    );
    echo '<ul class="dorian-benefits">';
    foreach ($items as $i => $b) {
        echo '<li><span class="dorian-benefits__ic" aria-hidden="true">' . $ic[$i] . '</span>'
            . '<span class="dorian-benefits__t"><b>' . esc_html($b[0]) . '</b><em>' . esc_html($b[1]) . '</em></span></li>';
    }
    echo '</ul>';
}
