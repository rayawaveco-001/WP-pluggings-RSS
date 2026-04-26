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

		add_action( 'wp_ajax_wpnc_load_more_news', array( $this, 'load_more_news' ) );
		add_action( 'wp_ajax_nopriv_wpnc_load_more_news', array( $this, 'load_more_news' ) );
	}

	/**
	 * Get queue items.
	 */
	public function get_queue() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
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
			wp_send_json_error( 'Unauthorized.' );
		}

		if ( ! isset( $_POST['id'] ) ) {
			wp_send_json_error( 'Invalid ID.' );
		}

		$id = intval( $_POST['id'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );

		if ( ! $item ) {
			wp_send_json_error( 'Item not found.' );
		}

		// Fetcher instance is required for post publishing logic
		$fetcher = new WPNC_Fetcher();
		$post_id = $fetcher->publish_post(
			$item->title,
			$item->description,
			$item->main_link,
			$item->source_name,
			$item->image_url,
			$item->pub_date,
			isset( $item->category_id ) ? $item->category_id : 0
		);

		if ( ! is_wp_error( $post_id ) && $post_id ) {
			$wpdb->update( $table_name, array( 'status' => 'approved' ), array( 'id' => $id ) );
			wp_send_json_success( array( 'message' => 'Approved and published.' ) );
		} else {
			wp_send_json_error( 'Failed to publish post.' );
		}
	}

	/**
	 * Reject item.
	 */
	public function reject_item() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		if ( ! isset( $_POST['id'] ) ) {
			wp_send_json_error( 'Invalid ID.' );
		}

		$id = intval( $_POST['id'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'news_queue';

		$wpdb->update( $table_name, array( 'status' => 'rejected' ), array( 'id' => $id ) );

		wp_send_json_success( array( 'message' => 'Rejected successfully.' ) );
	}

	/**
	 * Edit item.
	 */
	public function edit_item() {
		check_ajax_referer( 'wpnc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		if ( ! isset( $_POST['id'] ) || ! isset( $_POST['title'] ) || ! isset( $_POST['description'] ) ) {
			wp_send_json_error( 'Missing parameters.' );
		}

		$id = intval( $_POST['id'] );
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

		wp_send_json_success( array( 'message' => 'Updated successfully.' ) );
	}

	/**
	 * Load More News for frontend shortcode.
	 */
	public function load_more_news() {
		check_ajax_referer( 'wpnc_frontend_nonce', 'nonce' );

		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 10;
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

		$args = array(
			'post_type'      => 'post',
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
			wp_send_json_error( 'No more posts' );
		}
	}
}

// Initialize
new WPNC_Ajax();
