<?php
/**
 * Plugin Name: RFKala AI Advisor
 * Plugin URI: https://rfkala.ir
 * Description: RAG-based AI Technical Advisor for RF networking equipment.
 * Version: 1.0.0
 * Author: RFKala
 * Author URI: https://rfkala.ir
 * Text Domain: rfkala-ai-advisor
 */

namespace RfkalaAiAdvisor;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants.
define( 'RFKALA_AI_ADVISOR_VERSION', '1.0.0' );
define( 'RFKALA_AI_ADVISOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RFKALA_AI_ADVISOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Define generic constants. Sensitive data must NOT be hardcoded here.
// Provide instructions on how the user can set these in wp-config.php or the WP Admin UI instead.
define( 'RFKALA_DEFAULT_AI_ENDPOINT', 'https://api.gapgpt.app/v1/chat/completions' );
define( 'RFKALA_DEFAULT_WC_PATH', 'https://rfkala.ir/wp-json/wc/v3/products/' );

// If the user hasn't defined these in their wp-config.php, provide empty defaults
if ( ! defined( 'RFKALA_DEFAULT_AI_API_KEY' ) ) define( 'RFKALA_DEFAULT_AI_API_KEY', '' );
if ( ! defined( 'RFKALA_DEFAULT_WC_KEY' ) ) define( 'RFKALA_DEFAULT_WC_KEY', '' );
if ( ! defined( 'RFKALA_DEFAULT_WC_SECRET' ) ) define( 'RFKALA_DEFAULT_WC_SECRET', '' );
if ( ! defined( 'RFKALA_DEFAULT_BOT_TOKEN' ) ) define( 'RFKALA_DEFAULT_BOT_TOKEN', '' );

// Autoloader.
spl_autoload_register( function ( $class ) {
    $prefix = 'RfkalaAiAdvisor\\';
    $base_dir = RFKALA_AI_ADVISOR_PLUGIN_DIR . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// Initialize Plugin.
function rfkala_ai_advisor_init() {
    // Admin Settings
    if ( is_admin() ) {
        new Admin\Settings();
    }

    // Frontend Widget
    new Frontend\Widget();

    // API Endpoints
    new API\ChatEndpoint();
    new API\WebhookHandler();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\rfkala_ai_advisor_init' );

// Plugin Activation Hook to force credentials into the DB immediately
register_activation_hook( __FILE__, function() {
    $default_settings = [
        'rfkala_ai_api_endpoint' => RFKALA_DEFAULT_AI_ENDPOINT,
        'rfkala_ai_api_key'      => RFKALA_DEFAULT_AI_API_KEY,
        'rfkala_wc_consumer_key' => RFKALA_DEFAULT_WC_KEY,
        'rfkala_wc_consumer_secret' => RFKALA_DEFAULT_WC_SECRET,
        'rfkala_bot_token'       => RFKALA_DEFAULT_BOT_TOKEN,
        'rfkala_wc_api_path'     => RFKALA_DEFAULT_WC_PATH,
    ];

    foreach ( $default_settings as $key => $value ) {
        // Use add_option on activation to initialize DB without overwriting existing user keys upon reactivation
        add_option( $key, $value );
    }
} );
