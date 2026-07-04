<?php
/**
 * Archives — category / tag / author / date listings.
 */
if (!defined('ABSPATH')) exit;
get_header();
$cols = dorian_opt('blog_cols', '3') === '2' ? '2' : '3';
$title = get_the_archive_title();
$desc  = get_the_archive_description();
// strip WP's "Category:" style prefixes for a cleaner luxury heading
$clean = preg_replace('/^[^:]+:\s*/u', '', wp_strip_all_tags($title));
dorian_page_hero(
    $clean ?: $title,
    'Archive',
    $desc ? wp_strip_all_tags($desc) : '',
    array('خانه' => home_url('/'), 'مقالات' => get_permalink(get_option('page_for_posts')) ?: null, ($clean ?: 'بایگانی') => null)
);
?>
<section class="listing">
  <div class="wrap">
    <?php if (have_posts()) : ?>
      <div class="post-grid cols-<?php echo esc_attr($cols); ?>">
        <?php while (have_posts()) : the_post(); dorian_post_card(); endwhile; ?>
      </div>
      <?php dorian_pagination(); ?>
    <?php else : ?>
      <p class="listing__empty">موردی یافت نشد.</p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer();
