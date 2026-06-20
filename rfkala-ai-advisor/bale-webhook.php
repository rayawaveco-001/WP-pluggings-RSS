<?php
namespace RFKala\AIAdvisor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BaleWebhook {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Webhook to receive messages from Bale
        register_rest_route( 'chatbot/v1', '/bale-webhook', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_bale_webhook' ],
            'permission_callback' => [ $this, 'verify_webhook_token' ],
        ] );

        // Polling endpoint for the frontend widget
        register_rest_route( 'chatbot/v1', '/poll-messages', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_frontend_polling' ],
            'permission_callback' => '__return_true', // Public for users, rely on session_id
        ] );
    }

    public function handle_bale_webhook( \WP_REST_Request $request ) {
        global $wpdb;
        $body = $request->get_json_params();

        // Basic validation of Bale payload structure
        if ( empty( $body['message'] ) || empty( $body['message']['text'] ) ) {
            return new \WP_REST_Response( 'Invalid payload', 400 );
        }

        $text = sanitize_text_field( $body['message']['text'] );

        // Expected format from Admin: "SESSION_ID: The message"
        // E.g., "12345: سلام کاربر عزیز"
        if ( preg_match( '/^([^:]+):\s*(.*)$/su', $text, $matches ) ) {
            $session_id = sanitize_text_field( trim( $matches[1] ) );
            $admin_message = sanitize_text_field( trim( $matches[2] ) );

            $table_name = $wpdb->prefix . 'rfkala_bale_messages';

            $wpdb->insert(
                $table_name,
                [
                    'session_id' => $session_id,
                    'message'    => $admin_message,
                    'sender'     => 'admin',
                    'created_at' => current_time( 'mysql' )
                ],
                [ '%s', '%s', '%s', '%s' ]
            );

            return new \WP_REST_Response( 'Message saved', 200 );
        }

        return new \WP_REST_Response( 'Message format invalid, expecting SESSION_ID: message', 200 );
    }

    public function handle_frontend_polling( \WP_REST_Request $request ) {
        global $wpdb;

        $session_id = $request->get_param( 'session_id' );
        $last_id    = intval( $request->get_param( 'last_id' ) );

        if ( empty( $session_id ) ) {
            return new \WP_Error( 'missing_session', 'Session ID is required', [ 'status' => 400 ] );
        }

        $table_name = $wpdb->prefix . 'rfkala_bale_messages';

        // Get messages from admin for this session that are newer than last_id
        $query = $wpdb->prepare(
            "SELECT id, message FROM $table_name WHERE session_id = %s AND sender = 'admin' AND id > %d ORDER BY id ASC",
            $session_id,
            $last_id
        );

        $messages = $wpdb->get_results( $query, ARRAY_A );

        return new \WP_REST_Response( [ 'messages' => $messages ], 200 );
    }

    public function verify_webhook_token( \WP_REST_Request $request ) {
        // Expecting a token in the query params ?token=SECRET or checking Bale token
        $provided_token = $request->get_param( 'token' );
        // The token is part of the bot token, we can use the bot token as a simple secret
        $expected_token = get_option( 'rfkala_bale_bot_token', '' );

        if ( empty( $expected_token ) || $provided_token !== $expected_token ) {
            return new \WP_Error( 'rest_forbidden', 'Unauthorized webhook access.', [ 'status' => 401 ] );
        }

        return true;
    }
}

new BaleWebhook();
