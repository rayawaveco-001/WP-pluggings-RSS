<?php

namespace RfkalaAiAdvisor\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Widget {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_footer', [ $this, 'render_widget' ] );
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'rfkala-ai-widget-css',
            RFKALA_AI_ADVISOR_PLUGIN_URL . 'assets/css/widget.css',
            [],
            RFKALA_AI_ADVISOR_VERSION
        );

        wp_enqueue_script(
            'rfkala-ai-widget-js',
            RFKALA_AI_ADVISOR_PLUGIN_URL . 'assets/js/widget.js',
            [],
            RFKALA_AI_ADVISOR_VERSION,
            true
        );

        wp_localize_script( 'rfkala-ai-widget-js', 'RfkalaAiConfig', [
            'apiUrl' => esc_url_raw( rest_url( 'rfkala-ai/v1/chat' ) )
        ] );
    }

    public function render_widget() {
        $template_path = RFKALA_AI_ADVISOR_PLUGIN_DIR . 'templates/widget.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }
}
