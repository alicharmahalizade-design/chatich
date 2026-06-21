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
    }
    return $fragments;
});
