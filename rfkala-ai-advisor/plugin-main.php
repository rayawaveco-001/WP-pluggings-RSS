<?php
/**
 * Plugin Name: RFKala AI Advisor
 * Description: A smart, minimalist AI chat widget integrated directly with WooCommerce data, GapGPT, and Bale Messenger.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wp-news-collector
 */

namespace RFKala\AIAdvisor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RFKALA_AI_ADVISOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'RFKALA_AI_ADVISOR_URL', plugin_dir_url( __FILE__ ) );

// Autoloader/Includes
require_once RFKALA_AI_ADVISOR_PATH . 'admin-settings.php';
require_once RFKALA_AI_ADVISOR_PATH . 'ai-woo-logic.php';
require_once RFKALA_AI_ADVISOR_PATH . 'bale-webhook.php';

class Plugin {
    public function __construct() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_footer', [ $this, 'render_widget_html' ] );
    }

    public function activate() {
        global $wpdb;

        // Add default options
        $default_faqs = [
            [ 'question' => 'هزینه ارسال چقدر است؟', 'answer' => 'هزینه ارسال برای سفارش‌های بالای ۱ میلیون تومان رایگان است.' ],
            [ 'question' => 'چگونه می‌توانم سفارش خود را پیگیری کنم؟', 'answer' => 'شما می‌توانید از طریق پنل کاربری بخش سفارش‌ها وضعیت را مشاهده کنید.' ]
        ];
        add_option( 'rfkala_ai_faqs', $default_faqs );

        // Initialize empty settings
        add_option( 'rfkala_gapgpt_api_key', '' );
        add_option( 'rfkala_bale_bot_token', '' );
        add_option( 'rfkala_woo_ck', '' );
        add_option( 'rfkala_woo_cs', '' );
        add_option( 'rfkala_bale_admin_chat_id', '' );

        // Create custom table for Bale handoff messages
        $table_name = $wpdb->prefix . 'rfkala_bale_messages';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            message text NOT NULL,
            sender ENUM('user', 'admin') NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'rfkala-ai-widget-style',
            RFKALA_AI_ADVISOR_URL . 'widget.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'rfkala-ai-widget-script',
            RFKALA_AI_ADVISOR_URL . 'widget.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script( 'rfkala-ai-widget-script', 'rfkalaAiVars', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'rfkala_ai_nonce' ),
            'poll_url' => esc_url_raw( rest_url( 'chatbot/v1/poll-messages' ) ),
        ] );
    }

    public function render_widget_html() {
        ?>
        <div id="rfkala-ai-chatbot-wrapper">
            <div class="rfkala-chatbot-container" style="display: none;">
                <div class="rfkala-chatbot-header">
                    <span>پشتیبانی هوشمند و آنلاین</span>
                    <button id="rfkala-chatbot-close" title="بستن">&times;</button>
                </div>
                <div class="rfkala-chatbot-body" id="rfkala-chatbot-body">
                    <div class="rfkala-message bot">
                        سلام! من دستیار هوشمند شما هستم. چطور می‌توانم کمکتان کنم؟
                    </div>
                </div>
                <div class="rfkala-chatbot-typing" style="display: none;">
                    <div class="bounce1"></div>
                    <div class="bounce2"></div>
                    <div class="bounce3"></div>
                </div>
                <div class="rfkala-chatbot-footer">
                    <input type="text" id="rfkala-chatbot-input" placeholder="پیام خود را بنویسید..." autocomplete="off">
                    <button id="rfkala-chatbot-send">
                        <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                    </button>
                </div>
            </div>
            <button id="rfkala-chatbot-fab">
                <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path></svg>
            </button>
        </div>
        <?php
    }
}

new Plugin();
