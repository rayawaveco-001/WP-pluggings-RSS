<?php
namespace RFKala\AIAdvisor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminSettings {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
    }

    public function add_menu_page() {
        add_menu_page(
            'تنظیمات چت‌بات',
            'تنظیمات چت‌بات',
            'manage_options',
            'rfkala-ai-chatbot-settings',
            [ $this, 'render_settings_page' ],
            'dashicons-format-chat',
            30
        );
    }

    public function handle_form_submission() {
        if ( isset( $_POST['rfkala_ai_save_faqs'] ) && current_user_can( 'manage_options' ) ) {
            check_admin_referer( 'rfkala_ai_save_faqs_action', 'rfkala_ai_save_faqs_nonce' );

            $questions = isset( $_POST['faq_questions'] ) ? wp_unslash( $_POST['faq_questions'] ) : [];
            $answers   = isset( $_POST['faq_answers'] ) ? wp_unslash( $_POST['faq_answers'] ) : [];

            $faqs = [];
            if ( is_array( $questions ) && is_array( $answers ) ) {
                $count = count( $questions );
                for ( $i = 0; $i < $count; $i++ ) {
                    $q = sanitize_text_field( $questions[$i] );
                    $a = sanitize_textarea_field( $answers[$i] );
                    if ( ! empty( $q ) && ! empty( $a ) ) {
                        $faqs[] = [ 'question' => $q, 'answer' => $a ];
                    }
                }
            }

            update_option( 'rfkala_ai_faqs', $faqs );

            if ( isset( $_POST['rfkala_gapgpt_api_key'] ) ) {
                update_option( 'rfkala_gapgpt_api_key', sanitize_text_field( $_POST['rfkala_gapgpt_api_key'] ) );
            }
            if ( isset( $_POST['rfkala_bale_bot_token'] ) ) {
                update_option( 'rfkala_bale_bot_token', sanitize_text_field( $_POST['rfkala_bale_bot_token'] ) );
            }
            if ( isset( $_POST['rfkala_woo_ck'] ) ) {
                update_option( 'rfkala_woo_ck', sanitize_text_field( $_POST['rfkala_woo_ck'] ) );
            }
            if ( isset( $_POST['rfkala_woo_cs'] ) ) {
                update_option( 'rfkala_woo_cs', sanitize_text_field( $_POST['rfkala_woo_cs'] ) );
            }
            if ( isset( $_POST['rfkala_bale_admin_chat_id'] ) ) {
                update_option( 'rfkala_bale_admin_chat_id', sanitize_text_field( $_POST['rfkala_bale_admin_chat_id'] ) );
            }

            add_settings_error(
                'rfkala_ai_messages',
                'rfkala_ai_message',
                'تنظیمات با موفقیت ذخیره شد.',
                'updated'
            );
        }
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $faqs = get_option( 'rfkala_ai_faqs', [] );
        $gapgpt_key = get_option( 'rfkala_gapgpt_api_key', '' );
        $bale_token = get_option( 'rfkala_bale_bot_token', '' );
        $woo_ck = get_option( 'rfkala_woo_ck', '' );
        $woo_cs = get_option( 'rfkala_woo_cs', '' );
        $admin_chat_id = get_option( 'rfkala_bale_admin_chat_id', '' );

        settings_errors( 'rfkala_ai_messages' );
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1>تنظیمات چت‌بات هوشمند</h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'rfkala_ai_save_faqs_action', 'rfkala_ai_save_faqs_nonce' ); ?>

                <h2>تنظیمات API</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="rfkala_gapgpt_api_key">GapGPT API Key</label></th>
                        <td>
                            <input name="rfkala_gapgpt_api_key" type="password" id="rfkala_gapgpt_api_key" value="<?php echo esc_attr( $gapgpt_key ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rfkala_bale_bot_token">Bale Bot Token</label></th>
                        <td>
                            <input name="rfkala_bale_bot_token" type="password" id="rfkala_bale_bot_token" value="<?php echo esc_attr( $bale_token ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rfkala_woo_ck">WooCommerce Consumer Key</label></th>
                        <td>
                            <input name="rfkala_woo_ck" type="password" id="rfkala_woo_ck" value="<?php echo esc_attr( $woo_ck ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rfkala_woo_cs">WooCommerce Consumer Secret</label></th>
                        <td>
                            <input name="rfkala_woo_cs" type="password" id="rfkala_woo_cs" value="<?php echo esc_attr( $woo_cs ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rfkala_bale_admin_chat_id">شناسه چت ادمین در بله (Chat ID)</label></th>
                        <td>
                            <input name="rfkala_bale_admin_chat_id" type="text" id="rfkala_bale_admin_chat_id" value="<?php echo esc_attr( $admin_chat_id ); ?>" class="regular-text">
                            <p class="description">برای ارسال پیام‌های کاربران به ادمین در بله، شناسه عددی چت ادمین را وارد کنید.</p>
                        </td>
                    </tr>
                </table>

                <h2>پرسش و پاسخ‌ها (FAQs)</h2>

                <table class="widefat fixed striped" id="rfkala-faq-table" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 40%;">پرسش (Question)</th>
                            <th style="width: 50%;">پاسخ (Answer)</th>
                            <th style="width: 10%;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="rfkala-faq-body">
                        <?php
                        if ( ! empty( $faqs ) ) {
                            foreach ( $faqs as $faq ) {
                                $this->render_faq_row( $faq['question'], $faq['answer'] );
                            }
                        } else {
                            $this->render_faq_row();
                        }
                        ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button button-secondary" id="rfkala-add-faq">افزودن ردیف جدید</button>
                </p>
                <p class="submit">
                    <input type="submit" name="rfkala_ai_save_faqs" id="submit" class="button button-primary" value="ذخیره تنظیمات">
                </p>
            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var tbody = document.getElementById('rfkala-faq-body');
                var addButton = document.getElementById('rfkala-add-faq');

                addButton.addEventListener('click', function() {
                    var tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><input type="text" name="faq_questions[]" value="" class="regular-text" style="width: 100%;" required></td>
                        <td><textarea name="faq_answers[]" rows="3" style="width: 100%;" required></textarea></td>
                        <td><button type="button" class="button rfkala-remove-faq">حذف</button></td>
                    `;
                    tbody.appendChild(tr);
                });

                tbody.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('rfkala-remove-faq')) {
                        e.target.closest('tr').remove();
                    }
                });
            });
        </script>
        <?php
    }

    private function render_faq_row( $q = '', $a = '' ) {
        ?>
        <tr>
            <td>
                <input type="text" name="faq_questions[]" value="<?php echo esc_attr( $q ); ?>" class="regular-text" style="width: 100%;" required>
            </td>
            <td>
                <textarea name="faq_answers[]" rows="3" style="width: 100%;" required><?php echo esc_textarea( $a ); ?></textarea>
            </td>
            <td>
                <button type="button" class="button rfkala-remove-faq">حذف</button>
            </td>
        </tr>
        <?php
    }
}

new AdminSettings();
