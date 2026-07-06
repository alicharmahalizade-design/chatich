<?php
/**
 * Template Name: کارت هدیه — Dorian
 *
 * Dedicated gift-card landing page. A minimal preview row lets visitors pick a
 * tier; a horizontal slider then shows each card in full. Every slide carries a
 * refined dark band, the full portrait card art, an English name, a Persian
 * value label, description, inline features and a small add-to-cart button.
 * Anchors #card-1/2/5/10 let the home-page cards deep-link straight to a tier.
 * Buy links come from «تنظیمات دوریان → لینک‌ها» (dorian_gift_url_1..4),
 * falling back to the booking page.
 */
if (!defined('ABSPATH')) exit;
get_header();
$img = get_stylesheet_directory_uri() . '/assets/img/';
$reserve = function_exists('dorian_reserve_link') ? dorian_reserve_link() : home_url('/');

$cards = array(
    array('id'=>'card-1',  'slug'=>'gentle','en'=>'Gentle Card', 'label'=>'یک میلیون تومانی',  'opt'=>'dorian_gift_url_1',
          'bg'=>'#33302b','bg2'=>'#201d18',
          'desc'=>'شروعی برازنده؛ یک نوبتِ کاملِ آراستگی برای هدیه‌ای کوچک اما به‌یادماندنی.',
          'feats'=>array('یک جلسه خدمات مو و صورت','مناسبِ تجربهٔ نخستِ دوریان')),
    array('id'=>'card-2',  'slug'=>'duke',  'en'=>'Duke Card',   'label'=>'دو میلیون تومانی',  'opt'=>'dorian_gift_url_2',
          'bg'=>'#243043','bg2'=>'#161e2b',
          'desc'=>'کمی بیشتر از یک نوبت؛ ترکیبی از خدمات مو، ریش و صورت برای روزی که حالِ او را خوب کند.',
          'feats'=>array('پکیجِ مو + ریش + صورت','امکانِ رزروِ اختصاصی')),
    array('id'=>'card-5',  'slug'=>'noble', 'en'=>'Noble Card',  'label'=>'پنج میلیون تومانی', 'opt'=>'dorian_gift_url_3',
          'bg'=>'#3a3327','bg2'=>'#241f17',
          'desc'=>'چند جلسه مراقبت و آرامش؛ هدیه‌ای که بارها یادِ شما را زنده می‌کند.',
          'feats'=>array('چند جلسه مو، پوست و ماساژ','مشاورهٔ تخصصیِ رایگان')),
    array('id'=>'card-10', 'slug'=>'royal', 'en'=>'Royal Card',  'label'=>'ده میلیون تومانی',  'opt'=>'dorian_gift_url_4',
          'bg'=>'#2a231c','bg2'=>'#181310',
          'desc'=>'کامل‌ترین تجربهٔ دوریان؛ در شأنِ عزیزترین‌ها، از سر تا پا آراسته و آرام.',
          'feats'=>array('دسترسی به تمامِ خدماتِ مجموعه','تجربهٔ کاملِ VIP')),
);
?>

<!-- preview row: click a card to jump the slider to it -->
<section class="giftshow">
  <div class="wrap giftshow__head">
    <span class="eyebrow c">Gift Cards</span>
    <h2 class="giftshow__h">کارتِ خود را انتخاب کنید</h2>
    <p class="giftshow__sub">روی هر کارت بزنید تا جزئیات و خریدش را ببینید.</p>
  </div>
  <div class="wrap">
    <div class="giftthumbs" role="tablist" aria-label="کارت‌های هدیه">
      <?php foreach ($cards as $i => $c) : ?>
        <button class="giftthumb<?php echo $i === 0 ? ' is-active' : ''; ?>" type="button" role="tab" data-go="<?php echo $i; ?>"
                aria-controls="<?php echo esc_attr($c['id']); ?>">
          <span class="giftthumb__img"><img src="<?php echo esc_url($img.'gift-'.$c['slug'].'-front.jpg'); ?>" alt="<?php echo esc_attr($c['en']); ?>" loading="lazy"></span>
          <span class="giftthumb__en lat"><?php echo esc_html($c['en']); ?></span>
          <span class="giftthumb__label"><?php echo esc_html($c['label']); ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="giftslider-wrap">
    <button class="giftslider-nav giftslider-nav--prev" type="button" aria-label="کارت قبلی"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
    <div class="giftslider" id="giftSlider" tabindex="0">
      <?php foreach ($cards as $i => $c) :
        $buy = function_exists('dorian_link') ? dorian_link($c['opt'], $reserve) : $reserve; ?>
        <article class="giftslide" id="<?php echo esc_attr($c['id']); ?>" data-i="<?php echo $i; ?>"
                 style="--gbg:<?php echo esc_attr($c['bg']); ?>;--gbg2:<?php echo esc_attr($c['bg2']); ?>">
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
              <h2 class="giftband__name lat"><?php echo esc_html($c['en']); ?></h2>
              <p class="giftband__label">کارت هدیه <?php echo esc_html($c['label']); ?></p>
              <p class="giftband__desc"><?php echo esc_html($c['desc']); ?></p>
              <ul class="giftband__meta">
                <?php foreach ($c['feats'] as $f) : ?><li><?php echo esc_html($f); ?></li><?php endforeach; ?>
                <li>بدونِ تاریخِ انقضا</li>
              </ul>
              <a class="giftbuy" href="<?php echo esc_url($buy); ?>">
                <svg class="giftbuy__cart" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2 3h2.2l1.6 12.2a1.5 1.5 0 0 0 1.5 1.3h9.1a1.5 1.5 0 0 0 1.5-1.2L20.5 7H6"/></svg>
                <span>افزودن به سبد خرید</span>
              </a>
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

<?php get_footer();
