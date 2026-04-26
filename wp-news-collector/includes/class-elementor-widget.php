<?php
/**
 * Elementor Widget for News Collector.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPNC_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 */
	public function get_name() {
		return 'wpnc_news_widget';
	}

	/**
	 * Get widget title.
	 */
	public function get_title() {
		return __( 'News Collector Bulletin', 'wp-news-collector' );
	}

	/**
	 * Get widget icon.
	 */
	public function get_icon() {
		return 'eicon-post-list';
	}

	/**
	 * Get widget categories.
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * Register widget controls.
	 */
	protected function _register_controls() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Content', 'wp-news-collector' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'limit',
			[
				'label' => __( 'Number of Posts', 'wp-news-collector' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 100,
				'step' => 1,
				'default' => 10,
			]
		);

		$this->add_control(
			'category',
			[
				'label' => __( 'Category Slug', 'wp-news-collector' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Leave empty for all categories.', 'wp-news-collector' ),
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$limit = ! empty( $settings['limit'] ) ? intval( $settings['limit'] ) : 10;
		$category = ! empty( $settings['category'] ) ? sanitize_text_field( $settings['category'] ) : '';

		echo do_shortcode( sprintf( '[news_bulletin limit="%d" category="%s"]', $limit, $category ) );
	}
}
