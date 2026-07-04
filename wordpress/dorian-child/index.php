<?php
/**
 * Generic fallback template (also the blog index when no home.php match).
 */
if (!defined('ABSPATH')) exit;
get_header();
$cols = dorian_opt('blog_cols', '3') === '2' ? '2' : '3';
dorian_page_hero(
    dorian_opt('blog_heading', 'مقالات دوریان'),
    dorian_opt('blog_eyebrow', 'Journal'),
    dorian_opt('blog_intro'),
    array('خانه' => home_url('/'), 'مقالات' => null)
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
      <p class="listing__empty">چیزی برای نمایش نیست.</p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer();
