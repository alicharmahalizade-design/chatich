<?php
/**
 * Generic page (editor content) wrapped in the Dorian chrome.
 * Note: the landing (template-dorian.php), About and Contact templates
 * have their own files and are not affected by this default.
 */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post();
    dorian_page_hero(get_the_title(), '', '', array('خانه' => home_url('/'), get_the_title() => null));
    ?>
    <article <?php post_class('single single--page'); ?>>
      <div class="wrap single__wrap">
        <?php if (has_post_thumbnail()) : ?>
          <figure class="single__hero"><?php the_post_thumbnail('large', array('alt' => esc_attr(get_the_title()))); ?><span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span></figure>
        <?php endif; ?>
        <div class="prose">
          <?php the_content(); ?>
          <?php wp_link_pages(array('before' => '<div class="prose__pages">صفحه: ', 'after' => '</div>')); ?>
        </div>
      </div>
    </article>
    <?php
    if (comments_open() || get_comments_number()) :
        echo '<section class="comments-section"><div class="wrap">';
        comments_template();
        echo '</div></section>';
    endif;
endwhile;
get_footer();
