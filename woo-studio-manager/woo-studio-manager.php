<?php
/**
 * Plugin Name: WooCommerce Studio Manager
 * Description: A lightning-fast, production-ready product manager for WooCommerce.
 * Version: 1.0.0
 * Author: WP Expert
 * Text Domain: woo-studio-manager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo_Studio_Manager {

	private $page_hook;

	public function __construct() {
		// Hook into the admin menu
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );

		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX endpoints
		add_action( 'wp_ajax_wsm_fetch_all', array( $this, 'ajax_fetch_all' ) );
		add_action( 'wp_ajax_wsm_batch_sync', array( $this, 'ajax_batch_sync' ) );
	}

	public function add_plugin_page() {
		$this->page_hook = add_menu_page(
			__( 'Woo Studio Manager', 'woo-studio-manager' ), // Page title
			__( 'Studio Manager', 'woo-studio-manager' ), // Menu title
			'manage_options', // Capability
			'woo-studio-manager', // Menu slug
			array( $this, 'render_admin_page' ), // Callback
			'dashicons-store', // Icon
			56 // Position
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Include the view file
		$view_file = plugin_dir_path( __FILE__ ) . 'views/dashboard.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			echo '<div class="wrap"><p>' . esc_html__( 'Dashboard view not found.', 'woo-studio-manager' ) . '</p></div>';
		}
	}

	public function enqueue_assets( $hook ) {
		// Only load assets on our plugin page
		if ( $this->page_hook !== $hook && 'toplevel_page_woo-studio-manager' !== $hook ) {
			return;
		}

		// DataTables CSS
		wp_enqueue_style( 'datatables-css', plugin_dir_url( __FILE__ ) . 'assets/css/jquery.dataTables.min.css', array(), '1.13.6' );

		// Plugin specific CSS
		wp_enqueue_style( 'wsm-admin-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-style.css', array(), '1.0.0' );

		// jQuery is required for DataTables, usually loaded by WP admin already.
		// DataTables JS
		wp_enqueue_script( 'datatables-js', plugin_dir_url( __FILE__ ) . 'assets/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.6', true );

		// Plugin specific JS
		wp_enqueue_script( 'wsm-admin-app', plugin_dir_url( __FILE__ ) . 'assets/js/admin-app.js', array( 'jquery', 'datatables-js' ), '1.0.0', true );

		// Localize script with AJAX URL and Nonce
		wp_localize_script( 'wsm-admin-app', 'wsmData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wsm_ajax_nonce' ),
			'i18n'    => array(
				'success' => __( 'Changes saved successfully!', 'woo-studio-manager' ),
				'error'   => __( 'An error occurred while saving.', 'woo-studio-manager' ),
				'noItems' => __( 'No modified items to save.', 'woo-studio-manager' ),
			)
		) );
	}

	public function ajax_fetch_all() {
		// Check nonce and capability
		check_ajax_referer( 'wsm_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'woo-studio-manager' ) ) );
		}

		// Use wc_get_products for efficient querying
		$args = array(
			'limit'   => -1, // Fetch all (~300 items)
			'status'  => array( 'publish', 'draft', 'pending', 'private' ),
			'return'  => 'objects', // Return WC_Product objects
			'orderby' => 'title',
			'order'   => 'ASC',
			'type'    => array( 'simple', 'variation' ),
		);

		$products = wc_get_products( $args );
		$data     = array();

		foreach ( $products as $product ) {
			$product_id = $product->get_id();
			$parent_id  = $product->get_parent_id();
			$title      = $product->get_name();

			// If it's a variation, the name might already include attributes depending on WC version,
			// but we can ensure it by appending attributes if needed, or simply use the name provided.
			// wc_get_products usually formats the name nicely for variations.

			// Category logic for simple products
			$categories = array();
			if ( $product->is_type( 'simple' ) ) {
				$terms = get_the_terms( $product_id, 'product_cat' );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$categories[] = $term->name;
					}
				}
			} elseif ( $product->is_type( 'variation' ) && $parent_id ) {
				$terms = get_the_terms( $parent_id, 'product_cat' );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$categories[] = $term->name;
					}
				}
			}

			$data[] = array(
				'id'             => $product_id,
				'title'          => $title,
				'sku'            => $product->get_sku(),
				'type'           => $product->get_type(),
				'categories'     => implode( ', ', $categories ),
				'regular_price'  => $product->get_regular_price(),
				'sale_price'     => $product->get_sale_price(),
				'stock_quantity' => $product->get_stock_quantity() ? $product->get_stock_quantity() : 0,
				'stock_status'   => $product->get_stock_status(),
			);
		}

		wp_send_json_success( array( 'products' => $data ) );
	}

	public function ajax_batch_sync() {
		// Check nonce and capability
		check_ajax_referer( 'wsm_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'woo-studio-manager' ) ) );
		}

		// Receive data
		$modified_items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();

		if ( empty( $modified_items ) || ! is_array( $modified_items ) ) {
			wp_send_json_error( array( 'message' => __( 'No items provided', 'woo-studio-manager' ) ) );
		}

		foreach ( $modified_items as $item ) {
			if ( ! isset( $item['id'] ) ) {
				continue;
			}

			$product_id = absint( $item['id'] );
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// Update Regular Price
			if ( isset( $item['regular_price'] ) ) {
				$product->set_regular_price( sanitize_text_field( $item['regular_price'] ) );
			}

			// Update Sale Price
			if ( isset( $item['sale_price'] ) ) {
				$product->set_sale_price( sanitize_text_field( $item['sale_price'] ) );
			}

			// Update Stock Quantity and Manage Stock
			if ( isset( $item['stock_quantity'] ) ) {
				$qty = intval( $item['stock_quantity'] );
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $qty );

				if ( $qty <= 0 ) {
					$product->set_stock_status( 'outofstock' );
				} else {
					$product->set_stock_status( 'instock' );
				}
			}

			// Save the product
			$product->save();
		}

		wp_send_json_success( array( 'message' => __( 'Batch sync completed successfully', 'woo-studio-manager' ) ) );
	}
}

// Initialize the plugin
function wsm_init_plugin() {
	// Ensure WooCommerce is active before instantiating
	if ( class_exists( 'WooCommerce' ) ) {
		new Woo_Studio_Manager();
	} else {
		add_action( 'admin_notices', 'wsm_woocommerce_missing_notice' );
	}
}
add_action( 'plugins_loaded', 'wsm_init_plugin' );

function wsm_woocommerce_missing_notice() {
	echo '<div class="error"><p>' . esc_html__( 'WooCommerce Studio Manager requires WooCommerce to be installed and active.', 'woo-studio-manager' ) . '</p></div>';
}
