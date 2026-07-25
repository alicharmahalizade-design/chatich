<?php
if (!defined('ABSPATH')) exit;

/* =========================================================================
   Independent secretary panel  (dorianstudio.ir/<manage_slug>)
   — passcode protected, monitors all providers, evaluates performance,
     and manages per-provider prices
   ========================================================================= */

class Dorian_Manage {

    protected static function slug()  { $s = dorian_settings(); return !empty($s['manage_slug']) ? sanitize_title($s['manage_slug']) : 'monshi'; }
    protected static function pass()  { $s = dorian_settings(); return isset($s['manage_pass']) ? (string) $s['manage_pass'] : ''; }
    protected static function ckey()  { return 'dmanage'; }
    protected static function token() { return wp_hash('dmanage|' . self::pass()); }
    protected static function write_cookie($value, $expires) {
        $path   = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        setcookie(self::ckey(), $value, $expires, $path, $domain, is_ssl(), true);
    }
    protected static function authed() {
        $p = self::pass();
        if ($p === '') return false;
        return isset($_COOKIE[self::ckey()]) && hash_equals(self::token(), $_COOKIE[self::ckey()]);
    }

    public static function handle() {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();

        $pass = self::pass();
        if ($pass === '') { status_header(404); return; } // disabled until admin sets a passcode

        if (isset($_POST['dmanage_login'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'dmanage_login')) {
                $err = 'درخواست معتبر نیست؛ دوباره تلاش کنید.';
            } elseif (hash_equals($pass, (string) wp_unslash($_POST['passcode'] ?? ''))) {
                self::write_cookie(self::token(), time() + 86400 * 7);
                wp_safe_redirect(remove_query_arg('x')); exit;
            } else { $err = 'رمز اشتباه است.'; }
        }
        if (isset($_GET['logout'])) {
            check_admin_referer('dmanage_logout');
            self::write_cookie('', time() - 3600);
            wp_safe_redirect(strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }
        if (!self::authed()) { self::login(isset($err) ? $err : ''); return; }

        if (isset($_POST['dmanage_prices']) && check_admin_referer('dmanage_prices')) {
            self::save_prices();
            wp_safe_redirect(add_query_arg('saved', '1', strtok($_SERVER['REQUEST_URI'], '?'))); exit;
        }
        self::page();
    }

    protected static function providers() {
        return get_posts(array('post_type' => 'dorian_provider', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC'));
    }

    protected static function save_prices() {
        if (empty($_POST['price']) || !is_array($_POST['price'])) return;
        foreach ($_POST['price'] as $pid => $svcs) {
            $pid = (int) $pid;
            if (!$pid || get_post_type($pid) !== 'dorian_provider') continue;
            $svc = get_post_meta($pid, '_dorian_svc', true);
            if (!is_array($svc)) $svc = array();
            foreach ((array) $svcs as $sid => $val) {
                $sid  = (int) $sid;
                $prev = isset($svc[$sid]) && is_array($svc[$sid]) ? $svc[$sid] : array();
                $prev['price'] = max(0, (int) $val);
                if (!isset($prev['dur'])) $prev['dur'] = max(15, (int) get_post_meta($sid, '_dorian_duration', true) ?: 30);
                $svc[$sid] = $prev;
            }
            update_post_meta($pid, '_dorian_svc', $svc);
        }
    }

    /* ---- data across all providers ---- */
    protected static function all_on($ymd) {
        global $wpdb; $t = Dorian_DB::table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE DATE(start_dt)=%s ORDER BY start_dt ASC", $ymd));
    }
    protected static function upcoming($from, $limit = 300) {
        global $wpdb; $t = Dorian_DB::table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE DATE(start_dt) >= %s ORDER BY start_dt ASC LIMIT %d", $from, $limit));
    }
    protected static function revenue($days) {
        global $wpdb; $t = Dorian_DB::table();
        $since = date('Y-m-d 00:00:00', strtotime('-' . (max(1, (int) $days) - 1) . ' day', current_time('timestamp')));
        $row = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) c, COALESCE(SUM(total_price),0) s FROM $t WHERE status='done' AND start_dt >= %s", $since));
        return array('c' => (int) $row->c, 's' => (int) $row->s);
    }
    protected static function revenue_by_provider($days) {
        global $wpdb; $t = Dorian_DB::table();
        $since = date('Y-m-d 00:00:00', strtotime('-' . (max(1, (int) $days) - 1) . ' day', current_time('timestamp')));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT provider_ids, total_price FROM $t WHERE status='done' AND start_dt >= %s", $since));
        $out = array();
        foreach ($rows as $r) foreach (explode(',', $r->provider_ids) as $pid) {
            $pid = (int) $pid; if (!$pid) continue;
            $out[$pid] = (isset($out[$pid]) ? $out[$pid] : 0) + (int) $r->total_price;
        }
        return $out;
    }
    protected static function revenue_by_day($days) {
        global $wpdb; $t = Dorian_DB::table();
        $wd = array('ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'); $out = array();
        for ($i = max(1, (int) $days) - 1; $i >= 0; $i--) {
            $ts = strtotime("-$i day", current_time('timestamp'));
            $s  = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_price),0) FROM $t WHERE status='done' AND DATE(start_dt)=%s", date('Y-m-d', $ts)));
            $out[] = array('s' => $s, 'x' => $wd[((int) date('w', $ts) + 1) % 7], 'today' => ($i === 0));
        }
        return $out;
    }

    /**
     * Operational scorecard for every provider over the last N calendar days.
     * "Total" deliberately includes cancelled bookings so the completion rate
     * describes the whole demand handled by the provider.
     */
    protected static function provider_scorecards($providers, $days = 30) {
        global $wpdb; $t = Dorian_DB::table();
        $days  = max(1, (int) $days);
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day', current_time('timestamp')));
        $until = current_time('mysql'); // do not penalize today's still-upcoming appointments
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT provider_ids, status, total_price FROM $t WHERE start_dt BETWEEN %s AND %s",
            $since, $until
        ));
        $out = array();
        foreach ($providers as $p) {
            $out[$p->ID] = array(
                'total' => 0, 'done' => 0, 'noshow' => 0, 'cancelled' => 0,
                'revenue' => 0, 'services' => count(dorian_provider_services($p->ID)),
            );
        }
        foreach ($rows as $r) {
            foreach (array_unique(array_filter(array_map('intval', explode(',', $r->provider_ids)))) as $pid) {
                if (!isset($out[$pid])) continue;
                $out[$pid]['total']++;
                if (isset($out[$pid][$r->status])) $out[$pid][$r->status]++;
                if ($r->status === 'done') $out[$pid]['revenue'] += (int) $r->total_price;
            }
        }
        foreach ($out as &$m) {
            $m['rate'] = $m['total'] ? round($m['done'] * 100 / $m['total'], 1) : 0;
        }
        unset($m);
        return $out;
    }

    protected static function provider_photo($provider) {
        $img = get_the_post_thumbnail_url($provider->ID, 'thumbnail');
        return $img ? $img : DORIAN_URL . 'assets/avatar.svg';
    }
    protected static function pname($ids) {
        $names = array();
        foreach (explode(',', $ids) as $pid) { $pid = (int) $pid; if (!$pid) continue; $t = get_the_title($pid); if ($t) $names[] = $t; }
        return implode('، ', $names);
    }

    /* ---- read-only monitoring row (provider label + contact) ---- */
    protected static function row($r, $labels) {
        $st = isset($labels[$r->status]) ? $r->status : 'confirmed';
        $lb = $labels[$st];
        $ts = strtotime($r->start_dt);
        $ph = esc_attr($r->customer_phone);
        $prov = self::pname($r->provider_ids);
        $q = (function_exists('mb_strtolower') ? mb_strtolower($r->customer_name . ' ' . $prov) : strtolower($r->customer_name . ' ' . $prov)) . ' ' . $r->customer_phone;
        return '<div class="bk bk--' . esc_attr($st) . '" data-q="' . esc_attr($q) . '" data-start="' . ((int) date('G', $ts) * 60 + (int) date('i', $ts)) . '" data-dur="' . (int) $r->duration_min . '">'
            . '<span class="time">' . esc_html(date('H:i', $ts)) . '</span>'
            . '<span><span class="who">' . esc_html($r->customer_name) . '</span><br><span class="svc">' . esc_html($r->service_names) . ' · ' . (int) $r->duration_min . '′' . ($prov ? ' · <b style="color:var(--blue)">' . esc_html($prov) . '</b>' : '') . '</span></span>'
            . '<span class="badge" style="background:' . esc_attr($lb[1]) . '">' . esc_html($lb[0]) . '</span>'
            . '<span class="contact"><a class="tel" href="tel:' . $ph . '">' . esc_html($r->customer_phone) . '</a>'
            . '<a class="cbtn call" href="tel:' . $ph . '" aria-label="تماس">' . Dorian_Panel::icon('phone') . '</a>'
            . '<a class="cbtn sms" href="sms:' . $ph . '" aria-label="پیامک">' . Dorian_Panel::icon('sms') . '</a></span>'
            . '</div>';
    }

    protected static function login($err) {
        Dorian_Panel::head('ورود منشی — دوریان');
        ?><div class="pcard login">
          <h1 style="text-align:center;margin:0 0 4px">پنل منشی</h1>
          <p class="hint" style="text-align:center">برای ورود به فضای کاری منشی، رمز اختصاصی را وارد کنید.</p>
          <?php if ($err) echo '<p class="err" style="text-align:center">' . esc_html($err) . '</p>'; ?>
          <form method="post">
            <?php wp_nonce_field('dmanage_login'); ?>
            <label for="passcode" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">رمز منشی</label>
            <input id="passcode" type="password" name="passcode" placeholder="رمز منشی" autocomplete="current-password" inputmode="numeric" autofocus>
            <button class="btn btn--blue" name="dmanage_login" value="1" style="width:100%">ورود</button>
          </form>
        </div><?php
        Dorian_Panel::foot();
    }

    protected static function page() {
        $labels = array(
            'confirmed' => array('تأییدشده', '#0066B3'),
            'paid'      => array('پرداخت‌شده', '#1f8a4c'),
            'done'      => array('انجام شد', '#1f8a4c'),
            'noshow'    => array('نیامد', '#b23b3b'),
            'cancelled' => array('لغو شد', '#9a9a9a'),
        );
        $days = array('امروز', 'فردا', 'پس‌فردا');
        $rw = self::revenue(7); $rm = self::revenue(30);
        $today = date('Y-m-d', current_time('timestamp'));
        $trows = self::all_on($today);
        $provs = self::providers();
        $scores = self::provider_scorecards($provs, 30);
        $t_total = 0; $t_done = 0; $byprov = array();
        foreach ($trows as $r) {
            if ($r->status !== 'cancelled') $t_total++;
            if ($r->status === 'done') $t_done++;
            foreach (explode(',', $r->provider_ids) as $pid) { $pid = (int) $pid; if (!$pid) continue; if ($r->status !== 'cancelled') $byprov[$pid] = (isset($byprov[$pid]) ? $byprov[$pid] : 0) + 1; }
        }
        $sv = isset($_GET['saved']) ? 'price' : 'dash';

        Dorian_Panel::head('پنل منشی دوریان');
        ?>
        <a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a>
        <div class="splash" id="splash"><div class="splash__box"><div class="splash__ava" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1E86D6,#0066B3);color:#fff;font-size:2rem">م</div><div class="splash__bar"><i></i></div></div></div>
        <header class="appbar">
          <div class="appbar__id">
            <span class="appbar__ava" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1E86D6,#0066B3);color:#fff;font-weight:700">م</span>
            <div class="appbar__meta"><h1>پنل منشی</h1><span class="role">Secretary · پذیرش</span></div>
          </div>
          <a class="iconbtn" href="<?php echo esc_url(wp_nonce_url(add_query_arg('logout', '1'), 'dmanage_logout')); ?>"><span aria-hidden="true">⎋</span> خروج</a>
        </header>

        <?php if (isset($_GET['saved'])) echo '<div class="saved">قیمت‌ها ذخیره شد.</div>'; ?>

        <main class="views" id="main-content">

          <section class="view<?php echo $sv === 'dash' ? ' on' : ''; ?>" data-view="dash">
            <div class="welcome">
              <div><span class="eyebrow">مرکز عملیات امروز</span><h2>همه‌چیز برای یک پذیرش منظم</h2><p>وضعیت امروز تمام متخصص‌ها و خلاصهٔ عملکرد مجموعه در یک نگاه.</p></div>
              <span class="today-pill"><?php echo Dorian_Panel::icon('cal'); ?><span class="jdate" data-ymd="<?php echo esc_attr($today); ?>"><?php echo esc_html($today); ?></span></span>
            </div>
            <div class="pcard">
              <h2>نمای کلی امروز</h2>
              <div class="tiles">
                <div class="tile"><span class="tile__ic"><?php echo Dorian_Panel::icon('cal'); ?></span><div class="tile__b"><span class="tile__n"><?php echo $t_total; ?></span><span class="tile__l">نوبت امروز</span></div></div>
                <div class="tile tile--ok"><span class="tile__ic"><?php echo Dorian_Panel::icon('check'); ?></span><div class="tile__b"><span class="tile__n"><?php echo $t_done; ?></span><span class="tile__l">انجام‌شدهٔ امروز</span></div></div>
                <div class="tile tile--rev"><span class="tile__ic"><?php echo Dorian_Panel::icon('coin'); ?></span><div class="tile__b"><span class="tile__n"><?php echo number_format($rw['s']); ?></span><span class="tile__l">درآمد هفته (تومان)</span></div></div>
                <div class="tile tile--rev"><span class="tile__ic"><?php echo Dorian_Panel::icon('coin'); ?></span><div class="tile__b"><span class="tile__n"><?php echo number_format($rm['s']); ?></span><span class="tile__l">درآمد ماه (تومان)</span></div></div>
              </div>
            </div>
            <div class="pcard">
              <h2>متخصص‌ها امروز</h2>
              <div class="provider-grid"><?php
              if (!$provs) echo '<p class="empty">متخصصی ثبت نشده.</p>';
              foreach ($provs as $p) {
                  $c = isset($byprov[$p->ID]) ? $byprov[$p->ID] : 0;
                  $slug = get_post_meta($p->ID, '_dorian_slug', true);
                  $role = get_post_meta($p->ID, '_dorian_role', true);
                  echo '<article class="provider-card"><img src="' . esc_url(self::provider_photo($p)) . '" alt="">'
                     . '<div class="provider-card__body"><b>' . esc_html($p->post_title) . '</b><small>' . esc_html($role ?: 'متخصص') . '</small></div>'
                     . '<span class="provider-card__count"><b>' . $c . '</b> نوبت</span>'
                     . ($slug ? '<a class="provider-card__go" href="' . esc_url(home_url('/' . $slug)) . '" target="_blank" rel="noopener" aria-label="باز کردن پنل ' . esc_attr($p->post_title) . '">↗</a>' : '') . '</article>';
              }
              ?></div>
            </div>
          </section>

          <section class="view<?php echo $sv === 'book' ? ' on' : ''; ?>" data-view="book">
            <div class="pcard">
              <h2>همهٔ نوبت‌ها</h2>
              <div class="search"><span class="search__ic"><?php echo Dorian_Panel::icon('search'); ?></span><input type="search" id="bkSearch" placeholder="جست‌وجوی نام، شماره یا متخصص…" autocomplete="off"><button type="button" class="search__x" id="bkSearchX" aria-label="پاک کردن" hidden>✕</button></div>
              <div class="seg">
                <?php foreach ($days as $i => $d) echo '<button type="button" class="tab' . ($i === 0 ? ' on' : '') . '" data-day="' . $i . '">' . esc_html($d) . '</button>'; ?>
                <button type="button" class="tab" data-day="up">آینده</button>
              </div>
              <?php
              foreach ($days as $i => $d) {
                  $ymd  = date('Y-m-d', strtotime("+$i day", current_time('timestamp')));
                  $rows = self::all_on($ymd);
                  echo '<div class="day' . ($i === 0 ? ' on' : '') . '" data-day="' . $i . '">';
                  if (!$rows) { echo '<p class="empty">نوبتی برای این روز ثبت نشده.</p></div>'; continue; }
                  foreach ($rows as $r) echo self::row($r, $labels);
                  echo '</div>';
              }
              $fut = self::upcoming(date('Y-m-d', strtotime('+3 day', current_time('timestamp'))));
              echo '<div class="day" data-day="up">';
              if (!$fut) echo '<p class="empty">نوبتی برای روزهای بعد ثبت نشده.</p>';
              else {
                  $cur = '';
                  foreach ($fut as $r) {
                      $dd = date('Y-m-d', strtotime($r->start_dt));
                      if ($dd !== $cur) { $cur = $dd; echo '<div class="dhead"><span class="jdate" data-ymd="' . esc_attr($dd) . '">' . esc_html($dd) . '</span></div>'; }
                      echo self::row($r, $labels);
                  }
              }
              echo '</div>';
              ?>
            </div>
          </section>

          <section class="view<?php echo $sv === 'eval' ? ' on' : ''; ?>" data-view="eval">
            <div class="section-head">
              <div><span class="eyebrow">بازهٔ ۳۰ روز اخیر</span><h2>ارزیابی متخصص‌ها</h2><p>تصویر عملیاتی عملکرد هر متخصص بر اساس نوبت‌های ثبت‌شده.</p></div>
              <span class="metric-note">نرخ تکمیل = انجام‌شده ÷ کل نوبت</span>
            </div>
            <?php if (!$provs) echo '<div class="pcard"><p class="empty">متخصصی ثبت نشده.</p></div>'; ?>
            <div class="score-grid">
            <?php foreach ($provs as $p) :
                $m = $scores[$p->ID];
                $rate_class = $m['rate'] >= 80 ? 'good' : ($m['rate'] >= 55 ? 'mid' : 'low');
            ?>
              <article class="score-card">
                <header class="score-card__head">
                  <img src="<?php echo esc_url(self::provider_photo($p)); ?>" alt="">
                  <div><h3><?php echo esc_html($p->post_title); ?></h3><span><?php echo esc_html(get_post_meta($p->ID, '_dorian_role', true) ?: 'متخصص'); ?></span></div>
                  <div class="rate-ring <?php echo esc_attr($rate_class); ?>" style="--rate:<?php echo esc_attr($m['rate']); ?>"><strong><?php echo esc_html(str_replace('.0', '', (string) $m['rate'])); ?>٪</strong><small>تکمیل</small></div>
                </header>
                <div class="score-main">
                  <div><strong><?php echo $m['total']; ?></strong><span>کل نوبت</span></div>
                  <div class="is-good"><strong><?php echo $m['done']; ?></strong><span>انجام‌شده</span></div>
                  <div class="is-bad"><strong><?php echo $m['noshow']; ?></strong><span>عدم‌حضور</span></div>
                  <div><strong><?php echo $m['cancelled']; ?></strong><span>لغو</span></div>
                </div>
                <footer class="score-card__foot">
                  <span><?php echo Dorian_Panel::icon('coin'); ?><span><small>درآمد واقعی</small><b><?php echo number_format($m['revenue']); ?> <i>تومان</i></b></span></span>
                  <span><?php echo Dorian_Panel::icon('set'); ?><span><small>خدمات فعال</small><b><?php echo $m['services']; ?> خدمت</b></span></span>
                </footer>
              </article>
            <?php endforeach; ?>
            </div>
          </section>

          <section class="view<?php echo $sv === 'rev' ? ' on' : ''; ?>" data-view="rev">
            <div class="pcard">
              <h2>درآمد کل</h2>
              <div class="tiles">
                <div class="tile tile--rev"><span class="tile__ic"><?php echo Dorian_Panel::icon('coin'); ?></span><div class="tile__b"><span class="tile__n"><?php echo number_format($rw['s']); ?></span><span class="tile__l">هفته · <?php echo $rw['c']; ?> نوبت</span></div></div>
                <div class="tile tile--rev"><span class="tile__ic"><?php echo Dorian_Panel::icon('coin'); ?></span><div class="tile__b"><span class="tile__n"><?php echo number_format($rm['s']); ?></span><span class="tile__l">ماه · <?php echo $rm['c']; ?> نوبت</span></div></div>
              </div>
            </div>
            <div class="pcard">
              <h2>نمودار ۷ روز اخیر</h2>
              <?php
              $bd = self::revenue_by_day(7); $mx = 1; foreach ($bd as $b) if ($b['s'] > $mx) $mx = $b['s'];
              echo '<div class="chart">';
              foreach ($bd as $b) {
                  echo '<div class="chart__col' . ($b['today'] ? ' is-today' : '') . '"><span class="chart__v">' . ($b['s'] ? number_format($b['s'] / 1000) . 'K' : '') . '</span>'
                     . '<div class="chart__bar" style="height:' . max(2, (int) round($b['s'] / $mx * 100)) . '%"></div><span class="chart__x">' . esc_html($b['x']) . '</span></div>';
              }
              echo '</div>';
              ?>
            </div>
            <div class="pcard">
              <h2>درآمد هر متخصص (۳۰ روز)</h2>
              <?php
              $rbp = self::revenue_by_provider(30);
              $mxp = 1; foreach ($rbp as $v) if ($v > $mxp) $mxp = $v;
              if (!array_filter($rbp)) echo '<p class="empty">درآمدی ثبت نشده.</p>';
              foreach ($provs as $p) {
                  $v = isset($rbp[$p->ID]) ? $rbp[$p->ID] : 0;
                  echo '<div class="pbar"><div class="pbar__top"><span>' . esc_html($p->post_title) . '</span><b>' . number_format($v) . '</b></div>'
                     . '<div class="pbar__track"><div class="pbar__fill" style="width:' . (int) round($v / $mxp * 100) . '%"></div></div></div>';
              }
              ?>
            </div>
          </section>

          <form method="post" class="view<?php echo $sv === 'price' ? ' on' : ''; ?>" data-view="price">
            <?php wp_nonce_field('dmanage_prices'); ?>
            <div class="pcard">
              <span class="eyebrow">تعرفه‌های اختصاصی</span>
              <h2>قیمت خدمات هر متخصص</h2>
              <p class="hint">یک متخصص را انتخاب کنید و تعرفهٔ خدمات فعال او را ویرایش کنید. متخصص‌ها امکان تغییر قیمت را ندارند.</p>
              <?php if ($provs) : ?><div class="provider-filter" role="tablist" aria-label="انتخاب متخصص">
                <?php foreach ($provs as $i => $p) echo '<button type="button" role="tab" class="provider-filter__btn' . ($i === 0 ? ' on' : '') . '" data-provider="' . (int) $p->ID . '">' . esc_html($p->post_title) . '</button>'; ?>
              </div><?php endif; ?>
            </div>
            <?php
            if (!$provs) echo '<div class="pcard"><p class="empty">متخصصی ثبت نشده.</p></div>';
            foreach ($provs as $pi => $p) {
                $svcs = dorian_provider_services($p->ID);
                echo '<div class="pcard price-provider' . ($pi === 0 ? ' on' : '') . '" data-provider="' . (int) $p->ID . '"><div class="price-provider__head"><img src="' . esc_url(self::provider_photo($p)) . '" alt=""><div><h3 class="pmgr">' . esc_html($p->post_title) . '</h3><span>' . count($svcs) . ' خدمت فعال</span></div></div>';
                if (!$svcs) { echo '<p class="hint" style="margin:0">خدمتی به این متخصص اختصاص داده نشده.</p></div>'; continue; }
                echo '<div class="svcrow svcrow--head"><span>خدمت</span><span>قیمت (تومان)</span><span>مدت</span></div>';
                foreach ($svcs as $sid => $s) {
                    echo '<div class="svcrow"><span class="nm">' . esc_html($s['name']) . '</span>'
                       . '<input type="number" min="0" step="5000" aria-label="تعرفهٔ ' . esc_attr($s['name']) . ' برای ' . esc_attr($p->post_title) . '" name="price[' . $p->ID . '][' . $sid . ']" value="' . esc_attr((int) $s['price']) . '">'
                       . '<span class="svc-price">' . (int) $s['dur'] . '′</span></div>';
                }
                echo '</div>';
            }
            ?>
            <div class="savebar"><button class="btn btn--blue btn--save" name="dmanage_prices" value="1">ذخیرهٔ قیمت‌ها</button></div>
          </form>

        </main>

        <nav class="tabbar" aria-label="بخش‌های پنل منشی">
          <span class="nav-brand"><b>دوریان</b><small>فضای کاری منشی</small></span>
          <button type="button" class="tabbtn<?php echo $sv === 'dash' ? ' on' : ''; ?>" data-view="dash"><span class="ic"><?php echo Dorian_Panel::icon('dash'); ?></span><span>پیشخوان</span></button>
          <button type="button" class="tabbtn<?php echo $sv === 'book' ? ' on' : ''; ?>" data-view="book"><span class="ic"><?php echo Dorian_Panel::icon('book'); ?></span><span>نوبت‌ها</span></button>
          <button type="button" class="tabbtn<?php echo $sv === 'eval' ? ' on' : ''; ?>" data-view="eval"><span class="ic"><?php echo Dorian_Panel::icon('check'); ?></span><span>ارزیابی</span></button>
          <button type="button" class="tabbtn<?php echo $sv === 'rev' ? ' on' : ''; ?>" data-view="rev"><span class="ic"><?php echo Dorian_Panel::icon('rev'); ?></span><span>درآمد</span></button>
          <button type="button" class="tabbtn<?php echo $sv === 'price' ? ' on' : ''; ?>" data-view="price"><span class="ic"><?php echo Dorian_Panel::icon('coin'); ?></span><span>تعرفه‌ها</span></button>
        </nav>

        <style>
        .skip-link{position:fixed;z-index:120;top:8px;right:8px;padding:9px 13px;border-radius:10px;background:var(--ink);color:var(--card);text-decoration:none;transform:translateY(-150%);transition:transform .18s}.skip-link:focus{transform:none}
        button,a,input{touch-action:manipulation}
        .iconbtn{min-height:44px}.tab{min-height:44px}.cbtn{width:44px;height:44px}
        .nav-brand{display:none}
        .eyebrow{display:block;margin-bottom:3px;color:var(--blue);font-size:.72rem;font-weight:800;letter-spacing:.08em}
        .welcome{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:20px;margin-bottom:14px;border-radius:22px;background:linear-gradient(135deg,#075f9f,#1287d3);color:#fff;box-shadow:0 18px 38px -24px rgba(0,76,135,.85)}
        .welcome::after{content:"";position:absolute;width:190px;height:190px;left:-55px;top:-95px;border:35px solid rgba(255,255,255,.08);border-radius:50%}
        .welcome .eyebrow{color:#d9efff}.welcome h2{padding:0;margin:0 0 3px;font-size:1.2rem}.welcome h2::before{display:none}.welcome p{position:relative;z-index:1;margin:0;color:rgba(255,255,255,.78);font-size:.82rem}
        .today-pill{position:relative;z-index:1;display:flex;align-items:center;gap:8px;flex:0 0 auto;padding:8px 12px;border:1px solid rgba(255,255,255,.24);border-radius:13px;background:rgba(255,255,255,.12);font-size:.78rem;font-weight:700;white-space:nowrap}
        .today-pill svg{width:18px;height:18px}
        .provider-grid{display:grid;gap:9px}
        .provider-card{display:flex;align-items:center;gap:10px;min-width:0;padding:10px;border:1px solid var(--line);border-radius:14px;background:#fff;transition:border-color .18s,transform .18s}
        .provider-card:hover{border-color:rgba(0,102,179,.28);transform:translateY(-1px)}
        .provider-card img{width:44px;height:44px;flex:0 0 auto;border-radius:13px;object-fit:cover}
        .provider-card__body{min-width:0;display:flex;flex-direction:column}.provider-card__body b{font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.provider-card__body small{color:var(--muted);font-size:.72rem}
        .provider-card__count{margin-inline-start:auto;display:flex;flex-direction:column;align-items:center;color:var(--muted);font-size:.68rem;white-space:nowrap}.provider-card__count b{color:var(--blue);font-family:'Space Grotesk';font-size:1rem;line-height:1.2}
        .provider-card__go{display:flex;align-items:center;justify-content:center;width:44px;height:44px;flex:0 0 auto;border:1px solid var(--line);border-radius:11px;text-decoration:none}
        .section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:4px 2px 18px}
        .section-head h2{padding:0;margin:0;font-size:1.35rem}.section-head h2::before{display:none}.section-head p{margin:3px 0 0;color:var(--muted);font-size:.82rem}
        .metric-note{padding:7px 11px;border:1px solid var(--line);border-radius:999px;color:var(--muted);font-size:.72rem;white-space:nowrap}
        .score-grid{display:grid;gap:14px}
        .score-card{overflow:hidden;background:var(--card);border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow)}
        .score-card__head{display:flex;align-items:center;gap:11px;padding:16px}
        .score-card__head>img{width:52px;height:52px;border-radius:15px;object-fit:cover}
        .score-card__head>div:nth-child(2){min-width:0}.score-card__head h3{margin:0;font-size:1rem}.score-card__head span{display:block;color:var(--muted);font-size:.75rem}
        .rate-ring{--ring:#b23b3b;--p:calc(var(--rate) * 1%);position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;width:66px;height:66px;margin-inline-start:auto;flex:0 0 auto;border-radius:50%;background:radial-gradient(circle at center,var(--card) 59%,transparent 61%),conic-gradient(var(--ring) var(--p),rgba(0,0,0,.07) 0)}
        .rate-ring.good{--ring:var(--ok)}.rate-ring.mid{--ring:#c99b20}.rate-ring strong{font-family:'Space Grotesk';font-size:.92rem;line-height:1.1}.rate-ring small{font-size:.58rem;color:var(--muted)}
        .score-main{display:grid;grid-template-columns:repeat(4,1fr);border-block:1px solid var(--line);background:rgba(0,0,0,.015)}
        .score-main>div{padding:11px 5px;text-align:center;border-inline-end:1px solid var(--line)}.score-main>div:last-child{border:0}
        .score-main strong{display:block;font-family:'Space Grotesk';font-size:1.12rem;color:var(--ink)}.score-main span{display:block;color:var(--muted);font-size:.66rem;white-space:nowrap}
        .score-main .is-good strong{color:var(--ok)}.score-main .is-bad strong{color:var(--no)}
        .score-card__foot{display:grid;grid-template-columns:1.35fr 1fr;padding:13px 16px;gap:10px}
        .score-card__foot>span{display:flex;align-items:center;gap:8px}.score-card__foot svg{width:20px;height:20px;color:var(--gold)}.score-card__foot>span:last-child svg{color:var(--blue)}
        .score-card__foot span span{display:flex;flex-direction:column}.score-card__foot small{color:var(--muted);font-size:.64rem}.score-card__foot b{font-family:'Space Grotesk';font-size:.82rem}.score-card__foot i{font-family:'Pinar';font-style:normal;font-size:.66rem}
        .provider-filter{display:flex;gap:7px;overflow-x:auto;padding:3px 0 4px;scrollbar-width:none}.provider-filter::-webkit-scrollbar{display:none}
        .provider-filter__btn{min-height:44px;padding:7px 14px;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--muted);font-family:inherit;font-weight:700;white-space:nowrap;cursor:pointer}
        .provider-filter__btn.on{border-color:transparent;background:linear-gradient(135deg,var(--blue-2),var(--blue));color:#fff;box-shadow:0 9px 20px -13px rgba(0,102,179,.9)}
        .price-provider{display:none}.price-provider.on{display:block;animation:fadeUp .25s ease both}
        .price-provider__head{display:flex;align-items:center;gap:11px;padding-bottom:13px;margin-bottom:13px;border-bottom:1px solid var(--line)}
        .price-provider__head img{width:48px;height:48px;border-radius:14px;object-fit:cover}.price-provider__head span{display:block;color:var(--muted);font-size:.72rem}
        .pmgr{font-size:1rem;margin:0;color:var(--blue)}
        .svcrow--head{font-size:.72rem;color:var(--muted)}
        .pbar{margin-bottom:12px}
        .pbar__top{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:5px}
        .pbar__top b{font-family:'Space Grotesk';color:#a8862a}
        .pbar__track{height:9px;border-radius:999px;background:rgba(0,0,0,.06);overflow:hidden}
        .pbar__fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#e3c04a,#b8901f)}
        @media (max-width:520px){
          .welcome{align-items:flex-start}.welcome p{display:none}.today-pill{padding:7px 9px}
          .section-head{align-items:flex-start}.metric-note{display:none}
          .score-card__head{padding:14px}.score-card__foot{padding:12px 14px}
          .score-main span{font-size:.6rem}
          .tabbtn{padding-inline:1px}.tabbtn .ic svg{width:22px;height:22px}
          .svcrow--head{display:none}
          .price-provider .svcrow:not(.svcrow--head){grid-template-columns:1fr 112px;grid-template-areas:"name price" "name duration";padding:10px 0;border-bottom:1px solid var(--line)}
          .price-provider .svcrow .nm{grid-area:name}.price-provider .svcrow input{grid-area:price}.price-provider .svcrow .svc-price{grid-area:duration;min-height:34px}
        }
        @media (min-width:700px){
          .provider-grid{grid-template-columns:1fr 1fr}
          .score-grid{grid-template-columns:1fr 1fr}
        }
        @media (min-width:960px){
          body{background:linear-gradient(135deg,#e7ddca,#d8c8ab);font-size:15px}
          .pw{max-width:1440px;min-height:100vh;padding:24px 304px 44px 28px}
          .appbar{top:20px;margin:0 0 22px;padding:13px 18px;border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow)}
          .views{max-width:1080px;margin:0 auto}
          .tabbar{top:24px;right:max(24px,calc((100vw - 1440px)/2 + 24px));bottom:24px;left:auto;width:252px;display:flex;flex-direction:column;align-items:stretch;gap:5px;padding:18px;border:1px solid var(--line);border-radius:24px;background:rgba(253,250,243,.92);box-shadow:0 24px 55px -30px rgba(40,30,10,.7)}
          .nav-brand{display:flex;flex-direction:column;padding:4px 9px 18px;margin-bottom:8px;border-bottom:1px solid var(--line)}.nav-brand b{font-size:1.35rem;color:var(--blue)}.nav-brand small{color:var(--muted);font-size:.72rem}
          .tabbtn{flex:0 0 auto;min-height:50px;flex-direction:row;justify-content:flex-start;gap:12px;padding:8px 13px;border-radius:13px;font-size:.88rem}
          .tabbtn .ic svg{width:22px;height:22px}.tabbtn::before{display:none}.tabbtn.on{background:linear-gradient(135deg,rgba(30,134,214,.14),rgba(0,102,179,.08));color:var(--blue)}
          .welcome{padding:24px 26px;margin-bottom:18px}.welcome h2{font-size:1.42rem}
          .view[data-view="dash"]{grid-template-columns:minmax(0,1.45fr) minmax(290px,.75fr);gap:18px}.view[data-view="dash"].on{display:grid}.view[data-view="dash"] .welcome,.view[data-view="dash"]>.pcard:first-of-type{grid-column:1/-1}.view[data-view="dash"]>.pcard{margin:0}
          .tiles{grid-template-columns:repeat(4,1fr)}
          .score-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
          .pcard{border-radius:22px}
          .view[data-view="book"]>.pcard{padding:26px 28px}
          .bk{padding:12px 15px}.bk .contact{margin-inline-start:auto}
          .price-provider{padding:26px 28px}
          .savebar{position:sticky;left:auto;right:auto;bottom:16px;margin-top:12px;padding:10px;background:rgba(225,213,192,.82);border:1px solid var(--line);border-radius:18px}
          .btn--save{max-width:360px;margin-inline-end:0}
        }
        @media (min-width:1280px){
          .score-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
        }
        @media (prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;transition-duration:.01ms!important}}
        @media (prefers-color-scheme:dark){
          .provider-card,.provider-filter__btn{background:#26231d;color:var(--ink)}
          .score-main{background:rgba(255,255,255,.02)}
          .rate-ring{background:radial-gradient(circle at center,var(--card) 59%,transparent 61%),conic-gradient(var(--ring) var(--p),rgba(255,255,255,.09) 0)}
          .pbar__track{background:rgba(255,255,255,.08)}
        }
        @media (prefers-color-scheme:dark) and (min-width:960px){body{background:linear-gradient(135deg,#171511,#242017)}.tabbar{background:rgba(30,28,23,.94)}}
        </style>

        <script>
        (function(){
          var views=document.querySelectorAll('.view'),btns=document.querySelectorAll('.tabbtn');
          function sb(v){document.body.className=document.body.className.replace(/\bview-\S+/g,'').trim();document.body.classList.add('view-'+v);}
          function show(v,writeHash){views.forEach(function(s){s.classList.toggle('on',s.dataset.view===v);});btns.forEach(function(b){b.classList.toggle('on',b.dataset.view===v);});sb(v);if(writeHash&&history.replaceState)history.replaceState(null,'','#'+v);window.scrollTo({top:0,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});}
          btns.forEach(function(b){b.addEventListener('click',function(){show(b.dataset.view,true);});});
          var cur=document.querySelector('.view.on'),hash=location.hash.slice(1),valid=document.querySelector('.view[data-view="'+hash+'"]');
          if(valid&&!<?php echo isset($_GET['saved']) ? 'true' : 'false'; ?>)show(hash,false);else sb(cur?cur.dataset.view:'dash');
          document.querySelectorAll('.tab').forEach(function(t){t.addEventListener('click',function(){document.querySelectorAll('.tab').forEach(x=>x.classList.remove('on'));document.querySelectorAll('.day').forEach(x=>x.classList.remove('on'));t.classList.add('on');var d=document.querySelector('.day[data-day="'+t.dataset.day+'"]');if(d)d.classList.add('on');});});
          var inp=document.getElementById('bkSearch');
          if(inp){var card=inp.closest('.pcard'),xb=document.getElementById('bkSearchX');
            function norm(s){return s.replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);}).toLowerCase().trim();}
            function ap(){var q=norm(inp.value);card.classList.toggle('searching',q.length>0);if(xb)xb.hidden=!q;var hits=0;card.querySelectorAll('.bk').forEach(function(bk){var sh=!q||(bk.dataset.q||'').indexOf(q)>=0;bk.classList.toggle('hidden',!sh);if(sh&&q)hits++;});var re=card.querySelector('.search-empty');if(q&&hits===0){if(!re){re=document.createElement('p');re.className='empty search-empty';re.textContent='نتیجه‌ای پیدا نشد.';card.appendChild(re);}re.hidden=false;}else if(re)re.hidden=true;}
            inp.addEventListener('input',ap);if(xb)xb.addEventListener('click',function(){inp.value='';ap();inp.focus();});}
          document.querySelectorAll('.provider-filter__btn').forEach(function(b){b.addEventListener('click',function(){
            document.querySelectorAll('.provider-filter__btn').forEach(function(x){x.classList.toggle('on',x===b);x.setAttribute('aria-selected',x===b?'true':'false');});
            document.querySelectorAll('.price-provider').forEach(function(x){x.classList.toggle('on',x.dataset.provider===b.dataset.provider);});
          });});
          var sp=document.getElementById('splash');
          if(sp){function h(){sp.classList.add('hide');setTimeout(function(){sp.parentNode&&sp.remove();},450);}if(document.readyState==='complete')setTimeout(h,300);else window.addEventListener('load',function(){setTimeout(h,300);});setTimeout(h,1600);}
          /* jalaali labels for upcoming date headers */
          function tr(a,b){return Math.trunc(a/b)}function md(a,b){return a-Math.trunc(a/b)*b}
          function jc(jy){var br=[-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178],gy=jy+621,lj=-14,jp=br[0],jm,ju=0,lp,n,i;for(i=1;i<br.length;i++){jm=br[i];ju=jm-jp;if(jy<jm)break;lj+=tr(ju,33)*8+tr(md(ju,33),4);jp=jm}n=jy-jp;lj+=tr(n,33)*8+tr(md(n,33)+3,4);if(md(ju,33)===4&&ju-n===4)lj++;var lg=tr(gy,4)-tr((tr(gy,100)+1)*3,4)-150,mr=20+lj-lg;if(ju-n<6)n=n-ju+tr(ju+4,33)*33;lp=md(md(n+1,33)-1,4);if(lp===-1)lp=4;return{leap:lp,gy:gy,march:mr}}
          function g2d(gy,gm,gd){var d=tr((gy+tr(gm-8,6)+100100)*1461,4)+tr(153*md(gm+9,12)+2,5)+gd-34840408;d=d-tr(tr(gy+100100+tr(gm-8,6),100)*3,4)+752;return d}
          function d2g(j){var i,k,gd,gm,gy;k=4*j+139361631;k=k+tr(tr(4*j+183187720,146097)*3,4)*4-3908;i=tr(md(k,1461),4)*5+308;gd=tr(md(i,153),5)+1;gm=md(tr(i,153),12)+1;gy=tr(k,1461)-100100+tr(8-gm,6);return{gy:gy,gm:gm,gd:gd}}
          function j2d(jy,jm,jd){var r=jc(jy);return g2d(r.gy,3,r.march)+(jm-1)*31-tr(jm,7)*(jm-7)+jd-1}
          function d2j(j){var gy=d2g(j).gy,jy=gy-621,r=jc(jy),f=g2d(gy,3,r.march),k=j-f,jm,jd;if(k>=0){if(k<=185){jm=1+tr(k,31);jd=md(k,31)+1;return{jy:jy,jm:jm,jd:jd}}else k-=186}else{jy--;k+=179;if(r.leap===1)k++}jm=7+tr(k,30);jd=md(k,30)+1;return{jy:jy,jm:jm,jd:jd}}
          function g2j(gy,gm,gd){return d2j(g2d(gy,gm,gd))}
          function col(jy,jm,jd){var g=d2g(j2d(jy,jm,jd));return (new Date(g.gy,g.gm-1,g.gd).getDay()+1)%7}
          var M=["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"],WD=["شنبه","یک‌شنبه","دوشنبه","سه‌شنبه","چهارشنبه","پنج‌شنبه","جمعه"];
          function fa(n){return String(n).replace(/[0-9]/g,d=>"۰۱۲۳۴۵۶۷۸۹"[d])}
          document.querySelectorAll('.jdate').forEach(function(el){var s=(el.dataset.ymd||'').split('-');if(s.length===3){var j=g2j(+s[0],+s[1],+s[2]);el.textContent=WD[col(j.jy,j.jm,j.jd)]+' '+fa(j.jd)+' '+M[j.jm-1];}});
          /* persian numerals */
          (function(){var P='۰۱۲۳۴۵۶۷۸۹',sk={SCRIPT:1,STYLE:1,SELECT:1,OPTION:1,INPUT:1,TEXTAREA:1};function f(s){return s.replace(/[0-9]/g,function(d){return P[+d];}).replace(/([۰-۹])[,،]([۰-۹])/g,'$1٬$2').replace(/([۰-۹])[,،]([۰-۹])/g,'$1٬$2');}function w(n){for(var c=n.firstChild;c;c=c.nextSibling){if(c.nodeType===3){if(/[0-9]/.test(c.nodeValue))c.nodeValue=f(c.nodeValue);}else if(c.nodeType===1&&!sk[c.tagName]&&!c.hasAttribute('data-nofa'))w(c);}}w(document.body);})();
        })();
        </script>
        <?php
        Dorian_Panel::foot();
    }
}

/* ---------- routing: /<manage_slug> -> management panel ---------- */
/** Named so activation can register this rule without re-firing the whole `init` action. */
function dorian_manage_register_routes() {
    add_rewrite_tag('%dorian_manage%', '1');
    $slug = Dorian_Manage_slug();
    if ($slug) add_rewrite_rule('^' . preg_quote($slug, '#') . '/?$', 'index.php?dorian_manage=1', 'top');
}
add_action('init', 'dorian_manage_register_routes');
add_action('template_redirect', function () {
    if (get_query_var('dorian_manage')) { Dorian_Manage::handle(); exit; }
});
add_action('update_option_dorian_settings', function () { flush_rewrite_rules(); });

/** helper usable inside the init closure (class method is protected) */
function Dorian_Manage_slug() {
    $s = dorian_settings();
    return !empty($s['manage_slug']) ? sanitize_title($s['manage_slug']) : 'monshi';
}
