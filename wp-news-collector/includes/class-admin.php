<?php
/**
 * Admin Panel & Settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPNC_Admin {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'News Collector', 'wp-news-collector' ),
			__( 'News Collector', 'wp-news-collector' ),
			'manage_options',
			'wpnc-news-collector',
			array( $this, 'render_admin_page' ),
			'dashicons-rss',
			30
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'wpnc_settings_group', 'wpnc_rss_links', 'sanitize_textarea_field' );
		register_setting( 'wpnc_settings_group', 'wpnc_interval', 'sanitize_text_field' );
		register_setting( 'wpnc_settings_group', 'wpnc_target_post_type', 'sanitize_text_field' );
		register_setting( 'wpnc_settings_group', 'wpnc_default_category', 'absint' );
		register_setting( 'wpnc_settings_group', 'wpnc_auto_publish', 'absint' );
		register_setting( 'wpnc_settings_group', 'wpnc_default_image', 'esc_url_raw' );
		register_setting( 'wpnc_settings_group', 'wpnc_extract_full_text', 'absint' );
		register_setting( 'wpnc_settings_group', 'wpnc_include_words', 'sanitize_text_field' );
		register_setting( 'wpnc_settings_group', 'wpnc_exclude_words', 'sanitize_text_field' );

		// AI Settings
		register_setting( 'wpnc_settings_group', 'wpnc_openai_api_key', 'sanitize_text_field' );
		register_setting( 'wpnc_settings_group', 'wpnc_auto_rewrite', 'absint' );
		register_setting( 'wpnc_settings_group', 'wpnc_target_language', 'sanitize_text_field' );

		// Telegram
		register_setting( 'wpnc_settings_group', 'wpnc_telegram_token', 'sanitize_text_field' );
		register_setting( 'wpnc_settings_group', 'wpnc_telegram_chat_id', 'sanitize_text_field' );

		// Notifications
		register_setting( 'wpnc_settings_group', 'wpnc_admin_notify', 'absint' );
	}

	/**
	 * Render the admin page with tabs.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP News Collector', 'wp-news-collector' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=wpnc-news-collector&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'wp-news-collector' ); ?></a>
				<a href="?page=wpnc-news-collector&tab=moderation" class="nav-tab <?php echo $active_tab === 'moderation' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Moderation Queue', 'wp-news-collector' ); ?></a>
				<a href="?page=wpnc-news-collector&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Logs', 'wp-news-collector' ); ?></a>
			</h2>

			<div class="wpnc-tab-content">
				<?php
				if ( 'settings' === $active_tab ) {
					$this->render_settings_tab();
				} elseif ( 'moderation' === $active_tab ) {
					$this->render_moderation_tab();
				} elseif ( 'logs' === $active_tab ) {
					$this->render_logs_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Settings tab.
	 */
	private function render_settings_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'wpnc_settings_group' );
			do_settings_sections( 'wpnc_settings_group' );
			?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'RSS Links (One per line)', 'wp-news-collector' ); ?></th>
					<td>
						<textarea name="wpnc_rss_links" rows="10" cols="50" class="large-text code"><?php echo esc_textarea( get_option( 'wpnc_rss_links', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Format: https://example.com/feed or https://example.com/feed|category_id', 'wp-news-collector' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Update Interval', 'wp-news-collector' ); ?></th>
					<td>
						<?php $interval = get_option( 'wpnc_interval', 'hourly' ); ?>
						<select name="wpnc_interval">
							<option value="15min" <?php selected( $interval, '15min' ); ?>><?php esc_html_e( '15 Minutes', 'wp-news-collector' ); ?></option>
							<option value="hourly" <?php selected( $interval, 'hourly' ); ?>><?php esc_html_e( '1 Hour', 'wp-news-collector' ); ?></option>
							<option value="3hours" <?php selected( $interval, '3hours' ); ?>><?php esc_html_e( '3 Hours', 'wp-news-collector' ); ?></option>
							<option value="twicedaily" <?php selected( $interval, 'twicedaily' ); ?>><?php esc_html_e( '12 Hours', 'wp-news-collector' ); ?></option>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Target Post Type', 'wp-news-collector' ); ?></th>
					<td>
						<?php $target_pt = get_option( 'wpnc_target_post_type', 'post' ); ?>
						<select name="wpnc_target_post_type">
							<option value="post" <?php selected( $target_pt, 'post' ); ?>><?php esc_html_e( 'Standard Post', 'wp-news-collector' ); ?></option>
							<option value="wpnc_news" <?php selected( $target_pt, 'wpnc_news' ); ?>><?php esc_html_e( 'News (Custom Post Type)', 'wp-news-collector' ); ?></option>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Default Category', 'wp-news-collector' ); ?></th>
					<td>
						<?php
						$default_category = get_option( 'wpnc_default_category', 0 );
						wp_dropdown_categories( array(
							'hide_empty'       => 0,
							'name'             => 'wpnc_default_category',
							'selected'         => $default_category,
							'show_option_none' => __( 'None', 'wp-news-collector' ),
						) );
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Auto-Publish', 'wp-news-collector' ); ?></th>
					<td>
						<input type="checkbox" name="wpnc_auto_publish" value="1" <?php checked( get_option( 'wpnc_auto_publish', 0 ), 1 ); ?> />
						<label for="wpnc_auto_publish"><?php esc_html_e( 'Publish directly without moderation queue', 'wp-news-collector' ); ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Default Fallback Image URL', 'wp-news-collector' ); ?></th>
					<td>
						<input type="url" name="wpnc_default_image" value="<?php echo esc_url( get_option( 'wpnc_default_image', '' ) ); ?>" class="large-text" />
						<p class="description"><?php esc_html_e( 'URL of the image to use if the RSS feed has no image.', 'wp-news-collector' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Must Include Words (Comma separated)', 'wp-news-collector' ); ?></th>
					<td>
						<input type="text" name="wpnc_include_words" value="<?php echo esc_attr( get_option( 'wpnc_include_words', '' ) ); ?>" class="large-text" />
						<p class="description"><?php esc_html_e( 'Only fetch news that contain at least one of these words in the title or description. Leave empty to disable.', 'wp-news-collector' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Exclude Words (Comma separated)', 'wp-news-collector' ); ?></th>
					<td>
						<input type="text" name="wpnc_exclude_words" value="<?php echo esc_attr( get_option( 'wpnc_exclude_words', '' ) ); ?>" class="large-text" />
						<p class="description"><?php esc_html_e( 'Skip news that contain any of these words.', 'wp-news-collector' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Extract Full Text', 'wp-news-collector' ); ?></th>
					<td>
						<input type="checkbox" name="wpnc_extract_full_text" value="1" <?php checked( get_option( 'wpnc_extract_full_text', 0 ), 1 ); ?> />
						<label for="wpnc_extract_full_text"><?php esc_html_e( 'Attempt to scrape the full article content from the source URL (Note: May slow down fetching).', 'wp-news-collector' ); ?></label>
					</td>
				</tr>
			</table>

			<hr>
			<h3><?php esc_html_e( 'AI Rewrite (OpenAI)', 'wp-news-collector' ); ?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'OpenAI API Key', 'wp-news-collector' ); ?></th>
					<td>
						<input type="password" name="wpnc_openai_api_key" value="<?php echo esc_attr( get_option( 'wpnc_openai_api_key', '' ) ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Auto-Rewrite News', 'wp-news-collector' ); ?></th>
					<td>
						<input type="checkbox" name="wpnc_auto_rewrite" value="1" <?php checked( get_option( 'wpnc_auto_rewrite', 0 ), 1 ); ?> />
						<label for="wpnc_auto_rewrite"><?php esc_html_e( 'Rewrite title, description, and auto-generate Tags using AI before placing in queue.', 'wp-news-collector' ); ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Target Language', 'wp-news-collector' ); ?></th>
					<td>
						<input type="text" name="wpnc_target_language" value="<?php echo esc_attr( get_option( 'wpnc_target_language', '' ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Leave empty to keep original. Example: "Persian", "Spanish". Requires OpenAI API key.', 'wp-news-collector' ); ?></p>
					</td>
				</tr>
			</table>

			<hr>
			<h3><?php esc_html_e( 'Telegram Auto-Post', 'wp-news-collector' ); ?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Telegram Bot Token', 'wp-news-collector' ); ?></th>
					<td>
						<input type="text" name="wpnc_telegram_token" value="<?php echo esc_attr( get_option( 'wpnc_telegram_token', '' ) ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Telegram Chat ID', 'wp-news-collector' ); ?></th>
					<td>
						<input type="text" name="wpnc_telegram_chat_id" value="<?php echo esc_attr( get_option( 'wpnc_telegram_chat_id', '' ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Channel ID (e.g., @mychannel) or Group/User Chat ID.', 'wp-news-collector' ); ?></p>
					</td>
				</tr>
			</table>

			<hr>
			<h3><?php esc_html_e( 'Admin Notifications', 'wp-news-collector' ); ?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Email Notifications', 'wp-news-collector' ); ?></th>
					<td>
						<input type="checkbox" name="wpnc_admin_notify" value="1" <?php checked( get_option( 'wpnc_admin_notify', 0 ), 1 ); ?> />
						<label for="wpnc_admin_notify"><?php esc_html_e( 'Send me a daily email when new items are added to the Moderation Queue.', 'wp-news-collector' ); ?></label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the Moderation tab.
	 */
	private function render_moderation_tab() {
		echo '<div id="wpnc-moderation-app"></div>'; // To be handled by JS/AJAX
	}

	/**
	 * Render the Logs tab.
	 */
	private function render_logs_tab() {
		$last_run = get_option( 'wpnc_last_run', __( 'Never', 'wp-news-collector' ) );
		$last_count = get_option( 'wpnc_last_count', 0 );

		echo '<h3>' . esc_html__( 'Last Run Details', 'wp-news-collector' ) . '</h3>';
		echo '<p>' . esc_html__( 'Last Update:', 'wp-news-collector' ) . ' <strong>' . esc_html( $last_run ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Items Fetched:', 'wp-news-collector' ) . ' <strong>' . intval( $last_count ) . '</strong></p>';
		echo '<hr>';
		echo '<h3>' . esc_html__( 'Queue Statistics', 'wp-news-collector' ) . '</h3>';
		echo '<div style="max-width: 600px;"><canvas id="wpnc-stats-chart"></canvas></div>';
	}
}

// Initialize
new WPNC_Admin();
