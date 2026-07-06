<?php
/**
 * Search results.
 */
if (!defined('ABSPATH')) exit;
get_header();
$cols = dorian_opt('blog_cols', '3') === '2' ? '2' : '3';
global $wp_query;
$count = (int) $wp_query->found_posts;
dorian_page_hero(
    'نتایج جستجو',
    'Search',
    sprintf('%s نتیجه برای «%s»', number_format_i18n($count), get_search_query()),
    array('خانه' => home_url('/'), 'جستجو' => null)
);
?>
<section class="listing">
  <div class="wrap">
    <div class="listing__search"><?php get_search_form(); ?></div>
    <?php if (have_posts()) : ?>
      <div class="post-grid cols-<?php echo esc_attr($cols); ?>">
        <?php while (have_posts()) : the_post(); dorian_post_card(); endwhile; ?>
      </div>
      <?php dorian_pagination(); ?>
    <?php else : ?>
      <p class="listing__empty">نتیجه‌ای برای جستجوی شما پیدا نشد. عبارت دیگری را امتحان کنید.</p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer();
