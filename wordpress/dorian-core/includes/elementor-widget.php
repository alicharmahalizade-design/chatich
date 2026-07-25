<?php
if (!defined('ABSPATH')) exit;

/**
 * Loaded via require_once from the `elementor/widgets/register` handler, so the
 * class is declared at most once even if Elementor fires that hook more than
 * once. Declaring it inline in the handler would fatal on the second call.
 * Elementor must already be loaded — the caller checks that.
 */
class Dorian_Elementor_Booking extends \Elementor\Widget_Base {
    public function get_name() { return 'dorian_booking'; }
    public function get_title() { return 'رزرو دوریان'; }
    public function get_icon() { return 'eicon-calendar'; }
    public function get_categories() { return array('general'); }
    public function get_keywords() { return array('dorian', 'booking', 'رزرو', 'نوبت'); }
    protected function render() {
        echo do_shortcode('[dorian_booking]');
    }
    protected function content_template() {
        echo '<div style="padding:30px;text-align:center;border:1px dashed #C9A227;border-radius:12px">فرمِ «رزرو دوریان» — در صفحهٔ منتشرشده نمایش داده می‌شود.</div>';
    }
}
