(function () {
  "use strict";

  var IDLE_STATUS = "Iris is ready";
  var RESPONDING_STATUS = "Receiving...";
  var MSG_IDLE_MS = 1000;
  var FALLBACK_MSG = "System unavailable";

  var container = document.getElementById("iris-container");
  var core = document.getElementById("iris-core");
  var statusEl = document.getElementById("iris-status");
  var messageEl = document.getElementById("iris-message");
  var form = document.getElementById("iris-form");
  var input = document.getElementById("iris-input");

  if (!container || !core || !statusEl || !messageEl || !form || !input) {
    return;
  }

  var apiUrl = container.getAttribute("data-iris-api") || "";
  var state = "idle";
  var lastMessage = "";
  var idleTimer = null;

  function clearIdleTimer() {
    if (idleTimer !== null) {
      clearTimeout(idleTimer);
      idleTimer = null;
    }
  }

  function sameOriginRequest(url) {
    try {
      return new URL(url, window.location.href).origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function setState(newState) {
    state = newState;
    core.classList.remove("idle", "thinking", "responding");
    core.classList.add(newState);

    if (newState === "thinking") {
      statusEl.textContent = "Thinking...";
      return;
    }
    if (newState === "responding") {
      statusEl.textContent = RESPONDING_STATUS;
      return;
    }
    if (newState === "idle") {
      statusEl.textContent = IDLE_STATUS;
    }
  }

  function showMessage(text) {
    lastMessage = text;
    messageEl.textContent = text;
    messageEl.hidden = false;
  }

  function hideMessage() {
    messageEl.textContent = "";
    messageEl.hidden = true;
    lastMessage = "";
  }

  function setInputLocked(locked) {
    input.disabled = locked;
    input.style.opacity = locked ? "0.5" : "1";
  }

  async function sendToIris() {
    var prompt = (input.value || "").trim();
    if (!prompt) {
      return;
    }

    if (!apiUrl) {
      showMessage(FALLBACK_MSG);
      return;
    }

    clearIdleTimer();
    setState("thinking");
    setInputLocked(true);

    var creds = sameOriginRequest(apiUrl) ? "same-origin" : "omit";

    try {
      var res = await fetch(apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          message: prompt,
          context: {
            includeLastRun: true,
          },
          conversation_history: [],
        }),
        mode: "cors",
        credentials: creds,
      });

      var data;
      try {
        data = await res.json();
      } catch (parseErr) {
        showMessage(FALLBACK_MSG);
        setState("idle");
        return;
      }

      if (!res.ok) {
        showMessage(FALLBACK_MSG);
        setState("idle");
        return;
      }

      if (!data || typeof data.type !== "string") {
        showMessage(FALLBACK_MSG);
        setState("idle");
        return;
      }

      if (data.type === "redirect") {
        var target =
          data.redirect &&
          typeof data.redirect === "object" &&
          typeof data.redirect.target === "string"
            ? data.redirect.target
            : "";
        if (target.length > 0) {
          window.location.href = target;
          return;
        }
        showMessage(FALLBACK_MSG);
        setState("idle");
        return;
      }

      if (data.type === "chat") {
        if (typeof data.response !== "string") {
          showMessage(FALLBACK_MSG);
          setState("idle");
          return;
        }
        showMessage(data.response);
        setState("responding");
        clearIdleTimer();
        idleTimer = window.setTimeout(function () {
          idleTimer = null;
          setState("idle");
        }, MSG_IDLE_MS);
        input.value = "";
        return;
      }

      showMessage(FALLBACK_MSG);
      setState("idle");
    } catch (err) {
      showMessage(FALLBACK_MSG);
      setState("idle");
    } finally {
      setInputLocked(false);
    }
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (state === "thinking") {
      return;
    }
    void sendToIris();
  });
})();
