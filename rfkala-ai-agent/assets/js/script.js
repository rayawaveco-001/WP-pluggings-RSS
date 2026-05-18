jQuery(document).ready(function($) {

    var launcher = $('#rfkala-chat-launcher');
    var widget = $('#rfkala-chat-widget');
    var toggle = $('#rfkala-chat-toggle');
    var input = $('#rfkala-chat-input');
    var sendBtn = $('#rfkala-chat-send');
    var messages = $('#rfkala-chat-messages');

    launcher.on('click', function() {
        widget.fadeIn();
        launcher.hide();
    });

    toggle.on('click', function() {
        widget.fadeOut();
        launcher.show();
    });

    function appendMessage(text, sender) {
        var msgDiv = $('<div class="chat-msg ' + sender + '"></div>').text(text);
        messages.append(msgDiv);
        scrollToBottom();
    }

    function appendProducts(products) {
        if (!products || products.length === 0) return;

        var prodContainer = $('<div class="chat-products agent"></div>');
        var ul = $('<ul></ul>').css('list-style', 'none').css('padding', '0');

        $.each(products, function(i, p) {
            var li = $('<li class="chat-product-item"></li>');
            var link = $('<a></a>').attr('href', p.url).attr('target', '_blank').text(p.name);
            var price = p.price ? p.price + ' (Status: ' + p.stock_status + ')' : '(Status: ' + p.stock_status + ')';

            li.append(link);
            li.append('<br><small>Price: ' + price + '</small>');
            ul.append(li);
        });

        prodContainer.append(ul);
        messages.append(prodContainer);
        scrollToBottom();
    }

    function scrollToBottom() {
        var body = $('#rfkala-chat-body');
        body.scrollTop(body[0].scrollHeight);
    }

    function sendMessage() {
        var text = input.val().trim();
        if (text === '') return;

        appendMessage(text, 'user');
        input.val('');

        var loading = $('<div class="chat-loading">Agent is typing...</div>');
        messages.append(loading);
        scrollToBottom();

        $.ajax({
            url: rfkala_ai_agent_obj.api_url,
            method: 'POST',
            data: {
                message: text
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', rfkala_ai_agent_obj.nonce);
            },
            success: function(response) {
                loading.remove();
                if (response && response.reply) {
                    appendMessage(response.reply, 'agent');
                    if (response.products && response.products.length > 0) {
                        appendProducts(response.products);
                    }
                } else {
                    appendMessage('Received an empty response from the server.', 'agent');
                }
            },
            error: function() {
                loading.remove();
                appendMessage('Sorry, there was an error processing your request.', 'agent');
            }
        });
    }

    sendBtn.on('click', sendMessage);

    input.on('keypress', function(e) {
        if (e.which == 13) {
            sendMessage();
        }
    });

});
