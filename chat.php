<?php
// chat.php - KND Chat Interface
// DO NOT CHANGE fetch URL - must use relative path /api/ollama-proxy.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>KND Chat</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap"
    rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      background: #010508;
      color: #00e8ff;
      font-family: "Share Tech Mono", monospace;
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden
    }

    body::after {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background: repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(0, 0, 0, .04) 3px, rgba(0, 0, 0, .04) 4px);
      z-index: 9999
    }

    #header {
      padding: 14px 24px;
      border-bottom: 1px solid rgba(0, 232, 255, .15);
      display: flex;
      align-items: center;
      gap: 12px
    }

    #header h1 {
      font-family: "Orbitron", sans-serif;
      font-size: 13px;
      font-weight: 900;
      letter-spacing: .3em;
      color: #fff
    }

    #header h1 span {
      color: #00e8ff
    }

    .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #00e8ff;
      box-shadow: 0 0 8px #00e8ff;
      animation: pulse 2s infinite
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .3
      }
    }

    #messages {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 12px
    }

    #messages::-webkit-scrollbar {
      width: 4px
    }

    #messages::-webkit-scrollbar-track {
      background: transparent
    }

    #messages::-webkit-scrollbar-thumb {
      background: rgba(0, 232, 255, .2);
      border-radius: 2px
    }

    .message {
      max-width: 75%;
      padding: 12px 16px;
      border-radius: 6px;
      font-size: 13px;
      line-height: 1.7;
      word-wrap: break-word
    }

    .message.user {
      align-self: flex-end;
      background: rgba(0, 232, 255, .1);
      border: 1px solid rgba(0, 232, 255, .2);
      color: #00e8ff
    }

    .message.bot {
      align-self: flex-start;
      background: rgba(255, 255, 255, .03);
      border: 1px solid rgba(255, 255, 255, .08);
      color: rgba(255, 255, 255, .85)
    }

    .message.bot.typing::after {
      content: "▋";
      animation: blink .7s infinite
    }

    @keyframes blink {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: 0
      }
    }

    #input-area {
      padding: 16px 20px;
      border-top: 1px solid rgba(0, 232, 255, .1);
      display: flex;
      gap: 10px
    }

    #input {
      flex: 1;
      background: rgba(0, 232, 255, .05);
      border: 1px solid rgba(0, 232, 255, .15);
      border-radius: 6px;
      color: #00e8ff;
      font-family: "Share Tech Mono", monospace;
      font-size: 14px;
      padding: 12px;
      outline: none;
      resize: none;
      height: 48px
    }

    #input:focus {
      border-color: rgba(0, 232, 255, .4);
      box-shadow: 0 0 12px rgba(0, 232, 255, .08)
    }

    #send {
      padding: 0 20px;
      background: rgba(0, 232, 255, .08);
      border: 1px solid rgba(0, 232, 255, .25);
      border-radius: 6px;
      color: #00e8ff;
      font-family: "Orbitron", sans-serif;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .15em;
      cursor: pointer;
      transition: all .2s;
      white-space: nowrap
    }

    #send:hover {
      background: rgba(0, 232, 255, .15);
      box-shadow: 0 0 16px rgba(0, 232, 255, .15)
    }

    #send:disabled {
      opacity: .4;
      cursor: not-allowed
    }

    #status {
      font-size: 10px;
      color: rgba(0, 232, 255, .35);
      letter-spacing: .08em;
      margin-left: auto
    }
  </style>
</head>

<body>
  <div id="header">
    <div class="dot"></div>
    <h1>KND <span>AI</span></h1>
    <span id="status">ONLINE · slekrem/gpt-oss-claude-code-32k</span>
  </div>
  <div id="messages"></div>
  <div id="input-area">
    <input type="text" id="input" placeholder="Escribe tu mensaje..." autocomplete="off">
    <button id="send">ENVIAR</button>
  </div>
  <script>
    let conversation = [];
    const input = document.getElementById('input');
    const send = document.getElementById('send');
    const messages = document.getElementById('messages');
    const status = document.getElementById('status');

    function addMessage(content, role) {
      const div = document.createElement('div');
      div.className = 'message ' + role;
      div.textContent = content;
      messages.appendChild(div);
      messages.scrollTop = messages.scrollHeight;
      return div;
    }

    async function sendMessage() {
      const text = input.value.trim();
      if (!text) return;
      addMessage(text, 'user');
      input.value = '';
      input.disabled = true;
      send.disabled = true;
      status.textContent = 'PROCESANDO...';

      const botDiv = addMessage('', 'bot typing');

      conversation.push({ role: 'user', content: text });

      try {
        // DO NOT CHANGE THIS URL - must be relative path
        const resp = await fetch('/api/ollama-proxy.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            model: 'slekrem/gpt-oss-claude-code-32k',
            messages: conversation,
            stream: true
          })
        });
        if (!resp.ok) throw new Error('Network response was not ok');
        const reader = resp.body.getReader();
        const decoder = new TextDecoder('utf-8');
        let done = false;
        let buffer = '';
        while (!done) {
          const { value, done: doneReading } = await reader.read();
          done = doneReading;
          if (value) {
            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();
            for (const line of lines) {
              if (!line.trim()) continue;
              try {
                const obj = JSON.parse(line);
                if (obj.done) { done = true; break; }
                if (obj.message && obj.message.content) {
                  botDiv.textContent += obj.message.content;
                  botDiv.classList.add('typing');
                  messages.scrollTop = messages.scrollHeight;
                }
              } catch (_) {}
            }
          }
        }
        botDiv.classList.remove('typing');
        conversation.push({ role: 'assistant', content: botDiv.textContent });
    saveConversation();
      } catch (err) {
        // Remove last user message to avoid duplicate on retry
        if (conversation.length && conversation[conversation.length-1].role === 'user') {
          conversation.pop();
        }
        botDiv.textContent = err.message;
        botDiv.classList.remove('typing');
      }

      input.disabled = false;
      send.disabled = false;
      status.textContent = 'ONLINE · slekrem/gpt-oss-claude-code-32k';
      input.focus();
    }

    send.addEventListener('click', sendMessage);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });
    

  // Persistence functions
  function loadConversation() {
    const stored = localStorage.getItem('knd_chat_history');
    if (stored) {
      try {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed)) {
          conversation = parsed;
          for (const msg of conversation) {
            const roleClass = msg.role === 'assistant' ? 'bot' : msg.role;
            addMessage(msg.content, roleClass);
          }
        }
      } catch (_) {}
    }
  }

  function saveConversation() {
    const toStore = conversation.slice(-20);
    localStorage.setItem('knd_chat_history', JSON.stringify(toStore));
  }

  function clearChat() {
    localStorage.removeItem('knd_chat_history');
    conversation = [];
    messages.innerHTML = '';
    input.disabled = false;
    send.disabled = false;
    status.textContent = 'ONLINE · slekrem/gpt-oss-claude-code-32k';
  }

  // Create Clear Chat button
  const clearBtn = document.createElement('button');
  clearBtn.id = 'clear-chat';
  clearBtn.textContent = 'Clear Chat';
  clearBtn.style = 'margin-left:10px;padding:0 10px;background:rgba(0,232,255,.08);border:1px solid rgba(0,232,255,.25);border-radius:6px;color:#00e8ff;font-family:\'Orbitron\',sans-serif;font-size:10px;font-weight:700;letter-spacing:.15em;cursor:pointer;transition:all .2s;';
  document.getElementById('header').appendChild(clearBtn);
  clearBtn.addEventListener('click', clearChat);

  // Load conversation on page load
  loadConversation();
</script>

</html>
