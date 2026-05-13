<div class="wrap wsm-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'مدیریت استودیو ووکامرس', 'woo-studio-manager' ); ?></h1>
	<hr class="wp-header-end">

	<!-- Top Dashboard Cards -->
	<div class="wsm-stats-dashboard">
		<div class="wsm-stat-card">
			<div class="wsm-stat-title"><?php esc_html_e( 'کل محصولات', 'woo-studio-manager' ); ?></div>
			<div class="wsm-stat-value" id="wsm-stat-total">0</div>
		</div>
		<div class="wsm-stat-card">
			<div class="wsm-stat-title"><?php esc_html_e( 'ناموجود', 'woo-studio-manager' ); ?></div>
			<div class="wsm-stat-value" id="wsm-stat-outofstock" style="color: var(--wsm-highlight-danger-text);">0</div>
		</div>
		<div class="wsm-stat-card">
			<div class="wsm-stat-title"><?php esc_html_e( 'تغییرات در صف (استیج)', 'woo-studio-manager' ); ?></div>
			<div class="wsm-stat-value" id="wsm-stat-staged" style="color: var(--wsm-highlight-safe-text);">0</div>
		</div>
	</div>

	<div class="wsm-card">
		<!-- Unified Toolbar -->
		<div class="wsm-toolbar">
			<div class="wsm-filter-group">
				<label for="wsm-filter-category"><?php esc_html_e( 'دسته‌بندی', 'woo-studio-manager' ); ?></label>
				<select id="wsm-filter-category">
					<option value=""><?php esc_html_e( 'همه دسته‌بندی‌ها', 'woo-studio-manager' ); ?></option>
					<!-- Options will be populated via JS -->
				</select>
			</div>

			<div class="wsm-filter-group">
				<label for="wsm-filter-type"><?php esc_html_e( 'نوع محصول', 'woo-studio-manager' ); ?></label>
				<select id="wsm-filter-type">
					<option value=""><?php esc_html_e( 'همه انواع', 'woo-studio-manager' ); ?></option>
					<option value="simple"><?php esc_html_e( 'ساده', 'woo-studio-manager' ); ?></option>
					<option value="variation"><?php esc_html_e( 'متغیر', 'woo-studio-manager' ); ?></option>
				</select>
			</div>

			<div class="wsm-filter-group">
				<label for="wsm-filter-stock-status"><?php esc_html_e( 'وضعیت موجودی', 'woo-studio-manager' ); ?></label>
				<select id="wsm-filter-stock-status">
					<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'woo-studio-manager' ); ?></option>
					<option value="instock"><?php esc_html_e( 'موجود', 'woo-studio-manager' ); ?></option>
					<option value="outofstock"><?php esc_html_e( 'ناموجود', 'woo-studio-manager' ); ?></option>
					<option value="onbackorder"><?php esc_html_e( 'پیش‌خرید', 'woo-studio-manager' ); ?></option>
				</select>
			</div>

			<!-- Bulk Price-Tier Updater -->
			<div class="wsm-bulk-updater-group">
				<div class="wsm-filter-group">
					<label for="wsm-bulk-old-price"><?php esc_html_e( 'قیمت پایه (گروه)', 'woo-studio-manager' ); ?></label>
					<select id="wsm-bulk-old-price">
						<option value=""><?php esc_html_e( 'انتخاب قیمت هدف...', 'woo-studio-manager' ); ?></option>
					</select>
				</div>
				<div class="wsm-filter-group">
					<label for="wsm-bulk-new-price"><?php esc_html_e( 'قیمت جدید (تومان)', 'woo-studio-manager' ); ?></label>
					<input type="number" id="wsm-bulk-new-price" placeholder="<?php esc_attr_e( 'مبلغ جدید', 'woo-studio-manager' ); ?>" step="any">
				</div>
				<button id="wsm-bulk-apply-btn" type="button"><?php esc_html_e( 'اعمال روی گروه', 'woo-studio-manager' ); ?></button>
			</div>
		</div>

		<!-- Contextual Bulk Action Bar -->
		<div id="wsm-bulk-action-bar" class="wsm-bulk-action-bar hidden">
			<div class="wsm-bulk-count"><span id="wsm-selected-count">0</span> <?php esc_html_e( 'محصول انتخاب شده', 'woo-studio-manager' ); ?></div>
			<div class="wsm-bulk-inputs">
				<input type="number" id="wsm-bulk-new-price-input" placeholder="<?php esc_attr_e( 'قیمت جدید گروهی', 'woo-studio-manager' ); ?>" step="any">
				<button type="button" id="wsm-bulk-apply-selected-btn"><?php esc_html_e( 'اعمال به انتخاب‌شده‌ها', 'woo-studio-manager' ); ?></button>
			</div>
		</div>

		<div class="wsm-table-container">
			<table id="wsm-products-table" class="display stripe hover" style="width:100%">
				<thead>
					<tr>
						<th class="wsm-checkbox-col"><input type="checkbox" id="wsm-select-all"></th>
						<th><?php esc_html_e( 'شناسه', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'عنوان', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'شناسه محصول (SKU)', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'نوع', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'دسته‌ها', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'قیمت عادی', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'قیمت فروش ویژه', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'موجودی', 'woo-studio-manager' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'woo-studio-manager' ); ?></th>
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
		<span class="wsm-fab-text"><?php esc_html_e( 'ذخیره تغییرات (', 'woo-studio-manager' ); ?><span id="wsm-modified-count">0</span><?php esc_html_e( ' محصول)', 'woo-studio-manager' ); ?></span>
	</button>

	<!-- Toast Container -->
	<div id="wsm-toast-container"></div>
</div>