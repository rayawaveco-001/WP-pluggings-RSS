document.addEventListener('DOMContentLoaded', function() {
    const fab = document.getElementById('rfkala-ai-fab');
    const chatBox = document.getElementById('rfkala-ai-chat-box');
    const closeBtn = document.getElementById('rfkala-ai-chat-close');
    const sendBtn = document.getElementById('rfkala-ai-chat-send');
    const inputField = document.getElementById('rfkala-ai-chat-input');
    const messagesArea = document.getElementById('rfkala-ai-chat-messages');
    const loadingIndicator = document.getElementById('rfkala-ai-loading');

    // Toggle Chat
    fab.addEventListener('click', (e) => {
        e.preventDefault();
        if (chatBox.classList.contains('active')) {
            chatBox.classList.remove('active');
        } else {
            chatBox.classList.add('active');
            inputField.focus();
            // Auto-scroll just in case
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    });

    closeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        chatBox.classList.remove('active');
    });

    // Send Message
    const sendMessage = async () => {
        const text = inputField.value.trim();
        if (!text) return;

        // Add user message to UI
        addMessage(text, 'user');
        inputField.value = '';

        // Show dynamic "Typing..." indicator
        loadingIndicator.style.display = 'block';
        loadingIndicator.textContent = "مهندس در حال بررسی سیستم...";

        // Simple dot animation
        let dotCount = 0;
        const typingInterval = setInterval(() => {
            dotCount = (dotCount + 1) % 4;
            loadingIndicator.textContent = "مهندس در حال بررسی سیستم" + ".".repeat(dotCount);
        }, 500);

        messagesArea.scrollTop = messagesArea.scrollHeight;

        try {
            const response = await fetch(RfkalaAiConfig.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message: text })
            });

            const result = await response.json();

            clearInterval(typingInterval);
            loadingIndicator.style.display = 'none';

            if (result.success) {
                addMessage(result.data, 'bot');
            } else {
                addMessage('خطا در برقراری ارتباط با سرور.', 'bot');
            }

        } catch (error) {
            clearInterval(typingInterval);
            loadingIndicator.style.display = 'none';
            addMessage('خطا در شبکه. لطفا دوباره تلاش کنید.', 'bot');
        }
    };

    sendBtn.addEventListener('click', sendMessage);

    inputField.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    function addMessage(text, sender) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `rfkala-msg ${sender}`;

        // Use textContent to prevent XSS, handling newlines by creating text nodes and br elements
        const lines = text.split('\n');
        lines.forEach((line, index) => {
            msgDiv.appendChild(document.createTextNode(line));
            if (index < lines.length - 1) {
                msgDiv.appendChild(document.createElement('br'));
            }
        });

        messagesArea.insertBefore(msgDiv, loadingIndicator);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }
});
