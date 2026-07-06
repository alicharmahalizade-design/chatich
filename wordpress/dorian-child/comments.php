<?php
/**
 * Themed comments list + form.
 */
if (!defined('ABSPATH')) exit;
if (post_password_required()) return;
?>
<div id="comments" class="comments">
  <?php if (have_comments()) : ?>
    <h2 class="comments__title">
      <?php $n = get_comments_number();
        printf(_n('%s دیدگاه', '%s دیدگاه', $n, 'dorian'), number_format_i18n($n)); ?>
    </h2>
    <ol class="comments__list">
      <?php wp_list_comments(array('style' => 'ol', 'avatar_size' => 52, 'short_ping' => true)); ?>
    </ol>
    <?php the_comments_pagination(array('prev_text' => '→ قبلی', 'next_text' => 'بعدی ←')); ?>
  <?php endif; ?>

  <?php if (!comments_open() && get_comments_number() && post_type_supports(get_post_type(), 'comments')) : ?>
    <p class="comments__closed">دیدگاه‌ها بسته شده‌اند.</p>
  <?php endif; ?>

  <?php
  comment_form(array(
      'class_form'         => 'comment-form',
      'title_reply'        => 'دیدگاه خود را بنویسید',
      'title_reply_before' => '<h3 class="comments__reply-h">',
      'title_reply_after'  => '</h3>',
      'label_submit'       => 'ارسال دیدگاه',
      'comment_notes_before' => '',
  ));
  ?>
</div>
