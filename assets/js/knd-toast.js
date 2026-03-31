(function () {
    'use strict';
    var CONTAINER_ID = 'knd-toast-wrap';
    var DURATION = { success: 3500, info: 3500, warning: 3500, error: 6000 };

    function ensureContainer() {
        var wrap = document.getElementById(CONTAINER_ID);
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = CONTAINER_ID;
            wrap.className = 'knd-toast-wrap';
            document.body.appendChild(wrap);
        }
        return wrap;
    }

    window.kndToast = function (type, message, opts) {
        opts = opts || {};
        var wrap = ensureContainer();
        var duration = opts.duration !== undefined ? opts.duration : (DURATION[type] || 3500);
        var el = document.createElement('div');
        el.className = 'knd-toast knd-toast--' + type;
        el.setAttribute('role', 'alert');
        el.innerHTML = '<span class="knd-toast-msg"></span><button type="button" class="knd-toast-close" aria-label="Close">&times;</button>';
        el.querySelector('.knd-toast-msg').textContent = message;
        wrap.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('visible'); });
        function remove() {
            el.classList.remove('visible');
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 300);
        }
        el.querySelector('.knd-toast-close').addEventListener('click', remove);
        if (duration > 0) setTimeout(remove, duration);
    };
})();
