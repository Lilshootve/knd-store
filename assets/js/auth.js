/**
 * KND Access page — tabs, password toggles, strength, 6-digit codes, starfield
 */
(function () {
  'use strict';

  function initAuthStars() {
    var starsEl = document.getElementById('knd-access-stars');
    if (!starsEl) return;
    var i;
    for (i = 0; i < 60; i++) {
      var s = document.createElement('div');
      s.className = 'knd-access-star';
      s.style.cssText =
        'left:' +
        Math.random() * 100 +
        '%;top:' +
        Math.random() * 100 +
        '%;--d:' +
        (2 + Math.random() * 4) +
        's;--dl:' +
        Math.random() * 3 +
        's;--p:' +
        (0.3 + Math.random() * 0.6) +
        ';width:' +
        (1 + Math.random() * 2) +
        'px;height:' +
        (1 + Math.random() * 2) +
        'px;';
      starsEl.appendChild(s);
    }
  }

  function showAuthTab(name) {
    var loginRadio = document.getElementById('knd-auth-tab-login');
    var regRadio = document.getElementById('knd-auth-tab-register');
    if (name === 'register') {
      if (regRadio) regRadio.checked = true;
    } else {
      if (loginRadio) loginRadio.checked = true;
    }
  }

  window.kndAuthShowTab = showAuthTab;

  function getPasswordStrength(p) {
    if (!p || p.length === 0) return { level: 0, label: '' };
    var len = p.length;
    var hasUpper = /[A-Z]/.test(p);
    var hasLower = /[a-z]/.test(p);
    var hasNum = /\d/.test(p);
    var hasSym = /[^A-Za-z0-9]/.test(p);
    var count = (hasUpper ? 1 : 0) + (hasLower ? 1 : 0) + (hasNum ? 1 : 0) + (hasSym ? 1 : 0);
    if (len < 8) return { level: 1, label: 'Weak' };
    if (count >= 4 || (count >= 3 && len >= 10)) return { level: 4, label: 'Strong' };
    if (count >= 2) return { level: 3, label: 'Good' };
    return { level: 2, label: 'Fair' };
  }

  var segClass = ['', 'weak', 'weak', 'medium', 'strong'];

  function updateStrengthVisual(inputId, labelId) {
    var input = document.getElementById(inputId);
    var label = document.getElementById(labelId);
    if (!input) return;
    var wrap = input.closest('.knd-access-field');
    if (!wrap) return;
    var segs = wrap.querySelectorAll('.knd-access-pwd-str-seg');
    var s = getPasswordStrength(input.value);
    var cls = segClass[s.level] || '';
    var i;
    for (i = 0; i < segs.length; i++) {
      segs[i].className = 'knd-access-pwd-str-seg';
      if (s.level > 0 && i < s.level) {
        segs[i].classList.add(cls);
      }
    }
    if (label) {
      label.textContent = s.label;
      if (document.body.classList.contains('knd-auth-split-page')) {
        var splitColors = ['#6b6b6b', '#dc2626', '#ca8a04', '#ca8a04', '#15803d'];
        label.style.color = splitColors[s.level] || '#6b6b6b';
      } else if (s.level === 0) {
        label.style.color = 'var(--ka-t4)';
      } else if (s.level === 1) {
        label.style.color = 'var(--ka-red)';
      } else if (s.level === 2) {
        label.style.color = 'var(--ka-gold)';
      } else if (s.level === 3) {
        label.style.color = 'var(--ka-gold)';
      } else {
        label.style.color = 'var(--ka-green)';
      }
    }
  }

  function initPasswordStrengthSegments() {
    [['reg-password', 'reg-pwd-str-label'], ['reset-password', 'reset-pwd-str-label']].forEach(function (pair) {
      var input = document.getElementById(pair[0]);
      if (!input) return;
      function go() {
        updateStrengthVisual(pair[0], pair[1]);
      }
      input.addEventListener('input', go);
      input.addEventListener('blur', go);
      go();
    });
  }

  function kndTogglePasswordForButton(btn) {
    if (!btn || !btn.getAttribute) return;
    var id = btn.getAttribute('data-knd-toggle-pwd');
    var inp = id ? document.getElementById(id) : null;
    if (!inp) return;
    var showL = btn.getAttribute('data-label-show') || 'Show';
    var hideL = btn.getAttribute('data-label-hide') || 'Hide';
    var isPwd = inp.type === 'password' || (inp.getAttribute('type') || '').toLowerCase() === 'password';
    var next = isPwd ? 'text' : 'password';
    try {
      inp.type = next;
    } catch (err) {
      inp.setAttribute('type', next);
    }
    if (next === 'text') {
      btn.textContent = hideL;
      btn.setAttribute('aria-pressed', 'true');
    } else {
      btn.textContent = showL;
      btn.setAttribute('aria-pressed', 'false');
    }
  }

  /** Inline fallback / embed shells: keeps working even if init order changes */
  window.kndAuthTogglePasswordBtn = function (btn) {
    kndTogglePasswordForButton(btn);
    return false;
  };

  function bindPasswordToggleButtons() {
    document.querySelectorAll('.knd-access-pwd-toggle[data-knd-toggle-pwd]:not([data-knd-pwd-bound])').forEach(function (btn) {
      btn.setAttribute('data-knd-pwd-bound', '1');
      function onPtr(e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        /* Do not preventDefault on pointerdown — that suppresses the synthetic click in some UAs. */
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        btn.__kndPwdPtr = true;
        kndTogglePasswordForButton(btn);
      }
      function onClick(e) {
        e.preventDefault();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        if (btn.__kndPwdPtr) {
          btn.__kndPwdPtr = false;
          return;
        }
        kndTogglePasswordForButton(btn);
      }
      if (window.PointerEvent) {
        btn.addEventListener('pointerdown', onPtr, { capture: true, passive: true });
      } else {
        btn.addEventListener('mousedown', onPtr, { capture: true, passive: true });
        btn.addEventListener(
          'touchstart',
          function (e) {
            if (e.touches.length !== 1) return;
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            btn.__kndPwdPtr = true;
            kndTogglePasswordForButton(btn);
          },
          { capture: true, passive: true }
        );
      }
      btn.addEventListener('click', onClick, { capture: true, passive: false });
    });
  }

  window.kndAuthBindPasswordToggles = bindPasswordToggleButtons;

  function syncCodeWrap(wrap) {
    if (!wrap) return;
    var hiddenId = wrap.getAttribute('data-knd-code-hidden');
    var hidden = hiddenId ? document.getElementById(hiddenId) : null;
    if (!hidden) return;
    var digits = wrap.querySelectorAll('.knd-access-code-digit');
    var code = '';
    digits.forEach(function (d) {
      code += (d.value || '').replace(/\D/g, '').slice(0, 1);
    });
    hidden.value = code;
  }

  function initCodeWrap(wrap) {
    if (!wrap) return;
    var inputs = wrap.querySelectorAll('.knd-access-code-digit');
    inputs.forEach(function (inp, idx) {
      inp.addEventListener('input', function () {
        var v = (inp.value || '').replace(/\D/g, '');
        if (v.length > 1) v = v.slice(0, 1);
        inp.value = v;
        syncCodeWrap(wrap);
        if (v.length === 1 && idx < inputs.length - 1) {
          inputs[idx + 1].focus();
        }
      });
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && inp.value === '' && idx > 0) {
          inputs[idx - 1].focus();
        }
      });
      inp.addEventListener('paste', function (e) {
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text') || '';
        var digitsOnly = text.replace(/\D/g, '').slice(0, inputs.length);
        var j;
        for (j = 0; j < inputs.length; j++) {
          inputs[j].value = digitsOnly[j] || '';
        }
        syncCodeWrap(wrap);
        var next = Math.min(digitsOnly.length, inputs.length - 1);
        inputs[next].focus();
      });
    });
    syncCodeWrap(wrap);
  }

  function initCodeWraps() {
    document.querySelectorAll('.knd-access-code-wrap[data-knd-code-hidden]').forEach(initCodeWrap);
  }

  function syncAllCodeHiddens() {
    document.querySelectorAll('.knd-access-code-wrap[data-knd-code-hidden]').forEach(syncCodeWrap);
  }

  window.kndAuthSyncAllCodeHiddens = syncAllCodeHiddens;

  function clearCodeWrap(wrapId) {
    var wrap = document.getElementById(wrapId);
    if (!wrap) return;
    wrap.querySelectorAll('.knd-access-code-digit').forEach(function (d) {
      d.value = '';
    });
    syncCodeWrap(wrap);
  }

  window.kndAuthClearCodeWrap = clearCodeWrap;

  function init() {
    initAuthStars();
    bindPasswordToggleButtons();
    initPasswordStrengthSegments();
    initCodeWraps();
  }

  bindPasswordToggleButtons();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.kndAuthSetButtonLoading = function (btn, loading) {
    if (!btn) return;
    if (loading) {
      btn.classList.add('auth-btn--loading');
      btn.disabled = true;
    } else {
      btn.classList.remove('auth-btn--loading');
      btn.disabled = false;
    }
  };
})();
