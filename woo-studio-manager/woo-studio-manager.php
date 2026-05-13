<?php
/**
 * Plugin Name: WooCommerce Studio Manager
 * Description: Advanced WooCommerce Bulk Price Editor and Sync Manager.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wsm
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Woo_Studio_Manager {

    const BALE_BOT_TOKEN = '5837451:U1ukEZQ_63DfxNUPFmqVmcUz_nVqXYwIKes';
    const BALE_CHAT_ID = '765373061';
    const SITE2_SYNC_URL = 'https://fara-moj.ir/wp-json/wsm-receiver/v1/sync';
    const SITE2_SECRET = 'a21cd8da0ac053010494564e4c63cc2435292222bc7c45989f6c5d6ad2fd';
    const TARGET_SYNC_CATEGORY = 'آنتن GHz';

    private $page_hook;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_wsm_fetch_all', array( $this, 'ajax_fetch_all' ) );
        add_action( 'wp_ajax_wsm_batch_sync', array( $this, 'ajax_batch_sync' ) );
    }

    public function register_admin_menu() {
        if ( ! current_user_can( 'administrator' ) ) {
            return;
        }

        $this->page_hook = add_menu_page(
            'مدیریت محصولات',
            'مدیریت محصولات',
            'administrator',
            'woo-studio-manager',
            array( $this, 'render_dashboard' ),
            'dashicons-store',
            55
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== $this->page_hook ) {
            return;
        }

        // Enqueue DataTables assets via CDN
        wp_enqueue_style( 'wsm-datatables-css', 'https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css', array(), '1.13.4' );
        wp_enqueue_script( 'wsm-datatables-js', 'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.4', true );

        // Enqueue custom assets with cache busting using time()
        wp_enqueue_style( 'wsm-admin-style', plugins_url( 'assets/css/admin-style.css', __FILE__ ), array( 'wsm-datatables-css' ), time() );
        wp_enqueue_script( 'wsm-admin-app', plugins_url( 'assets/js/admin-app.js', __FILE__ ), array( 'jquery', 'wsm-datatables-js' ), time(), true );

        wp_localize_script( 'wsm-admin-app', 'wsm_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wsm_admin_nonce' )
        ) );
    }

    public function render_dashboard() {
        if ( ! current_user_can( 'administrator' ) ) {
            wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'wsm' ) );
        }
        require_once plugin_dir_path( __FILE__ ) . 'views/dashboard.php';
    }

    public function ajax_fetch_all() {
        check_ajax_referer( 'wsm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'administrator' ) ) {
            wp_send_json_error( 'عدم دسترسی' );
        }

        global $wpdb;

        $query = "
            SELECT
                p.ID as id,
                p.post_title as title,
                p.post_type as type,
                p.post_parent as parent_id,
                MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
                MAX(CASE WHEN pm.meta_key = '_sale_price' THEN pm.meta_value END) as sale_price,
                MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity,
                MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish'
              AND p.post_type IN ('product', 'product_variation')
            GROUP BY p.ID
        ";

        $results = $wpdb->get_results( $query );

        $data = array();

        // Prefetch terms for efficiency
        $all_terms_query = "
            SELECT tr.object_id, t.name
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'product_cat'
        ";
        $terms_results = $wpdb->get_results( $all_terms_query );
        $product_cats = array();
        foreach ( $terms_results as $tr ) {
            if ( ! isset( $product_cats[ $tr->object_id ] ) ) {
                $product_cats[ $tr->object_id ] = array();
            }
            $product_cats[ $tr->object_id ][] = $tr->name;
        }

        foreach ( $results as $row ) {
            $cat_id_to_check = ( $row->type === 'product_variation' && $row->parent_id ) ? $row->parent_id : $row->id;
            $categories = isset( $product_cats[ $cat_id_to_check ] ) ? $product_cats[ $cat_id_to_check ] : array();

            $data[] = array(
                'id'            => $row->id,
                'title'         => $row->title,
                'sku'           => $row->sku,
                'type'          => $row->type,
                'categories'    => implode( ', ', $categories ),
                'regular_price' => $row->regular_price,
                'sale_price'    => $row->sale_price,
                'stock_quantity'=> $row->stock_quantity,
                'stock_status'  => $row->stock_status,
            );
        }

        wp_send_json_success( $data );
    }

    public function ajax_batch_sync() {
        check_ajax_referer( 'wsm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'administrator' ) ) {
            wp_send_json_error( 'عدم دسترسی' );
        }

        if ( ! isset( $_POST['items'] ) || ! is_array( wp_unslash( $_POST['items'] ) ) ) {
            wp_send_json_error( 'اطلاعات نامعتبر' );
        }

        $items = wp_unslash( $_POST['items'] );
        $updated_items = array();
        $bale_csv_data = array();
        $site2_sync_data = array();

        foreach ( $items as $item ) {
            $product_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
            $new_price = isset( $item['new_regular_price'] ) ? sanitize_text_field( $item['new_regular_price'] ) : '';

            if ( $product_id && $new_price !== '' ) {
                $product = wc_get_product( $product_id );
                if ( $product ) {
                    $old_price = $product->get_regular_price();
                    $title = $product->get_name();

                    // Update product price
                    $product->set_regular_price( $new_price );
                    // Keep sale price updated or clear it if needed? The requirement only states updating regular price
                    if( $product->get_sale_price() && floatval($product->get_sale_price()) > floatval($new_price) ) {
                        $product->set_sale_price(''); // Ensure sale price is not higher than regular price
                    }
                    // For variations, it's safe to just set_regular_price and save
                    $product->save();

                    // Track for automations
                    $updated_items[] = array(
                        'id' => $product_id,
                        'title' => $title,
                        'old_regular_price' => $old_price,
                        'new_regular_price' => $new_price
                    );

                    // Bale CSV row
                    $bale_csv_data[] = array( $title, $old_price, $new_price );

                    // Site 2 Sync logic
                    // Check if product belongs strictly to TARGET_SYNC_CATEGORY
                    $is_target_cat = false;

                    $parent_id = $product->get_parent_id();
                    $check_id = $parent_id ? $parent_id : $product_id;
                    $cat_ids = wc_get_product_term_ids( $check_id, 'product_cat' );
                    foreach( $cat_ids as $cat_id ) {
                        $term = get_term_by( 'id', $cat_id, 'product_cat' );
                        if ( $term && $term->name === self::TARGET_SYNC_CATEGORY ) {
                            $is_target_cat = true;
                            break;
                        }
                    }

                    if ( $is_target_cat ) {
                        $site2_sync_data[] = array(
                            'title'     => $title,
                            'new_price' => $new_price,
                            'old_price' => $old_price,
                        );
                    }
                }
            }
        }

        // Trigger Automations non-blockingly
        if ( ! empty( $bale_csv_data ) ) {
            $this->send_bale_report( $bale_csv_data );
        }

        if ( ! empty( $site2_sync_data ) ) {
            $this->send_site2_sync( $site2_sync_data );
        }

        wp_send_json_success( array( 'updated_count' => count( $updated_items ), 'items' => $updated_items ) );
    }

    private function send_bale_report( $data ) {
        // Build in-memory CSV string starting with UTF-8 BOM
        $fp = fopen( 'php://temp', 'r+' );
        fputs( $fp, "\xEF\xBB\xBF" );

        fputcsv( $fp, array( 'نام محصول', 'قیمت قدیم', 'قیمت جدید' ) );
        foreach ( $data as $row ) {
            fputcsv( $fp, $row );
        }

        rewind( $fp );
        $csv_string = stream_get_contents( $fp );
        fclose( $fp );

        $boundary = wp_generate_password( 24, false );
        $payload  = '';

        // Add chat_id
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="chat_id"' . "\r\n\r\n";
        $payload .= self::BALE_CHAT_ID . "\r\n";

        // Add document (CSV file)
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="document"; filename="report_' . date('Ymd_His') . '.csv"' . "\r\n";
        $payload .= 'Content-Type: text/csv' . "\r\n\r\n";
        $payload .= $csv_string . "\r\n";
        $payload .= '--' . $boundary . '--' . "\r\n";

        $url = 'https://tapi.bale.ai/bot' . self::BALE_BOT_TOKEN . '/sendDocument';

        wp_remote_post( $url, array(
            'headers'   => array(
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body'      => $payload,
            'blocking'  => false,
            'timeout'   => 5,
        ) );
    }

    private function send_site2_sync( $data ) {
        wp_remote_post( self::SITE2_SYNC_URL, array(
            'headers' => array(
                'Content-Type'  => 'application/json; charset=utf-8',
                'X-WSM-Secret'  => self::SITE2_SECRET,
            ),
            'body'      => wp_json_encode( $data ),
            'blocking'  => false,
            'timeout'   => 5,
        ) );
    }
}

new Woo_Studio_Manager();
