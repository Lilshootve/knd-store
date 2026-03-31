/**
 * KND Ecosystem page: starfield canvas, scroll reveal, section pills, apparel chips.
 */
(function () {
  var root = document.querySelector(".knd-ecosystem");
  if (!root) {
    return;
  }

  var c = document.getElementById("eco-bg-canvas");
  if (c && c.getContext) {
    var x = c.getContext("2d");
    var stars = [];
    var N = 160;
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

  root.querySelectorAll(".eco-section, .cta-banner").forEach(function (s) {
    s.style.opacity = "0";
    s.style.transform = "translateY(28px)";
    s.style.transition = "opacity .7s ease,transform .7s ease";
    new IntersectionObserver(
      function (entries) {
        if (entries[0].isIntersecting) {
          s.style.opacity = "1";
          s.style.transform = "translateY(0)";
        }
      },
      { threshold: 0.08 }
    ).observe(s);
  });

  var pills = root.querySelectorAll(".eco-pill");
  var sections = {
    labs: document.getElementById("eco-labs"),
    services: document.getElementById("eco-services"),
    custom: document.getElementById("eco-custom"),
    apparel: document.getElementById("eco-apparel"),
  };

  function setActivePill(key) {
    pills.forEach(function (p) {
      p.classList.remove("active");
    });
    var el = root.querySelector('[data-s="' + key + '"]');
    if (el) {
      el.classList.add("active");
    }
  }

  window.addEventListener("scroll", function () {
    var st = window.scrollY + 200;
    if (sections.apparel && st >= sections.apparel.offsetTop) {
      setActivePill("apparel");
    } else if (sections.custom && st >= sections.custom.offsetTop) {
      setActivePill("custom");
    } else if (sections.services && st >= sections.services.offsetTop) {
      setActivePill("services");
    } else {
      setActivePill("labs");
    }
  });

  root.querySelectorAll(".apparel-chip").forEach(function (chip) {
    chip.addEventListener("click", function () {
      var icon = chip.dataset.icon;
      var name = chip.dataset.name;
      var el = document.getElementById("eco-product-icon");
      var lbl = document.getElementById("eco-product-label");
      if (!el || !lbl) {
        return;
      }
      el.style.transform = "scale(0)";
      setTimeout(function () {
        el.textContent = icon;
        lbl.textContent = name;
        el.style.transform = "scale(1)";
        el.style.transition = "transform .3s cubic-bezier(.15,1.2,.3,1)";
      }, 200);
      root.querySelectorAll(".apparel-chip").forEach(function (ch) {
        ch.style.borderColor = "rgba(255,204,0,.15)";
        ch.style.color = "var(--t2)";
      });
      chip.style.borderColor = "rgba(255,204,0,.4)";
      chip.style.color = "var(--gold)";
    });
  });

  root.querySelectorAll(".lab-card").forEach(function (card, i) {
    card.style.animationDelay = i * 0.08 + "s";
  });
})();
