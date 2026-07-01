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
    return is_array($c) ? $c : array();  // ["Y-m-d", ...] full-day closes (incl. vacation ranges)
}
function dorian_provider_blocks($pid) {
    $b = get_post_meta($pid, '_dorian_blocks', true);
    return is_array($b) ? $b : array();  // ["Y-m-d|HH:MM-HH:MM", ...] partial-day closes
}
function dorian_provider_cap($pid) {
    return max(0, (int) get_post_meta($pid, '_dorian_cap', true)); // 0 = unlimited
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

    $busy = array(); $count = 0;
    foreach (Dorian_DB::bookings_for(array($pid), $date_ymd) as $b) {
        $t = strtotime($b->start_dt);
        $from = (int) date('G', $t) * 60 + (int) date('i', $t);
        $busy[] = array($from, $from + (int) $b->duration_min);
        $count++;
    }
    // partial-day blocks ("this day, only this range") act like busy intervals
    foreach (dorian_provider_blocks($pid) as $blk) {
        $parts = explode('|', $blk);
        if (count($parts) !== 2 || $parts[0] !== $date_ymd || strpos($parts[1], '-') === false) continue;
        list($ba, $bb) = explode('-', $parts[1], 2);
        $busy[] = array(dorian_hm($ba), dorian_hm($bb));
    }
    // daily cap: once reached, no more slots are offered that day
    $cap = dorian_provider_cap($pid);
    $cap_reached = ($cap > 0 && $count >= $cap);
    $now = ($date_ymd === current_time('Y-m-d')) ? ((int) current_time('G') * 60 + (int) current_time('i')) : -1;

    $out = array();
    foreach ($ranges as $r) {
        if (strpos($r, '-') === false) continue;
        list($a, $b2) = explode('-', $r, 2);
        $s = dorian_hm($a); $e = dorian_hm($b2);
        for ($m = $s; $m + $dur <= $e + 1; $m += $dur) {
            $free = ($m > $now) && !$cap_reached;
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

        // this page is login/cookie-gated; never let a page-cache plugin or CDN serve a stale copy of it
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();

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

        // update a booking's status (done / no-show / cancel / undo)
        if (isset($_POST['dorian_status']) && check_admin_referer('dorian_panel_' . $pid)) {
            self::set_status($pid, sanitize_text_field($_POST['dorian_status']));
            wp_safe_redirect(strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }
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

        // closed dates (Gregorian Y-m-d, comma separated, sent by the JS calendar — includes vacation ranges)
        $closed = array();
        foreach (explode(',', (string) ($_POST['closed'] ?? '')) as $d) {
            $d = trim($d);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $closed[] = $d;
        }
        update_post_meta($pid, '_dorian_closed', array_values(array_unique($closed)));

        // partial-day blocks ("Y-m-d|HH:MM-HH:MM", comma separated)
        $blocks = array();
        foreach (explode(',', (string) ($_POST['blocks'] ?? '')) as $b) {
            $b = trim($b);
            if (preg_match('/^\d{4}-\d{2}-\d{2}\|\d{1,2}:\d{2}-\d{1,2}:\d{2}$/', $b)) $blocks[] = $b;
        }
        update_post_meta($pid, '_dorian_blocks', array_values(array_unique($blocks)));

        // daily cap (0 = unlimited)
        update_post_meta($pid, '_dorian_cap', max(0, (int) ($_POST['cap'] ?? 0)));
    }

    /** Update a booking status; only if the booking belongs to this provider. */
    protected static function set_status($pid, $val) {
        $parts = explode(':', $val);
        if (count($parts) !== 2) return;
        $id = (int) $parts[0]; $st = $parts[1];
        if (!$id || !in_array($st, array('confirmed', 'done', 'noshow', 'cancelled'), true)) return;
        global $wpdb;
        $t = Dorian_DB::table();
        $owns = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE id=%d AND FIND_IN_SET(%d, provider_ids)", $id, $pid));
        if ($owns) $wpdb->update($t, array('status' => $st), array('id' => $id));
    }

    /** Realized income (from bookings marked "done") over the last N days, incl. today. */
    protected static function revenue($pid, $days) {
        global $wpdb;
        $t = Dorian_DB::table();
        $since = date('Y-m-d 00:00:00', strtotime('-' . (max(1, (int) $days) - 1) . ' day', current_time('timestamp')));
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) c, COALESCE(SUM(total_price),0) s FROM $t
             WHERE status='done' AND FIND_IN_SET(%d, provider_ids) AND start_dt >= %s",
            $pid, $since
        ));
        return array('c' => (int) $row->c, 's' => (int) $row->s);
    }

    /* ---- bookings for a provider on a Y-m-d (all statuses, for display) ---- */
    protected static function bookings_on($pid, $ymd) {
        global $wpdb;
        $t = Dorian_DB::table();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $t WHERE DATE(start_dt)=%s AND FIND_IN_SET(%d, provider_ids) ORDER BY start_dt ASC",
            $ymd, $pid
        ));
    }

    /* ---- all upcoming bookings for a provider from a Y-m-d onward (for the "آینده" tab) ---- */
    protected static function upcoming($pid, $from_ymd, $limit = 200) {
        global $wpdb;
        $t = Dorian_DB::table();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $t WHERE DATE(start_dt) >= %s AND FIND_IN_SET(%d, provider_ids) ORDER BY start_dt ASC LIMIT %d",
            $from_ymd, $pid, $limit
        ));
    }

    /* ---- one booking row (shared by the day tabs and the upcoming tab) ---- */
    protected static function render_row($r, $labels) {
        $st = isset($labels[$r->status]) ? $r->status : 'confirmed';
        $lb = $labels[$st];
        $h  = '<div class="bk bk--' . esc_attr($st) . '"><span class="time">' . esc_html(date('H:i', strtotime($r->start_dt))) . '</span>'
            . '<span><span class="who">' . esc_html($r->customer_name) . '</span><br><span class="svc">' . esc_html($r->service_names) . ' · ' . (int) $r->duration_min . '′</span></span>'
            . '<span class="badge" style="background:' . esc_attr($lb[1]) . '">' . esc_html($lb[0]) . '</span>'
            . '<a class="tel" href="tel:' . esc_attr($r->customer_phone) . '">' . esc_html($r->customer_phone) . '</a>'
            . '<span class="acts">';
        if ($st === 'confirmed' || $st === 'paid') {
            $h .= '<button class="mini ok" name="dorian_status" value="' . (int) $r->id . ':done">✓ انجام شد</button>'
                . '<button class="mini no" name="dorian_status" value="' . (int) $r->id . ':noshow">نیامد</button>'
                . '<button class="mini cx" name="dorian_status" value="' . (int) $r->id . ':cancelled">لغو</button>';
        } else {
            $h .= '<button class="mini undo" name="dorian_status" value="' . (int) $r->id . ':confirmed">↺ بازگردانی</button>';
        }
        return $h . '</span></div>';
    }

    protected static function head($title) {
        ?><!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#DFD2BF">
        <title><?php echo esc_html($title); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
        @font-face{font-family:'Pinar';src:url('<?php echo DORIAN_URL; ?>assets/fonts/Pinar-VF.woff2') format('woff2');font-weight:100 900;font-display:swap}
        :root{
          --blue:#0066B3;--blue-2:#1E86D6;--gold:#C9A227;
          --ink:#1c160c;--muted:#736a58;--line:rgba(28,22,12,.10);
          --card:#FEFCF7;--ok:#1f8a4c;--no:#c0392b;
          --rad:20px;--shadow:0 10px 30px -18px rgba(40,30,10,.45),0 2px 6px -4px rgba(40,30,10,.22)
        }
        *{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%}
        body{margin:0;font-family:'Pinar',Tahoma,sans-serif;background:radial-gradient(140% 120% at 50% 0%,#EEE6D4,#E1D5C0 55%,#D6C8AD);background-attachment:fixed;color:var(--ink);line-height:1.85;min-height:100vh;font-size:16px;-webkit-font-smoothing:antialiased}
        img{max-width:100%}
        a{color:var(--blue)}
        :focus-visible{outline:3px solid rgba(30,134,214,.55);outline-offset:2px;border-radius:8px}
        .pw{width:100%;max-width:720px;margin:0 auto;padding:0 14px calc(96px + env(safe-area-inset-bottom))}
        .pcard{background:var(--card);border:1px solid var(--line);border-radius:var(--rad);padding:18px 16px;margin-bottom:14px;box-shadow:var(--shadow)}

        /* app bar */
        .appbar{position:sticky;top:0;z-index:30;display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 -14px 16px;padding:calc(10px + env(safe-area-inset-top)) 16px 12px;background:rgba(254,252,247,.82);backdrop-filter:saturate(1.4) blur(14px);-webkit-backdrop-filter:saturate(1.4) blur(14px);border-bottom:1px solid var(--line)}
        .appbar__id{display:flex;align-items:center;gap:12px;min-width:0}
        .appbar__ava{width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 4px 12px -6px rgba(40,30,10,.6);flex:0 0 auto}
        .appbar__meta{min-width:0}
        .appbar__meta h1{font-size:1.2rem;margin:0;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .role{display:inline-block;font-family:'Space Grotesk';letter-spacing:.14em;font-size:.66rem;color:var(--blue);text-transform:uppercase;margin-top:1px}
        .iconbtn{display:inline-flex;align-items:center;gap:6px;min-height:40px;padding:.35em 1em;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);font-family:inherit;font-weight:700;font-size:.85rem;text-decoration:none;flex:0 0 auto}
        .iconbtn:active{transform:translateY(1px)}

        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:48px;padding:.5em 1.4em;border-radius:14px;border:1px solid transparent;font-family:inherit;font-weight:700;font-size:.98rem;cursor:pointer;text-decoration:none;transition:transform .12s ease,box-shadow .2s ease,filter .2s ease}
        .btn:active{transform:translateY(1px)}
        .btn--blue{background:linear-gradient(135deg,var(--blue-2),var(--blue));color:#fff;box-shadow:0 14px 26px -14px rgba(0,102,179,.95)}
        .btn--blue:hover{filter:brightness(1.05)}
        .btn--ghost{background:#fff;border-color:var(--line);color:var(--ink);border-radius:999px}
        .btn--ghost:hover{background:#fbfbfb}

        h2{font-size:1.08rem;margin:0 0 12px;position:relative;padding-inline-start:13px}
        h2::before{content:"";position:absolute;inset-inline-start:0;top:.18em;bottom:.18em;width:4px;border-radius:4px;background:linear-gradient(var(--blue-2),var(--blue))}
        .hint{color:var(--muted);font-size:.85rem;margin:0 0 12px;line-height:1.7}
        .hint code{background:rgba(0,0,0,.05);padding:.1em .4em;border-radius:5px;direction:ltr;display:inline-block}

        /* segmented control */
        .seg{display:flex;gap:4px;background:#ece2d0;border:1px solid var(--line);border-radius:14px;padding:4px;margin-bottom:16px}
        .tab{flex:1 1 0;min-width:0;min-height:40px;padding:.3em .4em;border-radius:11px;border:none;background:transparent;cursor:pointer;font-family:inherit;font-size:.9rem;font-weight:600;color:var(--muted);white-space:nowrap;transition:background .18s,color .18s,box-shadow .18s}
        .tab.on{background:#fff;color:var(--blue);box-shadow:0 2px 8px -2px rgba(40,30,10,.28);font-weight:700}
        .day{display:none}.day.on{display:block}

        .stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
        .stat{background:#faf6ee;border:1px solid var(--line);border-radius:12px;padding:.45em .95em;font-size:.82rem;line-height:1.5}
        .stat b{font-family:'Space Grotesk';color:var(--blue);font-size:.98rem}
        .stat--ok b{color:var(--ok)}.stat--no b{color:var(--no)}.stat--rev b{color:#a8862a}

        .bk{position:relative;display:grid;grid-template-columns:auto 1fr auto;grid-template-areas:"time who badge" "tel tel tel" "acts acts acts";gap:8px 12px;align-items:center;padding:14px;border:1px solid var(--line);border-inline-start:4px solid var(--blue);border-radius:14px;background:#fff;margin-bottom:10px;box-shadow:0 6px 16px -14px rgba(40,30,10,.6)}
        .bk .time{grid-area:time;justify-self:start;font-family:'Space Grotesk';font-weight:700;color:var(--blue);font-size:1.02rem;background:#eef6fd;border-radius:9px;padding:.15em .6em}
        .bk>span:nth-child(2){grid-area:who;min-width:0}
        .bk .who{font-weight:700}.bk .svc{color:var(--muted);font-size:.85rem}
        .bk .badge{grid-area:badge;color:#fff;border-radius:999px;padding:.25em .85em;font-size:.72rem;white-space:nowrap;justify-self:end}
        .bk .tel{grid-area:tel;color:var(--blue);text-decoration:none;font-family:'Space Grotesk';font-weight:600}
        .bk--confirmed{border-inline-start-color:var(--blue)}
        .bk--paid{border-inline-start-color:var(--ok)}
        .bk--done{border-inline-start-color:var(--ok);background:#f4fbf6}
        .bk--noshow{border-inline-start-color:var(--no);background:#fdf5f4}
        .bk--cancelled{border-inline-start-color:#c2c2c2;opacity:.6}
        .bk--cancelled .who{text-decoration:line-through}
        .acts{grid-area:acts;display:flex;gap:6px;flex-wrap:wrap;margin-top:2px}
        .mini{min-height:40px;border:1px solid var(--line);background:#fff;border-radius:11px;padding:.3em 1em;font-family:inherit;font-size:.8rem;font-weight:600;cursor:pointer;transition:background .15s,transform .1s}
        .mini:active{transform:translateY(1px)}
        .mini.ok{color:var(--ok);border-color:#bfe3ca}.mini.ok:hover{background:#f1fbf4}
        .mini.no{color:var(--no);border-color:#e8c4c4}.mini.no:hover{background:#fdf5f4}
        .mini.cx{color:#8a8a8a}.mini.undo{color:var(--blue)}
        .empty{color:var(--muted);padding:18px 0;text-align:center}
        .dhead{display:flex;align-items:center;gap:8px;margin:18px 0 10px;font-weight:700;color:var(--ink);font-size:.92rem}
        .dhead::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--gold);flex:0 0 auto}
        .dhead::after{content:"";flex:1;height:1px;background:var(--line)}
        .dhead:first-child{margin-top:0}

        input,select{font-family:inherit;font-size:16px;color:var(--ink)}

        /* weekly working hours (option-based) */
        .wh{display:flex;flex-direction:column;gap:10px}
        .wh-day{border:1px solid var(--line);border-radius:14px;background:#fff;padding:12px 14px;transition:box-shadow .2s}
        .wh-day.on{box-shadow:0 6px 16px -14px rgba(40,30,10,.6)}
        .wh-head{display:flex;align-items:center;gap:12px}
        .wh-name{font-weight:700;min-width:60px}
        .wh-sum{color:var(--muted);font-family:'Space Grotesk';font-size:.85rem;direction:ltr;margin-inline-start:auto;text-align:left}
        .wh-day.on .wh-sum{display:none}
        .switch{position:relative;display:inline-flex;flex:0 0 auto;width:48px;height:28px;cursor:pointer}
        .switch input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer}
        .switch .knob{position:absolute;inset:0;border-radius:999px;background:#d8cdb8;transition:background .2s}
        .switch .knob::before{content:"";position:absolute;top:3px;inset-inline-start:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:transform .2s}
        .switch input:checked+.knob{background:linear-gradient(135deg,var(--blue-2),var(--blue))}
        .switch input:checked+.knob::before{transform:translateX(-20px)}
        .wh-body{margin-top:12px;display:flex;flex-direction:column;gap:8px}
        .wh-range{display:flex;align-items:center;gap:8px}
        .wh-lbl{color:var(--muted);font-size:.85rem;flex:0 0 auto}
        .wh-range select{flex:1 1 80px;min-width:0;min-height:44px;padding:8px 6px;border:1px solid var(--line);border-radius:10px;background:#fff;font-family:'Space Grotesk';direction:ltr;text-align:center}
        .wh-rm{flex:0 0 auto;width:40px;min-height:40px;border:1px solid var(--line);background:#fff;border-radius:10px;color:var(--no);cursor:pointer;font-size:.9rem}
        .wh-tools{display:flex;gap:8px;flex-wrap:wrap;margin-top:2px}
        .wh-add,.wh-copy{min-height:38px;border-radius:10px;padding:.3em 1em;font-family:inherit;font-weight:600;font-size:.82rem;cursor:pointer}
        .wh-add{border:1px dashed var(--blue-2);background:#eef6fd;color:var(--blue)}
        .wh-copy{border:1px solid var(--line);background:#faf6ee;color:var(--muted)}
        .svcrow{display:grid;grid-template-columns:1fr 96px 78px;gap:8px;align-items:center;margin-bottom:8px}
        .svcrow input{min-height:44px;padding:8px 10px;border:1px solid var(--line);border-radius:10px;font-family:'Space Grotesk';direction:ltr;background:#fff;text-align:center}
        .svcrow .nm{font-weight:600;font-size:.9rem}
        .capf{width:140px;min-height:44px;padding:8px 12px;border:1px solid var(--line);border-radius:10px;font-family:'Space Grotesk';direction:ltr;background:#fff}

        .blkadd{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
        .blkadd select{min-height:44px;padding:8px 12px;border:1px solid var(--line);border-radius:10px;font-family:inherit;background:#fff;flex:1 1 130px}
        .blkadd input{flex:0 0 84px;width:84px;min-height:44px;padding:8px 10px;border:1px solid var(--line);border-radius:10px;font-family:'Space Grotesk';direction:ltr;background:#fff;text-align:center}
        .blklist{display:flex;flex-direction:column;gap:6px}
        .blkitem{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:8px 12px;font-size:.9rem}
        .blkitem .rm{border:none;background:none;color:var(--no);cursor:pointer;font-size:1.1rem;min-width:32px;min-height:32px}

        #vac{margin-bottom:12px}
        #vac.on{background:linear-gradient(135deg,var(--blue-2),var(--blue));color:#fff;border-color:transparent}
        .calwrap{max-width:360px;margin:0 auto}
        .calnav{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
        .calnav b{font-size:1rem}
        .calnav button{width:40px;height:40px;border-radius:12px;border:1px solid var(--line);background:#fff;cursor:pointer;font-size:1.2rem;color:var(--blue);display:flex;align-items:center;justify-content:center}
        .calnav button:hover{background:#f5f5f5}
        .cal{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}
        .cal .h{text-align:center;font-size:.72rem;color:var(--muted);font-weight:600;padding-bottom:4px}
        .cal .c{display:flex;align-items:center;justify-content:center;aspect-ratio:1;border:1px solid var(--line);border-radius:10px;background:#fff;cursor:pointer;font-family:'Space Grotesk';font-size:.9rem;transition:background .15s,transform .1s}
        .cal .c:not(.empty):not(.past):hover{background:#eef6fd;border-color:var(--blue-2)}
        .cal .c:active{transform:scale(.94)}
        .cal .c.empty{background:none;border-color:transparent;cursor:default}
        .cal .c.past{opacity:.4;cursor:not-allowed;background:#f4efe4}
        .cal .c.today{border-color:var(--gold);box-shadow:inset 0 0 0 1px var(--gold)}
        .cal .c.closed{background:var(--no);color:#fff;border-color:transparent}
        .cal .c.selstart{background:var(--gold);color:#fff;border-color:transparent}
        .callegend{display:flex;gap:16px;flex-wrap:wrap;justify-content:center;margin-top:14px;font-size:.78rem;color:var(--muted)}
        .callegend span{display:inline-flex;align-items:center;gap:6px}
        .callegend i{width:14px;height:14px;border-radius:4px;display:inline-block}
        .lg-closed{background:var(--no)}.lg-today{border:1.5px solid var(--gold)}

        .saved{background:#d9efe0;border:1px solid #9ecfa8;color:#1c5a2e;padding:10px 14px;border-radius:12px;margin-bottom:14px;font-weight:600}
        .savebar{position:sticky;bottom:0;z-index:20;margin:8px -14px 0;padding:12px 14px calc(12px + env(safe-area-inset-bottom));background:linear-gradient(180deg,rgba(225,213,192,0),rgba(225,213,192,.92) 55%);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
        .btn--save{width:100%}

        .login{max-width:380px;margin:10vh auto}
        .login input{width:100%;min-height:50px;padding:12px 14px;border:1px solid var(--line);border-radius:12px;margin:8px 0;font-size:16px;text-align:center;background:#fff}
        .err{color:var(--no);font-weight:600}

        @media (min-width:640px){
          .pw{padding:0 20px 60px}
          .pcard{padding:22px 24px;margin-bottom:18px}
          .appbar{padding-top:16px;padding-bottom:14px}
          .appbar__meta h1{font-size:1.35rem}
          h2{font-size:1.18rem}
          .bk{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
          .bk .badge{margin-inline-start:auto}
          .acts{width:100%}
          .svcrow{grid-template-columns:1fr 130px 110px;gap:10px}
          .savebar{position:static;margin:0;padding:0;background:none;backdrop-filter:none}
          .btn--save{width:auto}
        }
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
            <label for="passcode" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">رمز ورود</label>
            <input id="passcode" type="password" name="passcode" placeholder="رمز" autocomplete="current-password" inputmode="numeric" autofocus>
            <button class="btn btn--blue" name="dorian_login" value="1" style="width:100%">ورود</button>
          </form>
        </div><?php
        self::foot();
    }

    protected static function panel_page($provider) {
        $pid = $provider->ID;
        $role = get_post_meta($pid, '_dorian_role', true);
        $photo = get_the_post_thumbnail_url($pid, 'thumbnail');
        if (!$photo) $photo = DORIAN_URL . 'assets/avatar.svg';
        $days = array('امروز', 'فردا', 'پس‌فردا');
        self::head('پنل ' . $provider->post_title);
        ?>
        <header class="appbar">
          <div class="appbar__id">
            <img class="appbar__ava" src="<?php echo esc_url($photo); ?>" alt="">
            <div class="appbar__meta"><h1><?php echo esc_html($provider->post_title); ?></h1><span class="role"><?php echo esc_html($role); ?></span></div>
          </div>
          <a class="iconbtn" href="?logout=1"><span aria-hidden="true">⎋</span> خروج</a>
        </header>

        <?php if (isset($_GET['saved'])) echo '<div class="saved">تغییرات ذخیره شد.</div>'; ?>

        <div class="pcard">
          <h2>نوبت‌ها</h2>
          <div class="seg">
            <?php foreach ($days as $i => $d) echo '<button type="button" class="tab' . ($i === 0 ? ' on' : '') . '" data-day="' . $i . '">' . esc_html($d) . '</button>'; ?>
            <button type="button" class="tab" data-day="up">آینده</button>
          </div>
          <?php
          $labels = array(
              'confirmed' => array('تأییدشده', '#0066B3'),
              'paid'      => array('پرداخت‌شده', '#1f8a4c'),
              'done'      => array('انجام شد', '#1f8a4c'),
              'noshow'    => array('نیامد', '#b23b3b'),
              'cancelled' => array('لغو شد', '#9a9a9a'),
          );
          foreach ($days as $i => $d) {
              $ymd = date('Y-m-d', strtotime("+$i day", current_time('timestamp')));
              echo '<div class="day' . ($i === 0 ? ' on' : '') . '" data-day="' . $i . '">';
              $rows = self::bookings_on($pid, $ymd);

              // daily stats
              $c_total = 0; $c_done = 0; $c_no = 0; $rev = 0;
              foreach ($rows as $r) {
                  if ($r->status !== 'cancelled') $c_total++;
                  if ($r->status === 'done') { $c_done++; $rev += (int) $r->total_price; }
                  if ($r->status === 'noshow') $c_no++;
              }
              echo '<div class="stats">'
                 . '<span class="stat"><b>' . $c_total . '</b> نوبت</span>'
                 . '<span class="stat stat--ok"><b>' . $c_done . '</b> انجام‌شده</span>'
                 . '<span class="stat stat--no"><b>' . $c_no . '</b> عدم‌حضور</span>'
                 . '<span class="stat stat--rev"><b>' . number_format($rev) . '</b> تومان درآمد</span></div>';

              if (!$rows) { echo '<p class="empty">نوبتی برای این روز ثبت نشده.</p>'; echo '</div>'; continue; }

              echo '<form method="post">';
              wp_nonce_field('dorian_panel_' . $pid);
              foreach ($rows as $r) echo self::render_row($r, $labels);
              echo '</form>';
              echo '</div>';
          }

          // upcoming tab: everything from the 4th day onward, grouped by date
          $fut = self::upcoming($pid, date('Y-m-d', strtotime('+3 day', current_time('timestamp'))));
          echo '<div class="day" data-day="up">';
          if (!$fut) {
              echo '<p class="empty">نوبتی برای روزهای بعد ثبت نشده.</p>';
          } else {
              echo '<form method="post">';
              wp_nonce_field('dorian_panel_' . $pid);
              $cur = '';
              foreach ($fut as $r) {
                  $d = date('Y-m-d', strtotime($r->start_dt));
                  if ($d !== $cur) {
                      $cur = $d;
                      echo '<div class="dhead"><span class="jdate" data-ymd="' . esc_attr($d) . '">' . esc_html($d) . '</span></div>';
                  }
                  echo self::render_row($r, $labels);
              }
              echo '</form>';
          }
          echo '</div>';
          ?>
        </div>

        <?php $rw = self::revenue($pid, 7); $rm = self::revenue($pid, 30); ?>
        <div class="pcard">
          <h2>گزارش درآمد</h2>
          <div class="stats">
            <span class="stat stat--rev"><b><?php echo number_format($rw['s']); ?></b> تومان · هفتگی (۷ روز اخیر، <?php echo $rw['c']; ?> نوبت)</span>
            <span class="stat stat--rev"><b><?php echo number_format($rm['s']); ?></b> تومان · ماهانه (۳۰ روز اخیر، <?php echo $rm['c']; ?> نوبت)</span>
          </div>
          <p class="hint" style="margin:10px 0 0">درآمد بر اساس نوبت‌هایی که آن‌ها را «انجام شد» علامت زده‌اید محاسبه می‌شود.</p>
        </div>

        <form method="post">
          <?php wp_nonce_field('dorian_panel_' . $pid); ?>

          <div class="pcard">
            <h2>ساعت کاری هفتگی</h2>
            <p class="hint">روزهای کاری را روشن کنید و ساعتِ شروع و پایان را از لیست انتخاب کنید. برای هر روز می‌توانید چند بازه اضافه کنید (مثلاً صبح و عصر). روز خاموش یعنی تعطیل.</p>
            <div id="wh" class="wh"></div>
            <?php
              $hours = dorian_provider_hours($pid);
              for ($w = 0; $w < 7; $w++) {
                  $val = isset($hours[$w]) && is_array($hours[$w]) ? implode(',', $hours[$w]) : '';
                  echo '<input type="hidden" name="hours[' . $w . ']" id="wh_' . $w . '" value="' . esc_attr($val) . '">';
              }
            ?>
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
            <h2>سقف نوبت روزانه</h2>
            <p class="hint">حداکثر تعداد نوبت در هر روز. وقتی پر شد، دیگر ساعت خالی نمایش داده نمی‌شود. صفر یعنی نامحدود.</p>
            <input type="number" min="0" name="cap" class="capf" value="<?php echo esc_attr(dorian_provider_cap($pid)); ?>">
          </div>

          <div class="pcard">
            <h2>بستن بازهٔ ساعتی یک روز</h2>
            <p class="hint">اگر روزی فقط بخشی از آن نیستید (مثلاً فقط بعدازظهر)، روز و بازهٔ ساعت را انتخاب و «افزودن» بزنید. کل روز را از تقویم پایین ببندید.</p>
            <div class="blkadd">
              <select id="blkDay"></select>
              <input type="text" id="blkFrom" placeholder="18:00">
              <span>تا</span>
              <input type="text" id="blkTo" placeholder="20:00">
              <button type="button" class="btn btn--ghost" id="blkAdd">افزودن</button>
            </div>
            <div class="blklist" id="blkList"></div>
            <input type="hidden" name="blocks" id="blocks" value="<?php echo esc_attr(implode(',', dorian_provider_blocks($pid))); ?>">
          </div>

          <div class="pcard">
            <h2>روزهای تعطیل و مرخصی</h2>
            <p class="hint">روی هر روز کلیک کنید تا کل آن روز تعطیل شود (قرمز). برای مرخصیِ چندروزه، «حالت مرخصی» را بزنید و سپس ابتدا و انتهای بازه را کلیک کنید.</p>
            <button type="button" class="btn btn--ghost" id="vac">🏖️ حالت مرخصی (بازهٔ چندروزه)</button>
            <div class="calwrap">
              <div class="calnav"><button type="button" id="cprev" aria-label="ماه قبل">‹</button><b id="ctitle">—</b><button type="button" id="cnext" aria-label="ماه بعد">›</button></div>
              <div class="cal" id="cal"></div>
              <div class="callegend"><span><i class="lg-closed"></i> تعطیل</span><span><i class="lg-today"></i> امروز</span></div>
            </div>
            <input type="hidden" name="closed" id="closed" value="<?php echo esc_attr(implode(',', dorian_provider_closed($pid))); ?>">
          </div>

          <div class="savebar"><button class="btn btn--blue btn--save" name="dorian_save" value="1">ذخیرهٔ تنظیمات</button></div>
        </form>

        <script>
        // tabs
        document.querySelectorAll('.tab').forEach(function(t){t.addEventListener('click',function(){
          document.querySelectorAll('.tab').forEach(x=>x.classList.remove('on'));
          document.querySelectorAll('.day').forEach(x=>x.classList.remove('on'));
          t.classList.add('on'); document.querySelector('.day[data-day="'+t.dataset.day+'"]').classList.add('on');
        });});
        (function(){
          /* ---- jalaali core (shared) ---- */
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
          var WD=["شنبه","یک‌شنبه","دوشنبه","سه‌شنبه","چهارشنبه","پنج‌شنبه","جمعه"];
          function fa(n){return String(n).replace(/[0-9]/g,d=>"۰۱۲۳۴۵۶۷۸۹"[d])}
          function p(n){return (n<10?'0':'')+n}
          function greg(jy,jm,jd){var g=d2g(j2d(jy,jm,jd));return g.gy+'-'+p(g.gm)+'-'+p(g.gd)}
          function ymdOf(jdn){var g=d2g(jdn);return g.gy+'-'+p(g.gm)+'-'+p(g.gd)}
          function jlabel(ymd){var s=ymd.split('-'),j=g2j(+s[0],+s[1],+s[2]);return WD[col(j.jy,j.jm,j.jd)]+' '+fa(j.jd)+' '+M[j.jm-1]}

          /* ---- closed-days + vacation calendar ---- */
          var closedEl=document.getElementById('closed');
          var closed=new Set((closedEl.value||'').split(',').filter(Boolean));
          var td=new Date(),tj=g2j(td.getFullYear(),td.getMonth()+1,td.getDate()),view={jy:tj.jy,jm:tj.jm},tdn=j2d(tj.jy,tj.jm,tj.jd);
          var grid=document.getElementById('cal');
          var rangeMode=false,rangeStart=null,vac=document.getElementById('vac');
          function commit(){closedEl.value=[...closed].join(',')}
          if(vac)vac.addEventListener('click',function(){rangeMode=!rangeMode;rangeStart=null;vac.classList.toggle('on',rangeMode);render()});
          function render(){
            document.getElementById('ctitle').textContent=M[view.jm-1]+' '+fa(view.jy);
            grid.innerHTML='';["ش","ی","د","س","چ","پ","ج"].forEach(h=>{var e=document.createElement('div');e.className='h';e.textContent=h;grid.appendChild(e)});
            var lead=col(view.jy,view.jm,1);for(var i=0;i<lead;i++){var e=document.createElement('div');e.className='c empty';grid.appendChild(e)}
            var len=ml(view.jy,view.jm);
            for(var dd=1;dd<=len;dd++){(function(day){
              var jdn=j2d(view.jy,view.jm,day),g=greg(view.jy,view.jm,day),c=document.createElement('div');c.className='c';c.textContent=fa(day);
              if(jdn<tdn){c.className+=' past'}
              else{
                if(jdn===tdn)c.className+=' today';
                if(closed.has(g))c.className+=' closed';
                if(rangeMode&&rangeStart===jdn)c.className+=' selstart';
                c.addEventListener('click',function(){
                  if(rangeMode){
                    if(rangeStart===null){rangeStart=jdn;c.classList.add('selstart')}
                    else{var a=Math.min(rangeStart,jdn),b=Math.max(rangeStart,jdn);for(var x=a;x<=b;x++)closed.add(ymdOf(x));rangeStart=null;rangeMode=false;if(vac)vac.classList.remove('on');commit();render()}
                  }else{
                    if(closed.has(g)){closed.delete(g);c.classList.remove('closed')}else{closed.add(g);c.classList.add('closed')}commit()
                  }
                })
              }
              grid.appendChild(c)})(dd)}
          }
          document.getElementById('cprev').addEventListener('click',function(){view.jm--;if(view.jm<1){view.jm=12;view.jy--}render()});
          document.getElementById('cnext').addEventListener('click',function(){view.jm++;if(view.jm>12){view.jm=1;view.jy++}render()});
          render();

          /* ---- Jalaali date headers in the "upcoming" tab ---- */
          document.querySelectorAll('.jdate').forEach(function(el){ if(el.dataset.ymd) el.textContent=jlabel(el.dataset.ymd); });

          /* ---- partial-day time blocks ---- */
          var blkEl=document.getElementById('blocks');
          if(blkEl){
            var blocks=new Set((blkEl.value||'').split(',').filter(Boolean));
            var sel=document.getElementById('blkDay');
            for(var i=0;i<45;i++){var dd=new Date();dd.setDate(dd.getDate()+i);var g=dd.getFullYear()+'-'+p(dd.getMonth()+1)+'-'+p(dd.getDate());var o=document.createElement('option');o.value=g;o.textContent=jlabel(g);sel.appendChild(o)}
            function isHM(v){return /^\d{1,2}:\d{2}$/.test(v)}
            function padHM(v){var s=v.split(':');return p(+s[0])+':'+p(+s[1])}
            function renderBlocks(){
              var list=document.getElementById('blkList');list.innerHTML='';
              if(!blocks.size){list.innerHTML='<p class="hint" style="margin:0">بازه‌ای بسته نشده.</p>';return}
              [...blocks].sort().forEach(function(b){
                var parts=b.split('|'),item=document.createElement('div');item.className='blkitem';
                item.innerHTML='<span>'+jlabel(parts[0])+' · <b dir="ltr">'+parts[1]+'</b></span>';
                var x=document.createElement('button');x.type='button';x.className='rm';x.textContent='✕';
                x.addEventListener('click',function(){blocks.delete(b);blkEl.value=[...blocks].join(',');renderBlocks()});
                item.appendChild(x);list.appendChild(item)
              })
            }
            document.getElementById('blkAdd').addEventListener('click',function(){
              var d=sel.value,f=document.getElementById('blkFrom').value.trim(),t=document.getElementById('blkTo').value.trim();
              if(!d||!isHM(f)||!isHM(t)){alert('ساعت را به صورت 18:00 وارد کنید.');return}
              blocks.add(d+'|'+padHM(f)+'-'+padHM(t));blkEl.value=[...blocks].join(',');
              document.getElementById('blkFrom').value='';document.getElementById('blkTo').value='';renderBlocks()
            });
            renderBlocks();
          }
        })();

        /* ---- weekly working hours: option-based picker ---- */
        (function(){
          var wrap=document.getElementById('wh');
          if(!wrap) return;
          var WD=["شنبه","یک‌شنبه","دوشنبه","سه‌شنبه","چهارشنبه","پنج‌شنبه","جمعه"];
          var TIMES=[];
          for(var t=6*60;t<=24*60;t+=30){var h=Math.floor(t/60),m=t%60;TIMES.push((h<10?'0':'')+h+':'+(m<10?'0':'')+m);}
          var DEF=['12:00','20:00'];
          function idx(v){return TIMES.indexOf(v);}
          function opts(cur){return TIMES.map(function(t){return '<option'+(t===cur?' selected':'')+'>'+t+'</option>';}).join('');}
          function hid(w){return document.getElementById('wh_'+w);}

          // build model from the hidden inputs
          var model=[];
          for(var w=0;w<7;w++){
            var rs=(hid(w).value||'').split(/[،,]/).map(function(s){return s.trim();}).filter(Boolean)
                    .map(function(r){var pp=r.split('-');return [pp[0],pp[1]];})
                    .filter(function(pp){return idx(pp[0])>=0&&idx(pp[1])>=0&&idx(pp[1])>idx(pp[0]);});
            model[w]={on:rs.length>0, ranges:rs.length?rs:[DEF.slice()]};
          }
          function commit(w){var mm=model[w];hid(w).value=mm.on?mm.ranges.map(function(r){return r[0]+'-'+r[1];}).join('،'):'';}
          function summary(w){var mm=model[w];return mm.on?mm.ranges.map(function(r){return r[0]+'-'+r[1];}).join('  ·  '):'تعطیل';}

          function render(){
            wrap.innerHTML='';
            for(var w=0;w<7;w++){(function(w){
              var mm=model[w];
              var day=document.createElement('div');day.className='wh-day'+(mm.on?' on':'');
              var head=document.createElement('div');head.className='wh-head';
              head.innerHTML='<label class="switch"><input type="checkbox"'+(mm.on?' checked':'')+' aria-label="'+WD[w]+'"><span class="knob"></span></label>'
                +'<span class="wh-name">'+WD[w]+'</span><span class="wh-sum">'+summary(w)+'</span>';
              head.querySelector('input').addEventListener('change',function(e){mm.on=e.target.checked;if(mm.on&&!mm.ranges.length)mm.ranges=[DEF.slice()];commit(w);render();});
              day.appendChild(head);
              if(mm.on){
                var body=document.createElement('div');body.className='wh-body';
                mm.ranges.forEach(function(r,ri){
                  var row=document.createElement('div');row.className='wh-range';
                  row.innerHTML='<span class="wh-lbl">از</span><select class="wh-from">'+opts(r[0])+'</select>'
                    +'<span class="wh-lbl">تا</span><select class="wh-to">'+opts(r[1])+'</select>'
                    +'<button type="button" class="wh-rm" aria-label="حذف بازه">✕</button>';
                  var fs=row.querySelector('.wh-from'),ts=row.querySelector('.wh-to');
                  fs.addEventListener('change',function(){r[0]=fs.value;if(idx(r[1])<=idx(r[0])){r[1]=TIMES[Math.min(idx(r[0])+1,TIMES.length-1)];ts.value=r[1];}commit(w);day.querySelector('.wh-sum').textContent=summary(w);});
                  ts.addEventListener('change',function(){r[1]=ts.value;if(idx(r[1])<=idx(r[0])){r[0]=TIMES[Math.max(idx(r[1])-1,0)];fs.value=r[0];}commit(w);day.querySelector('.wh-sum').textContent=summary(w);});
                  row.querySelector('.wh-rm').addEventListener('click',function(){mm.ranges.splice(ri,1);if(!mm.ranges.length)mm.on=false;commit(w);render();});
                  body.appendChild(row);
                });
                var tools=document.createElement('div');tools.className='wh-tools';
                var add=document.createElement('button');add.type='button';add.className='wh-add';add.textContent='+ افزودن بازه';
                add.addEventListener('click',function(){mm.ranges.push(['18:00','20:00']);commit(w);render();});
                var cp=document.createElement('button');cp.type='button';cp.className='wh-copy';cp.textContent='اعمال به همهٔ روزها';
                cp.addEventListener('click',function(){for(var x=0;x<7;x++){model[x]={on:mm.on,ranges:mm.ranges.map(function(r){return r.slice();})};commit(x);}render();});
                tools.appendChild(add);tools.appendChild(cp);
                body.appendChild(tools);
                day.appendChild(body);
              }
              wrap.appendChild(day);
            })(w);}
          }
          for(var w2=0;w2<7;w2++)commit(w2); // normalize hidden values
          render();
        })();
        </script>
        <?php
        self::foot();
    }
}
