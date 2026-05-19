document.addEventListener('DOMContentLoaded', function() {
    const fab = document.getElementById('rfkala-chatbot-fab');
    const container = document.querySelector('.rfkala-chatbot-container');
    const closeBtn = document.getElementById('rfkala-chatbot-close');
    const inputField = document.getElementById('rfkala-chatbot-input');
    const sendBtn = document.getElementById('rfkala-chatbot-send');
    const chatBody = document.getElementById('rfkala-chatbot-body');
    const typingIndicator = document.querySelector('.rfkala-chatbot-typing');

    let sessionId = localStorage.getItem('rfkala_chat_session');
    if (!sessionId) {
        sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('rfkala_chat_session', sessionId);
    }

    let lastPollId = localStorage.getItem('rfkala_last_poll_id') || 0;

    // Load history
    const history = JSON.parse(localStorage.getItem('rfkala_chat_history')) || [];
    if (history.length > 0) {
        // Clear default greeting if we have history
        chatBody.innerHTML = '';
        history.forEach(msg => {
            appendMessage(msg.text, msg.sender, false);
        });
    }

    // Toggle logic
    fab.addEventListener('click', function(e) {
        e.preventDefault();
        container.style.display = 'flex';
        fab.style.display = 'none';
        scrollToBottom();
    });

    closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        container.style.display = 'none';
        fab.style.display = 'flex';
    });

    // Send logic
    sendBtn.addEventListener('click', sendMessage);
    inputField.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    function appendMessage(text, sender, save = true) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'rfkala-message ' + sender;
        msgDiv.textContent = text; // Prevent XSS
        chatBody.appendChild(msgDiv);
        scrollToBottom();

        if (save) {
            const hist = JSON.parse(localStorage.getItem('rfkala_chat_history')) || [];
            hist.push({ text: text, sender: sender });
            localStorage.setItem('rfkala_chat_history', JSON.stringify(hist));
        }
    }

    function scrollToBottom() {
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function sendMessage() {
        const text = inputField.value.trim();
        if (!text) return;

        inputField.value = '';
        appendMessage(text, 'user');

        typingIndicator.style.display = 'flex';
        scrollToBottom();

        // Prepare AJAX request
        const formData = new FormData();
        formData.append('action', 'rfkala_chat');
        formData.append('nonce', rfkalaAiVars.nonce);
        formData.append('message', text);
        formData.append('session_id', sessionId);

        fetch(rfkalaAiVars.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            typingIndicator.style.display = 'none';
            if (data.success && data.data && data.data.reply) {
                appendMessage(data.data.reply, 'bot');
            } else {
                appendMessage('خطایی رخ داد. لطفا دوباره تلاش کنید.', 'bot');
            }
        })
        .catch(error => {
            typingIndicator.style.display = 'none';
            appendMessage('خطا در ارتباط با سرور.', 'bot');
            console.error('Error:', error);
        });
    }

    // Polling for Bale Handoff Messages
    function pollBaleMessages() {
        const url = new URL(rfkalaAiVars.poll_url);
        url.searchParams.append('session_id', sessionId);
        url.searchParams.append('last_id', lastPollId);

        fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    appendMessage(msg.message, 'bot');
                    lastPollId = msg.id;
                });
                localStorage.setItem('rfkala_last_poll_id', lastPollId);
            }
        })
        .catch(err => console.error('Polling error:', err));
    }

    // Poll every 10 seconds
    setInterval(pollBaleMessages, 10000);
});
