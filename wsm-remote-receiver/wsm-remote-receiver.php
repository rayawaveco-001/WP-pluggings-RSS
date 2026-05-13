<?php
/**
 * Plugin Name: WSM Remote Price Receiver
 * Description: Lightweight receiver for WooCommerce Studio Manager remote synchronization.
 * Version: 1.0.0
 * Author: WP Expert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the fallback constant if not already defined securely in wp-config.php
if ( ! defined( 'WSM_SHARED_SECRET' ) ) {
	define( 'WSM_SHARED_SECRET', get_option('wsm_site2_secret', '') );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'wsm-receiver/v1', '/sync', array(
		'methods'             => 'POST',
		'callback'            => 'wsm_receiver_process_sync',
		'permission_callback' => 'wsm_receiver_verify_secret',
	) );
} );

/**
 * Strict permission callback to verify the custom header secret.
 */
function wsm_receiver_verify_secret( WP_REST_Request $request ) {
	$header_secret = $request->get_header( 'x_wsm_secret' ); // WordPress converts custom headers to lowercase with underscores
	if ( $header_secret === WSM_SHARED_SECRET ) {
		return true;
	}
	return new WP_Error( 'forbidden', 'Invalid Secret', array( 'status' => 403 ) );
}

/**
 * High-performance processing of the sync payload.
 */
function wsm_receiver_process_sync( WP_REST_Request $request ) {
	global $wpdb;

	$items = $request->get_json_params();

	if ( empty( $items ) || ! is_array( $items ) ) {
		return new WP_Error( 'bad_request', 'Invalid payload', array( 'status' => 400 ) );
	}

	$updated_count = 0;

	foreach ( $items as $item ) {
		if ( empty( $item['title'] ) ) {
			continue;
		}

		$title = sanitize_text_field( $item['title'] );

		// Blazing-fast direct query by exact title for active products/variations
		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type IN ('product', 'product_variation') AND post_status != 'trash' LIMIT 1",
			$title
		);
		$product_id = $wpdb->get_var( $query );

		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				// Tax Deduction Business Logic: The prices sent from the source website include a 10% VAT/Tax.
				// Apply mathematical formula to extract the base price before saving.
				if ( isset( $item['regular_price'] ) && is_numeric( $item['regular_price'] ) ) {
					$base_regular = round( floatval( $item['regular_price'] ) / 1.1 );
					$product->set_regular_price( $base_regular );
				}

				if ( isset( $item['sale_price'] ) && is_numeric( $item['sale_price'] ) && $item['sale_price'] > 0 ) {
					$base_sale = round( floatval( $item['sale_price'] ) / 1.1 );
					$product->set_sale_price( $base_sale );
				} else {
					// Clear the sale price if empty
					$product->set_sale_price( '' );
				}

				$product->save();
				$updated_count++;
			}
		}
	}

	return rest_ensure_response( array(
		'success' => true,
		'message' => sprintf( '%d products updated successfully.', $updated_count ),
	) );
}
