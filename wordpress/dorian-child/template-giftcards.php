<?php
/**
 * Template Name: کارت هدیه — Dorian
 *
 * Dedicated gift-card landing page. Presents the four Dorian gift cards
 * (1 / 2 / 5 / 10 million) so visitors can buy one for someone they love.
 * Each card has an anchor (#card-1 / #card-2 / #card-5 / #card-10) so the gift
 * cards on the home page can deep-link straight to it. Buy links come from
 * «تنظیمات دوریان → لینک‌ها» (dorian_gift_url_1..4), with a sensible fallback.
 */
if (!defined('ABSPATH')) exit;
get_header();
$img = get_stylesheet_directory_uri() . '/assets/img/';
$reserve = function_exists('dorian_reserve_link') ? dorian_reserve_link() : home_url('/');

$cards = array(
    array('id' => 'card-1',  'slug' => 'gentle', 'en' => 'Gentle', 'fa' => 'کارت نجیب',   'amount' => '۱٬۰۰۰٬۰۰۰',  'opt' => 'dorian_gift_url_1',
          'desc' => 'شروعی برازنده؛ یک تجربهٔ آراستگیِ حرفه‌ای برای هدیه‌ای کوچک اما به‌یادماندنی.'),
    array('id' => 'card-2',  'slug' => 'duke',   'en' => 'Duke',   'fa' => 'کارت دوک',    'amount' => '۲٬۰۰۰٬۰۰۰',  'opt' => 'dorian_gift_url_2',
          'desc' => 'کمی بیشتر از یک نوبت؛ ترکیبی از خدمات مو و صورت برای روزی که حالِ او را خوب کند.'),
    array('id' => 'card-5',  'slug' => 'noble',  'en' => 'Noble',  'fa' => 'کارت اصیل',   'amount' => '۵٬۰۰۰٬۰۰۰',  'opt' => 'dorian_gift_url_3',
          'desc' => 'چند جلسه مراقبت و آرامش؛ هدیه‌ای که بارها یادِ شما را زنده می‌کند.'),
    array('id' => 'card-10', 'slug' => 'royal',  'en' => 'Royal',  'fa' => 'کارت سلطنتی', 'amount' => '۱۰٬۰۰۰٬۰۰۰', 'opt' => 'dorian_gift_url_4',
          'desc' => 'کامل‌ترین تجربهٔ دوریان؛ در شأنِ عزیزترین‌ها، از سر تا پا آراسته و آرام.'),
);

dorian_page_hero(
    'کارت هدیهٔ دوریان',
    'Gift Cards',
    'آراستگی و آرامش را به کسانی که دوستشان دارید هدیه دهید؛ کارت هدیهٔ دوریان، تجربه‌ای در شأنِ یک نجیب‌زاده.',
    array('خانه' => home_url('/'), 'کارت هدیه' => null)
);
?>

<!-- how it works -->
<section class="gift-how">
  <div class="wrap">
    <div class="gift-how__grid">
      <div class="gift-step"><span class="gift-step__n lat">1</span><h3>انتخاب کنید</h3><p>کارتی متناسب با سلیقه و مناسبت انتخاب کنید.</p></div>
      <div class="gift-step"><span class="gift-step__n lat">2</span><h3>خرید کنید</h3><p>پرداخت آنلاینِ امن؛ در چند لحظه.</p></div>
      <div class="gift-step"><span class="gift-step__n lat">3</span><h3>هدیه دهید</h3><p>کارت را به دوستان و عزیزانتان تقدیم کنید.</p></div>
    </div>
  </div>
</section>

<!-- the four cards -->
<section class="giftpage">
  <div class="wrap">
    <?php foreach ($cards as $i => $c) :
      $buy = function_exists('dorian_link') ? dorian_link($c['opt'], $reserve) : $reserve; ?>
      <article class="giftpage-card<?php echo $i % 2 ? ' is-reverse' : ''; ?>" id="<?php echo esc_attr($c['id']); ?>">
        <div class="giftpage-card__visual">
          <div class="giftcard giftcard--lg">
            <div class="giftcard__inner">
              <div class="giftcard__face giftcard__front"><img src="<?php echo esc_url($img . 'gift-' . $c['slug'] . '-front.jpg'); ?>" alt="<?php echo esc_attr($c['fa']); ?>" loading="lazy"></div>
              <div class="giftcard__face giftcard__back"><img src="<?php echo esc_url($img . 'gift-' . $c['slug'] . '-back.jpg'); ?>" alt="<?php echo esc_attr($c['fa']); ?>" loading="lazy"></div>
            </div>
          </div>
        </div>
        <div class="giftpage-card__body">
          <span class="eyebrow c"><?php echo esc_html($c['en']); ?></span>
          <h2 class="giftpage-card__title"><?php echo esc_html($c['fa']); ?></h2>
          <div class="giftpage-card__amount"><span class="lat"><?php echo esc_html($c['amount']); ?></span> تومان</div>
          <p class="giftpage-card__desc"><?php echo esc_html($c['desc']); ?></p>
          <div class="giftpage-card__cta">
            <a class="btn btn--gold" href="<?php echo esc_url($buy); ?>">هدیه بدهید</a>
            <a class="btn btn--ghost" href="#gift-faq" style="color:var(--blue);border-color:var(--blue)">راهنمای خرید</a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- why + faq -->
<section class="gift-why" id="gift-faq">
  <div class="wrap gift-why__inner">
    <img class="gift-why__seal" src="<?php echo esc_url($img . 'badge.png'); ?>" alt="" aria-hidden="true">
    <span class="eyebrow c">Why Dorian</span>
    <h2>هدیه‌ای که فراموش نمی‌شود</h2>
    <p>کارت هدیهٔ دوریان تاریخِ انقضا ندارد و برای همهٔ خدماتِ مجموعه قابل استفاده است. کافی است کارت را تهیه کنید و کدِ آن را به عزیزتان بدهید؛ باقیِ کار با ماست.</p>
    <div class="gift-why__cta"><a class="btn btn--blue" href="<?php echo esc_url($reserve); ?>">رزرو نوبت</a></div>
  </div>
</section>

<?php get_footer();
