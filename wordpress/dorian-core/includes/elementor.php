<?php
if (!defined('ABSPATH')) exit;

add_action('elementor/widgets/register', function ($widgets_manager) {
    if (!class_exists('\Elementor\Widget_Base')) return;

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
    $widgets_manager->register(new Dorian_Elementor_Booking());
});
