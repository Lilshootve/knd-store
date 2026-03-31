/**
 * KND Arena - Legendary laser rays (Legendary Drop only). No external libs.
 * Full-page rays falling from top to bottom, KND neon style.
 */
(function () {
    'use strict';

    var COLORS = [
        { fill: '#00d4ff', glow: 'rgba(0, 212, 255, 0.8)' },
        { fill: '#a78bfa', glow: 'rgba(167, 139, 250, 0.8)' },
        { fill: '#ecc94b', glow: 'rgba(236, 201, 75, 0.8)' },
        { fill: '#ffffff', glow: 'rgba(255, 255, 255, 0.6)' }
    ];

    window.kndConfetti = function (opts) {
        opts = opts || {};
        var duration = opts.duration || 2200;
        var w = window.innerWidth;
        var h = window.innerHeight;
        var rayCount = opts.count || 28;

        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        canvas.style.cssText = 'position:fixed; inset:0; z-index:9996; pointer-events:none;';
        document.body.appendChild(canvas);

        var ctx = canvas.getContext('2d');
        var rays = [];
        for (var i = 0; i < rayCount; i++) {
            var c = COLORS[Math.floor(Math.random() * COLORS.length)];
            var len = 60 + Math.random() * 120;
            var thick = 2 + Math.random() * 3;
            var angle = (Math.random() - 0.5) * 0.4;
            rays.push({
                x: Math.random() * (w + 80) - 40,
                y: -len - Math.random() * 200,
                len: len,
                thick: thick,
                angle: angle,
                vy: 3 + Math.random() * 4,
                vx: (Math.random() - 0.5) * 1.5,
                color: c,
                opacity: 0.6 + Math.random() * 0.4
            });
        }

        var start = Date.now();
        function tick() {
            var elapsed = Date.now() - start;
            if (elapsed >= duration) {
                if (canvas.parentNode) document.body.removeChild(canvas);
                return;
            }
            ctx.clearRect(0, 0, w, h);
            var progress = elapsed / duration;
            var fadeOut = progress > 0.7 ? 1 - (progress - 0.7) / 0.3 : 1;

            for (var i = 0; i < rays.length; i++) {
                var r = rays[i];
                r.y += r.vy;
                r.x += r.vx;

                var alpha = r.opacity * fadeOut;
                if (r.y < -r.len) alpha *= 0.3;
                if (r.y > h + r.len) continue;

                ctx.save();
                ctx.translate(r.x, r.y);
                ctx.rotate(r.angle);

                var g = ctx.createLinearGradient(0, -r.len / 2, 0, r.len / 2);
                g.addColorStop(0, 'transparent');
                g.addColorStop(0.2, r.color.glow);
                g.addColorStop(0.5, r.color.fill);
                g.addColorStop(0.8, r.color.glow);
                g.addColorStop(1, 'transparent');

                ctx.shadowBlur = 20;
                ctx.shadowColor = r.color.fill;
                ctx.globalAlpha = alpha;
                ctx.fillStyle = g;
                ctx.fillRect(-r.thick / 2, -r.len / 2, r.thick, r.len);
                ctx.globalAlpha = 1;
                ctx.shadowBlur = 0;
                ctx.restore();
            }
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    };
})();
