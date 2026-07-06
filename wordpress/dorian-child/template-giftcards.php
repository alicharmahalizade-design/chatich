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
    array('id'=>'card-1',  'slug'=>'gentle','en'=>'Gentle Card','tr'=>'جنتل کارت',  'amount'=>'1,000,000',  'opt'=>'dorian_gift_url_1',
          'bg'=>'#cbb88f','bg2'=>'#9a7f52','tone'=>'dark',
          'desc'=>'شروعی برازنده؛ یک نوبتِ کاملِ آراستگی برای هدیه‌ای کوچک اما به‌یادماندنی.',
          'feats'=>array('یک جلسه خدمات مو و صورت','مناسبِ تجربهٔ نخستِ دوریان')),
    array('id'=>'card-2',  'slug'=>'duke',  'en'=>'Duke Card', 'tr'=>'دوک کارت',   'amount'=>'2,000,000',  'opt'=>'dorian_gift_url_2',
          'bg'=>'#2c3a52','bg2'=>'#18222f','tone'=>'light',
          'desc'=>'کمی بیشتر از یک نوبت؛ ترکیبی از خدمات مو، ریش و صورت برای روزی که حالِ او را خوب کند.',
          'feats'=>array('پکیجِ مو + ریش + صورت','امکانِ رزروِ اختصاصی')),
    array('id'=>'card-5',  'slug'=>'noble', 'en'=>'Noble Card','tr'=>'نوبل کارت',  'amount'=>'5,000,000',  'opt'=>'dorian_gift_url_3',
          'bg'=>'#7d6b51','bg2'=>'#574a38','tone'=>'light',
          'desc'=>'چند جلسه مراقبت و آرامش؛ هدیه‌ای که بارها یادِ شما را زنده می‌کند.',
          'feats'=>array('چند جلسه مو، پوست و ماساژ','مشاورهٔ تخصصیِ رایگان')),
    array('id'=>'card-10', 'slug'=>'royal', 'en'=>'Royal Card','tr'=>'رویال کارت', 'amount'=>'10,000,000', 'opt'=>'dorian_gift_url_4',
          'bg'=>'#40352a','bg2'=>'#29211a','tone'=>'light',
          'desc'=>'کامل‌ترین تجربهٔ دوریان؛ در شأنِ عزیزترین‌ها، از سر تا پا آراسته و آرام.',
          'feats'=>array('دسترسی به تمامِ خدماتِ مجموعه','تجربهٔ کاملِ VIP')),
);
?>

<!-- preview row: click a card to jump the slider to it -->
<section class="giftshow">
  <div class="wrap giftshow__head">
    <span class="eyebrow c">Choose</span>
    <h2 class="giftshow__h">کارتِ خود را انتخاب کنید</h2>
    <p class="giftshow__sub">روی هر کارت بزنید تا جزئیات و خریدش را ببینید.</p>
  </div>
  <div class="wrap">
    <div class="giftthumbs" role="tablist" aria-label="کارت‌های هدیه">
      <?php foreach ($cards as $i => $c) : ?>
        <button class="giftthumb<?php echo $i === 0 ? ' is-active' : ''; ?>" type="button" role="tab" data-go="<?php echo $i; ?>"
                aria-controls="<?php echo esc_attr($c['id']); ?>" style="--gbg:<?php echo esc_attr($c['bg']); ?>">
          <span class="giftthumb__img"><img src="<?php echo esc_url($img.'gift-'.$c['slug'].'-front.jpg'); ?>" alt="<?php echo esc_attr($c['en']); ?>" loading="lazy"></span>
          <span class="giftthumb__en lat"><?php echo esc_html($c['en']); ?></span>
          <span class="giftthumb__tr"><?php echo esc_html($c['tr']); ?></span>
          <span class="giftthumb__amt lat"><?php echo esc_html($c['amount']); ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="giftslider-wrap">
    <button class="giftslider-nav giftslider-nav--prev" type="button" aria-label="کارت قبلی"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
    <div class="giftslider" id="giftSlider" tabindex="0">
      <?php foreach ($cards as $i => $c) :
        $buy = function_exists('dorian_link') ? dorian_link($c['opt'], $reserve) : $reserve; ?>
        <article class="giftslide" id="<?php echo esc_attr($c['id']); ?>" data-i="<?php echo $i; ?>" data-tone="<?php echo esc_attr($c['tone']); ?>"
                 style="--gbg:<?php echo esc_attr($c['bg']); ?>;--gbg2:<?php echo esc_attr($c['bg2']); ?>">
          <span class="giftband__wm lat" aria-hidden="true"><?php echo esc_html(strtok($c['en'], ' ')); ?></span>
          <div class="giftslide__inner">
            <div class="giftband__visual">
              <div class="giftcard giftcard--lg">
                <div class="giftcard__inner">
                  <div class="giftcard__face giftcard__front"><img src="<?php echo esc_url($img.'gift-'.$c['slug'].'-front.jpg'); ?>" alt="<?php echo esc_attr($c['en']); ?>" loading="lazy"></div>
                  <div class="giftcard__face giftcard__back"><img src="<?php echo esc_url($img.'gift-'.$c['slug'].'-back.jpg'); ?>" alt="<?php echo esc_attr($c['en']); ?>" loading="lazy"></div>
                </div>
              </div>
            </div>
            <div class="giftband__body">
              <span class="giftband__eyebrow lat">Dorian Gift</span>
              <h2 class="giftband__title">
                <span class="giftband__en lat"><?php echo esc_html($c['en']); ?></span>
                <span class="giftband__tr"><?php echo esc_html($c['tr']); ?></span>
              </h2>
              <div class="giftband__amount"><span class="lat"><?php echo esc_html($c['amount']); ?></span><small>تومان</small></div>
              <p class="giftband__desc"><?php echo esc_html($c['desc']); ?></p>
              <ul class="giftband__feats">
                <?php foreach ($c['feats'] as $f) : ?>
                  <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg><?php echo esc_html($f); ?></li>
                <?php endforeach; ?>
                <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg>بدونِ تاریخِ انقضا</li>
              </ul>
              <div class="giftband__cta">
                <a class="btn btn--gold giftband__buy" href="<?php echo esc_url($buy); ?>">
                  <svg class="giftband__cart" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.2l1.6 12.2a1.5 1.5 0 0 0 1.5 1.3h9.1a1.5 1.5 0 0 0 1.5-1.2L20.5 7H6"/></svg>
                  <span>افزودن به سبد خرید</span>
                </a>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <button class="giftslider-nav giftslider-nav--next" type="button" aria-label="کارت بعدی"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>
  </div>

  <div class="giftdots" aria-hidden="true">
    <?php foreach ($cards as $i => $c) : ?><span class="giftdot<?php echo $i === 0 ? ' is-active' : ''; ?>"></span><?php endforeach; ?>
  </div>
</section>
<?php // (bands are now horizontal slides above) ?>

<?php get_footer();
