<?php
/**
 * Plugin Name: WP News Collector
 * Plugin URI: https://example.com
 * Description: A WordPress plugin to fetch, manage, and publish news from RSS sources.
 * Version: 1.0.0
 * Author: Jules
 * Author URI: https://example.com
 * Text Domain: wp-news-collector
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'WPNC_VERSION', '1.0.0' );
define( 'WPNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPNC_PLUGIN_FILE', __FILE__ );

// Include required files
require_once WPNC_PLUGIN_DIR . 'includes/class-db.php';
require_once WPNC_PLUGIN_DIR . 'includes/class-cpt.php';
require_once WPNC_PLUGIN_DIR . 'includes/class-fetcher.php';

if ( is_admin() ) {
	require_once WPNC_PLUGIN_DIR . 'includes/class-admin.php';
	require_once WPNC_PLUGIN_DIR . 'includes/class-ajax.php';
}

require_once WPNC_PLUGIN_DIR . 'includes/class-shortcode.php';

// Register Elementor Widget
function wpnc_register_elementor_widget( $widgets_manager ) {
	require_once WPNC_PLUGIN_DIR . 'includes/class-elementor-widget.php';
	$widgets_manager->register( new \WPNC_Elementor_Widget() );
}
add_action( 'elementor/widgets/register', 'wpnc_register_elementor_widget' );

// Enqueue Admin Assets
function wpnc_enqueue_admin_assets( $hook ) {
	if ( 'toplevel_page_wpnc-news-collector' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'wpnc-admin-style', WPNC_PLUGIN_URL . 'assets/admin.css', array(), WPNC_VERSION );
	wp_enqueue_script( 'wpnc-chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true );
	wp_enqueue_script( 'wpnc-admin-script', WPNC_PLUGIN_URL . 'assets/admin.js', array( 'jquery', 'wpnc-chart-js' ), WPNC_VERSION, true );

	wp_localize_script( 'wpnc-admin-script', 'wpnc_ajax', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'wpnc_admin_nonce' ),
		'i18n'     => array(
			'loading'          => __( 'Loading...', 'wp-news-collector' ),
			'error_loading'    => __( 'Error loading queue.', 'wp-news-collector' ),
			'no_pending'       => __( 'No pending news in the queue.', 'wp-news-collector' ),
			'select_all'       => __( 'Select All', 'wp-news-collector' ),
			'approve_selected' => __( 'Approve Selected', 'wp-news-collector' ),
			'reject_selected'  => __( 'Reject Selected', 'wp-news-collector' ),
			'no_image'         => __( 'No Image', 'wp-news-collector' ),
			'tags'             => __( 'Tags', 'wp-news-collector' ),
			'approve'          => __( 'Approve', 'wp-news-collector' ),
			'edit'             => __( 'Edit', 'wp-news-collector' ),
			'reject'           => __( 'Reject', 'wp-news-collector' ),
			'edit_item'        => __( 'Edit News Item', 'wp-news-collector' ),
			'save'             => __( 'Save', 'wp-news-collector' ),
			'cancel'           => __( 'Cancel', 'wp-news-collector' ),
			'error_approve'    => __( 'Error approving item.', 'wp-news-collector' ),
			'error_reject'     => __( 'Error rejecting item.', 'wp-news-collector' ),
			'error_save'       => __( 'Error saving changes.', 'wp-news-collector' ),
			'processing'       => __( 'Processing...', 'wp-news-collector' ),
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'wpnc_enqueue_admin_assets' );

// Enqueue Frontend Assets
function wpnc_enqueue_frontend_assets() {
	wp_enqueue_style( 'wpnc-frontend-style', WPNC_PLUGIN_URL . 'assets/frontend.css', array(), WPNC_VERSION );
	wp_enqueue_script( 'wpnc-frontend-script', WPNC_PLUGIN_URL . 'assets/frontend.js', array( 'jquery' ), WPNC_VERSION, true );
	wp_localize_script( 'wpnc-frontend-script', 'wpnc_frontend_ajax', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'wpnc_frontend_nonce' ),
		'i18n'     => array(
			'loading'   => __( 'Loading...', 'wp-news-collector' ),
			'load_more' => __( 'Load More News', 'wp-news-collector' ),
			'no_more'   => __( 'No more news', 'wp-news-collector' ),
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'wpnc_enqueue_frontend_assets' );

// Initialize the plugin
function wpnc_init() {
	// Classes are already instantiated in their respective files or initialized directly.
}
add_action( 'plugins_loaded', 'wpnc_init' );
