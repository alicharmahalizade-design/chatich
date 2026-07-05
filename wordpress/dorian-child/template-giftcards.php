<?php
/**
 * Template Name: کارت هدیه — Dorian
 *
 * Dedicated gift-card landing page. Each of the four tiers (1/2/5/10M) gets a
 * full-width band whose background matches the card's own colour, with the full
 * portrait card, price, features and a buy CTA. Anchors #card-1/2/5/10 let the
 * home-page cards deep-link straight to their tier. Buy links come from
 * «تنظیمات دوریان → لینک‌ها» (dorian_gift_url_1..4), falling back to the booking page.
 */
if (!defined('ABSPATH')) exit;
get_header();
$img = get_stylesheet_directory_uri() . '/assets/img/';
$reserve = function_exists('dorian_reserve_link') ? dorian_reserve_link() : home_url('/');

$cards = array(
    array('id'=>'card-1',  'slug'=>'gentle','en'=>'Gentle','fa'=>'کارت نجیب',   'amount'=>'۱٬۰۰۰٬۰۰۰',  'opt'=>'dorian_gift_url_1',
          'bg'=>'#cbb88f','bg2'=>'#9a7f52','tone'=>'dark',
          'desc'=>'شروعی برازنده؛ یک نوبتِ کاملِ آراستگی برای هدیه‌ای کوچک اما به‌یادماندنی.',
          'feats'=>array('یک جلسه خدمات مو و صورت','مناسبِ تجربهٔ نخستِ دوریان')),
    array('id'=>'card-2',  'slug'=>'duke',  'en'=>'Duke',  'fa'=>'کارت دوک',    'amount'=>'۲٬۰۰۰٬۰۰۰',  'opt'=>'dorian_gift_url_2',
          'bg'=>'#2c3a52','bg2'=>'#18222f','tone'=>'light',
          'desc'=>'کمی بیشتر از یک نوبت؛ ترکیبی از خدمات مو، ریش و صورت برای روزی که حالِ او را خوب کند.',
          'feats'=>array('پکیجِ مو + ریش + صورت','امکانِ رزروِ اختصاصی')),
    array('id'=>'card-5',  'slug'=>'noble', 'en'=>'Noble', 'fa'=>'کارت اصیل',   'amount'=>'۵٬۰۰۰٬۰۰۰',  'opt'=>'dorian_gift_url_3',
          'bg'=>'#7d6b51','bg2'=>'#574a38','tone'=>'light',
          'desc'=>'چند جلسه مراقبت و آرامش؛ هدیه‌ای که بارها یادِ شما را زنده می‌کند.',
          'feats'=>array('چند جلسه مو، پوست و ماساژ','مشاورهٔ تخصصیِ رایگان')),
    array('id'=>'card-10', 'slug'=>'royal', 'en'=>'Royal', 'fa'=>'کارت سلطنتی', 'amount'=>'۱۰٬۰۰۰٬۰۰۰', 'opt'=>'dorian_gift_url_4',
          'bg'=>'#40352a','bg2'=>'#29211a','tone'=>'light',
          'desc'=>'کامل‌ترین تجربهٔ دوریان؛ در شأنِ عزیزترین‌ها، از سر تا پا آراسته و آرام.',
          'feats'=>array('دسترسی به تمامِ خدماتِ مجموعه','تجربهٔ کاملِ VIP')),
);
?>

<section class="pagehero giftlp-hero">
  <div class="wrap pagehero__inner">
    <nav class="crumbs"><a href="<?php echo esc_url(home_url('/')); ?>">خانه</a><span class="sep">/</span><span class="here">کارت هدیه</span></nav>
    <span class="eyebrow c">Gift Cards</span>
    <h1 class="pagehero__title">کارت هدیهٔ <span class="serif">دوریان</span></h1>
    <div class="ds"><i></i><em></em><i></i></div>
    <p class="pagehero__sub">آراستگی و آرامش را به کسانی که دوستشان دارید هدیه دهید؛ تجربه‌ای در شأنِ یک نجیب‌زاده، در قابی که فراموش نمی‌شود.</p>
  </div>
</section>

<section class="gift-how">
  <div class="wrap">
    <div class="gift-how__grid">
      <div class="gift-step"><span class="gift-step__n lat">۱</span><h3>انتخاب کنید</h3><p>کارتی متناسب با سلیقه و مناسبت انتخاب کنید.</p></div>
      <div class="gift-step"><span class="gift-step__n lat">۲</span><h3>خرید کنید</h3><p>پرداختِ آنلاینِ امن، تنها در چند لحظه.</p></div>
      <div class="gift-step"><span class="gift-step__n lat">۳</span><h3>هدیه دهید</h3><p>کارت را به دوستان و عزیزانتان تقدیم کنید.</p></div>
    </div>
  </div>
</section>

<?php foreach ($cards as $i => $c) :
  $buy = function_exists('dorian_link') ? dorian_link($c['opt'], $reserve) : $reserve; ?>
  <section class="giftband" id="<?php echo esc_attr($c['id']); ?>" data-tone="<?php echo esc_attr($c['tone']); ?>"
           style="--gbg:<?php echo esc_attr($c['bg']); ?>;--gbg2:<?php echo esc_attr($c['bg2']); ?>">
    <span class="giftband__wm lat" aria-hidden="true"><?php echo esc_html($c['en']); ?></span>
    <div class="wrap giftband__inner<?php echo $i % 2 ? ' is-reverse' : ''; ?>">
      <div class="giftband__visual">
        <div class="giftcard giftcard--lg">
          <div class="giftcard__inner">
            <div class="giftcard__face giftcard__front"><img src="<?php echo esc_url($img.'gift-'.$c['slug'].'-front.jpg'); ?>" alt="<?php echo esc_attr($c['fa']); ?>" loading="lazy"></div>
            <div class="giftcard__face giftcard__back"><img src="<?php echo esc_url($img.'gift-'.$c['slug'].'-back.jpg'); ?>" alt="<?php echo esc_attr($c['fa']); ?>" loading="lazy"></div>
          </div>
        </div>
      </div>
      <div class="giftband__body">
        <span class="giftband__eyebrow lat"><?php echo esc_html($c['en']); ?></span>
        <h2 class="giftband__title"><?php echo esc_html($c['fa']); ?></h2>
        <div class="giftband__amount"><span class="lat"><?php echo esc_html($c['amount']); ?></span><small>تومان</small></div>
        <p class="giftband__desc"><?php echo esc_html($c['desc']); ?></p>
        <ul class="giftband__feats">
          <?php foreach ($c['feats'] as $f) : ?>
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg><?php echo esc_html($f); ?></li>
          <?php endforeach; ?>
          <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg>بدونِ تاریخِ انقضا</li>
        </ul>
        <div class="giftband__cta">
          <a class="btn btn--gold" href="<?php echo esc_url($buy); ?>">هدیه بدهید</a>
        </div>
      </div>
    </div>
  </section>
<?php endforeach; ?>

<section class="gift-why">
  <div class="wrap gift-why__inner">
    <img class="gift-why__seal" src="<?php echo esc_url($img.'badge.png'); ?>" alt="" aria-hidden="true">
    <span class="eyebrow c">Why Dorian</span>
    <h2>هدیه‌ای که فراموش نمی‌شود</h2>
    <p>کارت هدیهٔ دوریان تاریخِ انقضا ندارد و برای همهٔ خدماتِ مجموعه قابل استفاده است. کافی است کارت را تهیه کنید و کدِ آن را به عزیزتان بدهید؛ باقیِ کار با ماست.</p>
    <div class="gift-why__cta"><a class="btn btn--blue" href="<?php echo esc_url($reserve); ?>">رزرو نوبت</a></div>
  </div>
</section>

<?php get_footer();
