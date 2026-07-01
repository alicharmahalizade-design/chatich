<?php
if (!defined('ABSPATH')) exit;

/* =========================================================================
   Provider self-panel  (dorianstudio.ir/<slug>)  — passcode protected
   ========================================================================= */

/* ---------- provider schedule / services helpers ---------- */
function dorian_hm($s) { $p = explode(':', trim($s)); return ((int) $p[0]) * 60 + (int) ($p[1] ?? 0); }
function dorian_provider_by_slug($slug) {
    $q = get_posts(array('post_type' => 'dorian_provider', 'numberposts' => 1,
        'meta_key' => '_dorian_slug', 'meta_value' => sanitize_title($slug)));
    return $q ? $q[0] : null;
}
function dorian_provider_hours($pid) {
    $h = get_post_meta($pid, '_dorian_hours', true);
    return is_array($h) ? $h : array();  // [weekday 0=Sat..6=Fri => ["12:00-14:00", ...]]
}
function dorian_provider_closed($pid) {
    $c = get_post_meta($pid, '_dorian_closed', true);
    return is_array($c) ? $c : array();  // ["Y-m-d", ...]
}
function dorian_global_ranges() {
    $s = dorian_settings();
    return array($s['work_start'] . '-' . $s['work_end']);
}
/** Services this provider offers, with per-provider price + duration (fallback to service defaults). */
function dorian_provider_services($pid) {
    $svc = get_post_meta($pid, '_dorian_svc', true);
    if (!is_array($svc)) $svc = array();
    $out = array();
    foreach (get_posts(array('post_type' => 'dorian_service', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC')) as $s) {
        $allowed = array_map('intval', (array) get_post_meta($s->ID, '_dorian_providers', true));
        if (!in_array((int) $pid, $allowed, true)) continue;
        $g = wp_get_post_terms($s->ID, 'dorian_group', array('fields' => 'names'));
        $out[$s->ID] = array(
            'name'  => $s->post_title,
            'group' => $g ? $g[0] : '',
            'price' => isset($svc[$s->ID]['price']) ? (int) $svc[$s->ID]['price'] : (int) get_post_meta($s->ID, '_dorian_price', true),
            'dur'   => isset($svc[$s->ID]['dur'])   ? (int) $svc[$s->ID]['dur']   : ((int) get_post_meta($s->ID, '_dorian_duration', true) ?: 30),
        );
    }
    return $out;
}

/** Availability slots for ONE provider on a Gregorian date, given total duration. */
function dorian_provider_slots($pid, $date_ymd, $dur) {
    $dur = max(15, (int) $dur);
    if (in_array($date_ymd, dorian_provider_closed($pid), true)) return array();
    $ts = strtotime($date_ymd);
    $w  = ((int) date('w', $ts) + 1) % 7;                 // 0=Sat .. 6=Fri
    $hours = dorian_provider_hours($pid);
    $ranges = empty($hours) ? dorian_global_ranges() : (isset($hours[$w]) ? (array) $hours[$w] : array());

    $busy = array();
    foreach (Dorian_DB::bookings_for(array($pid), $date_ymd) as $b) {
        $t = strtotime($b->start_dt);
        $from = (int) date('G', $t) * 60 + (int) date('i', $t);
        $busy[] = array($from, $from + (int) $b->duration_min);
    }
    $now = ($date_ymd === current_time('Y-m-d')) ? ((int) current_time('G') * 60 + (int) current_time('i')) : -1;

    $out = array();
    foreach ($ranges as $r) {
        if (strpos($r, '-') === false) continue;
        list($a, $b2) = explode('-', $r, 2);
        $s = dorian_hm($a); $e = dorian_hm($b2);
        for ($m = $s; $m + $dur <= $e + 1; $m += $dur) {
            $free = ($m > $now);
            foreach ($busy as $x) { if ($m < $x[1] && ($m + $dur) > $x[0]) { $free = false; break; } }
            $out[] = array('t' => sprintf('%02d:%02d', intdiv($m, 60), $m % 60), 'free' => $free);
        }
    }
    usort($out, function ($x, $y) { return strcmp($x['t'], $y['t']); });
    return $out;
}

/** Provider-centric data for the booking form: each provider + their grouped services (own price/dur). */
function dorian_form_providers() {
    $out = array();
    foreach (get_posts(array('post_type' => 'dorian_provider', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC')) as $p) {
        $groups = array();
        foreach (dorian_provider_services($p->ID) as $sid => $s) {
            $g = $s['group'] !== '' ? $s['group'] : 'خدمات';
            if (!isset($groups[$g])) $groups[$g] = array();
            $groups[$g][] = array('id' => (string) $sid, 'name' => $s['name'], 'price' => (int) $s['price'], 'dur' => (int) $s['dur']);
        }
        if (!$groups) continue;
        $glist = array();
        foreach ($groups as $gn => $subs) $glist[] = array('name' => $gn, 'subs' => $subs);
        $img = get_the_post_thumbnail_url($p->ID, 'medium');
        if (!$img) $img = DORIAN_URL . 'assets/avatar.svg';
        $out[] = array(
            'id'     => (string) $p->ID,
            'name'   => $p->post_title,
            'role'   => (string) get_post_meta($p->ID, '_dorian_role', true),
            'photo'  => $img,
            'groups' => $glist,
        );
    }
    return $out;
}

/* ---------- rewrite: /<slug> -> panel ---------- */
add_action('init', function () {
    add_rewrite_tag('%dorian_pslug%', '([^&/]+)');
    foreach (get_posts(array('post_type' => 'dorian_provider', 'numberposts' => -1, 'fields' => 'ids')) as $pid) {
        $slug = get_post_meta($pid, '_dorian_slug', true);
        if ($slug) add_rewrite_rule('^' . preg_quote($slug, '#') . '/?$', 'index.php?dorian_pslug=' . $slug, 'top');
    }
});
add_action('save_post_dorian_provider', function () { flush_rewrite_rules(); });

/* ---------- render the panel ---------- */
add_action('template_redirect', function () {
    $slug = get_query_var('dorian_pslug');
    if (!$slug) return;
    $provider = dorian_provider_by_slug($slug);
    if (!$provider) return; // let WP 404
    Dorian_Panel::handle($provider);
    exit;
});

class Dorian_Panel {

    protected static function cookie_key($pid) { return 'dpanel_' . $pid; }
    protected static function token($pid) {
        $pass = (string) get_post_meta($pid, '_dorian_pass', true);
        return wp_hash($pid . '|' . $pass);
    }
    protected static function is_authed($pid) {
        $pass = (string) get_post_meta($pid, '_dorian_pass', true);
        if ($pass === '') return true; // no passcode set → open (admin can set one)
        return isset($_COOKIE[self::cookie_key($pid)]) && hash_equals(self::token($pid), $_COOKIE[self::cookie_key($pid)]);
    }

    public static function handle($provider) {
        $pid = $provider->ID;

        // login
        if (isset($_POST['dorian_login'])) {
            $pass = (string) get_post_meta($pid, '_dorian_pass', true);
            if (hash_equals($pass, (string) ($_POST['passcode'] ?? ''))) {
                setcookie(self::cookie_key($pid), self::token($pid), time() + 86400 * 7, '/');
                wp_safe_redirect(remove_query_arg('x')); exit;
            } else { $err = 'رمز اشتباه است.'; }
        }
        // logout
        if (isset($_GET['logout'])) {
            setcookie(self::cookie_key($pid), '', time() - 3600, '/');
            wp_safe_redirect(strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }

        if (!self::is_authed($pid)) { self::login_page($provider, isset($err) ? $err : ''); return; }

        // save settings
        if (isset($_POST['dorian_save']) && check_admin_referer('dorian_panel_' . $pid)) {
            self::save($pid);
            wp_safe_redirect(add_query_arg('saved', '1', strtok($_SERVER['REQUEST_URI'], '?'))); exit;
        }
        self::panel_page($provider);
    }

    protected static function save($pid) {
        // working hours per weekday (comma-separated ranges "12:00-14:00, 18:00-20:00")
        $hours = array();
        for ($w = 0; $w < 7; $w++) {
            $raw = isset($_POST['hours'][$w]) ? sanitize_text_field($_POST['hours'][$w]) : '';
            $ranges = array();
            foreach (preg_split('/[،,]/u', $raw) as $part) {
                $part = trim($part);
                if (preg_match('/^\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2}$/', $part)) $ranges[] = str_replace(' ', '', $part);
            }
            $hours[$w] = $ranges;
        }
        update_post_meta($pid, '_dorian_hours', $hours);

        // per-service price + duration
        $svc = array();
        if (!empty($_POST['svc']) && is_array($_POST['svc'])) {
            foreach ($_POST['svc'] as $sid => $vals) {
                $svc[(int) $sid] = array('price' => (int) ($vals['price'] ?? 0), 'dur' => max(15, (int) ($vals['dur'] ?? 30)));
            }
        }
        update_post_meta($pid, '_dorian_svc', $svc);

        // closed dates (Gregorian Y-m-d, comma separated, sent by the JS calendar)
        $closed = array();
        foreach (explode(',', (string) ($_POST['closed'] ?? '')) as $d) {
            $d = trim($d);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $closed[] = $d;
        }
        update_post_meta($pid, '_dorian_closed', array_values(array_unique($closed)));
    }

    /* ---- bookings for a provider on a Y-m-d ---- */
    protected static function bookings_on($pid, $ymd) {
        global $wpdb;
        $t = Dorian_DB::table();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $t WHERE DATE(start_dt)=%s AND status<>'cancelled' AND FIND_IN_SET(%d, provider_ids) ORDER BY start_dt ASC",
            $ymd, $pid
        ));
    }

    protected static function head($title) {
        ?><!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($title); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
        @font-face{font-family:'Pinar';src:url('<?php echo DORIAN_URL; ?>assets/fonts/Pinar-VF.woff2') format('woff2');font-weight:100 900;font-display:swap}
        :root{--blue:#0066B3;--gold:#C9A227;--ink:#1c160c;--muted:#736a59;--line:rgba(0,75,133,.16);--cream:#DFD2BF}
        *{box-sizing:border-box}
        body{margin:0;font-family:'Pinar',Tahoma,sans-serif;background:radial-gradient(120% 120% at 50% 0%,#E9DFCB,#DFD2BF 60%,#D3C4A8);color:var(--ink);line-height:1.9;min-height:100vh}
        .pw{width:min(880px,94%);margin:0 auto;padding:26px 0 60px}
        .pcard{background:linear-gradient(180deg,#FBF6EC,#F3E9D6);border:1px solid var(--line);border-radius:18px;padding:22px 24px;margin-bottom:18px;box-shadow:0 30px 70px -50px rgba(40,30,10,.5)}
        .ptop{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:6px}
        .ptop h1{font-size:1.5rem;margin:0}
        .ptop .role{font-family:'Space Grotesk';letter-spacing:.16em;font-size:.7rem;color:var(--blue)}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:.6em 1.3em;border-radius:999px;border:1px solid transparent;font-family:inherit;font-weight:700;font-size:.9rem;cursor:pointer;text-decoration:none}
        .btn--blue{background:linear-gradient(135deg,#1E86D6,#0066B3);color:#fff}
        .btn--ghost{background:transparent;border-color:var(--line);color:var(--ink)}
        .tabs{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
        .tab{padding:.5em 1.2em;border-radius:999px;border:1px solid var(--line);background:#fff;cursor:pointer;font-family:inherit;font-size:.9rem}
        .tab.on{background:linear-gradient(135deg,#1E86D6,#0066B3);color:#fff;border-color:transparent}
        .day{display:none}.day.on{display:block}
        .bk{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:#fff;margin-bottom:8px}
        .bk .time{font-family:'Space Grotesk';font-weight:700;color:var(--blue);min-width:56px}
        .bk .who{font-weight:700}.bk .svc{color:var(--muted);font-size:.9rem}
        .bk .tel{margin-inline-start:auto;color:var(--blue);text-decoration:none;font-family:'Space Grotesk'}
        .empty{color:var(--muted);padding:14px 0}
        table.hrs{width:100%;border-collapse:collapse}
        table.hrs td{padding:6px 4px;border-bottom:1px solid rgba(0,0,0,.05)}
        table.hrs input{width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-family:'Space Grotesk';direction:ltr}
        .svcrow{display:grid;grid-template-columns:1fr 130px 110px;gap:10px;align-items:center;margin-bottom:8px}
        .svcrow input{padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-family:'Space Grotesk';direction:ltr}
        .svcrow .nm{font-weight:600}
        .cal{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;max-width:340px}
        .cal .h{text-align:center;font-size:.72rem;color:var(--muted)}
        .cal .c{aspect-ratio:1;border:1px solid transparent;border-radius:8px;background:#fff;cursor:pointer;font-family:'Space Grotesk';font-size:.85rem}
        .cal .c.empty{background:none;cursor:default}.cal .c.past{opacity:.35;cursor:not-allowed}
        .cal .c.closed{background:#c0392b;color:#fff;border-color:transparent}
        .calnav{display:flex;align-items:center;gap:10px;margin-bottom:8px}
        .calnav button{width:30px;height:30px;border-radius:50%;border:1px solid var(--line);background:#fff;cursor:pointer}
        h2{font-size:1.1rem;margin:0 0 10px}
        .hint{color:var(--muted);font-size:.85rem;margin:0 0 12px}
        .saved{background:#d4edda;border:1px solid #9ecfa8;color:#20612f;padding:8px 14px;border-radius:10px;margin-bottom:14px}
        .login{max-width:360px;margin:12vh auto}.login input{width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;margin:8px 0;font-size:1rem}
        .err{color:#c0392b}
        </style></head><body><div class="pw"><?php
    }
    protected static function foot() { echo '</div></body></html>'; }

    protected static function login_page($provider, $err) {
        self::head('ورود — ' . $provider->post_title);
        ?><div class="pcard login">
          <h1 style="text-align:center;margin:0 0 4px"><?php echo esc_html($provider->post_title); ?></h1>
          <p class="hint" style="text-align:center">برای ورود به پنل، رمز خود را وارد کنید.</p>
          <?php if ($err) echo '<p class="err" style="text-align:center">' . esc_html($err) . '</p>'; ?>
          <form method="post">
            <input type="password" name="passcode" placeholder="رمز" autofocus>
            <button class="btn btn--blue" name="dorian_login" value="1" style="width:100%;justify-content:center">ورود</button>
          </form>
        </div><?php
        self::foot();
    }

    protected static function panel_page($provider) {
        $pid = $provider->ID;
        $role = get_post_meta($pid, '_dorian_role', true);
        $days = array('امروز', 'فردا', 'پس‌فردا');
        self::head('پنل ' . $provider->post_title);
        ?>
        <div class="pcard">
          <div class="ptop">
            <div><h1><?php echo esc_html($provider->post_title); ?></h1><span class="role"><?php echo esc_html($role); ?></span></div>
            <a class="btn btn--ghost" href="?logout=1">خروج</a>
          </div>
        </div>

        <?php if (isset($_GET['saved'])) echo '<div class="saved">تغییرات ذخیره شد.</div>'; ?>

        <div class="pcard">
          <h2>نوبت‌ها</h2>
          <div class="tabs">
            <?php foreach ($days as $i => $d) echo '<button type="button" class="tab' . ($i === 0 ? ' on' : '') . '" data-day="' . $i . '">' . esc_html($d) . '</button>'; ?>
          </div>
          <?php foreach ($days as $i => $d) {
              $ymd = date('Y-m-d', strtotime("+$i day", current_time('timestamp')));
              echo '<div class="day' . ($i === 0 ? ' on' : '') . '" data-day="' . $i . '">';
              $rows = self::bookings_on($pid, $ymd);
              if (!$rows) { echo '<p class="empty">نوبتی برای این روز ثبت نشده.</p>'; }
              foreach ($rows as $r) {
                  echo '<div class="bk"><span class="time">' . esc_html(date('H:i', strtotime($r->start_dt))) . '</span>'
                     . '<span><span class="who">' . esc_html($r->customer_name) . '</span><br><span class="svc">' . esc_html($r->service_names) . ' · ' . (int) $r->duration_min . '′</span></span>'
                     . '<a class="tel" href="tel:' . esc_attr($r->customer_phone) . '">' . esc_html($r->customer_phone) . '</a></div>';
              }
              echo '</div>';
          } ?>
        </div>

        <form method="post">
          <?php wp_nonce_field('dorian_panel_' . $pid); ?>

          <div class="pcard">
            <h2>ساعت کاری هفتگی</h2>
            <p class="hint">برای هر روز، بازه‌ها را با ویرگول جدا کنید. مثال: <code style="direction:ltr">12:00-14:00, 18:00-20:00</code> — خالی بگذارید یعنی آن روز تعطیل است.</p>
            <table class="hrs"><?php
              $wd = array('شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه');
              $hours = dorian_provider_hours($pid);
              foreach ($wd as $w => $name) {
                  $val = isset($hours[$w]) && is_array($hours[$w]) ? implode('، ', $hours[$w]) : '';
                  echo '<tr><td style="width:90px">' . esc_html($name) . '</td><td><input type="text" name="hours[' . $w . ']" value="' . esc_attr($val) . '" placeholder="12:00-14:00, 18:00-20:00"></td></tr>';
              }
            ?></table>
          </div>

          <div class="pcard">
            <h2>خدمات (قیمت و زمان)</h2>
            <p class="hint">قیمت و مدتِ هر خدمت مختصِ شماست. اسلات‌ها بر اساس مدتِ خدمت ساخته می‌شوند.</p>
            <div class="svcrow" style="font-size:.8rem;color:var(--muted)"><span>خدمت</span><span>قیمت (تومان)</span><span>مدت (دقیقه)</span></div>
            <?php foreach (dorian_provider_services($pid) as $sid => $s) {
                echo '<div class="svcrow"><span class="nm">' . esc_html($s['name']) . ($s['group'] ? ' <small style="color:var(--muted)">· ' . esc_html($s['group']) . '</small>' : '') . '</span>'
                   . '<input type="number" name="svc[' . $sid . '][price]" value="' . esc_attr($s['price']) . '">'
                   . '<input type="number" step="5" name="svc[' . $sid . '][dur]" value="' . esc_attr($s['dur']) . '"></div>';
            } ?>
          </div>

          <div class="pcard">
            <h2>روزهای تعطیل</h2>
            <p class="hint">روی روزهایی که نیستید کلیک کنید (قرمز = تعطیل).</p>
            <div class="calnav"><button type="button" id="cprev">‹</button><b id="ctitle">—</b><button type="button" id="cnext">›</button></div>
            <div class="cal" id="cal"></div>
            <input type="hidden" name="closed" id="closed" value="<?php echo esc_attr(implode(',', dorian_provider_closed($pid))); ?>">
          </div>

          <button class="btn btn--blue" name="dorian_save" value="1">ذخیرهٔ تنظیمات</button>
        </form>

        <script>
        // tabs
        document.querySelectorAll('.tab').forEach(function(t){t.addEventListener('click',function(){
          document.querySelectorAll('.tab').forEach(x=>x.classList.remove('on'));
          document.querySelectorAll('.day').forEach(x=>x.classList.remove('on'));
          t.classList.add('on'); document.querySelector('.day[data-day="'+t.dataset.day+'"]').classList.add('on');
        });});
        // jalaali closed-days calendar
        (function(){
          function tr(a,b){return Math.trunc(a/b)} function md(a,b){return a-Math.trunc(a/b)*b}
          function jc(jy){var br=[-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178],gy=jy+621,lj=-14,jp=br[0],jm,ju=0,lp,n,i;for(i=1;i<br.length;i++){jm=br[i];ju=jm-jp;if(jy<jm)break;lj+=tr(ju,33)*8+tr(md(ju,33),4);jp=jm}n=jy-jp;lj+=tr(n,33)*8+tr(md(n,33)+3,4);if(md(ju,33)===4&&ju-n===4)lj++;var lg=tr(gy,4)-tr((tr(gy,100)+1)*3,4)-150,mr=20+lj-lg;if(ju-n<6)n=n-ju+tr(ju+4,33)*33;lp=md(md(n+1,33)-1,4);if(lp===-1)lp=4;return{leap:lp,gy:gy,march:mr}}
          function g2d(gy,gm,gd){var d=tr((gy+tr(gm-8,6)+100100)*1461,4)+tr(153*md(gm+9,12)+2,5)+gd-34840408;d=d-tr(tr(gy+100100+tr(gm-8,6),100)*3,4)+752;return d}
          function d2g(j){var i,k,gd,gm,gy;k=4*j+139361631;k=k+tr(tr(4*j+183187720,146097)*3,4)*4-3908;i=tr(md(k,1461),4)*5+308;gd=tr(md(i,153),5)+1;gm=md(tr(i,153),12)+1;gy=tr(k,1461)-100100+tr(8-gm,6);return{gy:gy,gm:gm,gd:gd}}
          function j2d(jy,jm,jd){var r=jc(jy);return g2d(r.gy,3,r.march)+(jm-1)*31-tr(jm,7)*(jm-7)+jd-1}
          function d2j(j){var gy=d2g(j).gy,jy=gy-621,r=jc(jy),f=g2d(gy,3,r.march),k=j-f,jm,jd;if(k>=0){if(k<=185){jm=1+tr(k,31);jd=md(k,31)+1;return{jy:jy,jm:jm,jd:jd}}else k-=186}else{jy--;k+=179;if(r.leap===1)k++}jm=7+tr(k,30);jd=md(k,30)+1;return{jy:jy,jm:jm,jd:jd}}
          function g2j(gy,gm,gd){return d2j(g2d(gy,gm,gd))}
          function ml(jy,jm){if(jm<=6)return 31;if(jm<=11)return 30;return jc(jy).leap===0?30:29}
          function col(jy,jm,jd){var g=d2g(j2d(jy,jm,jd));return (new Date(g.gy,g.gm-1,g.gd).getDay()+1)%7}
          var M=["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
          function fa(n){return String(n).replace(/[0-9]/g,d=>"۰۱۲۳۴۵۶۷۸۹"[d])}
          function p(n){return (n<10?'0':'')+n}
          function greg(jy,jm,jd){var g=d2g(j2d(jy,jm,jd));return g.gy+'-'+p(g.gm)+'-'+p(g.gd)}
          var closedEl=document.getElementById('closed');
          var closed=new Set((closedEl.value||'').split(',').filter(Boolean));
          var td=new Date(),tj=g2j(td.getFullYear(),td.getMonth()+1,td.getDate()),view={jy:tj.jy,jm:tj.jm},tdn=j2d(tj.jy,tj.jm,tj.jd);
          var grid=document.getElementById('cal');
          function render(){
            document.getElementById('ctitle').textContent=M[view.jm-1]+' '+fa(view.jy);
            grid.innerHTML='';["ش","ی","د","س","چ","پ","ج"].forEach(h=>{var e=document.createElement('div');e.className='h';e.textContent=h;grid.appendChild(e)});
            var lead=col(view.jy,view.jm,1);for(var i=0;i<lead;i++){var e=document.createElement('div');e.className='c empty';grid.appendChild(e)}
            var len=ml(view.jy,view.jm);
            for(var dd=1;dd<=len;dd++){(function(day){var jdn=j2d(view.jy,view.jm,day),g=greg(view.jy,view.jm,day),c=document.createElement('div');c.className='c';c.textContent=fa(day);
              if(jdn<tdn){c.className+=' past'}else{if(closed.has(g))c.className+=' closed';c.addEventListener('click',function(){if(closed.has(g)){closed.delete(g);c.classList.remove('closed')}else{closed.add(g);c.classList.add('closed')}closedEl.value=[...closed].join(',')})}
              grid.appendChild(c)})(dd)}
          }
          document.getElementById('cprev').addEventListener('click',function(){view.jm--;if(view.jm<1){view.jm=12;view.jy--}render()});
          document.getElementById('cnext').addEventListener('click',function(){view.jm++;if(view.jm>12){view.jm=1;view.jy++}render()});
          render();
        })();
        </script>
        <?php
        self::foot();
    }
}
