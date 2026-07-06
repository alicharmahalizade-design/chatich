<?php
/**
 * Template Name: درباره ما — Dorian
 *
 * Assign this template to a Page. Content comes from «تنظیمات دوریان → درباره ما»,
 * with the page's own editor content shown below the intro (optional).
 */
if (!defined('ABSPATH')) exit;
get_header();

$stats = array();
foreach (array(1, 2, 3) as $i) {
    $n = dorian_opt("about_stat{$i}_n");
    $l = dorian_opt("about_stat{$i}_l");
    if ($n || $l) $stats[] = array($n, $l);
}
$values = array_filter(array_map('trim', explode("\n", (string) dorian_opt('about_values'))));
$paras  = array_filter(array_map('trim', preg_split('/\n\s*\n/', (string) dorian_opt('about_body'))));
$img    = dorian_opt('about_image');
$reserve = function_exists('dorian_reserve_link') ? dorian_reserve_link() : home_url('/');

dorian_page_hero(
    dorian_opt('about_heading', 'دربارهٔ دوریان'),
    dorian_opt('about_eyebrow', 'The Story'),
    dorian_opt('about_intro'),
    array('خانه' => home_url('/'), 'درباره ما' => null)
);
?>

<section class="about">
  <div class="wrap about__grid">
    <div class="about__copy">
      <?php foreach ($paras as $p) : ?>
        <p><?php echo esc_html($p); ?></p>
      <?php endforeach; ?>

      <?php if ($stats) : ?>
        <div class="about__stats">
          <?php foreach ($stats as $s) : ?>
            <div class="stat"><div class="n lat"><?php echo esc_html($s[0]); ?></div><div class="l"><?php echo esc_html($s[1]); ?></div></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($img) : ?>
      <figure class="about__visual">
        <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(dorian_opt('about_heading', 'دوریان')); ?>" loading="lazy">
        <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
      </figure>
    <?php endif; ?>
  </div>
</section>

<?php if (function_exists('dorian_render_characters')) : ?>
<section class="about-team">
  <div class="wrap">
    <span class="eyebrow c">The Gentlemen</span>
    <h2 class="about-team__h">شخصیت‌های دوریان</h2>
    <div class="ds"><i></i><em></em><i></i></div>
    <p class="about-team__lead">تیمی از بهترین متخصصان مو، پوست و آرامش؛ هر چهره، یک کاراکتر.</p>
    <div class="about-team__grid">
      <?php
      ob_start();
      $has_team = dorian_render_characters();
      $team_html = ob_get_clean();
      if ($has_team) {
          echo $team_html; // .tcard articles, styled by site.css + internal.css
      } else {
          echo '<p class="about-team__empty">به‌زودی اعضای تیم معرفی می‌شوند.</p>';
      }
      ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($values) : ?>
<section class="values">
  <div class="wrap">
    <span class="eyebrow c">Our Values</span>
    <h2 class="values__h">آنچه دوریان را دوریان می‌کند</h2>
    <div class="ds"><i></i><em></em><i></i></div>
    <div class="values__grid">
      <?php foreach ($values as $v) :
        $parts = array_map('trim', explode('|', $v, 2)); ?>
        <article class="value-card">
          <span class="value-card__mark" aria-hidden="true"></span>
          <h3 class="value-card__t"><?php echo esc_html($parts[0]); ?></h3>
          <?php if (!empty($parts[1])) : ?><p class="value-card__d"><?php echo esc_html($parts[1]); ?></p><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="about-cta">
  <div class="wrap about-cta__inner">
    <img class="about-cta__seal" src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/badge.png'); ?>" alt="" aria-hidden="true">
    <h2>آماده‌اید تجربه‌اش کنید؟</h2>
    <p>نوبت خود را رزرو کنید و تفاوت را حس کنید.</p>
    <a class="btn btn--gold" href="<?php echo esc_url($reserve); ?>">رزرو نوبت</a>
  </div>
</section>

<?php get_footer();
