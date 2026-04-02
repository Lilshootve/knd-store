<?php
// chat.php - Simple KND styled chat interface
// Uses lobby.css for styling and colors
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>KND Chat</title>
<link rel="stylesheet" href="games/mind-wars/lobby.css">
<style>
/* Chat specific styles, leveraging KND variables */
.chat-shell {
  position:fixed;
  inset:0;
  display:flex;
  flex-direction:column;
  background:var(--panel);
  color:var(--t1);
  font-family:var(--FR);
  padding:20px;
  box-sizing:border-box;
}
.chat-messages {
  flex:1;
  overflow-y:auto;
  margin-bottom:10px;
  padding-right:10px;
}
.message {
  margin-bottom:12px;
  max-width:80%;
  padding:10px 14px;
  border-radius:8px;
  background:rgba(0,0,0,0.2);
  word-wrap:break-word;
}
.message.user {
  align-self:flex-end;
  background:var(--c);
  color:var(--void);
}
.message.bot {
  align-self:flex-start;
  background:var(--c2);
  color:var(--void);
}
.chat-input {
  display:flex;
  gap:8px;
}
.chat-input input {
  flex:1;
  padding:10px;
  border:1px solid var(--border2);
  border-radius:4px;
  background:var(--bg2);
  color:var(--t1);
  font-family:var(--FR);
  font-size:16px;
}
.chat-input button {
  padding:10px 16px;
  background:var(--c);
  color:var(--void);
  border:none;
  border-radius:4px;
  cursor:pointer;
  font-family:var(--FB);
  font-weight:600;
}
.chat-input button:hover {background:var(--c2);}
</style>
</head>
<body>
<div class="chat-shell">
  <div class="chat-messages" id="messages"></div>
  <form class="chat-input" id="chatForm">
    <input type="text" id="input" placeholder="Type your message..." autocomplete="off" required>
    <button type="submit">Send</button>
  </form>
</div>
<script>
let conversation = [];
const form = document.getElementById('chatForm');
const input = document.getElementById('input');
const messages = document.getElementById('messages');

function addMessage(content, role) {
  const div = document.createElement('div');
  div.className = 'message ' + role;
  div.textContent = content;
  messages.appendChild(div);
  messages.scrollTop = messages.scrollHeight;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const text = input.value.trim();
  if (!text) return;
  addMessage(text, 'user');
  input.value = '';
  input.disabled = true;
  const botDiv = document.createElement('div');
  botDiv.className = 'message bot';
  botDiv.textContent = '';
  messages.appendChild(botDiv);
  messages.scrollTop = messages.scrollHeight;

  conversation.push({ role: 'user', content: text });

  try {
    const resp = await fetch('https://kndstore.com/api/ollama-proxy.php', {
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
    let partial = '';
    while (!done) {
      const { value, done: doneReading } = await reader.read();
      done = doneReading;
      if (value) {
        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop(); // keep incomplete line
        for (const line of lines) {
          if (!line.trim()) continue;
          try {
            const obj = JSON.parse(line);
            if (obj.message && obj.message.content) {
              botDiv.textContent += obj.message.content;
              messages.scrollTop = messages.scrollHeight;
            }
          } catch (_) {}
        }
      }
    }
    conversation.push({ role: 'assistant', content: botDiv.textContent });
  } catch (err) {
    botDiv.textContent = 'Error: ' + err.message;
  }
  input.disabled = false;
});
</script>
</body>
</html>
