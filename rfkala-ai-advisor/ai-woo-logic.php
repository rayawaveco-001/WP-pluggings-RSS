<?php
namespace RFKala\AIAdvisor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIWooLogic {
    public function __construct() {
        add_action( 'wp_ajax_rfkala_chat', [ $this, 'handle_chat_request' ] );
        add_action( 'wp_ajax_nopriv_rfkala_chat', [ $this, 'handle_chat_request' ] );
    }

    public function handle_chat_request() {
        check_ajax_referer( 'rfkala_ai_nonce', 'nonce' );

        $user_message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        $session_id   = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

        if ( empty( $user_message ) || empty( $session_id ) ) {
            wp_send_json_error( 'Invalid input' );
        }

        // 1. Fetch relevant WooCommerce products (Anti-Hallucination)
        $product_context = $this->get_woocommerce_context( $user_message );

        // 2. Fetch relevant FAQs
        $faq_context = $this->get_faq_context();

        // 3. Construct System Prompt
        $system_prompt = "You are a precise e-commerce assistant for an Iranian store. Answer ONLY using the exact product data and FAQs provided below in Persian/Farsi. Do NOT invent, guess, or hallucinate prices, stock, or technical specs. If the context does not contain the answer, apologize and say you will transfer the user to a human operator ('پشتیبان انسانی').\n\n";
        $system_prompt .= "--- PRODUCT DATA ---\n" . $product_context . "\n\n";
        $system_prompt .= "--- FAQs ---\n" . $faq_context . "\n";

        // 4. Call GapGPT API
        $response = $this->call_gapgpt( $system_prompt, $user_message );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            $this->send_to_bale( $user_message, $session_id );
            wp_send_json_success( [
                'reply' => "متاسفانه در حال حاضر ارتباط با هوش مصنوعی برقرار نیست (خطا: $error_message). پیام شما به پشتیبان انسانی ارسال شد و به زودی پاسخ داده می‌شود."
            ] );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $response_code !== 200 ) {
            $api_error = isset( $data['error']['message'] ) ? $data['error']['message'] : 'کد وضعیت: ' . $response_code;
            $this->send_to_bale( $user_message, $session_id );
            wp_send_json_success( [
                'reply' => "متاسفانه در حال حاضر ارتباط با هوش مصنوعی برقرار نیست (خطا API: $api_error). پیام شما به پشتیبان انسانی ارسال شد."
            ] );
        }

        $ai_reply = $data['choices'][0]['message']['content'] ?? 'خطا در دریافت پاسخ.';

        // Check if AI decided it needs human handoff
        if ( strpos( $ai_reply, 'پشتیبان انسانی' ) !== false ) {
            $this->send_to_bale( $user_message, $session_id );
        }

        wp_send_json_success( [ 'reply' => $ai_reply ] );
    }

    private function get_woocommerce_context( $query ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return "WooCommerce is not active.";
        }

        // Remove common stop words for a better product search
        $stop_words = ['قیمت', 'موجودی', 'داری', 'دارید', 'چنده', 'هست', 'آیا', 'میخوام'];
        $clean_query = trim( str_replace( $stop_words, '', $query ) );

        // If clean query is empty after stripping, just use the original
        $search_term = empty( $clean_query ) ? $query : $clean_query;

        $args = [
            'status' => 'publish',
            'limit'  => 5,
            's'      => $search_term,
        ];

        $products = wc_get_products( $args );
        $context = "";

        if ( empty( $products ) ) {
            return "No matching products found.";
        }

        foreach ( $products as $product ) {
            $title = $product->get_name();
            $price = $product->get_price();
            $stock = $product->get_stock_status() === 'instock' ? 'موجود' : 'ناموجود';

            $context .= "- Product: {$title} | Price: {$price} | Stock: {$stock}\n";
        }

        return $context;
    }

    private function get_faq_context() {
        $faqs = get_option( 'rfkala_ai_faqs', [] );
        $context = "";

        foreach ( $faqs as $faq ) {
            $context .= "Q: {$faq['question']} \nA: {$faq['answer']}\n";
        }

        return empty( $context ) ? "No FAQs available." : $context;
    }

    private function call_gapgpt( $system_prompt, $user_message ) {
        $api_key = get_option( 'rfkala_gapgpt_api_key', '' );

        $payload = [
            'model' => 'gpt-3.5-turbo', // Or whatever specific model gapgpt uses
            'messages' => [
                [ 'role' => 'system', 'content' => $system_prompt ],
                [ 'role' => 'user', 'content' => $user_message ]
            ],
            'temperature' => 0.2 // Low temp for precision
        ];

        $args = [
            'body'        => json_encode( $payload ),
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
            'timeout'     => 30,
            'sslverify'   => false,
        ];

        return wp_remote_post( 'https://api.gapgpt.app/v1/chat/completions', $args );
    }

    private function send_to_bale( $user_message, $session_id ) {
        $bot_token = get_option( 'rfkala_bale_bot_token', '' );
        $admin_chat_id = get_option( 'rfkala_bale_admin_chat_id', '' );

        $bale_message = "پیام جدید از کاربر (Session: {$session_id}):\n\n{$user_message}";

        $payload = [
            'chat_id' => $admin_chat_id,
            'text'    => $bale_message
        ];

        $args = [
            'body'    => wp_json_encode( $payload ),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 10,
        ];

        wp_remote_post( "https://tapi.bale.ai/bot{$bot_token}/sendMessage", $args );
    }
}

new AIWooLogic();
