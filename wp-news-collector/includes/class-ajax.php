<?php
/**
 * AJAX endpoints for moderation queue.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPNC_Ajax {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wpnc_get_queue', array( $this, 'get_queue' ) );
		add_action( 'wp_ajax_wpnc_approve_item', array( $this, 'approve_item' ) );
		add_action( 'wp_ajax_wpnc_reject_item', array( $this, 'reject_item' ) );
		add_action( 'wp_ajax_wpnc_edit_item', array( $this, 'edit_item' ) );

		add_action( 'wp_ajax_wpnc_bulk_approve', array( $this, 'bulk_approve' ) );
		add_action( 'wp_ajax_wpnc_bulk_reject', array( $this, 'bulk_reject' ) );
		add_action( 'wp_ajax_wpnc_get_stats', array( $this, 'get_stats' ) );
		add_action( 'wp_ajax_wpnc_force_fetch', array( $this, 'force_fetch' ) );

		add_action( 'wp_ajax_wpnc_load_more_news', array( $this, 'load_more_news' ) );
		add_action( 'wp_ajax_nopriv_wpnc_load_more_news', array( $this, 'load_more_news' ) );
	}

	/**
	 * Get queue items.
	 */
	public function get_queue() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'wp-news-collector' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		$items = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'pending' ORDER BY pub_date DESC LIMIT 50" );

		wp_send_json_success( $items );
	}

	/**
	 * Approve item.
	 */
	public function approve_item() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'wp-news-collector' ) );
		}

		if ( ! isset( $_POST['id'] ) ) {
			wp_send_json_error( __( 'Invalid ID provided.', 'wp-news-collector' ) );
		}

		$id = intval( wp_unslash( $_POST['id'] ) );

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );

		if ( ! $item ) {
			wp_send_json_error( __( 'Item not found.', 'wp-news-collector' ) );
		}

		// Fetcher instance is required for post publishing logic
		$fetcher = new WPNC_Fetcher();
		$post_type = get_option( 'wpnc_target_post_type', 'post' );

		$post_id = $fetcher->publish_post(
			$item->title,
			$item->description,
			$item->main_link,
			$item->source_name,
			$item->image_url,
			$item->pub_date,
			isset( $item->category_id ) ? $item->category_id : 0,
			isset( $item->tags ) ? $item->tags : '',
			$post_type
		);

		if ( ! is_wp_error( $post_id ) && $post_id ) {
			$wpdb->update( $table_name, array( 'status' => 'approved' ), array( 'id' => $id ) );
			wp_send_json_success( array( 'message' => __( 'Item approved and published successfully.', 'wp-news-collector' ) ) );
		} else {
			wp_send_json_error( __( 'Failed to publish the post.', 'wp-news-collector' ) );
		}
	}

	/**
	 * Reject item.
	 */
	public function reject_item() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'wp-news-collector' ) );
		}

		if ( ! isset( $_POST['id'] ) ) {
			wp_send_json_error( __( 'Invalid ID provided.', 'wp-news-collector' ) );
		}

		$id = intval( wp_unslash( $_POST['id'] ) );

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		$wpdb->update( $table_name, array( 'status' => 'rejected' ), array( 'id' => $id ) );

		wp_send_json_success( array( 'message' => __( 'Item rejected successfully.', 'wp-news-collector' ) ) );
	}

	/**
	 * Edit item.
	 */
	public function edit_item() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'wp-news-collector' ) );
		}

		if ( ! isset( $_POST['id'] ) || ! isset( $_POST['title'] ) || ! isset( $_POST['description'] ) ) {
			wp_send_json_error( __( 'Missing required parameters.', 'wp-news-collector' ) );
		}

		$id = intval( wp_unslash( $_POST['id'] ) );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
		$description = wp_kses_post( wp_unslash( $_POST['description'] ) );

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		$wpdb->update(
			$table_name,
			array(
				'title'       => $title,
				'description' => $description,
			),
			array( 'id' => $id )
		);

		wp_send_json_success( array( 'message' => __( 'Item updated successfully.', 'wp-news-collector' ) ) );
	}

	/**
	 * Bulk approve items.
	 */
	public function bulk_approve() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'wp-news-collector' ) );
		}

		if ( ! isset( $_POST['ids'] ) || ! is_array( wp_unslash( $_POST['ids'] ) ) ) {
			wp_send_json_error( __( 'No valid IDs provided.', 'wp-news-collector' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';
		$fetcher = new WPNC_Fetcher();
		$post_type = get_option( 'wpnc_target_post_type', 'post' );
		$success_count = 0;

		$posted_ids = wp_unslash( $_POST['ids'] );

		foreach ( $posted_ids as $id ) {
			$id = intval( $id );
			$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d AND status = 'pending'", $id ) );

			if ( $item ) {
				$post_id = $fetcher->publish_post(
					$item->title,
					$item->description,
					$item->main_link,
					$item->source_name,
					$item->image_url,
					$item->pub_date,
					isset( $item->category_id ) ? $item->category_id : 0,
					isset( $item->tags ) ? $item->tags : '',
					$post_type
				);

				if ( ! is_wp_error( $post_id ) && $post_id ) {
					$wpdb->update( $table_name, array( 'status' => 'approved' ), array( 'id' => $id ) );
					$success_count++;
				}
			}
		}

		wp_send_json_success( array( 'message' => sprintf( __( '%d items approved and published successfully.', 'wp-news-collector' ), $success_count ) ) );
	}

	/**
	 * Bulk reject items.
	 */
	public function bulk_reject() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'wp-news-collector' ) );
		}

		if ( ! isset( $_POST['ids'] ) || ! is_array( wp_unslash( $_POST['ids'] ) ) ) {
			wp_send_json_error( __( 'No valid IDs provided.', 'wp-news-collector' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		$posted_ids = wp_unslash( $_POST['ids'] );
		$ids = array_map( 'intval', $posted_ids );
		$ids_list = implode( ',', $ids );

		if ( ! empty( $ids_list ) ) {
			$wpdb->query( "UPDATE $table_name SET status = 'rejected' WHERE id IN ($ids_list)" );
		}

		wp_send_json_success( array( 'message' => __( 'Selected items rejected successfully.', 'wp-news-collector' ) ) );
	}

	/**
	 * Get stats for Chart.js.
	 */
	public function get_stats() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'wp-news-collector' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		$stats = array(
			'approved' => intval( $wpdb->get_var( "SELECT COUNT(id) FROM $table_name WHERE status = 'approved'" ) ),
			'pending'  => intval( $wpdb->get_var( "SELECT COUNT(id) FROM $table_name WHERE status = 'pending'" ) ),
			'rejected' => intval( $wpdb->get_var( "SELECT COUNT(id) FROM $table_name WHERE status = 'rejected'" ) ),
		);

		wp_send_json_success( $stats );
	}

	/**
	 * Force fetch news manually.
	 */
	public function force_fetch() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'wp-news-collector' ) );
		}

		// Disable time limit for manual fetch since it can take a while
		set_time_limit( 0 );

		$fetcher = new WPNC_Fetcher();
		$fetcher->fetch_news();

		$last_count = get_option( 'wpnc_last_count', 0 );
		wp_send_json_success( array( 'message' => sprintf( __( 'Fetch completed successfully! %d items processed.', 'wp-news-collector' ), $last_count ) ) );
	}

	/**
	 * Load More News for frontend shortcode.
	 */
	public function load_more_news() {
		check_ajax_referer( 'wpnc_frontend_nonce', 'nonce' );

		$page = isset( $_POST['page'] ) ? intval( wp_unslash( $_POST['page'] ) ) : 1;
		$limit = isset( $_POST['limit'] ) ? intval( wp_unslash( $_POST['limit'] ) ) : 10;
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

		$post_type = get_option( 'wpnc_target_post_type', 'post' );

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'paged'          => $page,
		);

		if ( ! empty( $category ) ) {
			$args['category_name'] = $category;
		}

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				?>
				<div class="wpnc-news-item">
					<h3 class="wpnc-news-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<div class="wpnc-news-meta">
						<span class="wpnc-news-date"><?php echo get_the_date(); ?></span>
					</div>
					<div class="wpnc-news-excerpt">
						<?php the_content(); ?>
					</div>
				</div>
				<?php
			}
			wp_reset_postdata();
			$html = ob_get_clean();
			wp_send_json_success( array( 'html' => $html ) );
		} else {
			wp_send_json_error( __( 'No more posts available.', 'wp-news-collector' ) );
		}
	}
}

// Initialize
new WPNC_Ajax();
