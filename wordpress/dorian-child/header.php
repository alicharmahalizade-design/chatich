<?php
/**
 * Dorian shared header (chrome for all internal pages).
 * The landing template (template-dorian.php) prints its own <head>/<header>
 * and never calls get_header(), so it is unaffected by this file.
 */
if (!defined('ABSPATH')) exit;
$logo = dorian_logo_url();
$reserve = function_exists('dorian_reserve_link') ? dorian_reserve_link() : home_url('/');
$sticky  = dorian_opt('header_sticky') === '1';
$cta_show = dorian_opt('header_cta_show') === '1';
$cta_text = dorian_opt('header_cta_text', 'رزرو نوبت');
$home = home_url('/');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl" data-theme="light">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<script>document.documentElement.classList.add('js')</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php wp_head(); ?>
</head>
<body <?php body_class('dorian-body'); ?>>
<?php if (function_exists('wp_body_open')) wp_body_open(); ?>

<!-- atmosphere (light, no cinematics) -->
<div id="tone" aria-hidden="true"></div>
<div id="fx" aria-hidden="true"></div>
<div class="dust" id="dust" aria-hidden="true"></div>
<div class="cursor__ring" id="curRing" aria-hidden="true"></div>
<div class="cursor__dot" id="curDot" aria-hidden="true"></div>

<a class="skip-link screen-reader-text" href="#dorian-main">پرش به محتوا</a>

<header class="head<?php echo $sticky ? '' : ' head--static'; ?>" id="head">
  <div class="head__start">
    <a href="<?php echo esc_url($home); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
      <img class="head__logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
    </a>
    <nav class="nav" id="nav" aria-label="منوی اصلی">
      <?php if (has_nav_menu('dorian_primary')) {
        wp_nav_menu(array('theme_location' => 'dorian_primary', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1, 'fallback_cb' => false));
      } else { ?>
        <a href="<?php echo esc_url($home); ?>">خانه</a>
        <a href="<?php echo esc_url(home_url('/about')); ?>">دربارهٔ دوریان</a>
        <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts')) ?: home_url('/blog')); ?>">مقالات</a>
        <a href="<?php echo esc_url(home_url('/contact')); ?>">تماس با ما</a>
      <?php } ?>
      <?php if ($cta_show) : ?>
        <a class="btn btn--gold head__cta head__cta--mobile" href="<?php echo esc_url($reserve); ?>"><?php echo esc_html($cta_text); ?></a>
      <?php endif; ?>
    </nav>
  </div>
  <div class="head__end">
    <?php if ($cta_show) : ?>
      <a class="btn btn--gold head__cta" href="<?php echo esc_url($reserve); ?>"><?php echo esc_html($cta_text); ?></a>
    <?php endif; ?>
    <button class="burger" id="burger" aria-label="منو" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span></button>
  </div>
</header>

<main class="dorian-main" id="dorian-main">
