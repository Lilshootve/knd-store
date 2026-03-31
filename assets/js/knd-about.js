/**
 * KND About page: starfield + scroll reveal.
 */
(function () {
  var root = document.querySelector(".knd-about-page");
  if (!root) {
    return;
  }

  var c = document.getElementById("about-bg-canvas");
  if (c && c.getContext) {
    var x = c.getContext("2d");
    var stars = [];
    var N = 150;
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

  root.querySelectorAll(".ab-sect, .ab-mission, .ab-future, .ab-cta").forEach(function (s) {
    s.style.opacity = "0";
    s.style.transform = "translateY(24px)";
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
})();
