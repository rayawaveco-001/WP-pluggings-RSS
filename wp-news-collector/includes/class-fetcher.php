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
		$extract_full_text = get_option( 'wpnc_extract_full_text', 0 );
		$target_post_type = get_option( 'wpnc_target_post_type', 'post' );
		$include_words = array_filter( array_map( 'trim', explode( ',', get_option( 'wpnc_include_words', '' ) ) ) );
		$exclude_words = array_filter( array_map( 'trim', explode( ',', get_option( 'wpnc_exclude_words', '' ) ) ) );

		$total_fetched = 0;

		require_once ABSPATH . WPINC . '/feed.php';

		global $wpdb;
		$new_queue_items_count = 0;
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

				if ( $extract_full_text ) {
					$full_text = $this->extract_full_text( $main_link );
					if ( ! empty( $full_text ) ) {
						$desc = $full_text; // Override description with full text
					}
				}

				$tags = '';

				// AI Rewrite & Tagging
				if ( get_option( 'wpnc_auto_rewrite', 0 ) && get_option( 'wpnc_openai_api_key', '' ) ) {
					$rewritten = $this->rewrite_with_ai( $title, $desc );
					if ( $rewritten ) {
						$title = $rewritten['title'];
						$desc = $rewritten['description'];
						$tags = $rewritten['tags'];
					}
				}

				if ( $auto_publish ) {
					$this->publish_post( $title, $desc, $main_link, $source_name, $image_url, $pub_date, $category_id, $tags, $target_post_type );
				} else {
					$inserted = $wpdb->insert(
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
							'tags'        => $tags,
						),
						array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
					);
					if ( $inserted ) {
						$new_queue_items_count++;
					}
				}
				$total_fetched++;
			}
		}

		// Admin Notifications
		if ( $new_queue_items_count > 0 && get_option( 'wpnc_admin_notify', 0 ) ) {
			if ( false === get_transient( 'wpnc_admin_notify_lock' ) ) {
				$admin_email = get_option( 'admin_email' );
				$subject = sprintf( __( '[%s] New News Items for Moderation', 'wp-news-collector' ), get_option( 'blogname' ) );
				$message = sprintf( __( 'You have %d new news items pending in the Moderation Queue. Please log in to review them.', 'wp-news-collector' ), $new_queue_items_count );
				wp_mail( $admin_email, $subject, $message );
				set_transient( 'wpnc_admin_notify_lock', true, DAY_IN_SECONDS );
			}
		}

		update_option( 'wpnc_last_run', current_time( 'mysql' ) );
		update_option( 'wpnc_last_count', $total_fetched );
	}

	/**
	 * Rewrite Title and Description using OpenAI API.
	 */
	private function rewrite_with_ai( $title, $description ) {
		$api_key = get_option( 'wpnc_openai_api_key', '' );
		if ( empty( $api_key ) ) return false;

		$target_language = get_option( 'wpnc_target_language', '' );
		$translation_prompt = ! empty( $target_language ) ? " Translate it into {$target_language}." : "";

		$prompt = "Rewrite the following news title and description to be unique, SEO friendly, and preserve the main facts.{$translation_prompt} Also, extract up to 5 relevant SEO tags as a comma-separated string. Return ONLY a valid JSON object with exactly three keys: 'title', 'description', and 'tags'. Do not wrap the JSON in markdown code blocks. \n\nOriginal Title: " . wp_strip_all_tags( $title ) . "\nOriginal Description: " . wp_strip_all_tags( $description );

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'model' => 'gpt-3.5-turbo',
				'messages' => array(
					array(
						'role' => 'user',
						'content' => $prompt
					)
				),
				'temperature' => 0.7,
			) ),
			'timeout' => 30,
		);

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$json_str = trim( $data['choices'][0]['message']['content'] );
			$parsed = json_decode( $json_str, true );
			if ( isset( $parsed['title'] ) && isset( $parsed['description'] ) ) {
				return array(
					'title'       => sanitize_text_field( $parsed['title'] ),
					'description' => wp_kses_post( $parsed['description'] ),
					'tags'        => isset( $parsed['tags'] ) ? sanitize_text_field( $parsed['tags'] ) : '',
				);
			}
		}

		return false;
	}

	/**
	 * Extract Full Text by scraping <p> tags from the target URL.
	 */
	private function extract_full_text( $url ) {
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return '';
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) return '';

		libxml_use_internal_errors( true );
		$doc = new DOMDocument();
		$doc->loadHTML( $html );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );
		// Scrape content typically found in article bodies
		$paragraphs = $xpath->query( '//article//p | //main//p | //div[contains(@class, "content")]//p' );

		$content = '';
		if ( $paragraphs->length > 0 ) {
			foreach ( $paragraphs as $p ) {
				$text = trim( $p->nodeValue );
				if ( strlen( $text ) > 50 ) { // Avoid tiny menu items/footer links
					$content .= '<p>' . esc_html( $text ) . '</p>';
				}
			}
		}

		return $content;
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
	public function publish_post( $title, $description, $main_link, $source_name, $image_url, $pub_date, $category_id = 0, $tags = '', $post_type = 'post' ) {
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
			'post_type'     => $post_type,
		);

		$post_id = wp_insert_post( $post_data );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			if ( ! empty( $tags ) ) {
				wp_set_post_tags( $post_id, $tags, true );
			}

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

		// Telegram Automation
		$tg_token = get_option( 'wpnc_telegram_token', '' );
		$tg_chat_id = get_option( 'wpnc_telegram_chat_id', '' );

		if ( ! empty( $tg_token ) && ! empty( $tg_chat_id ) && ! is_wp_error( $post_id ) ) {
			$tg_message = "*" . wp_strip_all_tags( $title ) . "*\n\n" . esc_url( get_permalink( $post_id ) );
			$tg_api_url = "https://api.telegram.org/bot{$tg_token}/sendMessage";

			$tg_args = array(
				'body' => array(
					'chat_id'    => $tg_chat_id,
					'text'       => $tg_message,
					'parse_mode' => 'Markdown',
				),
			);
			wp_remote_post( $tg_api_url, $tg_args );
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
