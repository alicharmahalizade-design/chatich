<?php
/**
 * Single article (تک‌مقاله).
 */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post();
    $cats = get_the_category();
    $blog = get_permalink(get_option('page_for_posts'));
    dorian_page_hero(
        get_the_title(),
        $cats ? $cats[0]->name : 'Journal',
        '',
        array('خانه' => home_url('/'), 'مقالات' => $blog ?: null, get_the_title() => null)
    );
    ?>
    <article <?php post_class('single'); ?>>
      <div class="wrap single__wrap">

        <div class="single__meta">
          <span class="single__date lat"><?php echo esc_html(get_the_date()); ?></span>
          <?php if ($cats) : ?><span class="sep">·</span><a class="single__cat" href="<?php echo esc_url(get_category_link($cats[0]->term_id)); ?>"><?php echo esc_html($cats[0]->name); ?></a><?php endif; ?>
          <span class="sep">·</span><span class="single__read"><?php echo esc_html(max(1, (int) round(str_word_count(wp_strip_all_tags(get_the_content())) / 200))); ?> دقیقه مطالعه</span>
        </div>

        <?php if (has_post_thumbnail()) : ?>
          <figure class="single__hero"><?php the_post_thumbnail('large', array('alt' => esc_attr(get_the_title()))); ?><span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span></figure>
        <?php endif; ?>

        <div class="prose">
          <?php the_content(); ?>
          <?php wp_link_pages(array('before' => '<div class="prose__pages">صفحه: ', 'after' => '</div>')); ?>
        </div>

        <?php if (has_tag()) : ?>
          <div class="single__tags"><?php the_tags('<span class="single__tags-h">برچسب‌ها:</span> ', '', ''); ?></div>
        <?php endif; ?>

        <div class="single__share" aria-label="اشتراک‌گذاری">
          <span class="single__share-h">اشتراک‌گذاری:</span>
          <a href="https://t.me/share/url?url=<?php echo rawurlencode(get_permalink()); ?>&text=<?php echo rawurlencode(get_the_title()); ?>" target="_blank" rel="noopener">تلگرام</a>
          <a href="https://api.whatsapp.com/send?text=<?php echo rawurlencode(get_the_title() . ' ' . get_permalink()); ?>" target="_blank" rel="noopener">واتساپ</a>
          <a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode(get_permalink()); ?>&text=<?php echo rawurlencode(get_the_title()); ?>" target="_blank" rel="noopener">ایکس</a>
        </div>

      </div>
    </article>

    <?php
    // related posts (same category)
    if ($cats) :
        $related = new WP_Query(array(
            'category__in'        => array($cats[0]->term_id),
            'post__not_in'        => array(get_the_ID()),
            'posts_per_page'      => 3,
            'ignore_sticky_posts' => 1,
            'no_found_rows'       => true,
        ));
        if ($related->have_posts()) : ?>
        <section class="related">
          <div class="wrap">
            <span class="eyebrow c">Related</span>
            <h2 class="related__h">مقالات مرتبط</h2>
            <div class="ds"><i></i><em></em><i></i></div>
            <div class="post-grid cols-3">
              <?php while ($related->have_posts()) : $related->the_post(); dorian_post_card(); endwhile; ?>
            </div>
          </div>
        </section>
        <?php endif;
        wp_reset_postdata();
    endif;

    if (comments_open() || get_comments_number()) :
        echo '<section class="comments-section"><div class="wrap">';
        comments_template();
        echo '</div></section>';
    endif;

endwhile;
get_footer();
