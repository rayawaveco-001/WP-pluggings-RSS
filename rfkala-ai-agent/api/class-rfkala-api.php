<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RFKala_API {

    const OPENAI_API_KEY = 'sk-nswFYxPqIYB98HCrKUjH3KJp7AyzHsQE8mJJ0uz5YzIwUTIF';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'rfkala/v1', '/chat', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle_chat_request' ),
            'permission_callback' => '__return_true', // Open endpoint for frontend chat
        ) );
    }

    public function handle_chat_request( WP_REST_Request $request ) {
        $message = sanitize_text_field( $request->get_param( 'message' ) );

        if ( empty( $message ) ) {
            return new WP_Error( 'no_message', 'Message is required', array( 'status' => 400 ) );
        }

        // 1. Read admin rules
        $rules_file = RFKALA_AI_AGENT_DIR . 'data/admin_rules.json';
        $rules = '';
        if ( file_exists( $rules_file ) ) {
            $rules = file_get_contents( $rules_file );
        }

        // 2. Read products KB
        $kb_file = RFKALA_AI_AGENT_DIR . 'data/products_kb.json';
        $kb_data = '';
        if ( file_exists( $kb_file ) ) {
            $kb_data = file_get_contents( $kb_file );
        }

        // 3. Call OpenAI to analyze the request and identify products
        $ai_response = $this->query_openai( $message, $rules, $kb_data );

        // 4. Extract Product IDs from AI response and fetch live price/stock
        $matched_ids = $this->extract_product_ids_from_text( $ai_response );
        $live_product_data = $this->fetch_live_product_data( $matched_ids );

        // Build final response
        $final_response = array(
            'reply' => $ai_response,
            'products' => $live_product_data
        );

        return rest_ensure_response( $final_response );
    }

    private function query_openai( $user_message, $rules, $kb_data ) {
        $api_key = self::OPENAI_API_KEY;
        $url = 'https://api.openai.com/v1/chat/completions';

        $system_prompt = "You are an expert RF Technical Sales Agent for RFKala.ir. You help customers choose the right RF equipment based on their distance and environmental needs.\n";
        $system_prompt .= "Here are the administrative rules for equipment selection:\n$rules\n\n";
        $system_prompt .= "Here is our product knowledge base (JSON format):\n$kb_data\n\n";
        $system_prompt .= "Analyze the user's request. Recommend the best products from the knowledge base by mentioning their exact Product ID in brackets, like [ID: 123]. Provide a helpful and technical explanation.";

        $body = wp_json_encode( array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $user_message
                )
            ),
            'max_tokens' => 500,
            'temperature' => 0.7,
        ) );

        $args = array(
            'body'        => $body,
            'headers'     => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout'     => 30,
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return 'Sorry, I am currently unable to process your request due to a connection issue.';
        }

        $body_res = wp_remote_retrieve_body( $response );
        $data = json_decode( $body_res, true );

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return trim( $data['choices'][0]['message']['content'] );
        }

        return 'Sorry, I could not generate a response at this time.';
    }

    private function extract_product_ids_from_text( $text ) {
        $ids = array();
        // Match patterns like [ID: 123]
        if ( preg_match_all( '/\[ID:\s*(\d+)\]/i', $text, $matches ) ) {
            $ids = array_map( 'intval', $matches[1] );
        }
        return array_unique( $ids );
    }

    private function fetch_live_product_data( $ids ) {
        $products_data = array();

        if ( ! function_exists( 'wc_get_product' ) || empty( $ids ) ) {
            return $products_data;
        }

        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( $product ) {
                $products_data[] = array(
                    'id'           => $product->get_id(),
                    'name'         => $product->get_name(),
                    'price'        => $product->get_price(),
                    'stock_status' => $product->get_stock_status(), // 'instock', 'outofstock', 'onbackorder'
                    'url'          => $product->get_permalink(),
                );
            }
        }

        return $products_data;
    }
}
