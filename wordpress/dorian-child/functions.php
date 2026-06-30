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
   Theme settings — set where the "رزرو نوبت" buttons point.
   ========================================================================= */
// every editable link the theme exposes
function dorian_link_fields() {
    return array(
        'dorian_reserve_url' => 'لینک دکمه‌های «رزرو نوبت»',
        'dorian_tour_url'    => 'لینک دکمهٔ «گشتی در مجموعه»',
        'dorian_gift_cta_url'=> 'لینک دکمهٔ «هدیه بدهید»',
        'dorian_gift_url_1'  => 'لینک گیفت‌کارت ۱ (یک میلیون)',
        'dorian_gift_url_2'  => 'لینک گیفت‌کارت ۲ (دو میلیون)',
        'dorian_gift_url_3'  => 'لینک گیفت‌کارت ۳ (پنج میلیون)',
        'dorian_gift_url_4'  => 'لینک گیفت‌کارت ۴ (ده میلیون)',
    );
}
add_action('admin_menu', function () {
    add_theme_page('تنظیمات قالب دوریان', 'تنظیمات دوریان', 'manage_options', 'dorian-theme', 'dorian_theme_settings_page');
});
add_action('admin_init', function () {
    foreach (array_keys(dorian_link_fields()) as $key) {
        register_setting('dorian_theme_group', $key, array('sanitize_callback' => 'esc_url_raw'));
    }
});
/** A configured link, falling back to a default. */
function dorian_link($key, $fallback = '') {
    $url = get_option($key);
    return $url ? $url : $fallback;
}
function dorian_reserve_link() {
    $url = get_option('dorian_reserve_url');
    if ($url) return $url;
    if (function_exists('dorian_booking_url')) return dorian_booking_url(); // booking page from the plugin
    return home_url('/');
}
/** Render one gift card; becomes a link when a URL is configured. */
function dorian_giftcard($front, $back, $alt, $url) {
    $tag  = $url ? 'a' : 'div';
    $href = $url ? ' href="' . esc_url($url) . '"' : '';
    echo '<' . $tag . ' class="giftcard"' . $href . '><div class="giftcard__inner">'
        . '<div class="giftcard__face giftcard__front"><img src="' . esc_url($front) . '" alt="' . esc_attr($alt) . '" loading="lazy"></div>'
        . '<div class="giftcard__face giftcard__back"><img src="' . esc_url($back) . '" alt="' . esc_attr($alt) . '" loading="lazy"></div>'
        . '</div></' . $tag . '>';
}
function dorian_theme_settings_page() {
    $auto = function_exists('dorian_booking_url') ? dorian_booking_url() : '';
    ?>
    <div class="wrap"><h1>تنظیمات قالب دوریان</h1>
    <form method="post" action="options.php"><?php settings_fields('dorian_theme_group'); ?>
    <table class="form-table" role="presentation">
      <?php foreach (dorian_link_fields() as $key => $label) :
        $ph = ($key === 'dorian_reserve_url') ? $auto : ''; ?>
      <tr><th scope="row"><?php echo esc_html($label); ?></th><td>
        <input type="url" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr(get_option($key, '')); ?>" style="width:480px" placeholder="<?php echo esc_attr($ph); ?>">
      </td></tr>
      <?php endforeach; ?>
    </table>
    <p class="description">لینک دکمه‌ها و گیفت‌کارت‌ها را اینجا تنظیم کنید. هر گیفت‌کارت را می‌توانید به صفحهٔ تک‌محصولِ خودش وصل کنید. فیلدهای خالی به حالت پیش‌فرض می‌مانند.</p>
    <?php submit_button(); ?>
    </form></div>
    <?php
}
