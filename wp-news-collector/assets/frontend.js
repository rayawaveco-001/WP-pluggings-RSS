jQuery(document).ready(function($) {
    $('.wpnc-load-more-btn').on('click', function() {
        var button = $(this);
        var page = parseInt(button.attr('data-page'));
        var limit = button.attr('data-limit');
        var category = button.attr('data-category');
        var maxPages = parseInt(button.attr('data-max-pages'));

        var nextPage = page + 1;

        button.text('Loading...');
        button.prop('disabled', true);

        $.post(wpnc_frontend_ajax.ajax_url, {
            action: 'wpnc_load_more_news',
            nonce: wpnc_frontend_ajax.nonce,
            page: nextPage,
            limit: limit,
            category: category
        }, function(response) {
            if (response.success) {
                $('#wpnc-news-list').append(response.data.html);
                button.attr('data-page', nextPage);

                if (nextPage >= maxPages) {
                    button.parent().remove();
                } else {
                    button.text('Load More News');
                    button.prop('disabled', false);
                }
            } else {
                button.text('No more news');
            }
        });
    });
});