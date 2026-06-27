<?php
if (!defined('ABSPATH')) exit;

class Dorian_Frontend {

    public static function boot() {
        add_shortcode('dorian_booking', array(__CLASS__, 'shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
    }

    /** True when the current singular view hosts the booking form. */
    public static function is_booking_view() {
        if (!is_singular()) return false;
        $id = get_queried_object_id();
        if ($id && (int) $id === (int) get_option('dorian_booking_page_id')) return true;
        $post = get_post($id);
        return $post && has_shortcode($post->post_content, 'dorian_booking');
    }

    public static function body_class($classes) {
        if (self::is_booking_view()) $classes[] = 'dorian-booking-page';
        return $classes;
    }

    public static function register() {
        wp_register_style('dorian-fonts', 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap', array(), null);
        wp_register_style('dorian-booking', DORIAN_URL . 'assets/booking.css', array('dorian-fonts'), DORIAN_VER);
        wp_register_script('dorian-booking', DORIAN_URL . 'assets/booking.js', array(), DORIAN_VER, true);
    }

    public static function enqueue() {
        wp_enqueue_style('dorian-fonts');
        wp_enqueue_style('dorian-booking');
        // absolute @font-face so the Pinar font loads even if a cache plugin combines CSS
        wp_add_inline_style('dorian-booking', "@font-face{font-family:'Pinar';src:url('" . DORIAN_URL . "assets/fonts/Pinar-VF.woff2') format('woff2');font-weight:100 900;font-display:swap}");
        wp_enqueue_script('dorian-booking');
        $s = dorian_settings();
        wp_localize_script('dorian-booking', 'DorianBooking', array_merge(
            Dorian_CPT::form_data(),
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dorian_booking'),
                'settings' => array(
                    'depositRate' => (int) $s['deposit_rate'],
                    'currency'    => $s['currency'],
                ),
            )
        ));
    }

    public static function shortcode($atts) {
        self::enqueue();
        ob_start(); ?>
<div class="bk" id="bk">
  <header class="bk__top">
    <span class="bk__eyebrow">Reservation</span>
    <h1 class="bk__title">رزرو نوبت در <span class="serif">دوریان</span></h1>
    <p class="bk__sub">در چند گام، نوبت اختصاصی خود را رزرو کنید.</p>
    <ol class="bk-steps" id="bkSteps">
      <li class="bk-steps__item is-active" data-stepdot="1"><span class="n">1</span><span class="t">خدمات</span></li>
      <li class="bk-steps__item" data-stepdot="2"><span class="n">2</span><span class="t">متخصص</span></li>
      <li class="bk-steps__item" data-stepdot="3"><span class="n">3</span><span class="t">زمان</span></li>
      <li class="bk-steps__item" data-stepdot="4"><span class="n">4</span><span class="t">تأیید</span></li>
    </ol>
  </header>
  <main class="bk__stage">
    <section class="bk-step is-active" data-step="1">
      <h2 class="bk-step__h">خدمت مورد نظر را انتخاب کنید</h2>
      <p class="bk-step__hint">می‌توانید چند خدمت را هم‌زمان انتخاب کنید.</p>
      <div class="svc-list" id="svcList"></div>
    </section>
    <section class="bk-step" data-step="2">
      <h2 class="bk-step__h">متخصص خود را انتخاب کنید</h2>
      <p class="bk-step__hint">برای هر خدمت، متخصصِ مربوط را انتخاب کنید.</p>
      <div class="pro-list" id="proList"></div>
    </section>
    <section class="bk-step" data-step="3">
      <div class="when">
        <div class="when__cal">
          <div class="cal__head">
            <button class="cal__nav" id="calPrev" type="button" aria-label="ماه قبل">‹</button>
            <span class="cal__title" id="calTitle">—</span>
            <button class="cal__nav" id="calNext" type="button" aria-label="ماه بعد">›</button>
          </div>
          <div class="cal__week"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div>
          <div class="cal__grid" id="calGrid"></div>
        </div>
        <div class="when__side">
          <div class="when__hrow">
            <h3 class="when__h">ساعت نوبت</h3>
            <button type="button" class="firstfree" id="firstFree"><span>⚡</span> اولین وقت خالی</button>
          </div>
          <div class="slots" id="slots"><p class="slots__empty">ابتدا یک روز را انتخاب کنید.</p></div>
          <h3 class="when__h">تکرار نوبت</h3>
          <p class="when__hint">می‌توانید این نوبت را برای خود ثابت کنید.</p>
          <div class="recur" id="recur">
            <button type="button" class="recur__opt is-active" data-weeks="0">یک‌بار</button>
            <button type="button" class="recur__opt" data-weeks="1">هر هفته</button>
            <button type="button" class="recur__opt" data-weeks="2">هر ۲ هفته</button>
            <button type="button" class="recur__opt" data-weeks="3">هر ۳ هفته</button>
            <button type="button" class="recur__opt" data-weeks="4">هر ۴ هفته</button>
          </div>
          <p class="recur__note" id="recurNote"></p>
        </div>
      </div>
    </section>
    <section class="bk-step" data-step="4">
      <h2 class="bk-step__h">تأیید و پرداخت</h2>
      <div class="summary" id="summary"></div>
      <div class="payform">
        <label class="field"><span>نام و نام خانوادگی</span><input type="text" id="custName" placeholder="نام شما"></label>
        <label class="field"><span>شمارهٔ موبایل</span><input type="tel" id="custPhone" placeholder="09xxxxxxxxx" inputmode="numeric"></label>
      </div>
    </section>
  </main>
  <footer class="bk__bar">
    <div class="bk__total"><span class="bk__total-l">مبلغ قابل پرداخت</span><span class="bk__total-v" id="grandTotal">۰</span></div>
    <div class="bk__actions">
      <button class="bk-btn bk-btn--ghost" id="btnBack" type="button" hidden>بازگشت</button>
      <button class="bk-btn bk-btn--gold" id="btnNext" type="button">ادامه</button>
    </div>
  </footer>
  <div class="bk-done" id="bkDone" hidden>
    <div class="bk-done__card">
      <div class="bk-done__seal">✓</div>
      <h3>نوبت شما ثبت شد</h3>
      <p id="doneMsg">پیامک تأیید برای شما و متخصص ارسال خواهد شد.</p>
      <div class="bk-done__cal" id="doneCal"></div>
      <button class="bk-btn bk-btn--gold" id="btnReset" type="button">رزرو جدید</button>
    </div>
  </div>
</div>
        <?php
        return ob_get_clean();
    }
}
Dorian_Frontend::boot();
