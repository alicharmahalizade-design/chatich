<?php
/**
 * Template Name: تماس با ما — Dorian
 *
 * Assign this template to a Page. Phones/address come from «تنظیمات دوریان →
 * عمومی»; page-specific copy, hours, map and the contact form from the
 * «تماس با ما» tab. The form posts to admin-post.php (handler in functions.php).
 */
if (!defined('ABSPATH')) exit;
get_header();

$phones = array();
foreach (array(1, 2, 3) as $i) {
    $num = dorian_opt('phone' . $i);
    if ($num) $phones[] = array(dorian_opt('phone' . $i . '_label'), $num);
}
$hours = array_filter(array_map('trim', explode("\n", (string) dorian_opt('contact_hours'))));
$map   = dorian_opt('contact_map');
$sent  = isset($_GET['dsent']) ? sanitize_key($_GET['dsent']) : '';

dorian_page_hero(
    dorian_opt('contact_heading', 'با دوریان در تماس باشید'),
    dorian_opt('contact_eyebrow', 'Get in touch'),
    dorian_opt('contact_intro'),
    array('خانه' => home_url('/'), 'تماس با ما' => null)
);
?>

<section class="contact">
  <div class="wrap contact__grid">

    <div class="contact__info">
      <?php if ($phones) : ?>
        <div class="contact__card">
          <h3 class="contact__card-h">تلفن‌ها</h3>
          <?php foreach ($phones as $p) : $tel = preg_replace('/\D/', '', $p[1]); ?>
            <a class="contact__row" href="tel:<?php echo esc_attr($tel); ?>">
              <span class="contact__ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h3l2 5-2.5 1.5a11 11 0 005 5L14 12l5 2v3a2 2 0 01-2 2A15 15 0 013 6a2 2 0 013-3z"/></svg></span>
              <span class="contact__meta"><span class="k"><?php echo esc_html($p[0]); ?></span><span class="v lat"><?php echo esc_html($p[1]); ?></span></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (dorian_opt('address')) : ?>
        <div class="contact__card">
          <h3 class="contact__card-h">آدرس</h3>
          <div class="contact__row contact__row--static">
            <span class="contact__ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
            <span class="contact__meta"><span class="v"><?php echo esc_html(dorian_opt('address')); ?></span></span>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($hours) : ?>
        <div class="contact__card">
          <h3 class="contact__card-h">ساعات کاری</h3>
          <ul class="contact__hours">
            <?php foreach ($hours as $h) : ?><li><?php echo esc_html($h); ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="contact__social"><?php dorian_social_row(); ?></div>
    </div>

    <?php if (dorian_opt('contact_form_show') === '1') : ?>
      <div class="contact__formwrap">
        <h3 class="contact__form-h">پیام بفرستید</h3>
        <?php if ($sent === 'ok') : ?>
          <div class="contact__note contact__note--ok">پیام شما با موفقیت ارسال شد. به‌زودی با شما تماس می‌گیریم.</div>
        <?php elseif ($sent === 'err') : ?>
          <div class="contact__note contact__note--err">ارسال پیام ناموفق بود. لطفاً دوباره تلاش کنید یا تماس بگیرید.</div>
        <?php endif; ?>
        <form class="contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="dorian_contact">
          <?php wp_nonce_field('dorian_contact', 'dorian_contact_nonce'); ?>
          <p class="contact-form__hp" aria-hidden="true"><label>این فیلد را خالی بگذارید<input type="text" name="dorian_hp" tabindex="-1" autocomplete="off"></label></p>
          <label class="field"><span>نام و نام خانوادگی *</span><input type="text" name="d_name" required></label>
          <div class="field-row">
            <label class="field"><span>شمارهٔ موبایل</span><input type="tel" name="d_phone" inputmode="numeric" placeholder="09xxxxxxxxx"></label>
            <label class="field"><span>ایمیل</span><input type="email" name="d_email"></label>
          </div>
          <label class="field"><span>پیام شما *</span><textarea name="d_message" rows="5" required></textarea></label>
          <button class="btn btn--gold" type="submit">ارسال پیام</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($map) : ?>
    <div class="contact__map wrap">
      <?php echo $map; // sanitized (iframe/URL) on save ?>
    </div>
  <?php endif; ?>
</section>

<?php get_footer();
