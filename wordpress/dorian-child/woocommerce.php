<?php
/**
 * WooCommerce wrapper — gives the shop, category and single-product pages
 * (تک‌محصولی) the Dorian chrome. WooCommerce renders its own markup inside
 * woocommerce_content(); the look is tuned by assets/css/internal.css.
 */
if (!defined('ABSPATH')) exit;
get_header();

if (is_singular('product')) {
    // slim breadcrumb only — the product title is shown inside the summary
    echo '<section class="pagehero pagehero--slim"><div class="wrap pagehero__inner">';
    echo '<nav class="crumbs" aria-label="مسیر"><a href="' . esc_url(home_url('/')) . '">خانه</a><span class="sep">/</span>';
    echo '<a href="' . esc_url(wc_get_page_permalink('shop')) . '">فروشگاه</a><span class="sep">/</span>';
    echo '<span class="here">' . esc_html(get_the_title()) . '</span></nav>';
    echo '</div></section>';
} else {
    $title = woocommerce_page_title(false);
    dorian_page_hero(
        $title ?: 'فروشگاه',
        'The Boutique',
        '',
        array('خانه' => home_url('/'), 'فروشگاه' => is_shop() ? null : wc_get_page_permalink('shop'))
    );
}
?>
<section class="woo">
  <div class="wrap woo__wrap">
    <?php woocommerce_content(); ?>
  </div>
</section>
<?php get_footer();
