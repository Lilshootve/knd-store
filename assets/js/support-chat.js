// KND Store — Support AI Chat Widget

(function () {
    'use strict';

    var ENDPOINT = '/api/support/chat.php';
    var SESSION_KEY = 'knd_support_chat';
    var DISCLAIMER = 'This is an AI assistant. For billing, refunds, or complex issues, please <a href="/contact.php">contact support</a>.';
    var WELCOME = 'Hi! I\'m the KND Support assistant. How can I help you today?';
    var QUICK_TOPICS = [
        { label: 'Payment', msg: 'What payment methods do you accept?' },
        { label: 'Delivery', msg: 'How long does delivery take?' },
        { label: 'Sizing', msg: 'How do I choose the right size?' },
        { label: 'Refunds', msg: 'What is your refund policy?' },
        { label: 'Contact', msg: 'How can I reach human support?' }
    ];

    var panel, msgArea, input, sendBtn, typingEl, chatBtn;
    var history = [];
    var sending = false;

    function loadHistory() {
        try {
            var raw = sessionStorage.getItem(SESSION_KEY);
            if (raw) history = JSON.parse(raw);
        } catch (e) { history = []; }
    }

    function saveHistory() {
        try { sessionStorage.setItem(SESSION_KEY, JSON.stringify(history)); } catch (e) {}
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function linkify(text) {
        return text
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
            .replace(/(https?:\/\/[^\s<]+)/g, function (url) {
                if (url.indexOf('">') !== -1) return url;
                return '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>';
            })
            .replace(/\n/g, '<br>');
    }

    function addMsg(role, content, save) {
        var div = document.createElement('div');
        div.className = 'knd-chat-msg ' + role;
        if (role === 'system') {
            div.innerHTML = content;
        } else {
            div.innerHTML = linkify(escHtml(content));
        }
        msgArea.appendChild(div);
        msgArea.scrollTop = msgArea.scrollHeight;
        if (save !== false) {
            history.push({ role: role, content: content });
            saveHistory();
        }
    }

    function showTyping(on) {
        typingEl.style.display = on ? 'block' : 'none';
        if (on) msgArea.scrollTop = msgArea.scrollHeight;
    }

    function setEnabled(on) {
        sending = !on;
        sendBtn.disabled = !on;
        input.disabled = !on;
    }

    function sendMessage(text) {
        text = text.trim();
        if (!text || sending) return;

        addMsg('user', text);
        setEnabled(false);
        showTyping(true);

        var apiMessages = [];
        for (var i = 0; i < history.length; i++) {
            var m = history[i];
            if (m.role === 'user' || m.role === 'assistant') {
                apiMessages.push({ role: m.role, content: m.content });
            }
        }

        fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ messages: apiMessages, locale: document.documentElement.lang || 'en' })
        })
        .then(function (res) {
            if (!res.ok) {
                return res.json().then(function (d) { throw new Error(d.error || 'Server error'); });
            }
            return res.json();
        })
        .then(function (data) {
            showTyping(false);
            addMsg('assistant', data.reply);
            setEnabled(true);
            input.focus();
        })
        .catch(function (err) {
            showTyping(false);
            addMsg('assistant', err.message || 'Something went wrong. Please try again or contact support@kndstore.com.');
            setEnabled(true);
        });
    }

    function openPanel() {
        panel.classList.add('open');
        chatBtn.classList.add('has-panel');
        input.focus();
    }

    function closePanel() {
        panel.classList.remove('open');
        chatBtn.classList.remove('has-panel');
    }

    function renderHistory() {
        msgArea.innerHTML = '';
        addMsg('system', DISCLAIMER, false);
        if (history.length === 0) {
            addMsg('assistant', WELCOME);
        } else {
            for (var i = 0; i < history.length; i++) {
                var m = history[i];
                if (m.role === 'system') continue;
                addMsg(m.role, m.content, false);
            }
        }
    }

    function init() {
        chatBtn = document.getElementById('knd-chat-btn');
        panel = document.getElementById('knd-chat-panel');
        if (!chatBtn || !panel) return;

        msgArea = panel.querySelector('.knd-chat-messages');
        input = panel.querySelector('.knd-chat-input');
        sendBtn = panel.querySelector('.knd-chat-send');
        typingEl = panel.querySelector('.knd-chat-typing');

        loadHistory();
        renderHistory();

        chatBtn.addEventListener('click', openPanel);
        panel.querySelector('.knd-chat-close').addEventListener('click', closePanel);

        sendBtn.addEventListener('click', function () {
            sendMessage(input.value);
            input.value = '';
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage(input.value);
                input.value = '';
            }
        });

        var quickBtns = panel.querySelectorAll('.knd-chat-quick-btn');
        quickBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var msg = this.dataset.msg;
                if (msg) sendMessage(msg);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.KNDSupportChat = { open: openPanel, close: closePanel };
})();
