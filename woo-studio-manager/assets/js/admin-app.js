jQuery(document).ready(function($) {
	'use strict';

	let wsmTable;
	let modifiedItems = {};
	let originalPrices = {}; // Tracks original values for 10% calculation

	// Number Formatter for Farsi (Iran)
	const faNumberFormat = new Intl.NumberFormat('fa-IR');

	function formatNumber(num) {
		if (num === null || num === undefined || num === '') return '';
		return faNumberFormat.format(num);
	}

	function parseFaNumber(str) {
		if (!str) return '';
		// Replace Persian/Arabic numerals with standard digits and remove commas
		return str.toString()
			.replace(/[\u0660-\u0669]/g, function (c) { return c.charCodeAt(0) - 0x0660; })
			.replace(/[\u06f0-\u06f9]/g, function (c) { return c.charCodeAt(0) - 0x06f0; })
			.replace(/,/g, '')
			.replace(/٬/g, '');
	}

	// DOM Elements
	const $fab = $('#wsm-fab');
	const $modifiedCount = $('#wsm-modified-count');
	const $toastContainer = $('#wsm-toast-container');
	const $statTotal = $('#wsm-stat-total');
	const $statOutofstock = $('#wsm-stat-outofstock');
	const $statStaged = $('#wsm-stat-staged');

	// Bulk Updater Elements
	const $bulkOldPrice = $('#wsm-bulk-old-price');
	const $bulkNewPrice = $('#wsm-bulk-new-price');
	const $bulkApplyBtn = $('#wsm-bulk-apply-btn');

	// Contextual Action Bar Elements
	const $bulkActionBar = $('#wsm-bulk-action-bar');
	const $selectedCount = $('#wsm-selected-count');
	const $bulkNewPriceInput = $('#wsm-bulk-new-price-input');
	const $bulkApplySelectedBtn = $('#wsm-bulk-apply-selected-btn');
	const $selectAll = $('#wsm-select-all');

	let selectedRows = new Set();

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

	// Update FAB and Staged Stats
	function updateFAB() {
		const count = Object.keys(modifiedItems).length;
		$modifiedCount.text(formatNumber(count));
		$statStaged.text(formatNumber(count));

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
					populateFilters(response.data.products);
					updateStats(response.data.products);
				} else {
					showToast('خطا در بارگزاری محصولات.', 'error');
				}
			},
			error: function() {
				showToast('خطای سرور هنگام دریافت اطلاعات.', 'error');
			}
		});
	}

	function updateStats(products) {
		$statTotal.text(formatNumber(products.length));
		let outOfStockCount = products.filter(p => p.stock_status === 'outofstock').length;
		$statOutofstock.text(formatNumber(outOfStockCount));
	}

	// Populate Filters and Bulk Options
	function populateFilters(products) {
		const categories = new Set();
		const priceGroups = {};

		products.forEach(p => {
			// Categories
			if (p.categories) {
				p.categories.split(',').forEach(c => categories.add(c.trim()));
			}

			// Store original prices for 10% logic
			originalPrices[p.id] = {
				regular_price: parseFloat(p.regular_price) || 0,
				sale_price: parseFloat(p.sale_price) || 0
			};

			// Price Grouping
			const regPrice = parseFloat(p.regular_price);
			if (regPrice && regPrice > 0) {
				if (!priceGroups[regPrice]) {
					priceGroups[regPrice] = 0;
				}
				priceGroups[regPrice]++;
			}
		});

		// Render Category Filter
		const $catFilter = $('#wsm-filter-category');
		$catFilter.find('option:not(:first)').remove(); // Prevent duplicates on reload
		categories.forEach(cat => {
			if (cat) {
				$catFilter.append(new Option(cat, cat));
			}
		});

		// Render Bulk Price-Tier Options
		// Sort prices ascending
		const sortedPrices = Object.keys(priceGroups).sort((a, b) => a - b);
		$bulkOldPrice.empty().append('<option value="">انتخاب قیمت هدف...</option>');
		sortedPrices.forEach(price => {
			const count = priceGroups[price];
			const label = `${formatNumber(price)} تومان (شامل ${formatNumber(count)} محصول)`;
			$bulkOldPrice.append(new Option(label, price));
		});
	}

	// Render Cells considering the 10% Rule
	function renderPriceCell(data, type, row, field) {
		if (type === 'display') {
			let displayValue = formatNumber(data);
			let classes = 'wsm-editable';
			let tooltip = '';

			if (modifiedItems[row.id] && modifiedItems[row.id][field] !== undefined) {
				const oldVal = originalPrices[row.id][field];
				const newVal = parseFloat(modifiedItems[row.id][field]) || 0;

				if (oldVal > 0) {
					const diff = newVal - oldVal;
					const percent = (diff / oldVal) * 100;
					const absPercent = Math.abs(percent);

					let directionText = percent > 0 ? 'افزایش' : 'کاهش';
					tooltip = `${directionText} ${formatNumber(absPercent.toFixed(1))}٪`;

					if (absPercent > 10) {
						classes += ' wsm-staged-danger';
					} else {
						classes += ' wsm-staged-safe';
					}
				} else {
					// Edge case: Old price was 0, but new price set
					classes += ' wsm-staged-safe';
					tooltip = 'ثبت قیمت جدید';
				}
				displayValue = formatNumber(newVal);
			}

			let tooltipAttr = tooltip ? `data-tooltip="${tooltip}"` : '';
			return `<div class="${classes}" data-field="${field}" data-id="${row.id}" ${tooltipAttr}>${displayValue}</div>`;
		}
		return data;
	}

	// Initialize DataTable
	function initDataTable(data) {
		wsmTable = $('#wsm-products-table').DataTable({
			data: data,
			pageLength: 50,
			lengthMenu: [10, 25, 50, 100, 300],
			columns: [
				{
					data: 'id',
					render: function(data, type, row) {
						if (type === 'display') {
							let isChecked = selectedRows.has(data) ? 'checked' : '';
							return `<input type="checkbox" class="wsm-row-checkbox" value="${data}" ${isChecked}>`;
						}
						return data;
					},
					orderable: false,
					className: 'wsm-checkbox-col'
				},
				{ data: 'id', render: function(data) { return formatNumber(data); } },
				{ data: 'title' },
				{ data: 'sku', render: function(data) { return data ? data : '<span style="color:#aaa">-</span>'; } },
				{ data: 'type', render: function(data) { return data === 'simple' ? 'ساده' : 'متغیر'; } },
				{ data: 'categories' },
				{
					data: 'regular_price',
					render: function(data, type, row) { return renderPriceCell(data, type, row, 'regular_price'); }
				},
				{
					data: 'sale_price',
					render: function(data, type, row) { return renderPriceCell(data, type, row, 'sale_price'); }
				},
				{
					data: 'stock_quantity',
					render: function(data, type, row) {
						if (type === 'display') {
							let displayVal = formatNumber(data || '0');
							let classes = 'wsm-editable';
							if (modifiedItems[row.id] && modifiedItems[row.id]['stock_quantity'] !== undefined) {
								classes += ' wsm-staged-safe';
								displayVal = formatNumber(modifiedItems[row.id]['stock_quantity']);
							}
							return `<div class="${classes}" data-field="stock_quantity" data-id="${row.id}">${displayVal}</div>`;
						}
						return data;
					}
				},
				{
					data: 'stock_status',
					render: function(data, type, row) {
						let badgeClass = 'secondary';
						let label = data;
						if(data === 'instock') { badgeClass = 'success'; label = 'موجود'; }
						if(data === 'outofstock') { badgeClass = 'danger'; label = 'ناموجود'; }
						if(data === 'onbackorder') { badgeClass = 'warning'; label = 'پیش‌خرید'; }
						return `<span class="wsm-status-badge ${badgeClass}">${label}</span>`;
					}
				}
			],
			language: {
				search: "_INPUT_",
				searchPlaceholder: "جستجوی پیشرفته...",
				lengthMenu: "نمایش _MENU_ ردیف",
				info: "نمایش _START_ تا _END_ از _TOTAL_ محصول",
				infoEmpty: "موردی یافت نشد",
				infoFiltered: "(فیلتر شده از _MAX_ محصول)",
				paginate: {
					first: "ابتدا",
					last: "انتها",
					next: "بعدی",
					previous: "قبلی"
				}
			}
		});

		// Custom Filters logic
		$('#wsm-filter-category').on('change', function() { wsmTable.column(4).search(this.value).draw(); });
		$('#wsm-filter-type').on('change', function() { wsmTable.column(3).search(this.value).draw(); });
		$('#wsm-filter-stock-status').on('change', function() { wsmTable.column(8).search(this.value).draw(); });
	}

	// Helper to Process Edits
	function processEdit(id, field, newValueRaw) {
		const idNum = parseInt(id);
		let newValue = parseFloat(newValueRaw);
		if (isNaN(newValue)) newValue = 0;

		// Initialize item if not exists
		if (!modifiedItems[idNum]) {
			modifiedItems[idNum] = { id: idNum };
		}

		modifiedItems[idNum][field] = newValue;
		updateFAB();

		// Invalidate specific row to trigger re-render safely
		wsmTable.rows().every(function() {
			const data = this.data();
			if (data.id == idNum) {
				// We don't overwrite data[field] with raw new value immediately because render
				// function relies on `modifiedItems` for staging logic. We just invalidate.
				this.invalidate();
			}
		});
		wsmTable.draw(false);
	}

	// Inline Edit Logic
	$('#wsm-products-table').on('click', '.wsm-editable', function(e) {
		e.stopPropagation();
		const $cell = $(this);

		if ($cell.find('input').length > 0) return;

		const field = $cell.data('field');
		const id = $cell.data('id');

		// Use staged value if exists, else original
		let currentValue = originalPrices[id] && originalPrices[id][field] !== undefined ? originalPrices[id][field] : 0;
		if (field === 'stock_quantity') {
			currentValue = wsmTable.row($cell.closest('tr')).data().stock_quantity || 0;
		}
		if (modifiedItems[id] && modifiedItems[id][field] !== undefined) {
			currentValue = modifiedItems[id][field];
		}

		// Clean input (no commas)
		const cleanValue = currentValue;

		const $input = $(`<input type="number" step="any" class="wsm-edit-input" value="${cleanValue}">`);

		$cell.empty().removeClass('wsm-staged-safe wsm-staged-danger').removeAttr('data-tooltip').append($input);
		$input.focus().select();

		$input.on('blur keypress', function(e) {
			if (e.type === 'keypress' && e.which !== 13) return; // Only trigger on Enter key

			const rawVal = parseFaNumber($(this).val().trim());
			if (rawVal !== currentValue.toString() && rawVal !== '') {
				processEdit(id, field, rawVal);
			} else {
				// Revert visual change if no logical change
				wsmTable.row($cell.closest('tr')).invalidate().draw(false);
			}
		});
	});

	// Update Contextual Action Bar Visibility
	function updateActionBar() {
		const count = selectedRows.size;
		$selectedCount.text(formatNumber(count));

		if (count > 0) {
			$bulkActionBar.removeClass('hidden');
		} else {
			$bulkActionBar.addClass('hidden');
			$selectAll.prop('checked', false);
		}
	}

	// Master Select All Checkbox
	$selectAll.on('change', function() {
		const isChecked = $(this).is(':checked');
		const currentRows = wsmTable.rows({ page: 'current' }).data().toArray();

		if (isChecked) {
			currentRows.forEach(row => selectedRows.add(row.id));
		} else {
			currentRows.forEach(row => selectedRows.delete(row.id));
		}

		// Update checkboxes on current page
		$('.wsm-row-checkbox').prop('checked', isChecked);
		updateActionBar();
	});

	// Individual Row Checkbox
	$('#wsm-products-table').on('change', '.wsm-row-checkbox', function() {
		const id = parseInt($(this).val());
		if ($(this).is(':checked')) {
			selectedRows.add(id);
		} else {
			selectedRows.delete(id);
			$selectAll.prop('checked', false);
		}
		updateActionBar();
	});

	// Apply Bulk Price to Selected Rows
	$bulkApplySelectedBtn.on('click', function() {
		const newPriceRaw = parseFaNumber($bulkNewPriceInput.val().trim());

		if (!newPriceRaw) {
			showToast('لطفا قیمت جدید را وارد کنید.', 'error');
			return;
		}

		const newPrice = parseFloat(newPriceRaw);
		let affectedCount = 0;

		selectedRows.forEach(id => {
			processEdit(id, 'regular_price', newPrice);
			affectedCount++;
		});

		if (affectedCount > 0) {
			showToast(`${formatNumber(affectedCount)} محصول بروزرسانی و به صف اضافه شد.`, 'success');
			$bulkNewPriceInput.val('');
			selectedRows.clear();
			$('.wsm-row-checkbox').prop('checked', false);
			updateActionBar();
		}
	});

	// Bulk Price-Tier Updater Logic
	$bulkApplyBtn.on('click', function() {
		const targetPriceRaw = $bulkOldPrice.val();
		const newPriceRaw = parseFaNumber($bulkNewPrice.val().trim());

		if (!targetPriceRaw || !newPriceRaw) {
			showToast('لطفا قیمت پایه و مبلغ جدید را وارد کنید.', 'error');
			return;
		}

		const targetPrice = parseFloat(targetPriceRaw);
		const newPrice = parseFloat(newPriceRaw);

		let affectedCount = 0;

		wsmTable.rows().every(function() {
			const data = this.data();
			// Ensure we check original price or staged price
			let currentRegPrice = parseFloat(data.regular_price);

			if (currentRegPrice === targetPrice) {
				// Check if it's already staged to something else, skip or overwrite?
				// Overwrite makes sense here.
				processEdit(data.id, 'regular_price', newPrice);
				affectedCount++;
			}
		});

		if (affectedCount > 0) {
			showToast(`${formatNumber(affectedCount)} محصول بروزرسانی و به صف اضافه شد.`, 'success');
			$bulkNewPrice.val('');
		} else {
			showToast('محصولی با این قیمت یافت نشد.', 'error');
		}
	});

	// FAB Click - Save Changes
	$fab.on('click', function() {
		const itemsToSync = Object.values(modifiedItems);

		if (itemsToSync.length === 0) {
			showToast('هیچ تغییری برای ذخیره وجود ندارد.', 'error');
			return;
		}

		// Disable button
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
					showToast('تغییرات با موفقیت ذخیره شدند!', 'success');

					// Update underlying Data and originalPrices
					itemsToSync.forEach(item => {
						const id = item.id;
						wsmTable.rows().every(function() {
							const data = this.data();
							if (data.id == id) {
								if (item.regular_price !== undefined) {
									data.regular_price = item.regular_price;
									originalPrices[id].regular_price = parseFloat(item.regular_price);
								}
								if (item.sale_price !== undefined) {
									data.sale_price = item.sale_price;
									originalPrices[id].sale_price = parseFloat(item.sale_price);
								}
								if (item.stock_quantity !== undefined) {
									data.stock_quantity = item.stock_quantity;
									let qty = parseFloat(item.stock_quantity);
									data.stock_status = qty <= 0 ? 'outofstock' : 'instock';
								}
								this.invalidate();
							}
						});
					});

					// Clear modifications
					modifiedItems = {};
					updateFAB();

					wsmTable.draw(false);

					// Re-evaluate stats and filters
					const allData = wsmTable.rows().data().toArray();
					updateStats(allData);

					// Debounce or rebuild price filter
					setTimeout(() => { populateFilters(allData); }, 500);

				} else {
					showToast(response.data.message || 'خطا در ذخیره اطلاعات.', 'error');
				}
			},
			error: function() {
				showToast('ارتباط با سرور قطع شد.', 'error');
			},
			complete: function() {
				$icon.removeClass('dashicons-update').addClass('dashicons-saved').css('animation', 'none');
				$fab.prop('disabled', false);
			}
		});
	});

	// Init
	loadData();
});