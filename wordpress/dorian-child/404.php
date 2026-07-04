<?php
/**
 * 404 — not found.
 */
if (!defined('ABSPATH')) exit;
get_header();
?>
<section class="notfound">
  <div class="wrap notfound__inner">
    <div class="notfound__code lat">404</div>
    <h1 class="notfound__title">این طبقه وجود ندارد</h1>
    <p class="notfound__text">صفحه‌ای که دنبالش بودید پیدا نشد؛ شاید جابه‌جا شده یا هرگز نبوده است.</p>
    <div class="notfound__search"><?php get_search_form(); ?></div>
    <div class="notfound__cta"><a class="btn btn--gold" href="<?php echo esc_url(home_url('/')); ?>">بازگشت به خانه</a></div>
  </div>
</section>
<?php get_footer();
