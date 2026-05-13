<?php
/**
 * Plugin Name: WSM Remote Elementor Price Receiver
 * Description: Standalone, secure, ultra-lightweight webhook receiver plugin optimized for updating content inside Elementor templates on non-WooCommerce websites.
 * Version: 1.0.0
 * Author: WSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// 1. Custom REST Route & Strict Security
define( 'WSM_SHARED_SECRET', 'a21cd8da0ac053010494564e4c63cc2435292222bc7c45989f6c5d6ad2fd' );

add_action( 'rest_api_init', 'wsm_elementor_receiver_register_routes' );

/**
 * Register the REST API route.
 */
function wsm_elementor_receiver_register_routes() {
	register_rest_route( 'wsm-receiver/v1', '/sync', array(
		'methods'             => 'POST',
		'callback'            => 'wsm_elementor_receiver_sync_callback',
		'permission_callback' => 'wsm_elementor_receiver_permission_callback',
	) );
}

/**
 * Permission callback to verify the incoming custom header.
 *
 * @param WP_REST_Request $request The REST request.
 * @return bool|WP_Error True if authorized, WP_Error otherwise.
 */
function wsm_elementor_receiver_permission_callback( WP_REST_Request $request ) {
	$header_secret = $request->get_header( 'x-wsm-secret' );

	if ( $header_secret === WSM_SHARED_SECRET ) {
		return true;
	}

	return new WP_Error( 'rest_forbidden', 'Unauthorized.', array( 'status' => 403 ) );
}

/**
 * Main callback to handle the sync request.
 *
 * @param WP_REST_Request $request The REST request.
 * @return WP_REST_Response|WP_Error Response object or WP_Error.
 */
function wsm_elementor_receiver_sync_callback( WP_REST_Request $request ) {
	global $wpdb;

	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		return new WP_Error( 'invalid_payload', 'Payload must be a JSON array.', array( 'status' => 400 ) );
	}

	$processed_templates = 0;
	$updated_post_ids    = array();

	foreach ( $params as $item ) {
		if ( empty( $item['title'] ) || ! isset( $item['new_price'] ) || ! isset( $item['old_price'] ) ) {
			continue;
		}

		$title = $item['title'];

		// 2. Automatic 10% Tax Deduction Logic
		$new_base = round( floatval( $item['new_price'] ) / 1.1 );
		$old_base = round( floatval( $item['old_price'] ) / 1.1 );

		$old_representations = array(
			number_format( $old_base ),
			(string) $old_base,
			str_replace( ',', '،', number_format( $old_base ) ),
		);

		// 3. Advanced Elementor JSON Manipulation (_elementor_data)
		$title_escaped_unicode = trim( wp_json_encode( $title ), '"' );

		$query = $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND (meta_value LIKE %s OR meta_value LIKE %s)",
			'%' . $wpdb->esc_like( $title ) . '%',
			'%' . $wpdb->esc_like( $title_escaped_unicode ) . '%'
		);

		$results = $wpdb->get_results( $query );

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$meta_value = $row->meta_value;
				$data       = json_decode( $meta_value, true );

				if ( ! is_array( $data ) ) {
					continue;
				}

				$modified = false;

				// Recursive traversal to update Elementor data
				$data = wsm_elementor_receiver_traverse( $data, $title, $old_representations, $new_base, $modified );

				if ( $modified ) {
					$new_meta_value = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

					// Update the post meta cleanly.
					update_post_meta( $row->post_id, '_elementor_data', wp_slash( $new_meta_value ) );

					if ( ! in_array( $row->post_id, $updated_post_ids, true ) ) {
						$updated_post_ids[] = $row->post_id;
						$processed_templates++;
					}
				}
			}
		}
	}

	// 4. Dynamic Elementor Cache Flushing
	if ( ! empty( $updated_post_ids ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	return rest_ensure_response( array(
		'success'             => true,
		'processed_templates' => $processed_templates,
		'updated_post_ids'    => $updated_post_ids,
	) );
}

/**
 * Recursive traversal function targeting widget parameters.
 *
 * @param array  $data                Elementor data array.
 * @param string $title               Target product title.
 * @param array  $old_representations Array of old price representations.
 * @param float  $new_base            New base price.
 * @param bool   $modified            Reference to modification flag.
 * @return array Modified Elementor data array.
 */
function wsm_elementor_receiver_traverse( $data, $title, $old_representations, $new_base, &$modified ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$new_base_formatted = number_format( $new_base );
	$new_base_raw       = (string) $new_base;
	$new_base_persian   = str_replace( ',', '،', $new_base_formatted );

	foreach ( $data as $key => &$value ) {
		if ( is_array( $value ) ) {
			// a) Repeating Lists (Elementor Price List Widget)
			if ( $key === 'price_list' ) {
				foreach ( $value as &$list_item ) {
					if ( isset( $list_item['title'] ) && strpos( $list_item['title'], $title ) !== false ) {
						if ( isset( $list_item['price'] ) ) {
							$list_item['price'] = $new_base_formatted;
							$modified           = true;
						}
					}
				}
			}

			$value = wsm_elementor_receiver_traverse( $value, $title, $old_representations, $new_base, $modified );
		} elseif ( is_string( $value ) ) {
			// b) Universal Text Search (Independent Widgets)
			if ( in_array( $key, array( 'editor', 'title', 'text', 'description' ), true ) ) {
				$replace_pairs = array(
					$old_representations[0] => $new_base_formatted,
					$old_representations[1] => $new_base_raw,
					$old_representations[2] => $new_base_persian,
				);

				$new_val = strtr( $value, $replace_pairs );
				if ( $new_val !== $value ) {
					$value    = $new_val;
					$modified = true;
				}
			}
		}
	}
	unset( $value ); // Unset reference safely

	return $data;
}
