<?php
/**
 * Dorian shared footer (chrome for all internal pages).
 */
if (!defined('ABSPATH')) exit;
$logo = dorian_logo_url();
$reserve = function_exists('dorian_reserve_link') ? dorian_reserve_link() : home_url('/');
// quick links: each line "label|url" (empty url → reserve link)
$links = array_filter(array_map('trim', explode("\n", (string) dorian_opt('footer_links'))));
$phones = array();
foreach (array(1, 2, 3) as $i) {
    $num = dorian_opt('phone' . $i);
    if ($num) $phones[] = array(dorian_opt('phone' . $i . '_label'), $num);
}
?>
</main><!-- /.dorian-main -->

<footer class="site-foot" role="contentinfo">
  <div class="wrap site-foot__grid">
    <div class="site-foot__col site-foot__brand">
      <img class="foot__logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
      <?php if (dorian_opt('footer_about')) : ?>
        <p class="site-foot__about"><?php echo esc_html(dorian_opt('footer_about')); ?></p>
      <?php endif; ?>
      <?php dorian_social_row(); ?>
    </div>

    <?php if ($links) : ?>
    <nav class="site-foot__col site-foot__links" aria-label="لینک‌های سریع">
      <h3 class="site-foot__h">دسترسی سریع</h3>
      <ul>
        <?php foreach ($links as $line) :
          $parts = array_map('trim', explode('|', $line, 2));
          $label = $parts[0];
          $url   = !empty($parts[1]) ? $parts[1] : $reserve;
          if ($url && $url[0] === '/') $url = home_url($url);
          if ($label === '') continue; ?>
          <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>

    <div class="site-foot__col site-foot__contact">
      <h3 class="site-foot__h">تماس</h3>
      <?php foreach ($phones as $p) :
        $tel = preg_replace('/\D/', '', $p[1]); ?>
        <a class="site-foot__phone" href="tel:<?php echo esc_attr($tel); ?>"><span class="k"><?php echo esc_html($p[0]); ?></span><span class="v lat"><?php echo esc_html($p[1]); ?></span></a>
      <?php endforeach; ?>
      <?php if (dorian_opt('address')) : ?>
        <p class="site-foot__addr"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><?php echo esc_html(dorian_opt('address')); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="site-foot__bar">
    <div class="wrap">
      <p class="foot__copy"><span class="lat"><?php echo esc_html(dorian_opt('footer_copy')); ?></span></p>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
