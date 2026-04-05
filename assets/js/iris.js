(function () {
  "use strict";

  var IDLE_STATUS       = "Iris is ready";
  var RESPONDING_STATUS = "Receiving...";
  var MSG_IDLE_MS       = 4000;
  var FALLBACK_MSG      = "System unavailable";

  // ── DOM refs ───────────────────────────────────────────────────────────────
  var container   = document.getElementById("iris-container");
  var core        = document.getElementById("iris-core");
  var statusEl    = document.getElementById("iris-status");
  var messageEl   = document.getElementById("iris-message");
  var form        = document.getElementById("iris-form");
  var input       = document.getElementById("iris-input");
  var chatEl      = document.getElementById("iris-chat");

  // Sidebar
  var menuBtn     = document.getElementById("iris-menu-btn");
  var sidebar     = document.getElementById("iris-sidebar");
  var sidebarClose= document.getElementById("iris-sidebar-close");
  var backdrop    = document.getElementById("iris-sidebar-backdrop");
  var convList    = document.getElementById("iris-conv-list");
  var newConvBtn  = document.getElementById("iris-new-conv-btn");

  // Memory
  var memBtn      = document.getElementById("iris-mem-btn");
  var memCount    = document.getElementById("iris-mem-count");
  var memPanel    = document.getElementById("iris-mem-panel");
  var memPanelClose = document.getElementById("iris-mem-panel-close");
  var memList     = document.getElementById("iris-mem-list");
  var memEmpty    = document.getElementById("iris-mem-empty");

  if (!container || !core || !statusEl || !messageEl || !form || !input) return;

  var apiUrl     = container.getAttribute("data-iris-api") || "";
  var convApiUrl = container.getAttribute("data-iris-conv-api") || "";
  var memApiUrl  = container.getAttribute("data-iris-mem-api") || "";

  var state           = "idle";
  var idleTimer       = null;
  var pendingConfirmId= null;
  var conversationId  = null;   // current conversation ID (int or null)
  var activeConvId    = null;   // which sidebar item is highlighted

  // ── Utility ────────────────────────────────────────────────────────────────
  function _esc(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function sameOriginRequest(url) {
    try { return new URL(url, window.location.href).origin === window.location.origin; }
    catch (e) { return false; }
  }

  function creds(url) {
    return sameOriginRequest(url) ? "same-origin" : "omit";
  }

  // ── State machine ──────────────────────────────────────────────────────────
  function clearIdleTimer() {
    if (idleTimer !== null) { clearTimeout(idleTimer); idleTimer = null; }
  }

  function setState(newState) {
    state = newState;
    core.classList.remove("idle", "thinking", "responding", "data");
    core.classList.add(newState);
    if      (newState === "thinking")   { statusEl.textContent = "Thinking..."; }
    else if (newState === "responding") { statusEl.textContent = RESPONDING_STATUS; }
    else if (newState === "data")       { statusEl.textContent = "Showing results"; }
    else                                { statusEl.textContent = IDLE_STATUS; }
  }

  function setInputLocked(locked) {
    input.disabled      = locked;
    input.style.opacity = locked ? "0.5" : "1";
  }

  // ── Chat log ───────────────────────────────────────────────────────────────
  function enableChatLayout() {
    if (!container.classList.contains("has-chat")) {
      container.classList.add("has-chat");
    }
  }

  function appendChatMsg(role, contentHtml) {
    if (!chatEl) return;
    enableChatLayout();
    var wrap = document.createElement("div");
    wrap.className = "iris-chat-msg iris-chat-msg--" + role;
    var bubble = document.createElement("div");
    bubble.className = "iris-chat-msg__bubble";
    if (role === "assistant") {
      bubble.innerHTML = contentHtml;
    } else {
      bubble.textContent = contentHtml;
    }
    wrap.appendChild(bubble);
    chatEl.appendChild(wrap);
    chatEl.scrollTop = chatEl.scrollHeight;
  }

  function clearChat() {
    if (chatEl) { chatEl.innerHTML = ""; }
    container.classList.remove("has-chat");
    conversationId = null;
    activeConvId   = null;
    highlightConv(null);
  }

  // ── Message area (for confirm UI only) ────────────────────────────────────
  function showText(text) {
    messageEl.textContent = text;
    messageEl.hidden = false;
  }

  function showHTML(html) {
    messageEl.innerHTML = html;
    messageEl.hidden = false;
  }

  function hideMessage() {
    messageEl.innerHTML = "";
    messageEl.hidden = true;
  }

  // ── Table renderer ─────────────────────────────────────────────────────────
  function buildTable(rows, summaryText) {
    if (!Array.isArray(rows) || rows.length === 0) {
      return '<p class="iris-data-empty">No se encontraron resultados.</p>';
    }
    var keys = Object.keys(rows[0]);
    var wrap   = document.createElement("div");
    wrap.className = "iris-data-wrap";
    if (summaryText) {
      var p = document.createElement("p");
      p.className = "iris-data-summary";
      p.textContent = summaryText;
      wrap.appendChild(p);
    }
    var scroll = document.createElement("div");
    scroll.className = "iris-data-scroll";
    var table  = document.createElement("table");
    table.className = "iris-data-table";
    var thead = document.createElement("thead");
    var htr   = document.createElement("tr");
    keys.forEach(function (k) {
      var th = document.createElement("th");
      th.textContent = k;
      htr.appendChild(th);
    });
    thead.appendChild(htr);
    table.appendChild(thead);
    var tbody = document.createElement("tbody");
    rows.forEach(function (row) {
      var tr = document.createElement("tr");
      keys.forEach(function (k) {
        var td  = document.createElement("td");
        var val = row[k];
        td.textContent = val === null || val === undefined ? "—" : String(val);
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    scroll.appendChild(table);
    wrap.appendChild(scroll);
    return wrap.outerHTML;
  }

  // ── Confirm UI ─────────────────────────────────────────────────────────────
  function showConfirmUI(preview, confirmId) {
    pendingConfirmId = confirmId;
    var wrap = document.createElement("div");
    wrap.className = "iris-confirm-wrap";
    var msg = document.createElement("p");
    msg.className = "iris-confirm-msg";
    msg.textContent = preview;
    wrap.appendChild(msg);
    var btnRow = document.createElement("div");
    btnRow.className = "iris-confirm-btns";
    var btnConfirm = document.createElement("button");
    btnConfirm.className = "iris-confirm-btn iris-confirm-btn--ok";
    btnConfirm.type = "button";
    btnConfirm.textContent = "Confirmar";
    btnConfirm.addEventListener("click", function () { executeConfirm(confirmId); });
    var btnCancel = document.createElement("button");
    btnCancel.className = "iris-confirm-btn iris-confirm-btn--cancel";
    btnCancel.type = "button";
    btnCancel.textContent = "Cancelar";
    btnCancel.addEventListener("click", function () {
      pendingConfirmId = null;
      hideMessage();
      setState("idle");
      setInputLocked(false);
      input.value = "";
    });
    btnRow.appendChild(btnConfirm);
    btnRow.appendChild(btnCancel);
    wrap.appendChild(btnRow);
    messageEl.innerHTML = "";
    messageEl.appendChild(wrap);
    messageEl.hidden = false;
  }

  async function executeConfirm(confirmId) {
    if (!confirmId) return;
    pendingConfirmId = null;
    setState("thinking");
    setInputLocked(true);
    hideMessage();
    try {
      var res = await fetch(apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          message: "(confirmed)",
          confirm: true,
          confirm_id: confirmId,
          conversation_id: conversationId,
        }),
        mode: "cors",
        credentials: creds(apiUrl),
      });
      var data;
      try { data = await res.json(); } catch (_) { showText(FALLBACK_MSG); setState("idle"); return; }
      if (!res.ok || !data || typeof data.type !== "string") { showText(FALLBACK_MSG); setState("idle"); return; }
      handleResponse(data, null);
    } catch (_) {
      showText(FALLBACK_MSG); setState("idle");
    } finally {
      setInputLocked(false);
    }
  }

  // ── Response dispatcher ────────────────────────────────────────────────────
  /**
   * @param data      — parsed JSON from server
   * @param userPrompt — original user message (null for confirm responses)
   */
  function handleResponse(data, userPrompt) {
    // Store conversation_id if returned
    if (data.conversation_id != null && typeof data.conversation_id !== "undefined") {
      conversationId = data.conversation_id;
    }

    if (data.type === "redirect") {
      var target = data.redirect && typeof data.redirect.target === "string" ? data.redirect.target : "";
      if (target.length > 0) { window.location.href = target; return; }
      appendChatMsg("assistant", _esc(FALLBACK_MSG));
      setState("idle");
      return;
    }

    if (data.type === "confirm") {
      var preview   = typeof data.preview === "string" ? data.preview
        : (typeof data.response === "string" ? data.response : "¿Confirmas esta operación?");
      var confirmId = typeof data.confirm_id === "string" ? data.confirm_id : null;
      if (!confirmId) { appendChatMsg("assistant", _esc(FALLBACK_MSG)); setState("idle"); return; }
      setState("responding");
      showConfirmUI(preview, confirmId);
      return;
    }

    if (data.type === "chat" || data.type === "action") {
      var msg = typeof data.response === "string" ? data.response : FALLBACK_MSG;
      appendChatMsg("assistant", _esc(msg));
      setState("responding");
      clearIdleTimer();
      idleTimer = window.setTimeout(function () { idleTimer = null; setState("idle"); }, MSG_IDLE_MS);
      input.value = "";
      return;
    }

    if (data.type === "data") {
      var payload    = data.data   || {};
      var rows       = Array.isArray(payload.rows) ? payload.rows : null;
      var summaryTxt = typeof data.response === "string" ? data.response : "Resultados";
      var tableHtml;
      if (rows && rows.length > 0) {
        tableHtml = buildTable(rows, summaryTxt);
      } else {
        tableHtml =
          '<p class="iris-data-summary">' + _esc(summaryTxt) + '</p>' +
          '<pre class="iris-data-json">' + _esc(JSON.stringify(payload, null, 2)) + '</pre>';
      }
      appendChatMsg("assistant", tableHtml);
      setState("data");
      clearIdleTimer();
      idleTimer = window.setTimeout(function () { idleTimer = null; setState("idle"); }, MSG_IDLE_MS);
      input.value = "";
      return;
    }

    appendChatMsg("assistant", _esc(FALLBACK_MSG));
    setState("idle");
  }

  // ── Send ───────────────────────────────────────────────────────────────────
  async function sendToIris() {
    var prompt = (input.value || "").trim();
    if (!prompt) return;
    if (!apiUrl) { appendChatMsg("assistant", _esc(FALLBACK_MSG)); return; }

    appendChatMsg("user", prompt);
    clearIdleTimer();
    setState("thinking");
    setInputLocked(true);
    hideMessage();
    input.value = "";

    try {
      var res = await fetch(apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          message: prompt,
          context: { includeLastRun: true },
          conversation_id: conversationId,
        }),
        mode: "cors",
        credentials: creds(apiUrl),
      });
      var data;
      try { data = await res.json(); } catch (_) { appendChatMsg("assistant", _esc(FALLBACK_MSG)); setState("idle"); return; }
      if (!res.ok || !data || typeof data.type !== "string") { appendChatMsg("assistant", _esc(FALLBACK_MSG)); setState("idle"); return; }
      handleResponse(data, prompt);
    } catch (_) {
      appendChatMsg("assistant", _esc(FALLBACK_MSG));
      setState("idle");
    } finally {
      setInputLocked(false);
    }
  }

  // ── Sidebar ────────────────────────────────────────────────────────────────
  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add("open");
    sidebar.setAttribute("aria-hidden", "false");
    if (backdrop) { backdrop.classList.add("visible"); }
    loadConversationList();
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove("open");
    sidebar.setAttribute("aria-hidden", "true");
    if (backdrop) { backdrop.classList.remove("visible"); }
  }

  function highlightConv(id) {
    activeConvId = id;
    if (!convList) return;
    var items = convList.querySelectorAll(".iris-conv-item");
    items.forEach(function (el) {
      var elId = parseInt(el.getAttribute("data-id"), 10);
      el.classList.toggle("active", elId === id);
    });
  }

  async function loadConversationList() {
    if (!convList || !convApiUrl) return;
    convList.innerHTML = '<li class="iris-conv-loading">Cargando...</li>';
    try {
      var res = await fetch(convApiUrl, { credentials: creds(convApiUrl) });
      if (!res.ok) { convList.innerHTML = ""; return; }
      var data = await res.json();
      var convs = Array.isArray(data.conversations) ? data.conversations : [];
      renderConversationList(convs);
    } catch (_) {
      convList.innerHTML = "";
    }
  }

  function renderConversationList(convs) {
    if (!convList) return;
    convList.innerHTML = "";
    if (convs.length === 0) {
      convList.innerHTML = '<li class="iris-conv-loading">Sin conversaciones.</li>';
      return;
    }
    convs.forEach(function (c) {
      var li  = document.createElement("li");
      li.className = "iris-conv-item";
      li.setAttribute("data-id", c.id);
      if (c.id === activeConvId) { li.classList.add("active"); }

      var btn = document.createElement("button");
      btn.className = "iris-conv-item__btn";
      btn.type = "button";
      btn.textContent = c.title || "Conversación";
      btn.addEventListener("click", function () {
        closeSidebar();
        loadConversation(c.id);
      });

      var del = document.createElement("button");
      del.className = "iris-conv-item__del";
      del.type = "button";
      del.setAttribute("aria-label", "Eliminar conversación");
      del.textContent = "×";
      del.addEventListener("click", function (e) {
        e.stopPropagation();
        deleteConversation(c.id, li);
      });

      li.appendChild(btn);
      li.appendChild(del);
      convList.appendChild(li);
    });
  }

  async function loadConversation(id) {
    if (!convApiUrl) return;
    clearChat();
    setState("thinking");
    try {
      var res = await fetch(convApiUrl + "?id=" + id, { credentials: creds(convApiUrl) });
      if (!res.ok) { setState("idle"); return; }
      var data = await res.json();
      var messages = Array.isArray(data.messages) ? data.messages : [];
      if (messages.length > 0) { enableChatLayout(); }
      messages.forEach(function (m) {
        var role = m.role === "user" ? "user" : "assistant";
        appendChatMsg(role, role === "user" ? m.content : _esc(m.content));
      });
      conversationId = id;
      activeConvId   = id;
      highlightConv(id);
    } catch (_) {
      // ignore
    }
    setState("idle");
  }

  async function deleteConversation(id, liEl) {
    if (!convApiUrl) return;
    try {
      var res = await fetch(convApiUrl + "?id=" + id, {
        method: "DELETE",
        credentials: creds(convApiUrl),
      });
      if (!res.ok) return;
      if (liEl && liEl.parentNode) { liEl.parentNode.removeChild(liEl); }
      // If deleting the active conversation, clear chat
      if (conversationId === id) { clearChat(); setState("idle"); }
    } catch (_) {
      // ignore
    }
  }

  // ── Memory ─────────────────────────────────────────────────────────────────
  async function loadMemory() {
    if (!memApiUrl || !memList) return;
    try {
      var res = await fetch(memApiUrl, { credentials: creds(memApiUrl) });
      if (!res.ok) return;
      var data = await res.json();
      var facts = Array.isArray(data.facts) ? data.facts : [];
      renderMemory(facts);
    } catch (_) {
      // ignore
    }
  }

  function renderMemory(facts) {
    if (!memList) return;
    memList.innerHTML = "";
    if (facts.length === 0) {
      if (memEmpty) { memEmpty.hidden = false; }
      updateMemoryBadge(0);
      return;
    }
    if (memEmpty) { memEmpty.hidden = true; }
    updateMemoryBadge(facts.length);
    facts.forEach(function (f) {
      var li  = document.createElement("li");
      li.className = "iris-mem-item";

      var keyEl = document.createElement("span");
      keyEl.className = "iris-mem-item__key";
      keyEl.textContent = f.fact_key;

      var valEl = document.createElement("span");
      valEl.className = "iris-mem-item__val";
      valEl.textContent = f.fact_value;

      var del = document.createElement("button");
      del.className = "iris-mem-item__del";
      del.type = "button";
      del.setAttribute("aria-label", "Eliminar");
      del.textContent = "×";
      del.addEventListener("click", function () {
        deleteMemoryFact(f.fact_key, li);
      });

      li.appendChild(keyEl);
      li.appendChild(valEl);
      li.appendChild(del);
      memList.appendChild(li);
    });
  }

  function updateMemoryBadge(count) {
    if (!memBtn) return;
    if (count > 0) {
      memBtn.hidden = false;
      memBtn.classList.add("has-count");
      if (memCount) { memCount.textContent = String(count); }
    } else {
      memBtn.classList.remove("has-count");
      if (memCount) { memCount.textContent = ""; }
      // keep button visible if panel was ever opened
    }
  }

  async function deleteMemoryFact(key, liEl) {
    if (!memApiUrl) return;
    try {
      var url = memApiUrl + "?key=" + encodeURIComponent(key);
      var res = await fetch(url, {
        method: "DELETE",
        credentials: creds(memApiUrl),
      });
      if (!res.ok) return;
      if (liEl && liEl.parentNode) { liEl.parentNode.removeChild(liEl); }
      var remaining = memList ? memList.querySelectorAll(".iris-mem-item").length : 0;
      updateMemoryBadge(remaining);
      if (remaining === 0 && memEmpty) { memEmpty.hidden = false; }
    } catch (_) {
      // ignore
    }
  }

  function openMemPanel() {
    if (!memPanel) return;
    memPanel.hidden = false;
    loadMemory();
  }

  function closeMemPanel() {
    if (!memPanel) return;
    memPanel.hidden = true;
  }

  // ── Init ───────────────────────────────────────────────────────────────────
  function init() {
    // Load initial memory badge
    if (memApiUrl) {
      loadMemory();
    }
    // Hide sidebar/menu btn if no conversation API
    if (!convApiUrl && menuBtn) {
      menuBtn.style.display = "none";
    }
  }

  // ── Event listeners ────────────────────────────────────────────────────────
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (state === "thinking") return;
    void sendToIris();
  });

  if (menuBtn) {
    menuBtn.addEventListener("click", openSidebar);
  }
  if (sidebarClose) {
    sidebarClose.addEventListener("click", closeSidebar);
  }
  if (backdrop) {
    backdrop.addEventListener("click", closeSidebar);
  }
  if (newConvBtn) {
    newConvBtn.addEventListener("click", function () {
      clearChat();
      setState("idle");
      closeSidebar();
      input.focus();
    });
  }
  if (memBtn) {
    memBtn.addEventListener("click", function () {
      if (memPanel && !memPanel.hidden) { closeMemPanel(); } else { openMemPanel(); }
    });
  }
  if (memPanelClose) {
    memPanelClose.addEventListener("click", closeMemPanel);
  }

  // Close mem panel on outside click
  document.addEventListener("click", function (e) {
    if (!memPanel || memPanel.hidden) return;
    if (!memPanel.contains(e.target) && e.target !== memBtn && !memBtn.contains(e.target)) {
      closeMemPanel();
    }
  });

  init();
})();
