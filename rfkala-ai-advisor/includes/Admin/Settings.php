<?php

namespace RfkalaAiAdvisor\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_settings_page() {
        add_options_page(
            'RFKala AI Advisor Settings',
            'RFKala AI Advisor',
            'manage_options',
            'rfkala-ai-advisor',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        // We initialize the settings without forcing them via update_option
        // to prevent overwriting user input on every admin load.
        // Also removed plain-text secrets from code per secure engineering standards.
        $settings = [
            'rfkala_ai_api_endpoint' => RFKALA_DEFAULT_AI_ENDPOINT,
            'rfkala_ai_api_key'      => '',
            'rfkala_wc_consumer_key' => '',
            'rfkala_wc_consumer_secret' => '',
            'rfkala_bot_token'       => '',
            'rfkala_wc_api_path'     => RFKALA_DEFAULT_WC_PATH,
        ];

        foreach ( $settings as $option_name => $default_value ) {
            // Provide default values correctly to the option registration
            register_setting( 'rfkala_ai_advisor_settings_group', $option_name, [
                'default' => $default_value,
                'sanitize_callback' => 'sanitize_text_field'
            ] );

            // If the option has literally never been saved to the DB, initialize it once
            if ( get_option( $option_name ) === false ) {
                add_option( $option_name, $default_value );
            }
        }
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>RFKala AI Advisor Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'rfkala_ai_advisor_settings_group' );
                do_settings_sections( 'rfkala_ai_advisor_settings_group' );
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">AI API Endpoint</th>
                        <td><input type="text" name="rfkala_ai_api_endpoint" value="<?php echo esc_attr( get_option('rfkala_ai_api_endpoint') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">AI API Key</th>
                        <td><input type="password" name="rfkala_ai_api_key" value="<?php echo esc_attr( get_option('rfkala_ai_api_key') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">WooCommerce Consumer Key</th>
                        <td><input type="text" name="rfkala_wc_consumer_key" value="<?php echo esc_attr( get_option('rfkala_wc_consumer_key') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">WooCommerce Consumer Secret</th>
                        <td><input type="password" name="rfkala_wc_consumer_secret" value="<?php echo esc_attr( get_option('rfkala_wc_consumer_secret') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">WooCommerce REST API Path</th>
                        <td><input type="text" name="rfkala_wc_api_path" value="<?php echo esc_attr( get_option('rfkala_wc_api_path') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Telegram/Bale Bot Token</th>
                        <td><input type="password" name="rfkala_bot_token" value="<?php echo esc_attr( get_option('rfkala_bot_token') ); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
