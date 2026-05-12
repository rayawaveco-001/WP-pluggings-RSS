jQuery(document).ready(function($) {
	'use strict';

	let wsmTable;
	let modifiedItems = {};

	// DOM Elements
	const $fab = $('#wsm-fab');
	const $modifiedCount = $('#wsm-modified-count');
	const $toastContainer = $('#wsm-toast-container');

	// Show Toast Notification
	function showToast(message, type = 'success') {
		const $toast = $('<div class="wsm-toast ' + type + '"></div>').text(message);
		$toastContainer.append($toast);

		setTimeout(() => {
			$toast.addClass('fade-out');
			setTimeout(() => {
				$toast.remove();
			}, 300);
		}, 3000);
	}

	// Update FAB Visibility and Count
	function updateFAB() {
		const count = Object.keys(modifiedItems).length;
		$modifiedCount.text(count);

		if (count > 0) {
			$fab.removeClass('hidden');
		} else {
			$fab.addClass('hidden');
		}
	}

	// Load Data via AJAX
	function loadData() {
		$.ajax({
			url: wsmData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wsm_fetch_all',
				nonce: wsmData.nonce
			},
			success: function(response) {
				if (response.success && response.data && response.data.products) {
					initDataTable(response.data.products);
					populateCategoryFilter(response.data.products);
				} else {
					showToast('Failed to load products.', 'error');
				}
			},
			error: function() {
				showToast('AJAX error while loading products.', 'error');
			}
		});
	}

	// Populate Category Filter
	function populateCategoryFilter(products) {
		const categories = new Set();
		products.forEach(p => {
			if (p.categories) {
				p.categories.split(',').forEach(c => categories.add(c.trim()));
			}
		});

		const $catFilter = $('#wsm-filter-category');
		categories.forEach(cat => {
			if (cat) {
				$catFilter.append(new Option(cat, cat));
			}
		});
	}

	// Initialize DataTable
	function initDataTable(data) {
		wsmTable = $('#wsm-products-table').DataTable({
			data: data,
			pageLength: 50,
			lengthMenu: [10, 25, 50, 100, 300],
			columns: [
				{ data: 'id' },
				{ data: 'title' },
				{ data: 'sku' },
				{ data: 'type' },
				{ data: 'categories' },
				{
					data: 'regular_price',
					render: function(data, type, row) {
						if (type === 'display') {
							return '<div class="wsm-editable" data-field="regular_price" data-id="'+row.id+'">' + (data || '') + '</div>';
						}
						return data;
					}
				},
				{
					data: 'sale_price',
					render: function(data, type, row) {
						if (type === 'display') {
							return '<div class="wsm-editable" data-field="sale_price" data-id="'+row.id+'">' + (data || '') + '</div>';
						}
						return data;
					}
				},
				{
					data: 'stock_quantity',
					render: function(data, type, row) {
						if (type === 'display') {
							return '<div class="wsm-editable" data-field="stock_quantity" data-id="'+row.id+'">' + (data || '0') + '</div>';
						}
						return data;
					}
				},
				{
					data: 'stock_status',
					render: function(data, type, row) {
						let badgeClass = 'secondary';
						if(data === 'instock') badgeClass = 'success';
						if(data === 'outofstock') badgeClass = 'danger';
						if(data === 'onbackorder') badgeClass = 'warning';
						return '<span class="wsm-status-badge ' + badgeClass + '">' + data + '</span>';
					}
				}
			],
			language: {
				search: "_INPUT_",
				searchPlaceholder: "Global Search..."
			},
			createdRow: function(row, data, dataIndex) {
				if (modifiedItems[data.id]) {
					$(row).addClass('wsm-row-edited');
				}
			}
		});

		// Custom Filters logic
		$('#wsm-filter-category').on('change', function() {
			wsmTable.column(4).search(this.value).draw();
		});

		$('#wsm-filter-type').on('change', function() {
			wsmTable.column(3).search(this.value).draw();
		});

		$('#wsm-filter-stock-status').on('change', function() {
			wsmTable.column(8).search(this.value).draw();
		});
	}

	// Inline Edit Logic
	$('#wsm-products-table').on('click', '.wsm-editable', function(e) {
		e.stopPropagation();
		const $cell = $(this);

		// Prevent multiple inputs
		if ($cell.find('input').length > 0) return;

		const currentValue = $cell.text().trim();
		const field = $cell.data('field');
		const id = $cell.data('id');

		const $input = $('<input type="number" step="any" class="wsm-edit-input" value="' + currentValue + '">');

		$cell.empty().append($input);
		$input.focus();

		$input.on('blur keypress', function(e) {
			if (e.type === 'keypress' && e.which !== 13) return; // Only trigger on Enter key

			const newValue = $(this).val().trim();
			$cell.empty().text(newValue);

			if (newValue !== currentValue) {
				// Initialize item if not exists
				if (!modifiedItems[id]) {
					modifiedItems[id] = { id: id };
				}

				// Update value
				modifiedItems[id][field] = newValue;

				// Apply highlight to row
				$cell.closest('tr').addClass('wsm-row-edited');

				// Update FAB
				updateFAB();

				// Update underlying DataTable data so sorting/filtering still works
				const rowIdx = wsmTable.cell($cell.closest('td')).index().row;
				const rowData = wsmTable.row(rowIdx).data();
				rowData[field] = newValue;
				// Invalidate the row to refresh internal cache without re-rendering the whole row (which removes classes)
				wsmTable.row(rowIdx).invalidate().draw(false);
				$cell.closest('tr').addClass('wsm-row-edited'); // re-add class after draw
			}
		});
	});

	// FAB Click - Save Changes
	$fab.on('click', function() {
		const itemsToSync = Object.values(modifiedItems);

		if (itemsToSync.length === 0) {
			showToast(wsmData.i18n.noItems, 'error');
			return;
		}

		// Disable button during sync
		const $icon = $fab.find('.dashicons');
		$icon.removeClass('dashicons-saved').addClass('dashicons-update').css('animation', 'spin 1s linear infinite');
		$fab.prop('disabled', true);

		$.ajax({
			url: wsmData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wsm_batch_sync',
				nonce: wsmData.nonce,
				items: itemsToSync
			},
			success: function(response) {
				if (response.success) {
					showToast(wsmData.i18n.success, 'success');

					// Clear modifications
					modifiedItems = {};
					updateFAB();

					// Remove highlight classes
					$('#wsm-products-table tbody tr').removeClass('wsm-row-edited');

					// Optional: Reload data to get updated stock status, etc.
					// wsmTable.destroy();
					// loadData(); // Alternatively, just update the UI locally for speed.

					// Update local UI for stock status if qty was changed
					itemsToSync.forEach(item => {
						if (item.stock_quantity !== undefined) {
							// find row, update status column
							wsmTable.rows().every(function() {
								var data = this.data();
								if (data.id == item.id) {
									let qty = parseFloat(item.stock_quantity);
									data.stock_status = qty <= 0 ? 'outofstock' : 'instock';
									this.invalidate();
								}
							});
						}
					});
					wsmTable.draw(false);

				} else {
					showToast(response.data.message || wsmData.i18n.error, 'error');
				}
			},
			error: function() {
				showToast(wsmData.i18n.error, 'error');
			},
			complete: function() {
				$icon.removeClass('dashicons-update').addClass('dashicons-saved').css('animation', 'none');
				$fab.prop('disabled', false);
			}
		});
	});

	// Add simple spin animation CSS for sync icon
	$('<style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');

	// Init
	loadData();
});