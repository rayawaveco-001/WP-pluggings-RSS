<?php
/**
 * Fetching Logic & WP-Cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPNC_Fetcher {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( 'init', array( $this, 'schedule_cron_events' ) );
		add_action( 'wpnc_fetch_news_event', array( $this, 'fetch_news' ) );
		add_action( 'wpnc_cleanup_news_event', array( $this, 'cleanup_queue' ) );
		add_action( 'update_option_wpnc_interval', array( $this, 'reschedule_cron' ), 10, 3 );
	}

	/**
	 * Add custom cron schedules.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'wp-news-collector' ),
		);
		$schedules['3hours'] = array(
			'interval' => 3 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 3 Hours', 'wp-news-collector' ),
		);
		return $schedules;
	}

	/**
	 * Schedule cron events.
	 */
	public function schedule_cron_events() {
		$interval = get_option( 'wpnc_interval', 'hourly' );

		if ( ! wp_next_scheduled( 'wpnc_fetch_news_event' ) ) {
			wp_schedule_event( time(), $interval, 'wpnc_fetch_news_event' );
		}

		if ( ! wp_next_scheduled( 'wpnc_cleanup_news_event' ) ) {
			wp_schedule_event( time(), 'daily', 'wpnc_cleanup_news_event' );
		}
	}

	/**
	 * Reschedule cron when interval setting changes.
	 */
	public function reschedule_cron( $old_value, $value, $option ) {
		wp_clear_scheduled_hook( 'wpnc_fetch_news_event' );
		wp_schedule_event( time(), $value, 'wpnc_fetch_news_event' );
	}

	/**
	 * Fetch news from RSS sources.
	 */
	public function fetch_news() {
		$rss_links_text = get_option( 'wpnc_rss_links', '' );
		if ( empty( trim( $rss_links_text ) ) ) {
			return;
		}

		$links = array_filter( array_map( 'trim', explode( "\n", $rss_links_text ) ) );
		$auto_publish = get_option( 'wpnc_auto_publish', 0 );
		$include_words = array_filter( array_map( 'trim', explode( ',', get_option( 'wpnc_include_words', '' ) ) ) );
		$exclude_words = array_filter( array_map( 'trim', explode( ',', get_option( 'wpnc_exclude_words', '' ) ) ) );

		$total_fetched = 0;

		require_once ABSPATH . WPINC . '/feed.php';

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		foreach ( $links as $link_raw ) {
			$parts = explode( '|', $link_raw );
			$link = trim( $parts[0] );
			$category_id = isset( $parts[1] ) ? intval( trim( $parts[1] ) ) : 0;

			$feed = fetch_feed( $link );
			if ( is_wp_error( $feed ) ) {
				continue;
			}

			$source_name = $feed->get_title();
			$items = $feed->get_items( 0, 20 );

			foreach ( $items as $item ) {
				$main_link = esc_url_raw( $item->get_permalink() );
				$title     = sanitize_text_field( $item->get_title() );
				$desc      = wp_kses_post( $item->get_description() );
				$pub_date  = $item->get_date( 'Y-m-d H:i:s' );

				if ( ! $pub_date ) {
					$pub_date = current_time( 'mysql' );
				}

				// Filtering Logic
				$content_to_check = mb_strtolower( $title . ' ' . wp_strip_all_tags( $desc ) );

				// Exclude check
				$skip = false;
				foreach ( $exclude_words as $word ) {
					if ( mb_strpos( $content_to_check, mb_strtolower( $word ) ) !== false ) {
						$skip = true;
						break;
					}
				}
				if ( $skip ) {
					continue;
				}

				// Include check
				if ( ! empty( $include_words ) ) {
					$matched = false;
					foreach ( $include_words as $word ) {
						if ( mb_strpos( $content_to_check, mb_strtolower( $word ) ) !== false ) {
							$matched = true;
							break;
						}
					}
					if ( ! $matched ) {
						continue;
					}
				}

				// Check duplicates
				$exists_queue = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE main_link = %s", $main_link ) );
				if ( $exists_queue ) {
					continue;
				}

				// Check if it exists in wp_posts
				$exists_post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s", '%' . $wpdb->esc_like( $main_link ) . '%' ) );
				if ( $exists_post ) {
					continue;
				}

				$image_url = $this->extract_image( $item, $main_link );

				if ( $auto_publish ) {
					$this->publish_post( $title, $desc, $main_link, $source_name, $image_url, $pub_date, $category_id );
				} else {
					$wpdb->insert(
						$table_name,
						array(
							'source_name' => $source_name,
							'title'       => $title,
							'description' => $desc,
							'main_link'   => $main_link,
							'image_url'   => $image_url,
							'pub_date'    => $pub_date,
							'status'      => 'pending',
							'category_id' => $category_id,
						),
						array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
					);
				}
				$total_fetched++;
			}
		}

		update_option( 'wpnc_last_run', current_time( 'mysql' ) );
		update_option( 'wpnc_last_count', $total_fetched );
	}

	/**
	 * Extract image URL from feed item or scrape og:image.
	 */
	private function extract_image( $item, $main_link ) {
		// First try standard enclosures
		if ( $enclosure = $item->get_enclosure() ) {
			$link = $enclosure->get_link();
			if ( $link && strpos( $enclosure->get_type(), 'image/' ) === 0 ) {
				return esc_url_raw( $link );
			}
		}

		// Try scraping og:image
		$response = wp_remote_get( $main_link );
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$html = wp_remote_retrieve_body( $response );
			if ( $html ) {
				// Suppress warnings from malformed HTML
				libxml_use_internal_errors( true );
				$doc = new DOMDocument();
				$doc->loadHTML( $html );
				libxml_clear_errors();

				$xpath = new DOMXPath( $doc );
				$meta = $xpath->query( '//meta[@property="og:image"]' );
				if ( $meta->length > 0 ) {
					return esc_url_raw( $meta->item(0)->getAttribute( 'content' ) );
				}
			}
		}

		return '';
	}

	/**
	 * Publish post directly.
	 */
	public function publish_post( $title, $description, $main_link, $source_name, $image_url, $pub_date, $category_id = 0 ) {
		$default_category = get_option( 'wpnc_default_category', 0 );
		$cat_id = $category_id ? $category_id : $default_category;

		$content = $description . "\n\n" . sprintf( 'منبع: <a href="%s" target="_blank" rel="nofollow">%s</a>', esc_url( $main_link ), esc_html( $source_name ) );

		$post_data = array(
			'post_title'    => wp_strip_all_tags( $title ),
			'post_content'  => $content,
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_date'     => $pub_date,
			'post_category' => $cat_id ? array( $cat_id ) : array(),
		);

		$post_id = wp_insert_post( $post_data );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			if ( empty( $image_url ) ) {
				$image_url = get_option( 'wpnc_default_image', '' );
			}

			if ( ! empty( $image_url ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );
				if ( ! is_wp_error( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
				} else {
					add_post_meta( $post_id, 'wpnc_source_image', $image_url ); // Fallback to saving original URL
				}
			}
		}

		return $post_id;
	}

	/**
	 * Cleanup old and rejected queue items.
	 */
	public function cleanup_queue() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		// Delete rejected or older than 7 days
		$seven_days_ago = date( 'Y-m-d H:i:s', strtotime( '-7 days', current_time( 'timestamp' ) ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE status = 'rejected' OR pub_date < %s", $seven_days_ago ) );
	}
}

// Initialize
new WPNC_Fetcher();
