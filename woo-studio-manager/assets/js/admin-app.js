'use strict';

jQuery(document).ready(function ($) {
    let table;
    let pendingEdits = {}; // key: product id, value: modified product data
    let productsData = {}; // key: product id, value: original product data

    // Initialize Persian Number Formatter
    const persianFormatter = new Intl.NumberFormat('fa-IR');

    function formatNumberPersian(num) {
        if (!num || isNaN(num)) return num;
        return persianFormatter.format(num);
    }

    function initDataTables() {
        table = $('#wsm-products-table').DataTable({
            ajax: {
                url: wsm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsm_fetch_all',
                    nonce: wsm_ajax.nonce
                },
                dataSrc: function (json) {
                    if (json.success) {
                        let total = 0;
                        let outOfStock = 0;
                        json.data.forEach(item => {
                            productsData[item.id] = item;
                            total++;
                            if (item.stock_status === 'outofstock') {
                                outOfStock++;
                            }
                        });
                        $('#stat-total').text(formatNumberPersian(total));
                        $('#stat-outofstock').text(formatNumberPersian(outOfStock));
                        return json.data;
                    }
                    return [];
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'dt-body-center',
                    render: function (data, type, row) {
                        return '<input type="checkbox" class="wsm-row-checkbox" value="' + row.id + '">';
                    }
                },
                { data: 'id' },
                {
                    data: null,
                    orderable: false,
                    render: function () {
                        return '<span class="dashicons dashicons-format-image"></span>'; // Placeholder for image
                    }
                },
                { data: 'title' },
                { data: 'sku' },
                { data: 'type' },
                { data: 'categories' },
                {
                    data: 'stock_quantity',
                    render: function (data, type, row) {
                        if (row.stock_status === 'outofstock') {
                            return '<span style="color:red;">ناموجود</span>';
                        }
                        return data ? formatNumberPersian(data) : 'موجود';
                    }
                },
                {
                    data: 'regular_price',
                    render: function (data, type, row) {
                        let val = data ? data : '';
                        let displayVal = formatNumberPersian(val);
                        let html = '<div class="wsm-price-cell-container">';
                        html += '<input type="number" class="wsm-inline-price" data-id="' + row.id + '" value="' + val + '" placeholder="قیمت...">';
                        html += '<span class="wsm-price-badge" id="badge-' + row.id + '" style="display:none;"></span>';
                        html += '</div>';
                        return html;
                    }
                },
                {
                    data: 'sale_price',
                    render: function (data) {
                        return data ? formatNumberPersian(data) : '-';
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fa.json'
            },
            rowCallback: function (row, data) {
                if (pendingEdits[data.id]) {
                    applyRowHighlight(row, pendingEdits[data.id].new_regular_price, data.regular_price);
                    $(row).find('.wsm-inline-price').val(pendingEdits[data.id].new_regular_price);
                    updateBadge($(row), data.id, pendingEdits[data.id].new_regular_price, data.regular_price);
                } else {
                    $(row).removeClass('wsm-preview-danger wsm-preview-safe');
                    $(row).find('.wsm-price-badge').hide();
                }
            }
        });
    }

    initDataTables();

    // Select All
    $('#wsm-select-all').on('click', function () {
        let isChecked = $(this).prop('checked');
        $('.wsm-row-checkbox').prop('checked', isChecked);
        toggleBulkBar();
    });

    // Individual Checkbox
    $('#wsm-products-table tbody').on('change', '.wsm-row-checkbox', function () {
        toggleBulkBar();
        let allChecked = $('.wsm-row-checkbox:checked').length === $('.wsm-row-checkbox').length;
        $('#wsm-select-all').prop('checked', allChecked);
    });

    function toggleBulkBar() {
        let count = $('.wsm-row-checkbox:checked').length;
        if (count > 0) {
            $('#wsm-bulk-count-text').text(formatNumberPersian(count) + ' محصول انتخاب شده');
            $('#wsm-bulk-bar').slideDown();
        } else {
            $('#wsm-bulk-bar').slideUp();
        }
    }

    // Inline Edit
    $('#wsm-products-table tbody').on('input', '.wsm-inline-price', function () {
        let id = $(this).data('id');
        let newPrice = $(this).val();
        let originalPrice = productsData[id] ? productsData[id].regular_price : 0;

        updatePendingEdits(id, newPrice, originalPrice);

        let row = $(this).closest('tr');
        applyRowHighlight(row, newPrice, originalPrice);
        updateBadge(row, id, newPrice, originalPrice);
        updateFAB();
    });

    // Bulk Apply
    $('#wsm-bulk-apply-btn').on('click', function () {
        let newPrice = $('#wsm-bulk-price-input').val();
        if (!newPrice) return;

        $('.wsm-row-checkbox:checked').each(function () {
            let id = $(this).val();
            let originalPrice = productsData[id] ? productsData[id].regular_price : 0;

            // Update input value in DOM if visible
            let input = $('.wsm-inline-price[data-id="' + id + '"]');
            if (input.length) {
                input.val(newPrice);
                let row = input.closest('tr');
                applyRowHighlight(row, newPrice, originalPrice);
                updateBadge(row, id, newPrice, originalPrice);
            }

            updatePendingEdits(id, newPrice, originalPrice);
        });

        // Clear input and uncheck
        $('#wsm-bulk-price-input').val('');
        $('.wsm-row-checkbox').prop('checked', false);
        $('#wsm-select-all').prop('checked', false);
        toggleBulkBar();
        updateFAB();
    });

    function updatePendingEdits(id, newPrice, originalPrice) {
        if (!newPrice || parseFloat(newPrice) === parseFloat(originalPrice)) {
            delete pendingEdits[id];
        } else {
            pendingEdits[id] = {
                id: id,
                new_regular_price: newPrice
            };
        }
        $('#stat-pending').text(formatNumberPersian(Object.keys(pendingEdits).length));
    }

    function applyRowHighlight(row, newPrice, originalPrice) {
        row = $(row);
        row.removeClass('wsm-preview-danger wsm-preview-safe');

        if (!newPrice || parseFloat(newPrice) === parseFloat(originalPrice) || !originalPrice || parseFloat(originalPrice) === 0) {
            return;
        }

        let diffPercent = Math.abs((parseFloat(newPrice) - parseFloat(originalPrice)) / parseFloat(originalPrice)) * 100;

        if (diffPercent > 10) {
            row.addClass('wsm-preview-danger');
        } else {
            row.addClass('wsm-preview-safe');
        }
    }

    function updateBadge(row, id, newPrice, originalPrice) {
        let badge = row.find('#badge-' + id);

        if (!newPrice || parseFloat(newPrice) === parseFloat(originalPrice) || !originalPrice || parseFloat(originalPrice) === 0) {
            badge.hide();
            return;
        }

        let diff = parseFloat(newPrice) - parseFloat(originalPrice);
        let diffPercent = (Math.abs(diff) / parseFloat(originalPrice)) * 100;
        let sign = diff > 0 ? '+' : '-';

        badge.text(sign + diffPercent.toFixed(1) + '%');

        if (diffPercent > 10) {
            badge.css({'background-color': '#dc3232', 'color': '#fff'});
        } else {
            badge.css({'background-color': '#46b450', 'color': '#fff'});
        }

        badge.show();
    }

    function updateFAB() {
        let count = Object.keys(pendingEdits).length;
        if (count > 0) {
            $('#wsm-fab-text').text('ذخیره تغییرات (' + formatNumberPersian(count) + ' محصول)');
            $('#wsm-fab-save').fadeIn();
        } else {
            $('#wsm-fab-save').fadeOut();
        }
    }

    // Save Changes via AJAX
    $('#wsm-fab-save').on('click', function () {
        let itemsToSync = Object.values(pendingEdits);
        if (itemsToSync.length === 0) return;

        let btn = $(this);
        btn.prop('disabled', true).css('opacity', '0.6');

        $.ajax({
            url: wsm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wsm_batch_sync',
                nonce: wsm_ajax.nonce,
                items: itemsToSync
            },
            success: function (response) {
                if (response.success) {
                    // Show notice
                    let notice = $('<div class="notice notice-success is-dismissible"><p>تغییرات با موفقیت ذخیره شد.</p></div>');
                    $('.wp-header-end').after(notice);
                    setTimeout(() => notice.fadeOut(), 3000);

                    // Clear pending edits
                    pendingEdits = {};
                    $('#stat-pending').text('۰');
                    updateFAB();

                    // Reload table
                    table.ajax.reload(null, false);
                } else {
                    alert('خطا در ذخیره تغییرات: ' + (response.data || ''));
                }
            },
            error: function () {
                alert('خطا در ارتباط با سرور.');
            },
            complete: function () {
                btn.prop('disabled', false).css('opacity', '1');
            }
        });
    });

});
