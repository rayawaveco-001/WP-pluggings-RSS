<?php
/**
 * Frontend Shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPNC_Shortcode {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_shortcode( 'news_bulletin', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => 10,
				'category' => '', // Category slug
			),
			$atts,
			'news_bulletin'
		);

		$args = array(
			'post_type'      => 'post',
			'posts_per_page' => intval( $atts['limit'] ),
			'post_status'    => 'publish',
		);

		if ( ! empty( $atts['category'] ) ) {
			$args['category_name'] = sanitize_text_field( $atts['category'] );
		}

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<p>' . esc_html__( 'No news found.', 'wp-news-collector' ) . '</p>';
		}

		ob_start();
		?>
		<div class="wpnc-news-list">
			<?php
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
			?>
		</div>
		<?php

		return ob_get_clean();
	}
}

// Initialize
new WPNC_Shortcode();
