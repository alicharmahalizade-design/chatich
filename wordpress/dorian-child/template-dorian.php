<?php
/**
 * Template Name: Dorian Landing
 *
 * Full-screen cinematic landing for Dorian Gentlemen's Studio.
 * Assign this template to a Page, then set that Page as the static front page.
 */
$tpl_uri = get_stylesheet_directory_uri();
$reserve_url = function_exists('dorian_reserve_link') ? dorian_reserve_link() : ($tpl_uri . '/booking/index.html');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
<meta charset="UTF-8">
<script>document.documentElement.classList.add('js')</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="دوریان؛ مجموعه‌ی لاکچری دامادسرا و آرایشگاه تخصصی آقایان در اهواز. سه طبقه خدمات حرفه‌ای پوست، مو، ماساژ و مراقبت شخصی.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<!-- PRELOADER (elevator doors + counter) -->
<div id="pre">
  <div class="door door--t"></div>
  <div class="door door--b"></div>
  <div class="pcore">
    <img class="seal" src="<?php echo $tpl_uri; ?>/assets/img/badge.png" alt="Dorian">
    <div class="pcount"><span id="pcount">0</span><small>%</small></div>
    <div class="ptag">Dorian</div>
    <div class="pbar"><i id="pbar"></i></div>
  </div>
</div>

<!-- ATMOSPHERE -->
<div id="tone"></div>
<div id="fx"></div>
<div class="dust" id="dust"></div>
<div class="cursor__ring" id="curRing"></div>
<div class="cursor__dot" id="curDot"></div>

<!-- HEADER -->
<header class="head" id="head">
  <div class="head__start">
    <a href="#hero" data-go="0" aria-label="دوریان"><img class="head__logo" src="<?php echo $tpl_uri; ?>/assets/img/logo.png" alt="Dorian Gentlemen's Studio"></a>
    <nav class="nav" id="nav">
      <a href="#story" data-go="1">مجموعه</a>
      <a href="#floors" data-go="2">طبقات</a>
      <a href="#services" data-go="3">خدمات</a>
      <a href="#team" data-go="4">تیم</a>
      <a href="#gift" data-go="5">گیفت‌کارت</a>
    </nav>
  </div>
  <div class="head__end">
    <a class="btn btn--gold head__cta" href="<?php echo esc_url($reserve_url); ?>">رزرو نوبت</a>
    <button class="burger" id="burger" aria-label="منو"><span></span><span></span><span></span></button>
  </div>
</header>

<!-- DOT NAV -->
<div class="dots" id="dots"></div>

<!-- MOVING ELEVATOR (interactive call panel) -->
<div class="lift" id="lift" data-active="2">
  <ul class="lift__floors">
    <li><button class="lift__btn" data-go-floor="0" aria-label="طبقه دوم"><span class="lat">2</span></button></li>
    <li><button class="lift__btn" data-go-floor="1" aria-label="طبقه اول"><span class="lat">1</span></button></li>
    <li><button class="lift__btn" data-go-floor="2" aria-label="زیرزمین"><span class="lat">B</span></button></li>
  </ul>
  <div class="lift__track"><span class="lift__tick"></span><span class="lift__car"></span></div>
</div>

<main class="snap" id="snap">

  <!-- HERO -->
  <section class="screen hero" id="hero" data-theme="light" data-name="خانه">
    <div class="embers" id="embers"></div>
    <div class="hero__wm lat">DORIAN</div>
    <div class="hero__frame"><span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span></div>
    <div class="hero__inner wrap">
      <p class="eyebrow c hero__eyebrow anim" style="--i:0">Gentlemen's Studio · Ahvaz</p>
      <h1 class="hero__tag hero__tagline" style="--i:1"><span class="hero__tagline-ink">Let us enchant you, in a good way</span><i class="hero__pen" aria-hidden="true"></i></h1>
      <div class="hero__portrait anim scale" style="--i:2"><img src="<?php echo $tpl_uri; ?>/assets/img/portrait.png" alt="پرتره‌ی دوریان"></div>
      <div class="hero__base anim" style="--i:3"><i></i><em></em><i></i></div>
      <img class="hero__logo anim" style="--i:3" src="<?php echo $tpl_uri; ?>/assets/img/logo.png" alt="Dorian">
      <p class="hero__sub anim" style="--i:4">دامادسرا و آرایشگاه تخصصی آقایان</p>
      <div class="hero__ctas anim" style="--i:5">
        <a class="btn btn--blue" href="<?php echo esc_url($reserve_url); ?>">رزرو نوبت</a>
        <a class="btn btn--ghost" href="#floors" data-go="2" style="color:var(--blue);border-color:var(--blue)">گشتی در مجموعه</a>
      </div>
    </div>
    <div class="cue"><span>SCROLL</span><i></i></div>
  </section>

  <!-- STORY -->
  <section class="screen story" id="story" data-theme="light" data-name="مجموعه">
    <div class="story__grid wrap">
      <div class="story__copy">
        <span class="eyebrow anim" style="--i:0">The Story</span>
        <h2 class="anim" style="--i:1">دوریان؛ هنرِ <span class="serif">ماندگاری</span></h2>
        <p class="story__q anim" style="--i:2">«بگذارید مسحورتان کنیم، آن‌هم به بهترین شکل.»</p>
        <p class="anim" style="--i:3">نام «دوریان» از قوم باستانی یونان برگرفته شده؛ مردمانی که به نظم، انضباط و سبک زندگی متمایزشان شناخته می‌شدند و یادآور رمان «دوریان گری»؛ روایتِ جوانی که زمان بر چهره‌اش اثری نمی‌گذاشت.</p>
        <p class="anim" style="--i:4">مجموعه‌ی تخصصی دوریان در بهمن‌ماه ۱۴۰۴ در اهواز افتتاح شد؛ فضایی آرام و دور از هیاهوی روزمره، در بیش از ۳۰۰ متر مربع و سه طبقه‌ی مجزا که هرکدام با رویکردی هدفمند طراحی شده‌اند.</p>
        <div class="story__stats">
          <div class="stat anim" style="--i:5"><div class="n lat">1404</div><div class="l">سال تأسیس</div></div>
          <div class="stat anim" style="--i:6"><div class="n lat">300m²</div><div class="l">فضای مجموعه</div></div>
          <div class="stat anim" style="--i:7"><div class="n lat">3</div><div class="l">طبقه‌ی تخصصی</div></div>
        </div>
      </div>
      <div class="story__visual anim scale" style="--i:3">
        <div class="mv-frame">
          <video id="servicesVideo" class="mv-video" muted playsinline preload="auto" poster="<?php echo $tpl_uri; ?>/assets/video/services-360-poster.jpg" aria-label="ویدیوی سه‌بعدی ساختمان دوریان">
            <source src="<?php echo $tpl_uri; ?>/assets/video/services-360.mp4" type="video/mp4">
          </video>
        </div>
      </div>
    </div>
  </section>

  <!-- ELEVATOR · our shaft with light-on flicker + floor ding (v1 content) -->
  <section class="screen elevator" id="floors" data-theme="dark" data-name="طبقات">
    <div class="elevator__pin" id="elevatorPin">
      <div class="elevator__counter lat" id="floorCounter" aria-hidden="true">00</div>

      <!-- shaft: building cut with three lit floors + riding cabin -->
      <div class="elevator__shaft">
        <div class="elx-rail"><span class="elx-cabin" id="cabin"></span></div>
        <div class="elx-building">
          <!-- top → bottom : Second / First / Underground -->
          <div class="floor-cell" data-cell="2" data-tone="second">
            <img class="floor-cell__bg" src="<?php echo $tpl_uri; ?>/assets/img/floor-second.jpg" alt="">
            <span class="floor-cell__no lat">02</span>
            <span class="floor-cell__name lat">Second Floor</span>
          </div>
          <div class="floor-cell" data-cell="1" data-tone="first">
            <img class="floor-cell__bg" src="<?php echo $tpl_uri; ?>/assets/img/floor-first.jpg" alt="">
            <span class="floor-cell__no lat">01</span>
            <span class="floor-cell__name lat">First Floor</span>
          </div>
          <div class="floor-cell" data-cell="0" data-tone="under">
            <img class="floor-cell__bg" src="<?php echo $tpl_uri; ?>/assets/img/floor-underground.jpg" alt="">
            <span class="floor-cell__no lat">B</span>
            <span class="floor-cell__name lat">Underground</span>
          </div>
        </div>
      </div>

      <!-- content panels (v1 copy + chips) -->
      <div class="elevator__content" id="floorPanels">

        <!-- UNDERGROUND -->
        <div class="floor-panel" data-panel="0">
          <span class="floor-panel__no lat">B · Underground</span>
          <h3 class="floor-panel__title">فضای اجتماعی و چندمنظوره</h3>
          <p class="floor-panel__desc">زیرین‌ترین بخش مجموعه و ورودی آزاد برای عموم؛ فضایی برای گفت‌وگو و آرامش. شامل کافه و سالن نشیمن، اتاق گریم داماد، فضای خصوصی اصلاح و بخش بازی و سرگرمی.</p>
          <div class="chips">
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M4 8h13v5a4 4 0 01-4 4H8a4 4 0 01-4-4zM17 9h2a2 2 0 010 4h-2"/></svg>کافه</span>
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M4 18v-6a4 4 0 014-4h8a4 4 0 014 4v6M4 18h16"/></svg>سالن نشیمن</span>
            <span class="chip"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M9 10h.01M15 10h.01M9 15c1.5 1 4.5 1 6 0"/></svg>گریم داماد</span>
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M6 12h4M8 10v4M15 11h.01M17 13h.01M5 8h14l1 8H4z"/></svg>بازی و سرگرمی</span>
          </div>
        </div>

        <!-- FIRST -->
        <div class="floor-panel" data-panel="1">
          <span class="floor-panel__no lat">01 · First Floor</span>
          <h3 class="floor-panel__title">قلب اصلی مجموعه</h3>
          <p class="floor-panel__desc">مرکز خدمات آرایشگاهی؛ جایی برای ارائه‌ی خدماتی دقیق، سریع و حرفه‌ای در محیطی مدرن. شامل پذیرش، فضای تخصصی اصلاح مو و صورت و سرشورهای تخصصی.</p>
          <div class="chips">
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M5 19l7-7 7 7M8 8l8 8"/></svg>اصلاح مو</span>
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h7"/></svg>اصلاح صورت</span>
            <span class="chip"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21c1-5 13-5 14 0"/></svg>پذیرش</span>
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M7 4v8a5 5 0 0010 0V4M5 21h14"/></svg>سرشور تخصصی</span>
          </div>
        </div>

        <!-- SECOND -->
        <div class="floor-panel" data-panel="2">
          <span class="floor-panel__no lat">02 · Second Floor</span>
          <h3 class="floor-panel__title">حریم خصوصی و خدمات لوکس</h3>
          <p class="floor-panel__desc">بالاترین طبقه، با تمرکز بر آرامش ذهنی و خلوتِ شخصی؛ محیطی دنج برای مراقبت و تجدید انرژی. شامل اتاق‌های ماساژ مجزا، پاکسازی و فیشیال تخصصی، مانیکور و پدیکور ویژه‌ی آقایان و حمام اختصاصی.</p>
          <div class="chips">
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M4 14c4-6 12-6 16 0"/><circle cx="12" cy="9" r="2"/></svg>ماساژ تخصصی</span>
            <span class="chip"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M9 12h6"/></svg>فیشیال و پاکسازی</span>
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M6 18l12-12M8 6h10v10"/></svg>مانیکور و پدیکور</span>
            <span class="chip"><svg viewBox="0 0 24 24"><path d="M4 12h16M6 12V8a6 6 0 0112 0v4"/></svg>حمام اختصاصی</span>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- SERVICES -->
  <section class="screen services" id="services" data-theme="dark" data-name="خدمات">
    <div class="services__inner wrap">
      <span class="eyebrow c anim" style="--i:0">Tariffs</span>
      <h2 class="anim" style="--i:1">تعرفه‌ی خدمات</h2>
      <div class="ds anim" style="--i:1"><i></i><em></em><i></i></div>
      <div class="tabs anim" style="--i:2">
        <button class="tab active" data-tab="hair">مو <span class="lat">Hair</span></button>
        <button class="tab" data-tab="facial">فیشیال <span class="lat">Facial</span></button>
        <button class="tab" data-tab="massage">ماساژ <span class="lat">Massage</span></button>
        <button class="tab" data-tab="mani">مانیکور و پدیکور <span class="lat">Mani/Pedi</span></button>
      </div>
      <div class="anim" style="--i:3">
        <div class="panel active" data-panel="hair">
          <div class="tcard-wrap">
            <div class="matrix">
              <table>
                <thead><tr><th>خدمت / آرایشگر</th><th>آرمان</th><th>اشکان</th><th>حسین</th><th>احمد</th></tr></thead>
                <tbody>
                  <tr><td class="svc">اصلاح موی سر</td><td><span class="lat">650K</span></td><td><span class="lat">550K</span></td><td><span class="lat">550K</span></td><td><span class="lat">550K</span></td></tr>
                  <tr><td class="svc">سشوار و حالت</td><td><span class="lat">300K</span></td><td><span class="lat">300K</span></td><td><span class="lat">300K</span></td><td><span class="lat">300K</span></td></tr>
                  <tr><td class="svc">اصلاح ریش</td><td><span class="lat">400K</span></td><td><span class="lat">400K</span></td><td><span class="lat">400K</span></td><td><span class="lat">400K</span></td></tr>
                  <tr><td class="svc">پکیج مو + ریش</td><td><span class="lat">950K</span></td><td><span class="lat">800K</span></td><td><span class="lat">800K</span></td><td><span class="lat">800K</span></td></tr>
                  <tr><td class="svc">پکیج ریش + سشوار</td><td><span class="lat">500K</span></td><td><span class="lat">500K</span></td><td><span class="lat">500K</span></td><td><span class="lat">500K</span></td></tr>
                </tbody>
              </table>
            </div>
            <p class="svc-note">نرخ کوتاهی آرمان بر اساس حجم یا مدل مو تا ۷۵۰٬۰۰۰ تومان متغیر است · قیمت‌ها به تومان</p>
            <div class="hair-extra">
              <span class="ttl">خدمات تکمیلی مو</span>
              <div class="menu-cols two">
                <div class="m-item"><span class="name">ویتامین مو (حمام زیت)</span><span class="lead"></span><span class="cost">۹۵۰٬۰۰۰</span></div>
                <div class="m-item"><span class="name">رنگ مو</span><span class="lead"></span><span class="cost">۵۵۰٬۰۰۰</span></div>
                <div class="m-item"><span class="name">رنگ ریش</span><span class="lead"></span><span class="cost">۳۰۰٬۰۰۰</span></div>
                <div class="m-item"><span class="name">کراتین و احیا</span><span class="lead"></span><span class="cost">استعلام</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="panel" data-panel="facial">
          <div class="tcard-wrap">
            <div class="menu-cols two">
              <div class="m-item"><span class="name">پاکسازی سرد با بخار</span><span class="lead"></span><span class="cost">۹۵۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">پاکسازی لایت</span><span class="lead"></span><span class="cost">۲٬۱۰۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">پاکسازی <span class="lat">VIP</span></span><span class="lead"></span><span class="cost">۲٬۸۰۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">شمع صورت</span><span class="lead"></span><span class="cost">۳۰۰٬۰۰۰</span></div>
            </div>
            <p class="svc-note">قیمت‌ها به تومان</p>
          </div>
        </div>
        <div class="panel" data-panel="massage">
          <div class="tcard-wrap">
            <div class="menu-cols two">
              <div class="m-item"><span class="name">ماساژ ریلکسی</span><span class="lead"></span><span class="cost">۱٬۳۵۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">ماساژ و بادکش</span><span class="lead"></span><span class="cost">۱٬۷۵۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">سنگ داغ <small>یک‌ساعته</small></span><span class="lead"></span><span class="cost">۱٬۷۵۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">ماساژ سر و صورت</span><span class="lead"></span><span class="cost">۵۸۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">ماساژ موضعی <small>۲۰ دقیقه</small></span><span class="lead"></span><span class="cost">۵۸۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">ماساژ موضعی <small>۳۰ دقیقه</small></span><span class="lead"></span><span class="cost">۸۷۰٬۰۰۰</span></div>
            </div>
            <p class="svc-note">قیمت‌ها به تومان</p>
          </div>
        </div>
        <div class="panel" data-panel="mani">
          <div class="tcard-wrap">
            <div class="menu-cols two">
              <div class="m-item"><span class="name">مانیکور</span><span class="lead"></span><span class="cost">۸۰۰٬۰۰۰</span></div>
              <div class="m-item"><span class="name">پدیکور</span><span class="lead"></span><span class="cost">۱٬۰۰۰٬۰۰۰ <small>تا ۲٬۰۰۰٬۰۰۰</small></span></div>
              <div class="m-item"><span class="name">سم‌زدایی</span><span class="lead"></span><span class="cost">۸۰۰٬۰۰۰</span></div>
            </div>
            <p class="svc-note">قیمت‌ها به تومان</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- TEAM -->
  <section class="screen team" id="team" data-theme="dark" data-name="تیم">
    <div class="team__inner wrap">
      <div class="team__head">
        <div>
          <span class="eyebrow anim" style="--i:0">The Gentlemen</span>
          <h2 class="anim" style="--i:1">شخصیت‌های دوریان</h2>
          <p class="lead-fa anim" style="--i:2">تیمی از بهترین متخصصان مو، پوست و آرامش؛ هر چهره، یک کاراکتر.</p>
        </div>
        <div class="team__nav anim" style="--i:2">
          <button id="teamPrev" aria-label="قبلی"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>
          <button id="teamNext" aria-label="بعدی"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></button>
        </div>
      </div>
      <div class="rail anim" style="--i:3" id="teamRail">
        <article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Hair Master</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-01.jpg" alt="آرمان کاراگاه" loading="lazy"></div><div class="tcard__name"><div class="fa">آرمان کاراگاه</div><div class="en lat">Arman Karagah</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>کوتاهی مو و ریش</li><li>سشوار و استایل مو</li><li>اکستنشن طبیعی مو</li><li>پروتز مو</li><li>کراتین مو</li><li>گریم تخصصی داماد</li></ul></div></div></article>
<article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Hair Artist</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-02.jpg" alt="احسان حسن‌یاری" loading="lazy"></div><div class="tcard__name"><div class="fa">احسان حسن‌یاری</div><div class="en lat">Ehsan Hasanyari</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>کوتاهی مو و ریش</li><li>سشوار و استایل مو</li><li>شمع و ماسک صورت</li><li>مانیکور و پدیکور</li><li>گریم داماد</li></ul></div></div></article>
<article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Hair Artist</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-03.jpg" alt="ارسلان پورفضل" loading="lazy"></div><div class="tcard__name"><div class="fa">ارسلان پورفضل</div><div class="en lat">Arsalan Pourfazl</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>کوتاهی مو و ریش</li><li>سشوار و استایل مو</li><li>شمع و ماسک صورت</li></ul></div></div></article>
<article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Hair Artist</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-04.jpg" alt="اشکان محمدی" loading="lazy"></div><div class="tcard__name"><div class="fa">اشکان محمدی</div><div class="en lat">Ashkan Mohammadi</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>کوتاهی مو و ریش</li><li>سشوار و استایل مو</li><li>ماسک صورت</li></ul></div></div></article>
<article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Hair Artist</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-05.jpg" alt="حسین لویمی" loading="lazy"></div><div class="tcard__name"><div class="fa">حسین لویمی</div><div class="en lat">Hossein Loyami</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>کوتاهی مو و ریش</li><li>سشوار و استایل مو</li><li>شمع و ماسک صورت</li></ul></div></div></article>
<article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Hair Artist</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-06.jpg" alt="مجتبی میراحمدی" loading="lazy"></div><div class="tcard__name"><div class="fa">مجتبی میراحمدی</div><div class="en lat">Mojtaba Mirahmadi</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>کوتاهی مو و ریش</li><li>سشوار و استایل مو</li><li>شمع و ماسک صورت</li></ul></div></div></article>
<article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Hair Artist</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-07.jpg" alt="احمد سرخه" loading="lazy"></div><div class="tcard__name"><div class="fa">احمد سرخه</div><div class="en lat">Ahmad Sorkheh</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>کوتاهی مو و ریش</li><li>سشوار و استایل مو</li><li>شمع و ماسک صورت</li><li>رنگ مو</li><li>کراتین مو</li></ul></div></div></article>
<article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Massage</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-08.jpg" alt="احمدرضا جلالی" loading="lazy"></div><div class="tcard__name"><div class="fa">احمدرضا جلالی</div><div class="en lat">Ahmadreza Jalali</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>ماساژ درمانی</li><li>ماساژ ریلکسی</li></ul></div></div></article>
<article class="tcard"><div class="tcard__inner"><span class="tcard__corner tl"></span><span class="tcard__corner tr"></span><span class="tcard__corner bl"></span><span class="tcard__corner br"></span><span class="tcard__role lat">Facial</span><div class="tcard__photo"><img src="<?php echo $tpl_uri; ?>/assets/img/team-09.jpg" alt="حمید جوهری" loading="lazy"></div><div class="tcard__name"><div class="fa">حمید جوهری</div><div class="en lat">Hamid Johari</div></div><div class="tcard__skills"><div class="h">Specialties</div><ul><li>پاکسازی تخصصی</li><li>رنگ مو و ریش</li><li>ویتامینه مو</li><li>مانیکور و پدیکور</li><li>شمع صورت</li></ul></div></div></article>
      </div>
      <div class="team__progress" aria-hidden="true"><i id="teamProg"></i></div>
      <p class="team__hint">DRAG · SWIPE</p>
    </div>
  </section>

  <!-- GIFT -->
  <section class="screen gift" id="gift" data-theme="light" data-name="گیفت‌کارت">
    <div class="gift__inner wrap">
      <span class="eyebrow c anim" style="--i:0">Gift Cards</span>
      <h2 class="anim" style="--i:1">هدیه‌ای در شأن یک نجیب‌زاده</h2>
      <p class="anim" style="--i:2">چهار کارت هدیه‌ی دوریان؛ تجربه‌ای کامل از مراقبت و آرامش را به عزیزانتان هدیه دهید.</p>
      <div class="giftcard-row anim" style="--i:3">
        <div class="giftcard"><div class="giftcard__inner">
          <div class="giftcard__face giftcard__front"><img src="<?php echo $tpl_uri; ?>/assets/img/gift-gentle-front.jpg" alt="گیفت‌کارت یک میلیون تومان" loading="lazy"></div>
          <div class="giftcard__face giftcard__back"><img src="<?php echo $tpl_uri; ?>/assets/img/gift-gentle-back.jpg" alt="پشت گیفت‌کارت یک میلیون" loading="lazy"></div>
        </div></div>
        <div class="giftcard"><div class="giftcard__inner">
          <div class="giftcard__face giftcard__front"><img src="<?php echo $tpl_uri; ?>/assets/img/gift-duke-front.jpg" alt="گیفت‌کارت دو میلیون تومان" loading="lazy"></div>
          <div class="giftcard__face giftcard__back"><img src="<?php echo $tpl_uri; ?>/assets/img/gift-duke-back.jpg" alt="پشت گیفت‌کارت دو میلیون" loading="lazy"></div>
        </div></div>
        <div class="giftcard"><div class="giftcard__inner">
          <div class="giftcard__face giftcard__front"><img src="<?php echo $tpl_uri; ?>/assets/img/gift-noble-front.jpg" alt="گیفت‌کارت پنج میلیون تومان" loading="lazy"></div>
          <div class="giftcard__face giftcard__back"><img src="<?php echo $tpl_uri; ?>/assets/img/gift-noble-back.jpg" alt="پشت گیفت‌کارت پنج میلیون" loading="lazy"></div>
        </div></div>
        <div class="giftcard"><div class="giftcard__inner">
          <div class="giftcard__face giftcard__front"><img src="<?php echo $tpl_uri; ?>/assets/img/gift-royal-front.jpg" alt="گیفت‌کارت ده میلیون تومان" loading="lazy"></div>
          <div class="giftcard__face giftcard__back"><img src="<?php echo $tpl_uri; ?>/assets/img/gift-royal-back.jpg" alt="پشت گیفت‌کارت ده میلیون" loading="lazy"></div>
        </div></div>
      </div>
      <div class="gift__cta anim" style="--i:4"><a class="btn btn--blue" href="#reserve" data-go="6">هدیه بدهید</a></div>
    </div>
  </section>

  <!-- SHOP · dynamic WooCommerce products (latest) -->
  <?php
  if ( class_exists( 'WooCommerce' ) ) :
      $dorian_products = wc_get_products( array(
          'status'     => 'publish',
          'limit'      => 8,
          'orderby'    => 'date',
          'order'      => 'DESC',
          'visibility' => 'visible',
      ) );
      if ( ! empty( $dorian_products ) ) : ?>
  <section class="screen shop" id="shop" data-theme="light" data-name="فروشگاه">
    <div class="shop__inner wrap">
      <span class="eyebrow c anim" style="--i:0">The Boutique</span>
      <h2 class="anim" style="--i:1">آیینِ آراستگی، در خلوتِ <span class="serif">خانه</span></h2>
      <p class="anim" style="--i:2">برگزیده‌ای از بهترین محصولات مراقبت و آراستگیِ مردانه؛ همان اصالتی که در دوریان تجربه می‌کنید، اکنون در خانه‌ی شما.</p>
      <div class="shop__grid anim" style="--i:3">
        <?php foreach ( $dorian_products as $product ) :
            $pid   = $product->get_id();
            $img   = $product->get_image_id()
                ? wp_get_attachment_image_url( $product->get_image_id(), 'large' )
                : wc_placeholder_img_src( 'large' );
            $link  = get_permalink( $pid );
            $buyable = $product->is_purchasable() && $product->is_in_stock() && ! $product->is_type( 'variable' );
            ?>
        <article class="shopcard">
          <a class="shopcard__media" href="<?php echo esc_url( $link ); ?>">
            <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" loading="lazy">
          </a>
          <div class="shopcard__body">
            <h3 class="shopcard__name"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
            <div class="shopcard__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
            <?php if ( $buyable ) : ?>
            <a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
               class="btn btn--gold shopcard__add ajax_add_to_cart add_to_cart_button product_type_<?php echo esc_attr( $product->get_type() ); ?>"
               data-product_id="<?php echo esc_attr( $pid ); ?>" data-quantity="1" rel="nofollow">افزودن به سبد</a>
            <?php else : ?>
            <a href="<?php echo esc_url( $link ); ?>" class="btn btn--ghost shopcard__add">مشاهدهٔ محصول</a>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <div class="shop__cta anim" style="--i:4">
        <a class="btn btn--blue" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">مشاهدهٔ فروشگاه</a>
      </div>
    </div>
  </section>
  <?php endif;
  endif; ?>

  <!-- RESERVE + FOOTER -->
  <section class="screen reserve" id="reserve" data-theme="dark" data-name="رزرو">
    <div class="reserve__inner wrap">
      <img class="reserve__seal anim scale" style="--i:0" src="<?php echo $tpl_uri; ?>/assets/img/badge.png" alt="New Experiences">
      <span class="eyebrow c anim" style="--i:1">Reservation</span>
      <h2 class="anim" style="--i:1">نوبت خود را رزرو کنید</h2>
      <p class="anim" style="--i:2">برای رزرو نوبت یا مشاوره‌ی تخصصی داماد با ما در تماس باشید.</p>
      <div class="reserve__phones anim" style="--i:3">
        <a class="phone" href="tel:09167921710"><span class="k">تماس</span><span class="v">0916 792 1710</span></a>
        <a class="phone" href="tel:09167921310"><span class="k">تماس</span><span class="v">0916 792 1310</span></a>
        <a class="phone" href="tel:09165555532"><span class="k">مشاوره داماد</span><span class="v">0916 555 5532</span></a>
      </div>
      <div class="reserve__addr anim" style="--i:4"><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>اهواز، کیان‌آباد، نبش خیابان سوم، وهابی</div>
      <!-- TODO: لینک رزرو را به صفحه‌ی نوبت‌دهی تغییر بده -->
      <div class="reserve__cta anim" style="--i:4"><a class="btn btn--gold" href="<?php echo esc_url($reserve_url); ?>">رزرو آنلاین نوبت</a></div>

      <footer class="foot">
        <div class="wrap">
          <img class="foot__logo" src="<?php echo $tpl_uri; ?>/assets/img/logo.png" alt="Dorian">
          <div class="foot__soc">
            <a href="https://instagram.com/dorianstudio.ir" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24"><path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.3 1 .4 2.2.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .3-2.2.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.3-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.3 2.2-.4C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.1 0-3.5 0-4.7.1-1.1.1-1.7.2-2.1.4-.5.2-.9.4-1.3.8-.4.4-.6.8-.8 1.3-.2.4-.3 1-.4 2.1C2.6 9.9 2.6 10.3 2.6 12s0 2.1.1 3.3c.1 1.1.2 1.7.4 2.1.2.5.4.9.8 1.3.4.4.8.6 1.3.8.4.2 1 .3 2.1.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c1.1-.1 1.7-.2 2.1-.4.5-.2.9-.4 1.3-.8.4-.4.6-.8.8-1.3.2-.4.3-1 .4-2.1.1-1.2.1-1.6.1-3.3s0-2.1-.1-3.3c-.1-1.1-.2-1.7-.4-2.1-.2-.5-.4-.9-.8-1.3-.4-.4-.8-.6-1.3-.8-.4-.2-1-.3-2.1-.4-1.2-.1-1.6-.1-4.7-.1zM12 7.1A4.9 4.9 0 1017 12a4.9 4.9 0 00-5-4.9zm0 8.1A3.2 3.2 0 1112 8.8a3.2 3.2 0 010 6.4zm6.3-8.3a1.15 1.15 0 11-1.15-1.15 1.15 1.15 0 011.15 1.15z"/></svg></a>
            <a href="https://dorianstudio.ir" target="_blank" rel="noopener" aria-label="Website"><svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 1.8c1.3 0 3.1 2.3 3.6 6.4H8.4C8.9 6.1 10.7 3.8 12 3.8zM4 12c0-.7.1-1.4.2-2h3.3c-.1.6-.1 1.3-.1 2s0 1.4.1 2H4.2c-.1-.6-.2-1.3-.2-2zm.9 4h2.9c.4 2 .9 3.5 1.5 4.4A8.2 8.2 0 014.9 16zM7.8 8H4.9a8.2 8.2 0 014.4-4.4C8.7 4.5 8.2 6 7.8 8zm4.2 12.2c-1.3 0-3.1-2.3-3.6-6.2h7.2c-.5 3.9-2.3 6.2-3.6 6.2zM9.3 12c0-.7 0-1.4.1-2h5.2c.1.6.1 1.3.1 2s0 1.4-.1 2H9.4c-.1-.6-.1-1.3-.1-2zm6.9 8.4c.6-.9 1.1-2.4 1.5-4.4h2.9a8.2 8.2 0 01-4.4 4.4zM16.2 8c-.4-2-.9-3.5-1.5-4.4A8.2 8.2 0 0119.1 8zm.4 6c.1-.6.1-1.3.1-2s0-1.4-.1-2h3.3c.1.6.2 1.3.2 2s-.1 1.4-.2 2z"/></svg></a>
          </div>
          <p class="foot__copy"><span class="lat">© 2026 Designed by Ronakads | All rights reserved for Dorianstudio</span></p>
        </div>
      </footer>
    </div>
  </section>

</main>

<!-- sound toggle (elevator ding / card tick) -->
<button class="snd" id="soundToggle" aria-pressed="false" aria-label="روشن/خاموش کردن صدا">
  <span class="snd__ring" aria-hidden="true"></span>
  <span class="snd__bars" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
  <span class="snd__label" id="soundLabel">صدا خاموش</span>
</button>

<?php if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC()->cart ) : ?>
<!-- floating cart (live count via WooCommerce fragments) -->
<a class="dorian-cart" id="dorianCart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="سبد خرید" aria-expanded="false" aria-controls="dorianMiniCart">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12zM6 6L5 3H2M9 20a1 1 0 100 2 1 1 0 000-2zm9 0a1 1 0 100 2 1 1 0 000-2z"/></svg>
  <span class="dorian-cart__count"><?php echo esc_html( WC()->cart->get_cart_contents_count() ); ?></span>
</a>
<!-- minimal mini-cart (auto-refreshes via WooCommerce fragments) -->
<div class="dorian-minicart" id="dorianMiniCart" aria-hidden="true">
  <div class="dorian-minicart__head">
    <span>سبد خرید</span>
    <button class="dorian-minicart__close" id="dorianMiniCartClose" type="button" aria-label="بستن">&times;</button>
  </div>
  <div class="widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div>
</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
