<?php

namespace RfkalaAiAdvisor\API;

use RfkalaAiAdvisor\AI\Engine;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ChatEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'rfkala-ai/v1', '/chat', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'handle_chat' ],
            'permission_callback' => '__return_true', // Open endpoint for frontend users
        ] );
    }

    public function handle_chat( $request ) {
        $message = sanitize_text_field( $request->get_param( 'message' ) );

        if ( empty( $message ) ) {
            return rest_ensure_response( [
                'success' => false,
                'data'    => 'Message cannot be empty.'
            ] );
        }

        $engine = new Engine();
        $reply = $engine->process_message( $message );

        return rest_ensure_response( [
            'success' => true,
            'data'    => $reply
        ] );
    }
}
