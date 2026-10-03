<?php
/**
 * Elementor widget: «دکمه اتاق پُرُو» — places the try-on button anywhere in
 * an Elementor page, single-product template or loop item.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;

class OFR_Elementor_Widget extends Widget_Base {

	public function get_name() {
		return 'online-fitting-room';
	}

	public function get_title() {
		return __( 'دکمه اتاق پُرُو', 'online-fitting-room' );
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( 'online-fitting-room', 'woocommerce-elements' );
	}

	public function get_keywords() {
		return array( 'try on', 'tryon', 'fitting', 'woocommerce', 'پرو', 'پُرُو', 'اتاق پرو', 'لباس', 'هوش مصنوعی' );
	}

	/** Output depends on the product in context, so Elementor must never cache it. */
	protected function is_dynamic_content(): bool {
		return true;
	}

	public function get_style_depends() {
		return array( 'online-fitting-room' );
	}

	public function get_script_depends() {
		return array( 'online-fitting-room' );
	}

	/**
	 * Up to 200 recent published products for the picker; larger stores can use
	 * the ID field. Controls are also registered on the front end, where the
	 * list is not needed, so it is only queried in the admin/editor.
	 */
	private function product_options() {
		if ( ! is_admin() ) return array();
		$posts = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
		$options = array();
		foreach ( $posts as $post ) {
			$options[ $post->ID ] = $post->post_title . ' (#' . $post->ID . ')';
		}
		return $options;
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_product', array( 'label' => __( 'محصول', 'online-fitting-room' ) ) );
		$this->add_control( 'product_source', array(
			'label'   => __( 'منبع محصول', 'online-fitting-room' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'current',
			'options' => array(
				'current' => __( 'محصول فعلی (صفحه یا قالب محصول)', 'online-fitting-room' ),
				'select'  => __( 'انتخاب از فهرست محصولات', 'online-fitting-room' ),
				'id'      => __( 'وارد کردن شناسه محصول', 'online-fitting-room' ),
			),
		) );
		$this->add_control( 'product_select', array(
			'label'       => __( 'محصول', 'online-fitting-room' ),
			'type'        => Controls_Manager::SELECT2,
			'options'     => $this->product_options(),
			'label_block' => true,
			'condition'   => array( 'product_source' => 'select' ),
		) );
		$this->add_control( 'product_id', array(
			'label'     => __( 'شناسه محصول', 'online-fitting-room' ),
			'type'      => Controls_Manager::NUMBER,
			'min'       => 1,
			'condition' => array( 'product_source' => 'id' ),
		) );
		$this->add_control( 'product_note', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'دکمه فقط برای محصولی نمایش داده می‌شود که منتشر شده، تصویر شاخص دارد و پرو مجازی آن در ویرایش محصول خاموش نشده باشد.', 'online-fitting-room' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_button', array( 'label' => __( 'دکمه', 'online-fitting-room' ) ) );
		$this->add_control( 'button_text', array(
			'label'       => __( 'متن دکمه', 'online-fitting-room' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => Online_Fitting_Room::settings()['button_text'],
			'description' => __( 'خالی بگذارید تا متن تنظیمات افزونه استفاده شود.', 'online-fitting-room' ),
			'label_block' => true,
		) );
		$this->add_control( 'icon_type', array(
			'label'   => __( 'آیکون', 'online-fitting-room' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'default',
			'options' => array( 'default' => __( 'پیش‌فرض (✦)', 'online-fitting-room' ), 'custom' => __( 'انتخاب آیکون', 'online-fitting-room' ), 'none' => __( 'بدون آیکون', 'online-fitting-room' ) ),
		) );
		$this->add_control( 'icon', array(
			'label'     => __( 'آیکون دلخواه', 'online-fitting-room' ),
			'type'      => Controls_Manager::ICONS,
			'default'   => array( 'value' => 'fas fa-tshirt', 'library' => 'fa-solid' ),
			'condition' => array( 'icon_type' => 'custom' ),
		) );
		$this->add_control( 'icon_position', array(
			'label'     => __( 'جایگاه آیکون', 'online-fitting-room' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'before',
			'options'   => array( 'before' => __( 'قبل از متن', 'online-fitting-room' ), 'after' => __( 'بعد از متن', 'online-fitting-room' ) ),
			'condition' => array( 'icon_type!' => 'none' ),
		) );
		$this->add_responsive_control( 'align', array(
			'label'     => __( 'چینش', 'online-fitting-room' ),
			'type'      => Controls_Manager::CHOOSE,
			'options'   => array(
				'right'   => array( 'title' => __( 'راست', 'online-fitting-room' ), 'icon' => 'eicon-text-align-right' ),
				'center'  => array( 'title' => __( 'وسط', 'online-fitting-room' ), 'icon' => 'eicon-text-align-center' ),
				'left'    => array( 'title' => __( 'چپ', 'online-fitting-room' ), 'icon' => 'eicon-text-align-left' ),
				'justify' => array( 'title' => __( 'تمام‌عرض', 'online-fitting-room' ), 'icon' => 'eicon-text-align-justify' ),
			),
			'selectors_dictionary' => array(
				'right'   => 'text-align:right;--ofr-widget-width:auto',
				'center'  => 'text-align:center;--ofr-widget-width:auto',
				'left'    => 'text-align:left;--ofr-widget-width:auto',
				'justify' => '--ofr-widget-width:100%',
			),
			'selectors' => array( '{{WRAPPER}} .ofr-widget' => '{{VALUE}};' ),
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_button', array( 'label' => __( 'دکمه', 'online-fitting-room' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .ofr-open' ) );
		$this->start_controls_tabs( 'tabs_button' );
		$this->start_controls_tab( 'tab_normal', array( 'label' => __( 'عادی', 'online-fitting-room' ) ) );
		$this->add_control( 'text_color', array( 'label' => __( 'رنگ متن', 'online-fitting-room' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ofr-open' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'bg_color', array( 'label' => __( 'رنگ پس‌زمینه', 'online-fitting-room' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ofr-open' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'icon_color', array( 'label' => __( 'رنگ آیکون', 'online-fitting-room' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ofr-open__icon' => 'color: {{VALUE}};', '{{WRAPPER}} .ofr-open__icon svg' => 'fill: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'box_shadow', 'selector' => '{{WRAPPER}} .ofr-open' ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'tab_hover', array( 'label' => __( 'هاور', 'online-fitting-room' ) ) );
		$this->add_control( 'text_color_hover', array( 'label' => __( 'رنگ متن', 'online-fitting-room' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ofr-open:hover, {{WRAPPER}} .ofr-open:focus-visible' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'bg_color_hover', array( 'label' => __( 'رنگ پس‌زمینه', 'online-fitting-room' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ofr-open:hover, {{WRAPPER}} .ofr-open:focus-visible' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'icon_color_hover', array( 'label' => __( 'رنگ آیکون', 'online-fitting-room' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ofr-open:hover .ofr-open__icon' => 'color: {{VALUE}};', '{{WRAPPER}} .ofr-open:hover .ofr-open__icon svg' => 'fill: {{VALUE}};' ) ) );
		$this->add_control( 'border_color_hover', array( 'label' => __( 'رنگ حاشیه', 'online-fitting-room' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ofr-open:hover' => 'border-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'box_shadow_hover', 'selector' => '{{WRAPPER}} .ofr-open:hover' ) );
		$this->add_control( 'hover_lift', array(
			'label'        => __( 'جابجایی رو به بالا', 'online-fitting-room' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'selectors'    => array( '{{WRAPPER}} .ofr-open:hover' => 'transform: translateY(-2px);' ),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'border', 'selector' => '{{WRAPPER}} .ofr-open', 'separator' => 'before' ) );
		$this->add_responsive_control( 'border_radius', array(
			'label'      => __( 'گردی گوشه‌ها', 'online-fitting-room' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ofr-open' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'padding', array(
			'label'      => __( 'فاصله داخلی', 'online-fitting-room' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ofr-open' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'icon_size', array(
			'label'      => __( 'اندازه آیکون', 'online-fitting-room' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .ofr-open__icon' => 'font-size: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'icon_type!' => 'none' ),
		) );
		$this->add_responsive_control( 'icon_gap', array(
			'label'      => __( 'فاصله آیکون و متن', 'online-fitting-room' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .ofr-open' => 'gap: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'icon_type!' => 'none' ),
		) );
		$this->end_controls_section();
	}

	private function resolve_product_id( $settings ) {
		switch ( $settings['product_source'] ?? 'current' ) {
			case 'select':
				return absint( $settings['product_select'] ?? 0 );
			case 'id':
				return absint( $settings['product_id'] ?? 0 );
		}
		return Online_Fitting_Room::instance()->current_product_id();
	}

	/** Explains in the editor why the button is hidden on the live site. */
	private function unsupported_reason( $product_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) return __( 'ووکامرس فعال نیست.', 'online-fitting-room' );
		if ( 'yes' !== Online_Fitting_Room::settings()['enabled'] ) return __( 'اتاق پُرُو در تنظیمات افزونه غیرفعال است.', 'online-fitting-room' );
		if ( ! $product_id ) return __( 'محصولی پیدا نشد. داخل قالب محصول استفاده کنید یا یک محصول انتخاب کنید.', 'online-fitting-room' );
		if ( 'product' !== get_post_type( $product_id ) ) return __( 'شناسه وارد شده متعلق به محصول ووکامرس نیست.', 'online-fitting-room' );
		if ( 'publish' !== get_post_status( $product_id ) ) return __( 'محصول منتشر نشده است.', 'online-fitting-room' );
		if ( ! has_post_thumbnail( $product_id ) ) return __( 'محصول تصویر شاخص ندارد.', 'online-fitting-room' );
		return __( 'پرو مجازی برای این محصول خاموش است.', 'online-fitting-room' );
	}

	private function icon_html( $settings ) {
		$type = $settings['icon_type'] ?? 'default';
		if ( 'none' === $type ) return '';
		if ( 'custom' !== $type || empty( $settings['icon']['value'] ) ) return null;
		ob_start();
		Icons_Manager::render_icon( $settings['icon'], array( 'aria-hidden' => 'true' ) );
		return ob_get_clean();
	}

	protected function render() {
		$settings   = $this->get_settings_for_display();
		$plugin     = Online_Fitting_Room::instance();
		$product_id = $this->resolve_product_id( $settings );
		$editing    = \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode();
		$supported  = $plugin->is_supported_product( $product_id );

		// The live site shows nothing for an unsupported product; the editor shows a preview.
		if ( ! $supported && ! $editing ) return;

		$button = $plugin->button_html( $product_id, array(
			'text'          => $settings['button_text'] ?? '',
			'icon_html'     => $this->icon_html( $settings ),
			'icon_position' => $settings['icon_position'] ?? 'before',
			'class'         => 'ofr-open--widget',
			'preview'       => $editing,
		) );

		echo '<div class="ofr-widget">' . $button; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in button_html().
		if ( ! $supported ) {
			echo '<p class="ofr-widget__notice">' . esc_html( __( 'این دکمه در سایت نمایش داده نمی‌شود:', 'online-fitting-room' ) . ' ' . $this->unsupported_reason( $product_id ) ) . '</p>';
		}
		echo '</div>';
	}
}
