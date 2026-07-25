<?php
/**
 * پاک‌سازیِ اجباریِ پوشه‌های جامانده از افزونهٔ دوریان.
 *
 * وقتی وردپرس می‌گوید «پوشهٔ هدف از قبل موجود است» ولی حذف از پیشخان اثر ندارد،
 * یعنی نصبِ قبلی نیمه‌کاره مانده و PHP اجازهٔ حذف آن را ندارد. این اسکریپت همان
 * حذف را با هویتِ خودِ PHP انجام می‌دهد و دقیقاً می‌گوید کجا شکست خورده است.
 *
 * روش استفاده:
 *   ۱. این فایل را کنار wp-config.php (ریشهٔ وردپرس) آپلود کنید.
 *   ۲. با کاربر مدیر وارد پیشخان شوید، بعد https://example.com/dorian-cleanup.php را باز کنید.
 *   ۳. اول گزارش را ببینید، بعد روی «حذف کن» بزنید.
 *   ۴. *** بعد از کار، این فایل را از سرور پاک کنید. ***
 */

require_once __DIR__ . '/wp-load.php';

if (!is_user_logged_in() || !current_user_can('activate_plugins')) {
    wp_die('برای اجرای این ابزار باید با کاربر مدیر وارد شده باشید.');
}

$plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';

/** پوشه‌هایی که به افزونهٔ دوریان مربوط‌اند (نامِ ناقص/تکراری هم گرفته می‌شود). */
function dorian_cleanup_targets($plugins_dir)
{
    $out = array();
    foreach ((array) glob($plugins_dir . '/*', GLOB_ONLYDIR) as $dir) {
        $name = basename($dir);
        // فقط نام‌هایی که با dorian شروع می‌شوند — نه چیز دیگری.
        if (preg_match('/^dorian[-_]?core/i', $name) || preg_match('/^dorian/i', $name)) {
            $out[] = $dir;
        }
    }
    return $out;
}

/** حذفِ بازگشتی؛ آرایه‌ای از مسیرهایی که حذفشان شکست خورد برمی‌گرداند. */
function dorian_cleanup_rmdir($path, &$failed)
{
    if (is_link($path) || is_file($path)) {
        if (!@unlink($path)) $failed[] = $path;
        return;
    }
    if (!is_dir($path)) return;

    foreach ((array) scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        dorian_cleanup_rmdir($path . '/' . $entry, $failed);
    }
    if (!@rmdir($path)) $failed[] = $path;
}

$targets = dorian_cleanup_targets($plugins_dir);
$doing   = isset($_POST['go']) && check_admin_referer('dorian_cleanup');

header('Content-Type: text/html; charset=utf-8');
echo '<meta charset="utf-8"><body style="font:14px/2 Tahoma,sans-serif;direction:rtl;max-width:760px;margin:40px auto">';
echo '<h2>پاک‌سازی افزونهٔ دوریان</h2>';
echo '<p>مسیر افزونه‌ها: <code>' . esc_html($plugins_dir) . '</code></p>';
echo '<p>کاربر PHP: <code>' . esc_html(function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? posix_getpwuid(posix_geteuid())['name'] : 'نامشخص') . '</code></p>';

if (!$targets) {
    echo '<p><b>هیچ پوشهٔ دوریانی باقی نمانده.</b> حالا می‌توانید نسخهٔ جدید را نصب کنید.</p>';
    echo '</body>';
    return;
}

echo '<h3>پوشه‌های پیداشده</h3><ul>';
foreach ($targets as $t) {
    $writable = is_writable($t) && is_writable(dirname($t));
    echo '<li><code>' . esc_html(basename($t)) . '</code> — '
        . ($writable ? 'قابل حذف ✔' : '<b style="color:#b00">اجازهٔ نوشتن ندارد ✘</b>')
        . ' (owner: ' . esc_html(function_exists('posix_getpwuid') ? @posix_getpwuid(@fileowner($t))['name'] : @fileowner($t))
        . '، سطح دسترسی: ' . esc_html(substr(sprintf('%o', @fileperms($t)), -4)) . ')</li>';
}
echo '</ul>';

if (!$doing) {
    echo '<form method="post">';
    wp_nonce_field('dorian_cleanup');
    echo '<button name="go" value="1" style="padding:10px 22px;background:#b00;color:#fff;border:0;border-radius:6px;cursor:pointer">حذف کن</button>';
    echo '</form>';
    echo '</body>';
    return;
}

$failed = array();
foreach ($targets as $t) {
    dorian_cleanup_rmdir($t, $failed);
}

// کشِ فهرست افزونه‌ها را خالی کن تا پیشخان نسخهٔ قدیمی را نشان ندهد.
wp_cache_delete('plugins', 'plugins');
if (function_exists('wp_clean_plugins_cache')) wp_clean_plugins_cache(true);

if ($failed) {
    echo '<h3 style="color:#b00">این‌ها حذف نشدند</h3><ul>';
    foreach ($failed as $f) echo '<li><code>' . esc_html($f) . '</code></li>';
    echo '</ul><p>یعنی مالکِ این فایل‌ها با کاربرِ PHP فرق دارد. از فایل‌منیجرِ '
        . 'کنترل‌پنل هاست (DirectAdmin) یا از پشتیبانی هاست بخواهید حذفشان کنند.</p>';
} else {
    echo '<h3 style="color:#080">همه حذف شدند ✔</h3>'
        . '<p>حالا از پیشخان → افزونه‌ها → افزودن → بارگذاری، فایل <code>dorian-core.zip</code> را نصب کنید.</p>';
}
echo '<p style="margin-top:30px;padding:12px;background:#ffe;border:1px solid #dd0">'
    . '<b>مهم:</b> همین حالا این فایل (<code>dorian-cleanup.php</code>) را از سرور پاک کنید.</p>';
echo '</body>';
