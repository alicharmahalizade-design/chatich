<?php
if (!defined('ABSPATH')) exit;

class Dorian_Settings {

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register'));
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=dorian_service', 'تنظیمات رزرو دوریان', 'تنظیمات', 'manage_options', 'dorian-settings', array(__CLASS__, 'page'));
        add_submenu_page('edit.php?post_type=dorian_service', 'نوبت‌های ثبت‌شده', 'نوبت‌ها', 'manage_options', 'dorian-bookings', array(__CLASS__, 'bookings_page'));
    }

    public static function register() {
        register_setting('dorian_settings_group', 'dorian_settings', array(__CLASS__, 'sanitize'));
    }

    public static function sanitize($in) {
        $out = dorian_settings();
        $txt = function ($k) use ($in, &$out) { if (isset($in[$k])) $out[$k] = sanitize_text_field($in[$k]); };
        foreach (array('work_start','work_end','currency','sms_apikey','sms_sender','sms_base','sms_mode','pay_mode','pay_amount',
            'sms_pattern_customer','sms_pattern_provider','sms_pattern_reminder') as $k) $txt($k);
        foreach (array('tpl_customer','tpl_provider','tpl_reminder') as $k) if (isset($in[$k])) $out[$k] = wp_kses_post($in[$k]);
        if (isset($in['pay_amount']) && !in_array($in['pay_amount'], array('full','deposit'), true)) $out['pay_amount'] = 'full';
        if (isset($in['credit_codes'])) $out['credit_codes'] = sanitize_textarea_field($in['credit_codes']);
        $out['slot_min']       = max(15, (int) ($in['slot_min'] ?? 30));
        $out['deposit_rate']   = min(100, max(0, (int) ($in['deposit_rate'] ?? 30)));
        $out['reminder_hours'] = max(0, (int) ($in['reminder_hours'] ?? 3));
        $out['sms_enabled']    = !empty($in['sms_enabled']) ? 1 : 0;
        $out['off_days']       = isset($in['off_days']) ? array_map('intval', (array) $in['off_days']) : array();
        return $out;
    }

    public static function page() {
        $s = dorian_settings();
        $days = array('شنبه','یک‌شنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه');
        ?>
        <div class="wrap"><h1>تنظیمات رزرو دوریان</h1>
        <form method="post" action="options.php"><?php settings_fields('dorian_settings_group'); ?>
        <table class="form-table" role="presentation">
          <tr><th>ساعت شروع کار</th><td><input type="time" name="dorian_settings[work_start]" value="<?php echo esc_attr($s['work_start']); ?>"></td></tr>
          <tr><th>ساعت پایان کار</th><td><input type="time" name="dorian_settings[work_end]" value="<?php echo esc_attr($s['work_end']); ?>"></td></tr>
          <tr><th>طول هر نوبت (دقیقه)</th><td><input type="number" step="15" name="dorian_settings[slot_min]" value="<?php echo esc_attr($s['slot_min']); ?>"></td></tr>
          <tr><th>روزهای تعطیل</th><td><?php foreach ($days as $i => $d) {
              $c = in_array($i, array_map('intval', $s['off_days']), true) ? 'checked' : '';
              echo '<label style="margin-inline-end:12px"><input type="checkbox" name="dorian_settings[off_days][]" value="' . $i . '" ' . $c . '> ' . $d . '</label>';
          } ?></td></tr>
          <tr><th>مبلغ پرداخت آنلاین</th><td>
            <select name="dorian_settings[pay_amount]">
              <option value="full" <?php selected($s['pay_amount'],'full'); ?>>پرداخت کامل (کل مبلغ نوبت)</option>
              <option value="deposit" <?php selected($s['pay_amount'],'deposit'); ?>>فقط بیعانه (درصد زیر)</option>
            </select>
            <p class="description">اگر «پرداخت کامل» باشد، کل مبلغ نوبت هنگام رزرو پرداخت می‌شود.</p></td></tr>
          <tr><th>درصد بیعانه</th><td><input type="number" min="0" max="100" name="dorian_settings[deposit_rate]" value="<?php echo esc_attr($s['deposit_rate']); ?>"> ٪
            <p class="description">فقط وقتی «مبلغ پرداخت آنلاین» روی «فقط بیعانه» باشد استفاده می‌شود.</p></td></tr>
          <tr><th>یادآوری چند ساعت قبل</th><td><input type="number" min="0" name="dorian_settings[reminder_hours]" value="<?php echo esc_attr($s['reminder_hours']); ?>"></td></tr>
          <tr><th>واحد پول</th><td><input type="text" name="dorian_settings[currency]" value="<?php echo esc_attr($s['currency']); ?>"></td></tr>
          <tr><th>روش پرداخت</th><td>
            <select name="dorian_settings[pay_mode]">
              <option value="woocommerce" <?php selected($s['pay_mode'],'woocommerce'); ?>>WooCommerce (پرداخت به‌صورت سفارش)</option>
              <option value="none" <?php selected($s['pay_mode'],'none'); ?>>بدون پرداخت آنلاین (تأیید مستقیم)</option>
            </select></td></tr>
          <tr><th>کدهای اعتبار</th><td>
            <textarea name="dorian_settings[credit_codes]" rows="4" style="width:420px" placeholder="هر کد در یک خط، مثلاً:&#10;VIP1404&#10;GUEST-DORIAN"><?php echo esc_textarea($s['credit_codes']); ?></textarea>
            <p class="description">هر خط یک کد. مشتری‌ای که هنگام رزرو یکی از این کدها را وارد کند، بدون پرداخت آنلاین نوبتش ثبت می‌شود و پرداخت را <strong>هنگام حضور در آرایشگاه</strong> انجام می‌دهد.</p></td></tr>
        </table>

        <h2>پنل پیامک (FarazSMS / IPPanel)</h2>
        <table class="form-table" role="presentation">
          <tr><th>فعال‌سازی پیامک</th><td><label><input type="checkbox" name="dorian_settings[sms_enabled]" value="1" <?php checked($s['sms_enabled'],1); ?>> ارسال پیامک فعال باشد</label></td></tr>
          <tr><th>API Key</th><td><input type="text" name="dorian_settings[sms_apikey]" value="<?php echo esc_attr($s['sms_apikey']); ?>" style="width:420px"></td></tr>
          <tr><th>شمارهٔ خط (Sender)</th><td><input type="text" name="dorian_settings[sms_sender]" value="<?php echo esc_attr($s['sms_sender']); ?>" placeholder="+983000..."></td></tr>
          <tr><th>آدرس پایه API</th><td><input type="text" name="dorian_settings[sms_base]" value="<?php echo esc_attr($s['sms_base']); ?>" style="width:420px"></td></tr>
          <tr><th>حالت ارسال</th><td>
            <select name="dorian_settings[sms_mode]">
              <option value="message" <?php selected($s['sms_mode'],'message'); ?>>متن ساده (قالب‌های زیر)</option>
              <option value="pattern" <?php selected($s['sms_mode'],'pattern'); ?>>پترن (کدهای زیر)</option>
            </select></td></tr>
          <tr><th>قالب پیامکِ مشتری</th><td><textarea name="dorian_settings[tpl_customer]" rows="2" style="width:560px"><?php echo esc_textarea($s['tpl_customer']); ?></textarea></td></tr>
          <tr><th>قالب پیامکِ متخصص</th><td><textarea name="dorian_settings[tpl_provider]" rows="2" style="width:560px"><?php echo esc_textarea($s['tpl_provider']); ?></textarea></td></tr>
          <tr><th>قالب پیامکِ یادآوری</th><td><textarea name="dorian_settings[tpl_reminder]" rows="2" style="width:560px"><?php echo esc_textarea($s['tpl_reminder']); ?></textarea>
            <p class="description">جایگزین‌ها: <code>{name} {phone} {date} {time} {service} {provider}</code></p></td></tr>
          <tr><th>کدهای پترن (در صورت حالت پترن)</th><td>
            مشتری: <input type="text" name="dorian_settings[sms_pattern_customer]" value="<?php echo esc_attr($s['sms_pattern_customer']); ?>"><br>
            متخصص: <input type="text" name="dorian_settings[sms_pattern_provider]" value="<?php echo esc_attr($s['sms_pattern_provider']); ?>"><br>
            یادآوری: <input type="text" name="dorian_settings[sms_pattern_reminder]" value="<?php echo esc_attr($s['sms_pattern_reminder']); ?>">
          </td></tr>
        </table>

        <p><strong>نمایش فرم:</strong> در هر برگه/پست از شورت‌کد <code>[dorian_booking]</code> یا ویجتِ «رزرو دوریان» در المنتور استفاده کنید.</p>
        <?php submit_button(); ?>
        </form></div>
        <?php
    }

    public static function bookings_page() {
        global $wpdb;
        $table = Dorian_DB::table();
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY start_dt DESC LIMIT 200");
        echo '<div class="wrap"><h1>نوبت‌های ثبت‌شده</h1><table class="widefat striped"><thead><tr>
            <th>#</th><th>مشتری</th><th>موبایل</th><th>خدمات</th><th>زمان</th><th>مدت</th><th>تکرار</th><th>مبلغ</th><th>پرداخت آنلاین</th><th>کد اعتبار</th><th>وضعیت</th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="11">هنوز نوبتی ثبت نشده.</td></tr>';
        $status_fa = array('confirmed' => 'ثبت‌شده', 'paid' => 'پرداخت‌شده', 'at_salon' => 'پرداخت در آرایشگاه', 'cancelled' => 'لغوشده');
        foreach ($rows as $r) {
            $code = isset($r->credit_code) ? $r->credit_code : '';
            $st = isset($status_fa[$r->status]) ? $status_fa[$r->status] : $r->status;
            echo '<tr><td>' . $r->id . '</td><td>' . esc_html($r->customer_name) . '</td><td>' . esc_html($r->customer_phone) . '</td>'
                . '<td>' . esc_html($r->service_names) . '</td><td>' . esc_html($r->start_dt) . '</td><td>' . (int) $r->duration_min . '′</td>'
                . '<td>' . ((int) $r->recurrence_weeks ? 'هر ' . (int) $r->recurrence_weeks . ' هفته' : 'یک‌بار') . '</td>'
                . '<td>' . number_format($r->total_price) . '</td><td>' . number_format($r->deposit_price) . '</td>'
                . '<td>' . ($code ? esc_html($code) : '—') . '</td><td>' . esc_html($st) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
Dorian_Settings::boot();
