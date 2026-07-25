<?php
if (!defined('ABSPATH')) exit;

add_action('elementor/widgets/register', function ($widgets_manager) {
    if (!class_exists('\Elementor\Widget_Base')) return;
    if (!method_exists($widgets_manager, 'register')) return; // Elementor < 3.5
    require_once DORIAN_DIR . 'includes/elementor-widget.php';
    $widgets_manager->register(new Dorian_Elementor_Booking());
});
