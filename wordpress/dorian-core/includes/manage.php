<?php
if (!defined('ABSPATH')) exit;

/* =========================================================================
   Management / reception oversight panel  (dorianstudio.ir/<manage_slug>)
   — passcode protected, monitors all providers, manages per-provider prices
   ========================================================================= */

class Dorian_Manage {

    protected static function slug()  { $s = dorian_settings(); return !empty($s['manage_slug']) ? sanitize_title($s['manage_slug']) : 'modir'; }
    protected static function pass()  { $s = dorian_settings(); return isset($s['manage_pass']) ? (string) $s['manage_pass'] : ''; }
    protected static function ckey()  { return 'dmanage'; }
    protected static function token() { return wp_hash('dmanage|' . self::pass()); }
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
            if (hash_equals($pass, (string) ($_POST['passcode'] ?? ''))) {
                setcookie(self::ckey(), self::token(), time() + 86400 * 7, '/');
                wp_safe_redirect(remove_query_arg('x')); exit;
            } else { $err = 'رمز اشتباه است.'; }
        }
        if (isset($_GET['logout'])) {
            setcookie(self::ckey(), '', time() - 3600, '/');
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
        Dorian_Panel::head('ورود مدیریت — دوریان');
        ?><div class="pcard login">
          <h1 style="text-align:center;margin:0 0 4px">پنل مدیریت</h1>
          <p class="hint" style="text-align:center">برای ورود به پنل نظارتی، رمز مدیریت را وارد کنید.</p>
          <?php if ($err) echo '<p class="err" style="text-align:center">' . esc_html($err) . '</p>'; ?>
          <form method="post">
            <label for="passcode" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">رمز مدیریت</label>
            <input id="passcode" type="password" name="passcode" placeholder="رمز مدیریت" autocomplete="current-password" autofocus>
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
        $t_total = 0; $t_done = 0; $byprov = array();
        foreach ($trows as $r) {
            if ($r->status !== 'cancelled') $t_total++;
            if ($r->status === 'done') $t_done++;
            foreach (explode(',', $r->provider_ids) as $pid) { $pid = (int) $pid; if (!$pid) continue; if ($r->status !== 'cancelled') $byprov[$pid] = (isset($byprov[$pid]) ? $byprov[$pid] : 0) + 1; }
        }
        $sv = isset($_GET['saved']) ? 'price' : 'dash';

        Dorian_Panel::head('پنل مدیریت دوریان');
        ?>
        <div class="splash" id="splash"><div class="splash__box"><div class="splash__ava" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1E86D6,#0066B3);color:#fff;font-size:2rem">م</div><div class="splash__bar"><i></i></div></div></div>
        <header class="appbar">
          <div class="appbar__id">
            <span class="appbar__ava" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1E86D6,#0066B3);color:#fff;font-weight:700">م</span>
            <div class="appbar__meta"><h1>پنل مدیریت</h1><span class="role">Management · نظارت</span></div>
          </div>
          <a class="iconbtn" href="?logout=1"><span aria-hidden="true">⎋</span> خروج</a>
        </header>

        <?php if (isset($_GET['saved'])) echo '<div class="saved">قیمت‌ها ذخیره شد.</div>'; ?>

        <main class="views">

          <section class="view<?php echo $sv === 'dash' ? ' on' : ''; ?>" data-view="dash">
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
              <?php
              if (!$provs) echo '<p class="empty">متخصصی ثبت نشده.</p>';
              foreach ($provs as $p) {
                  $c = isset($byprov[$p->ID]) ? $byprov[$p->ID] : 0;
                  $slug = get_post_meta($p->ID, '_dorian_slug', true);
                  echo '<div class="prow"><span class="prow__nm">' . esc_html($p->post_title) . '</span>'
                     . '<span class="prow__c">' . $c . ' نوبت</span>'
                     . ($slug ? '<a class="prow__go" href="' . esc_url(home_url('/' . $slug)) . '" target="_blank" rel="noopener">پنل ↗</a>' : '') . '</div>';
              }
              ?>
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
              <h2>قیمت خدمات هر متخصص</h2>
              <p class="hint">قیمت هر خدمت را برای هر متخصص اینجا تعیین کنید. متخصص‌ها نمی‌توانند قیمت خود را تغییر دهند.</p>
            </div>
            <?php
            if (!$provs) echo '<div class="pcard"><p class="empty">متخصصی ثبت نشده.</p></div>';
            foreach ($provs as $p) {
                $svcs = dorian_provider_services($p->ID);
                echo '<div class="pcard"><h3 class="pmgr">' . esc_html($p->post_title) . '</h3>';
                if (!$svcs) { echo '<p class="hint" style="margin:0">خدمتی به این متخصص اختصاص داده نشده.</p></div>'; continue; }
                echo '<div class="svcrow" style="font-size:.78rem;color:var(--muted)"><span>خدمت</span><span>قیمت (تومان)</span><span>مدت</span></div>';
                foreach ($svcs as $sid => $s) {
                    echo '<div class="svcrow"><span class="nm">' . esc_html($s['name']) . '</span>'
                       . '<input type="number" min="0" step="5000" name="price[' . $p->ID . '][' . $sid . ']" value="' . esc_attr((int) $s['price']) . '">'
                       . '<span class="svc-price">' . (int) $s['dur'] . '′</span></div>';
                }
                echo '</div>';
            }
            ?>
            <div class="savebar"><button class="btn btn--blue btn--save" name="dmanage_prices" value="1">ذخیرهٔ قیمت‌ها</button></div>
          </form>

        </main>

        <nav class="tabbar" aria-label="بخش‌های مدیریت">
          <button type="button" class="tabbtn<?php echo $sv === 'dash' ? ' on' : ''; ?>" data-view="dash"><span class="ic"><?php echo Dorian_Panel::icon('dash'); ?></span>پیشخوان</button>
          <button type="button" class="tabbtn<?php echo $sv === 'book' ? ' on' : ''; ?>" data-view="book"><span class="ic"><?php echo Dorian_Panel::icon('book'); ?></span>نوبت‌ها</button>
          <button type="button" class="tabbtn<?php echo $sv === 'rev' ? ' on' : ''; ?>" data-view="rev"><span class="ic"><?php echo Dorian_Panel::icon('rev'); ?></span>درآمد</button>
          <button type="button" class="tabbtn<?php echo $sv === 'price' ? ' on' : ''; ?>" data-view="price"><span class="ic"><?php echo Dorian_Panel::icon('coin'); ?></span>قیمت‌ها</button>
        </nav>

        <style>
        .prow{display:flex;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid var(--line)}
        .prow:last-child{border-bottom:0}
        .prow__nm{font-weight:700}
        .prow__c{margin-inline-start:auto;background:#faf6ee;border:1px solid var(--line);border-radius:999px;padding:.2em .9em;font-size:.82rem;color:var(--muted)}
        .prow__go{color:var(--blue);text-decoration:none;font-weight:600;font-size:.85rem}
        .pmgr{font-size:1rem;margin:0 0 12px;color:var(--blue)}
        .pbar{margin-bottom:12px}
        .pbar__top{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:5px}
        .pbar__top b{font-family:'Space Grotesk';color:#a8862a}
        .pbar__track{height:9px;border-radius:999px;background:rgba(0,0,0,.06);overflow:hidden}
        .pbar__fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#e3c04a,#b8901f)}
        @media (prefers-color-scheme:dark){.prow__c{background:#231f19}.pbar__track{background:rgba(255,255,255,.08)}}
        </style>

        <script>
        (function(){
          var views=document.querySelectorAll('.view'),btns=document.querySelectorAll('.tabbtn');
          function sb(v){document.body.className=document.body.className.replace(/\bview-\S+/g,'').trim();document.body.classList.add('view-'+v);}
          function show(v){views.forEach(function(s){s.classList.toggle('on',s.dataset.view===v);});btns.forEach(function(b){b.classList.toggle('on',b.dataset.view===v);});sb(v);window.scrollTo(0,0);}
          btns.forEach(function(b){b.addEventListener('click',function(){show(b.dataset.view);});});
          var cur=document.querySelector('.view.on');sb(cur?cur.dataset.view:'dash');
          document.querySelectorAll('.tab').forEach(function(t){t.addEventListener('click',function(){document.querySelectorAll('.tab').forEach(x=>x.classList.remove('on'));document.querySelectorAll('.day').forEach(x=>x.classList.remove('on'));t.classList.add('on');var d=document.querySelector('.day[data-day="'+t.dataset.day+'"]');if(d)d.classList.add('on');});});
          var inp=document.getElementById('bkSearch');
          if(inp){var card=inp.closest('.pcard'),xb=document.getElementById('bkSearchX');
            function norm(s){return s.replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);}).toLowerCase().trim();}
            function ap(){var q=norm(inp.value);card.classList.toggle('searching',q.length>0);if(xb)xb.hidden=!q;var hits=0;card.querySelectorAll('.bk').forEach(function(bk){var sh=!q||(bk.dataset.q||'').indexOf(q)>=0;bk.classList.toggle('hidden',!sh);if(sh&&q)hits++;});var re=card.querySelector('.search-empty');if(q&&hits===0){if(!re){re=document.createElement('p');re.className='empty search-empty';re.textContent='نتیجه‌ای پیدا نشد.';card.appendChild(re);}re.hidden=false;}else if(re)re.hidden=true;}
            inp.addEventListener('input',ap);if(xb)xb.addEventListener('click',function(){inp.value='';ap();inp.focus();});}
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
add_action('init', function () {
    add_rewrite_tag('%dorian_manage%', '1');
    $slug = Dorian_Manage_slug();
    if ($slug) add_rewrite_rule('^' . preg_quote($slug, '#') . '/?$', 'index.php?dorian_manage=1', 'top');
});
add_action('template_redirect', function () {
    if (get_query_var('dorian_manage')) { Dorian_Manage::handle(); exit; }
});
add_action('update_option_dorian_settings', function () { flush_rewrite_rules(); });

/** helper usable inside the init closure (class method is protected) */
function Dorian_Manage_slug() {
    $s = dorian_settings();
    return !empty($s['manage_slug']) ? sanitize_title($s['manage_slug']) : 'modir';
}
