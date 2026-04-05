(function () {
  "use strict";

  var IDLE_STATUS = "Iris is ready";
  var RESPONDING_STATUS = "Receiving...";
  var MSG_IDLE_MS = 1000;

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
    var value = (input.value || "").trim();
    if (!value) {
      return;
    }

    if (!apiUrl) {
      showMessage("Configuration error: missing API URL.");
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
        body: JSON.stringify({ input: value }),
        mode: "cors",
        credentials: creds,
      });

      var data;
      try {
        data = await res.json();
      } catch (parseErr) {
        console.error(parseErr);
        showMessage("Something went wrong.");
        setState("idle");
        return;
      }

      if (!res.ok) {
        showMessage("Something went wrong.");
        setState("idle");
        return;
      }

      if (!data || typeof data.type !== "string") {
        showMessage("Something went wrong.");
        setState("idle");
        return;
      }

      if (data.type === "redirect") {
        if (typeof data.target === "string" && data.target.length > 0) {
          window.location.href = data.target;
          return;
        }
        showMessage("Something went wrong.");
        setState("idle");
        return;
      }

      if (data.type === "message") {
        if (typeof data.message !== "string") {
          showMessage("Something went wrong.");
          setState("idle");
          return;
        }
        showMessage(data.message);
        setState("responding");
        clearIdleTimer();
        idleTimer = window.setTimeout(function () {
          idleTimer = null;
          setState("idle");
        }, MSG_IDLE_MS);
        input.value = "";
        return;
      }

      showMessage("Something went wrong.");
      setState("idle");
    } catch (err) {
      console.error(err);
      showMessage("Something went wrong.");
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
