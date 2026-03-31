/**
 * KND Contact: starfield, scroll reveal, sales hours (GMT-4).
 */
(function () {
  var root = document.querySelector(".knd-contact-page");
  if (!root) {
    return;
  }

  var c = document.getElementById("contact-bg-canvas");
  if (c && c.getContext) {
    var x = c.getContext("2d");
    var stars = [];
    var N = 120;
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
        r: 0.3 + Math.random() * 1,
        a: 0.1 + Math.random() * 0.4,
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

  root
    .querySelectorAll(".kc-form-card, .kc-channel, .kc-hours-card, .kc-urgent-card")
    .forEach(function (el, i) {
      el.style.opacity = "0";
      el.style.transform = "translateY(16px)";
      el.style.transition =
        "opacity .5s ease " + i * 0.08 + "s, transform .5s ease " + i * 0.08 + "s";
      new IntersectionObserver(
        function (entries) {
          if (entries[0].isIntersecting) {
            el.style.opacity = "1";
            el.style.transform = "translateY(0)";
          }
        },
        { threshold: 0.1 }
      ).observe(el);
    });

  var salesDot = document.getElementById("kc-sales-dot");
  var salesTime = document.getElementById("kc-sales-time");
  if (salesDot && salesTime) {
    var onlineText = salesTime.getAttribute("data-online") || "ONLINE NOW";
    var offlineText = salesTime.getAttribute("data-offline") || "OFFLINE — OPENS 10:00";
    var schedText = salesTime.getAttribute("data-sched") || "";
    var h = new Date().getUTCHours() - 4;
    if (h < 0) {
      h += 24;
    }
    var isOpen = h >= 10 && h < 20;
    if (!isOpen) {
      salesDot.className = "kc-status-dot off";
      salesTime.textContent = offlineText;
      salesTime.className = "kc-hr-time sched";
    } else {
      salesDot.className = "kc-status-dot on";
      salesTime.textContent = onlineText;
      salesTime.className = "kc-hr-time live";
    }
    if (schedText && !isOpen) {
      salesTime.setAttribute("title", schedText);
    }
  }
})();
