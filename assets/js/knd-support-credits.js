/**
 * KND Support Credits page: starfield, balance count-up, chips, methods, submit, scroll reveal.
 */
(function () {
  var root = document.querySelector(".knd-support-credits-page");
  if (!root) {
    return;
  }

  var c = document.getElementById("ksc-bg-canvas");
  if (c && c.getContext) {
    var x = c.getContext("2d");
    var stars = [];
    var N = 80;
    function resizeCanvas() {
      c.width = window.innerWidth;
      c.height = window.innerHeight;
    }
    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();
    for (var i = 0; i < N; i++) {
      stars.push({
        x: Math.random() * c.width,
        y: Math.random() * c.height,
        r: 0.3 + Math.random() * 0.8,
        a: 0.08 + Math.random() * 0.3,
        s: 0.03 + Math.random() * 0.04,
        p: Math.random() * 6.28,
      });
    }
    (function draw() {
      x.clearRect(0, 0, c.width, c.height);
      var t = Date.now() * 0.001;
      stars.forEach(function (s) {
        x.fillStyle =
          "rgba(180,220,255," + s.a * (0.5 + 0.5 * Math.sin(t * s.s * 10 + s.p)) + ")";
        x.beginPath();
        x.arc(s.x, s.y, s.r, 0, 6.28);
        x.fill();
      });
      requestAnimationFrame(draw);
    })();
  }

  var cfgEl = document.getElementById("ksc-cfg");
  var cfg = {};
  try {
    cfg = cfgEl ? JSON.parse(cfgEl.textContent || "{}") : {};
  } catch (e) {
    cfg = {};
  }
  var ptsRate = typeof cfg.ptsRate === "number" ? cfg.ptsRate : 100;
  var csrf = cfg.csrf || "";
  var apiUrl = cfg.apiUrl || "/api/support-credits/create_payment.php";
  var i18n = cfg.i18n || {};

  var amountInput = document.getElementById("ksc-amount");
  var kpPreview = document.getElementById("ksc-kp-preview");
  var submitBtn = document.getElementById("ksc-submit");
  var resultDiv = document.getElementById("ksc-result");
  var notesInput = document.getElementById("ksc-notes");

  var selectedAmount = 25;
  var selectedMethod = "paypal";

  function formatKp(n) {
    return Math.round(n).toLocaleString();
  }

  function syncKpPreview() {
    if (!amountInput || !kpPreview) return;
    var v = parseFloat(amountInput.value);
    if (isNaN(v) || v < 0) v = 0;
    kpPreview.textContent = formatKp(v * ptsRate);
  }

  function setActiveChip(amount) {
    document.querySelectorAll(".ksc-amount-chip").forEach(function (chip) {
      var a = parseFloat(chip.getAttribute("data-usd") || "0");
      chip.classList.toggle("active", Math.abs(a - amount) < 0.001);
    });
  }

  document.querySelectorAll(".ksc-amount-chip").forEach(function (chip) {
    chip.addEventListener("click", function () {
      var usd = parseFloat(chip.getAttribute("data-usd") || "0");
      selectedAmount = usd;
      if (amountInput) amountInput.value = String(usd);
      setActiveChip(usd);
      syncKpPreview();
    });
  });

  if (amountInput) {
    amountInput.addEventListener("input", function () {
      selectedAmount = parseFloat(this.value) || 0;
      setActiveChip(selectedAmount);
      syncKpPreview();
    });
    if (amountInput.value) {
      selectedAmount = parseFloat(amountInput.value) || 25;
      setActiveChip(selectedAmount);
    }
    syncKpPreview();
  }

  document.querySelectorAll(".ksc-method-card").forEach(function (card) {
    card.addEventListener("click", function () {
      document.querySelectorAll(".ksc-method-card").forEach(function (x) {
        x.classList.remove("active");
      });
      card.classList.add("active");
      selectedMethod = card.getAttribute("data-method") || "paypal";
    });
  });

  function animateBalances() {
    root.querySelectorAll(".ksc-bal-value").forEach(function (el) {
      var raw = el.getAttribute("data-target");
      if (raw === null || raw === "") return;
      var n = parseInt(raw, 10);
      if (isNaN(n) || n === 0) {
        el.textContent = (parseInt(raw, 10) || 0).toLocaleString();
        return;
      }
      el.textContent = "0";
      setTimeout(function () {
        var t0 = performance.now();
        (function tick() {
          var p = Math.min((performance.now() - t0) / 900, 1);
          el.textContent = Math.round(n * (1 - Math.pow(1 - p, 3))).toLocaleString();
          if (p < 1) requestAnimationFrame(tick);
        })();
      }, 200);
    });
  }
  animateBalances();

  function fillSuccessTemplate(tpl, points, dateStr, days) {
    if (!tpl) {
      return (
        "Submitted! " +
        points +
        " KP pending. Available after: " +
        dateStr +
        " (hold: " +
        days +
        " business days)"
      );
    }
    return tpl
      .replace(/\{points\}/g, String(points))
      .replace(/\{date\}/g, dateStr)
      .replace(/\{days\}/g, String(days));
  }

  function escapeHtml(s) {
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  if (submitBtn && resultDiv) {
    submitBtn.addEventListener("click", function () {
      var btn = submitBtn;
      var willReload = false;
      btn.disabled = true;
      btn.classList.remove("success");
      btn.textContent = i18n.processing || "Processing...";
      resultDiv.style.display = "none";
      resultDiv.innerHTML = "";

      var fd = new FormData();
      fd.append("method", selectedMethod);
      fd.append("amount_usd", String(selectedAmount));
      fd.append("notes", notesInput ? notesInput.value : "");
      fd.append("csrf_token", csrf);

      fetch(apiUrl, { method: "POST", body: fd })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (data.ok && data.data) {
            willReload = true;
            var d = data.data;
            var msg = fillSuccessTemplate(
              i18n.resultSuccess,
              d.pending_points,
              d.available_at,
              d.hold_days
            );
            resultDiv.innerHTML =
              '<div class="ksc-alert ksc-alert--success" role="status">' + escapeHtml(msg) + "</div>";
            resultDiv.style.display = "block";
            btn.classList.add("success");
            btn.textContent = "✓";
            setTimeout(function () {
              location.reload();
            }, 3000);
            return;
          }
          var err =
            (data.error && data.error.message) || (data.error && data.error.code) || "Error";
          resultDiv.innerHTML =
            '<div class="ksc-alert ksc-alert--error" role="alert">' + escapeHtml(String(err)) + "</div>";
          resultDiv.style.display = "block";
        })
        .catch(function () {
          resultDiv.innerHTML =
            '<div class="ksc-alert ksc-alert--error" role="alert">' +
            escapeHtml(i18n.networkError || "Network error. Try again.") +
            "</div>";
          resultDiv.style.display = "block";
        })
        .finally(function () {
          if (!willReload) {
            btn.disabled = false;
            btn.classList.remove("success");
            btn.textContent = i18n.submitDefault || "Submit";
          }
        });
    });
  }

  root
    .querySelectorAll(
      ".ksc-purchase-card, .ksc-info-card, .ksc-history-card, .ksc-bal-card"
    )
    .forEach(function (el, i) {
      el.style.opacity = "0";
      el.style.transform = "translateY(14px)";
      el.style.transition =
        "opacity .5s ease " + i * 0.05 + "s, transform .5s ease " + i * 0.05 + "s";
      new IntersectionObserver(
        function (entries) {
          if (entries[0].isIntersecting) {
            el.style.opacity = "1";
            el.style.transform = "translateY(0)";
          }
        },
        { threshold: 0.05 }
      ).observe(el);
    });
})();
