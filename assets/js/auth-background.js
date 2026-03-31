/**
 * KND Store - Auth page starfield background
 * Vanilla JS animated starfield (no external libs)
 */
(function () {
  'use strict';

  var canvas = document.getElementById('auth-starfield-canvas');
  if (!canvas) return;

  var ctx = canvas.getContext('2d');
  if (!ctx) return;

  var stars = [];
  var starCount = 80;
  var isMobile = window.matchMedia('(max-width: 767.98px)').matches;
  if (isMobile) starCount = 40;

  function resize() {
    var w = window.innerWidth;
    var h = window.innerHeight;
    canvas.width = w;
    canvas.height = h;
    initStars();
  }

  function initStars() {
    stars = [];
    var count = window.matchMedia('(max-width: 767.98px)').matches ? 40 : 80;
    for (var i = 0; i < count; i++) {
      stars.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: 0.5 + Math.random() * 1.5,
        speed: 0.02 + Math.random() * 0.04,
        twinklePhase: Math.random() * Math.PI * 2,
        twinkleSpeed: 0.02 + Math.random() * 0.03,
      });
    }
  }

  function draw() {
    if (!ctx || !canvas.width || !canvas.height) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    var t = Date.now() * 0.001;
    for (var i = 0; i < stars.length; i++) {
      var s = stars[i];
      s.y -= s.speed;
      if (s.y < 0) s.y = canvas.height;
      s.twinklePhase += s.twinkleSpeed;
      var alpha = 0.3 + 0.5 * (0.5 + 0.5 * Math.sin(s.twinklePhase));
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(0, 212, 255, ' + alpha + ')';
      ctx.fill();
    }
  }

  function loop() {
    draw();
    requestAnimationFrame(loop);
  }

  window.addEventListener('resize', resize);
  resize();
  loop();
})();
