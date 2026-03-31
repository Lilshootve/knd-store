/**
 * KND fixed starfield — matches knd-home-concept.html canvas behavior.
 * No-op if #bg-canvas is missing (admin, lightweight, embeds).
 */
(function () {
  'use strict';

  function init() {
    var c = document.getElementById('bg-canvas');
    if (!c || !c.getContext) return;

    var ctx = c.getContext('2d');
    var stars = [];
    var count = 200;

    function resize() {
      c.width = window.innerWidth;
      c.height = window.innerHeight;
    }

    window.addEventListener('resize', resize);
    resize();

    for (var i = 0; i < count; i++) {
      stars.push({
        x: Math.random() * c.width,
        y: Math.random() * c.height,
        r: 0.3 + Math.random() * 1.2,
        a: 0.1 + Math.random() * 0.5,
        s: 0.02 + Math.random() * 0.04,
        p: Math.random() * Math.PI * 2,
      });
    }

    function draw() {
      ctx.clearRect(0, 0, c.width, c.height);
      var t = Date.now() * 0.001;
      for (var i = 0; i < stars.length; i++) {
        var s = stars[i];
        var alpha = s.a * (0.5 + 0.5 * Math.sin(t * s.s * 10 + s.p));
        ctx.fillStyle = 'rgba(180,220,255,' + alpha + ')';
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
        ctx.fill();
      }
      if (Math.random() < 0.002) {
        ctx.strokeStyle = 'rgba(0,232,255,.3)';
        ctx.lineWidth = 1;
        var sx = Math.random() * c.width;
        var sy = Math.random() * c.height * 0.4;
        ctx.beginPath();
        ctx.moveTo(sx, sy);
        ctx.lineTo(sx + 80, sy + 40);
        ctx.stroke();
      }
      requestAnimationFrame(draw);
    }

    draw();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
