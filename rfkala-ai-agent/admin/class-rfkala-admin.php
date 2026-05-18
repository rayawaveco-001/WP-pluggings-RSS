<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RFKala_Admin {

    const OPENAI_API_KEY = 'sk-nswFYxPqIYB98HCrKUjH3KJp7AyzHsQE8mJJ0uz5YzIwUTIF';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'wp_ajax_rfkala_save_rules', array( $this, 'ajax_save_rules' ) );
        add_action( 'wp_ajax_rfkala_extract_kb', array( $this, 'ajax_extract_kb' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'RFKala AI Agent',
            'RFKala AI',
            'manage_options',
            'rfkala-ai-agent',
            array( $this, 'render_admin_page' ),
            'dashicons-format-chat',
            6
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $rules_file = RFKALA_AI_AGENT_DIR . 'data/admin_rules.json';
        $rules = '';
        if ( file_exists( $rules_file ) ) {
            $rules = file_get_contents( $rules_file );
        }

        ?>
        <div class="wrap">
            <h1>RFKala AI Agent Settings</h1>

            <h2>Admin Override Rules (JSON)</h2>
            <p>Define your distance and environment rules here. These will be loaded dynamically by the API.</p>
            <textarea id="rfkala_rules_json" rows="10" cols="80" style="width: 100%; max-width: 800px;"><?php echo esc_textarea( $rules ); ?></textarea>
            <br>
            <button id="rfkala_save_rules" class="button button-primary">Save Rules</button>
            <span class="spinner" id="rfkala_save_spinner"></span>
            <span id="rfkala_save_msg"></span>

            <hr>

            <h2>Knowledge Base Extraction</h2>
            <p>Click the button below to fetch all WooCommerce products, grab missing specifications via OpenAI, and build the local <code>products_kb.json</code>.</p>
            <button id="rfkala_extract_kb" class="button">Extract Knowledge Base</button>
            <span class="spinner" id="rfkala_extract_spinner"></span>
            <span id="rfkala_extract_msg"></span>

        </div>

        <script>
        jQuery(document).ready(function($){
            $('#rfkala_save_rules').on('click', function(e){
                e.preventDefault();
                $('#rfkala_save_spinner').addClass('is-active');
                $('#rfkala_save_msg').text('');

                var rules = $('#rfkala_rules_json').val();

                $.post(ajaxurl, {
                    action: 'rfkala_save_rules',
                    nonce: '<?php echo wp_create_nonce("rfkala_admin_nonce"); ?>',
                    rules: rules
                }, function(response){
                    $('#rfkala_save_spinner').removeClass('is-active');
                    if(response.success){
                        $('#rfkala_save_msg').css('color', 'green').text('Rules saved successfully.');
                    } else {
                        $('#rfkala_save_msg').css('color', 'red').text('Error saving rules.');
                    }
                });
            });

            $('#rfkala_extract_kb').on('click', function(e){
                e.preventDefault();
                if(!confirm('This process may take some time. Are you sure you want to extract knowledge base?')) return;

                $('#rfkala_extract_spinner').addClass('is-active');
                $('#rfkala_extract_msg').css('color', 'black').text('Extracting... Please wait.');

                function extractBatch(page) {
                    $.post(ajaxurl, {
                        action: 'rfkala_extract_kb',
                        nonce: '<?php echo wp_create_nonce("rfkala_admin_nonce"); ?>',
                        page: page
                    }, function(response){
                        if(response.success){
                            if(response.data.done) {
                                $('#rfkala_extract_spinner').removeClass('is-active');
                                $('#rfkala_extract_msg').css('color', 'green').text('Extraction complete. Saved to products_kb.json.');
                            } else {
                                $('#rfkala_extract_msg').text('Extracting... Processed page ' + page + '. Please wait.');
                                extractBatch(page + 1);
                            }
                        } else {
                            $('#rfkala_extract_spinner').removeClass('is-active');
                            $('#rfkala_extract_msg').css('color', 'red').text('Error during extraction: ' + response.data);
                        }
                    }).fail(function() {
                        $('#rfkala_extract_spinner').removeClass('is-active');
                        $('#rfkala_extract_msg').css('color', 'red').text('A network error occurred.');
                    });
                }

                extractBatch(1);
            });
        });
        </script>
        <?php
    }

    public function ajax_save_rules() {
        check_ajax_referer( 'rfkala_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $rules = isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : '';

        // Validate JSON
        json_decode( $rules );
        if ( json_last_error() !== JSON_ERROR_NONE && !empty(trim($rules)) ) {
             wp_send_json_error( 'Invalid JSON' );
        }

        $data_dir = RFKALA_AI_AGENT_DIR . 'data';
        if ( ! file_exists( $data_dir ) ) {
            mkdir( $data_dir, 0755, true );
            file_put_contents( $data_dir . '/.htaccess', 'Deny from all' );
            file_put_contents( $data_dir . '/index.php', '<?php // Silence is golden.' );
        }

        $rules_file = $data_dir . '/admin_rules.json';
        $result = file_put_contents( $rules_file, $rules );

        if ( $result !== false ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( 'Failed to write file' );
        }
    }

    public function ajax_extract_kb() {
        check_ajax_referer( 'rfkala_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        // Only proceed if WooCommerce is active
        if ( ! function_exists( 'wc_get_products' ) ) {
            wp_send_json_error( 'WooCommerce not active' );
        }

        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
        $limit = 5;

        $products = wc_get_products( array(
            'limit'  => $limit,
            'page'   => $page,
            'status' => 'publish',
            'return' => 'objects',
        ) );

        $data_dir = RFKALA_AI_AGENT_DIR . 'data';
        if ( ! file_exists( $data_dir ) ) {
            mkdir( $data_dir, 0755, true );
            file_put_contents( $data_dir . '/.htaccess', 'Deny from all' );
            file_put_contents( $data_dir . '/index.php', '<?php // Silence is golden.' );
        }

        $kb_file = $data_dir . '/products_kb.json';
        $kb_data = array();

        if ( $page > 1 && file_exists( $kb_file ) ) {
            $existing_data = file_get_contents( $kb_file );
            if ( $existing_data ) {
                $kb_data = json_decode( $existing_data, true );
                if ( ! is_array( $kb_data ) ) {
                    $kb_data = array();
                }
            }
        }

        if ( empty( $products ) ) {
            wp_send_json_success( array( 'done' => true ) );
        }

        foreach ( $products as $product ) {
            $id = $product->get_id();
            $title = $product->get_name();
            $description = wp_strip_all_tags( $product->get_description() );
            $short_desc = wp_strip_all_tags( $product->get_short_description() );

            // Call OpenAI to extract key technical specifications
            $specs = $this->extract_specs_via_openai( $title, $description . ' ' . $short_desc );

            $kb_data[] = array(
                'id' => $id,
                'title' => $title,
                'specs' => $specs
            );
        }

        $result = file_put_contents( $kb_file, wp_json_encode( $kb_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

        if ( $result !== false ) {
            wp_send_json_success( array( 'done' => false ) );
        } else {
            wp_send_json_error( 'Failed to save KB' );
        }
    }

    private function extract_specs_via_openai( $title, $description ) {
        $api_key = self::OPENAI_API_KEY;
        $url = 'https://api.openai.com/v1/chat/completions';

        $prompt = "Extract key RF technical specifications (frequency, gain, impedance, connector type, etc.) from the following product title and description. Return only a concise summary.\n\nTitle: $title\nDescription: $description";

        $body = wp_json_encode( array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an RF engineer. Extract specs cleanly.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 100,
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
            return 'Could not fetch specs.';
        }

        $body_res = wp_remote_retrieve_body( $response );
        $data = json_decode( $body_res, true );

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return trim( $data['choices'][0]['message']['content'] );
        }

        return 'No specs extracted.';
    }
}
