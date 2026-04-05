(function () {
  "use strict";

  var IDLE_STATUS       = "Iris is ready";
  var RESPONDING_STATUS = "Receiving...";
  var MSG_IDLE_MS       = 4000;   // longer timeout when showing data tables
  var FALLBACK_MSG      = "System unavailable";

  var container = document.getElementById("iris-container");
  var core      = document.getElementById("iris-core");
  var statusEl  = document.getElementById("iris-status");
  var messageEl = document.getElementById("iris-message");
  var form      = document.getElementById("iris-form");
  var input     = document.getElementById("iris-input");

  if (!container || !core || !statusEl || !messageEl || !form || !input) {
    return;
  }

  var apiUrl    = container.getAttribute("data-iris-api") || "";
  var state     = "idle";
  var idleTimer = null;

  // ── State machine ──────────────────────────────────────────────────────────
  function clearIdleTimer() {
    if (idleTimer !== null) { clearTimeout(idleTimer); idleTimer = null; }
  }

  function sameOriginRequest(url) {
    try { return new URL(url, window.location.href).origin === window.location.origin; }
    catch (e) { return false; }
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

  // ── Message area helpers ───────────────────────────────────────────────────
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

  function setInputLocked(locked) {
    input.disabled     = locked;
    input.style.opacity = locked ? "0.5" : "1";
  }

  // ── Table renderer ─────────────────────────────────────────────────────────
  /**
   * Build an HTML table from an array of row objects.
   * Sanitizes all cell content with textContent assignment (no innerHTML injection).
   */
  function buildTable(rows, summaryText) {
    if (!Array.isArray(rows) || rows.length === 0) {
      return '<p class="iris-data-empty">No se encontraron resultados.</p>';
    }

    var keys = Object.keys(rows[0]);

    // Wrapper
    var wrap = document.createElement("div");
    wrap.className = "iris-data-wrap";

    // Summary line
    if (summaryText) {
      var p = document.createElement("p");
      p.className = "iris-data-summary";
      p.textContent = summaryText;
      wrap.appendChild(p);
    }

    // Scroll container
    var scroll = document.createElement("div");
    scroll.className = "iris-data-scroll";

    var table = document.createElement("table");
    table.className = "iris-data-table";

    // Head
    var thead = document.createElement("thead");
    var htr   = document.createElement("tr");
    keys.forEach(function (k) {
      var th = document.createElement("th");
      th.textContent = k;
      htr.appendChild(th);
    });
    thead.appendChild(htr);
    table.appendChild(thead);

    // Body
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

  // ── Main send handler ──────────────────────────────────────────────────────
  async function sendToIris() {
    var prompt = (input.value || "").trim();
    if (!prompt) return;

    if (!apiUrl) { showText(FALLBACK_MSG); return; }

    clearIdleTimer();
    setState("thinking");
    setInputLocked(true);
    hideMessage();

    var creds = sameOriginRequest(apiUrl) ? "same-origin" : "omit";

    try {
      var res = await fetch(apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          message: prompt,
          context: { includeLastRun: true },
          conversation_history: [],
        }),
        mode: "cors",
        credentials: creds,
      });

      var data;
      try { data = await res.json(); }
      catch (_) {
        showText(FALLBACK_MSG);
        setState("idle");
        return;
      }

      if (!res.ok || !data || typeof data.type !== "string") {
        showText(FALLBACK_MSG);
        setState("idle");
        return;
      }

      // ── type: redirect ─────────────────────────────────────────────────────
      if (data.type === "redirect") {
        var target =
          data.redirect && typeof data.redirect === "object" &&
          typeof data.redirect.target === "string"
            ? data.redirect.target : "";
        if (target.length > 0) { window.location.href = target; return; }
        showText(FALLBACK_MSG);
        setState("idle");
        return;
      }

      // ── type: chat (or action) ─────────────────────────────────────────────
      if (data.type === "chat" || data.type === "action") {
        var msg = typeof data.response === "string" ? data.response : FALLBACK_MSG;
        showText(msg);
        setState("responding");
        clearIdleTimer();
        idleTimer = window.setTimeout(function () {
          idleTimer = null; setState("idle");
        }, MSG_IDLE_MS);
        input.value = "";
        return;
      }

      // ── type: data — render table ──────────────────────────────────────────
      if (data.type === "data") {
        var payload   = data.data   || {};
        var rows      = Array.isArray(payload.rows) ? payload.rows : null;
        var summaryTxt = typeof data.response === "string" ? data.response : "Resultados";

        // SHOW_CONFIRM guard for write tools (db_execute) — never auto-render
        if (data.tool === "db_execute") {
          showText("⚠ Operación de escritura detectada. Por seguridad, las ejecuciones de escritura requieren confirmación manual.");
          setState("responding");
          clearIdleTimer();
          idleTimer = window.setTimeout(function () {
            idleTimer = null; setState("idle");
          }, MSG_IDLE_MS * 2);
          input.value = "";
          return;
        }

        var tableHtml;
        if (rows && rows.length > 0) {
          tableHtml = buildTable(rows, summaryTxt);
        } else {
          // Non-table data (file ops, etc.) — show summary + JSON
          tableHtml =
            '<p class="iris-data-summary">' + _esc(summaryTxt) + '</p>' +
            '<pre class="iris-data-json">' + _esc(JSON.stringify(payload, null, 2)) + '</pre>';
        }

        showHTML(tableHtml);
        setState("data");
        clearIdleTimer();
        idleTimer = window.setTimeout(function () {
          idleTimer = null; setState("idle");
        }, MSG_IDLE_MS);
        input.value = "";
        return;
      }

      // Unknown type — fallback
      showText(FALLBACK_MSG);
      setState("idle");

    } catch (_) {
      showText(FALLBACK_MSG);
      setState("idle");
    } finally {
      setInputLocked(false);
    }
  }

  // Minimal HTML-escaping for non-table content
  function _esc(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  // ── Events ─────────────────────────────────────────────────────────────────
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (state === "thinking") return;
    void sendToIris();
  });
})();
