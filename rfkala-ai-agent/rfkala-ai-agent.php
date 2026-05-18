<?php
/**
 * Plugin Name: RFKala AI Technical Sales Agent
 * Description: A standalone WordPress plugin acting as an AI Technical Sales Agent for RFKala.ir. Includes a floating chat widget and a REST API endpoint.
 * Version: 1.0.0
 * Author: Jules
 * Text Domain: rfkala-ai-agent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'RFKALA_AI_AGENT_VERSION', '1.0.0' );
define( 'RFKALA_AI_AGENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'RFKALA_AI_AGENT_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files
require_once RFKALA_AI_AGENT_DIR . 'admin/class-rfkala-admin.php';
require_once RFKALA_AI_AGENT_DIR . 'api/class-rfkala-api.php';

class RFKala_AI_Agent {

    public function __construct() {
        $this->init_hooks();
        $this->init_classes();
    }

    private function init_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_footer', array( $this, 'inject_chat_widget' ) );
    }

    private function init_classes() {
        if ( is_admin() ) {
            new RFKala_Admin();
        }
        new RFKala_API();
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'rfkala-ai-chat-css',
            RFKALA_AI_AGENT_URL . 'assets/css/style.css',
            array(),
            RFKALA_AI_AGENT_VERSION
        );

        wp_enqueue_script(
            'rfkala-ai-chat-js',
            RFKALA_AI_AGENT_URL . 'assets/js/script.js',
            array('jquery'),
            RFKALA_AI_AGENT_VERSION,
            true
        );

        wp_localize_script(
            'rfkala-ai-chat-js',
            'rfkala_ai_agent_obj',
            array(
                'api_url' => rest_url( 'rfkala/v1/chat' ),
                'nonce'   => wp_create_nonce( 'wp_rest' )
            )
        );
    }

    public function inject_chat_widget() {
        ?>
        <div id="rfkala-chat-widget" class="rfkala-chat-widget">
            <div id="rfkala-chat-header" class="rfkala-chat-header">
                <span>RFKala AI Assistant</span>
                <button id="rfkala-chat-toggle" class="rfkala-chat-toggle">_</button>
            </div>
            <div id="rfkala-chat-body" class="rfkala-chat-body">
                <div id="rfkala-chat-messages" class="rfkala-chat-messages"></div>
            </div>
            <div id="rfkala-chat-footer" class="rfkala-chat-footer">
                <input type="text" id="rfkala-chat-input" placeholder="Ask about our products..." />
                <button id="rfkala-chat-send">Send</button>
            </div>
        </div>
        <button id="rfkala-chat-launcher" class="rfkala-chat-launcher">💬 Chat</button>
        <?php
    }
}

new RFKala_AI_Agent();
