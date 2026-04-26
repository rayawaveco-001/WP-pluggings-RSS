jQuery(document).ready(function($) {
    if ($('#wpnc-moderation-app').length === 0) {
        return;
    }

    const app = $('#wpnc-moderation-app');

    function loadQueue() {
        app.html('<p>Loading...</p>');
        $.post(wpnc_ajax.ajax_url, {
            action: 'wpnc_get_queue',
            nonce: wpnc_ajax.nonce
        }, function(response) {
            if (response.success) {
                renderGrid(response.data);
            } else {
                app.html('<p>Error loading queue.</p>');
            }
        });
    }

    function renderGrid(items) {
        if (items.length === 0) {
            app.html('<p>No pending news in the queue.</p>');
            return;
        }

        let html = '<div class="wpnc-grid">';
        items.forEach(function(item) {
            let img = item.image_url ? `<img src="${item.image_url}" alt="Thumbnail">` : '<div class="wpnc-no-img">No Image</div>';
            html += `
                <div class="wpnc-card" id="wpnc-item-${item.id}">
                    ${img}
                    <div class="wpnc-card-content">
                        <h4>${item.title}</h4>
                        <p class="wpnc-source">${item.source_name}</p>
                        <div class="wpnc-actions">
                            <button class="button button-primary wpnc-approve" data-id="${item.id}">Approve</button>
                            <button class="button wpnc-edit" data-id="${item.id}" data-title="${encodeURIComponent(item.title)}" data-desc="${encodeURIComponent(item.description)}">Edit</button>
                            <button class="button wpnc-reject button-link-delete" data-id="${item.id}">Reject</button>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        // Modal HTML
        html += `
            <div id="wpnc-edit-modal" class="wpnc-modal" style="display:none;">
                <div class="wpnc-modal-content">
                    <h2>Edit News Item</h2>
                    <input type="hidden" id="wpnc-edit-id">
                    <p><input type="text" id="wpnc-edit-title" class="large-text"></p>
                    <p><textarea id="wpnc-edit-desc" class="large-text" rows="5"></textarea></p>
                    <p>
                        <button class="button button-primary" id="wpnc-save-edit">Save</button>
                        <button class="button" id="wpnc-close-modal">Cancel</button>
                    </p>
                </div>
            </div>
        `;

        app.html(html);
        bindEvents();
    }

    function bindEvents() {
        $('.wpnc-approve').on('click', function() {
            let id = $(this).data('id');
            let card = $(`#wpnc-item-${id}`);
            card.css('opacity', '0.5');
            $.post(wpnc_ajax.ajax_url, {
                action: 'wpnc_approve_item',
                id: id,
                nonce: wpnc_ajax.nonce
            }, function(response) {
                if (response.success) {
                    card.fadeOut();
                } else {
                    card.css('opacity', '1');
                    alert('Error approving item.');
                }
            });
        });

        $('.wpnc-reject').on('click', function() {
            let id = $(this).data('id');
            let card = $(`#wpnc-item-${id}`);
            card.css('opacity', '0.5');
            $.post(wpnc_ajax.ajax_url, {
                action: 'wpnc_reject_item',
                id: id,
                nonce: wpnc_ajax.nonce
            }, function(response) {
                if (response.success) {
                    card.fadeOut();
                } else {
                    card.css('opacity', '1');
                    alert('Error rejecting item.');
                }
            });
        });

        $('.wpnc-edit').on('click', function() {
            let id = $(this).data('id');
            let title = decodeURIComponent($(this).data('title'));
            let desc = decodeURIComponent($(this).data('desc'));

            $('#wpnc-edit-id').val(id);
            $('#wpnc-edit-title').val(title);
            $('#wpnc-edit-desc').val(desc);
            $('#wpnc-edit-modal').show();
        });

        $('#wpnc-close-modal').on('click', function() {
            $('#wpnc-edit-modal').hide();
        });

        $('#wpnc-save-edit').on('click', function() {
            let id = $('#wpnc-edit-id').val();
            let title = $('#wpnc-edit-title').val();
            let desc = $('#wpnc-edit-desc').val();

            $.post(wpnc_ajax.ajax_url, {
                action: 'wpnc_edit_item',
                id: id,
                title: title,
                description: desc,
                nonce: wpnc_ajax.nonce
            }, function(response) {
                if (response.success) {
                    $('#wpnc-edit-modal').hide();
                    loadQueue(); // Reload to show changes
                } else {
                    alert('Error saving changes.');
                }
            });
        });
    }

    loadQueue();
}));