<?php
/**
 * Database & Activation logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPNC_DB {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		register_activation_hook( WPNC_PLUGIN_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Plugin activation hook.
	 */
	public function activate() {
		$this->create_queue_table();
		// Can add initial options here if needed
	}

	/**
	 * Create the custom table wp_news_queue.
	 */
	private function create_queue_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'news_queue';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			source_name varchar(255) NOT NULL,
			title text NOT NULL,
			description text NOT NULL,
			main_link varchar(2083) NOT NULL,
			image_url varchar(2083) DEFAULT '' NOT NULL,
			pub_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			status varchar(50) DEFAULT 'pending' NOT NULL,
			category_id int(11) DEFAULT 0 NOT NULL,
			tags varchar(255) DEFAULT '' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY main_link (main_link(191))
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

// Initialize
new WPNC_DB();
