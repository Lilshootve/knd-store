/**
 * KND home — section reveals, portal stat count-up, avatar carousel drift.
 */
(function () {
    'use strict';

    function docReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    docReady(function () {
        initSectionFade();
        initPortalStatCount();
        initHomeAvatarTracks();
        initHomeAvatarNav();
    });

    function initSectionFade() {
        var sections = document.querySelectorAll('.knd-home-main .section');
        if (!sections.length) return;

        if (!('IntersectionObserver' in window)) {
            sections.forEach(function (s) {
                s.style.opacity = '1';
                s.style.transform = 'none';
            });
            return;
        }

        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        e.target.style.opacity = '1';
                        e.target.style.transform = 'translateY(0)';
                    }
                });
            },
            { threshold: 0.1 }
        );

        sections.forEach(function (s) {
            s.style.opacity = '0';
            s.style.transform = 'translateY(30px)';
            s.style.transition = 'opacity .8s ease, transform .8s ease';
            observer.observe(s);
        });
    }

    function initPortalStatCount() {
        document.querySelectorAll('.knd-home-main .portal-stat-val').forEach(function (el) {
            var text = el.textContent.trim();
            var num = parseFloat(text.replace(/[^0-9.]/g, ''));
            if (isNaN(num)) return;
            var suffix = text.replace(/[0-9.,]/g, '');
            var obs = new IntersectionObserver(
                function (entries) {
                    if (!entries[0].isIntersecting) return;
                    obs.disconnect();
                    var t0 = performance.now();
                    function tick() {
                        var p = Math.min((performance.now() - t0) / 1000, 1);
                        var ease = 1 - Math.pow(1 - p, 3);
                        var v = num * ease;
                        el.textContent =
                            (v >= 100 ? Math.round(v).toLocaleString() : v.toFixed(1)) + suffix;
                        if (p < 1) {
                            requestAnimationFrame(tick);
                        } else {
                            el.textContent = text;
                        }
                    }
                    tick();
                },
                { threshold: 0.3 }
            );
            obs.observe(el);
        });
    }

    /** Auto-drift for Mind Wars avatar card lanes (legendary + epic). */
    function initHomeAvatarTracks() {
        var tracks = document.querySelectorAll('.knd-home-auto-scroll');
        if (!tracks.length) return;

        var paused = [];
        var i;
        for (i = 0; i < tracks.length; i++) {
            paused[i] = false;
            (function (idx, el) {
                el.addEventListener('mouseenter', function () {
                    paused[idx] = true;
                });
                el.addEventListener('mouseleave', function () {
                    paused[idx] = false;
                });
            })(i, tracks[i]);
        }

        var speed = 0.45;
        function tick() {
            for (i = 0; i < tracks.length; i++) {
                if (paused[i]) continue;
                var el = tracks[i];
                if (el.scrollWidth <= el.clientWidth) continue;
                el.scrollLeft += speed;
                if (el.scrollLeft >= el.scrollWidth - el.clientWidth - 1) {
                    el.scrollLeft = 0;
                }
            }
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    function initHomeAvatarNav() {
        document.querySelectorAll('[data-knd-home-avatar-nav]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-knd-home-avatar-track');
                var track = id ? document.getElementById(id) : null;
                if (!track) return;
                var dir = btn.getAttribute('data-knd-home-avatar-nav') === 'prev' ? -1 : 1;
                var step = Math.min(300, Math.max(200, track.clientWidth * 0.72));
                track.scrollLeft += dir * step;
            });
        });
    }
})();
