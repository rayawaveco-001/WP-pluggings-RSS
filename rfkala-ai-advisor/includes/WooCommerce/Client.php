<?php

namespace RfkalaAiAdvisor\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Client {

    /**
     * Fetch products from the WooCommerce API by keyword.
     *
     * @param string $keyword
     * @return array|null Returns an array of products or null on failure.
     */
    public function search_products( $keyword ) {
        $api_path = get_option( 'rfkala_wc_api_path' );
        $consumer_key = get_option( 'rfkala_wc_consumer_key' );
        $consumer_secret = get_option( 'rfkala_wc_consumer_secret' );

        if ( empty( $api_path ) || empty( $consumer_key ) || empty( $consumer_secret ) ) {
            return null;
        }

        $url = add_query_arg( [
            'search' => $keyword,
            'status' => 'publish',
            'per_page' => 3
        ], $api_path );

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret )
            ],
            'timeout' => 15
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return null;
        }

        $results = [];
        foreach ( $data as $product ) {
            $results[] = [
                'name'  => $product['name'] ?? '',
                'price' => $product['price'] ?? '0',
                'stock' => $product['stock_status'] ?? 'outofstock',
                'url'   => $product['permalink'] ?? ''
            ];
        }

        return $results;
    }
}
