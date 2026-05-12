<div class="wrap wsm-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'WooCommerce Studio Manager', 'woo-studio-manager' ); ?></h1>
	<hr class="wp-header-end">

	<div class="wsm-card">
		<div class="wsm-filters">
			<div class="wsm-filter-group">
				<label for="wsm-filter-category"><?php esc_html_e( 'Category', 'woo-studio-manager' ); ?></label>
				<select id="wsm-filter-category">
					<option value=""><?php esc_html_e( 'All Categories', 'woo-studio-manager' ); ?></option>
					<!-- Options will be populated via JS -->
				</select>
			</div>

			<div class="wsm-filter-group">
				<label for="wsm-filter-type"><?php esc_html_e( 'Type', 'woo-studio-manager' ); ?></label>
				<select id="wsm-filter-type">
					<option value=""><?php esc_html_e( 'All Types', 'woo-studio-manager' ); ?></option>
					<option value="simple"><?php esc_html_e( 'Simple', 'woo-studio-manager' ); ?></option>
					<option value="variation"><?php esc_html_e( 'Variation', 'woo-studio-manager' ); ?></option>
				</select>
			</div>

			<div class="wsm-filter-group">
				<label for="wsm-filter-stock-status"><?php esc_html_e( 'Stock Status', 'woo-studio-manager' ); ?></label>
				<select id="wsm-filter-stock-status">
					<option value=""><?php esc_html_e( 'All Statuses', 'woo-studio-manager' ); ?></option>
					<option value="instock"><?php esc_html_e( 'In Stock', 'woo-studio-manager' ); ?></option>
					<option value="outofstock"><?php esc_html_e( 'Out of Stock', 'woo-studio-manager' ); ?></option>
					<option value="onbackorder"><?php esc_html_e( 'On Backorder', 'woo-studio-manager' ); ?></option>
				</select>
			</div>
		</div>

		<div class="wsm-table-container">
			<table id="wsm-products-table" class="display stripe hover" style="width:100%">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'Title', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'Type', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'Categories', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'Regular Price', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'Sale Price', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'Stock Qty', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'woo-studio-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<!-- Data will be loaded via AJAX -->
				</tbody>
			</table>
		</div>
	</div>

	<!-- Floating Action Button -->
	<button id="wsm-fab" class="wsm-fab hidden">
		<span class="dashicons dashicons-saved"></span>
		<span class="wsm-fab-text"><?php esc_html_e( 'Save Changes (', 'woo-studio-manager' ); ?><span id="wsm-modified-count">0</span><?php esc_html_e( ' items)', 'woo-studio-manager' ); ?></span>
	</button>

	<!-- Toast Container -->
	<div id="wsm-toast-container"></div>
</div>
