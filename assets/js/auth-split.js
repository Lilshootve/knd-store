/**
 * KND Store — split auth: client-side validation before fetch handlers run.
 * Loaded after auth.js; uses capture phase so it runs before auth.php inline listeners.
 */
(function () {
  'use strict';

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function showAuthMainAlert(msg) {
    var el = document.getElementById('auth-alert');
    if (!el) return;
    el.textContent = msg;
    el.className = 'knd-access-alert error show';
    el.style.display = 'block';
  }

  function clearInputError(id) {
    var inp = document.getElementById(id);
    if (inp) inp.classList.remove('knd-auth-input--error');
  }

  function setInputError(id) {
    var inp = document.getElementById(id);
    if (inp) inp.classList.add('knd-auth-input--error');
  }

  function validateLogin(e) {
    var email = document.getElementById('login-email');
    var pwd = document.getElementById('login-password');
    if (!email || !pwd) return;
    clearInputError('login-email');
    clearInputError('login-password');
    var ev = email.value.trim();
    if (!ev || !EMAIL_RE.test(ev)) {
      e.preventDefault();
      e.stopImmediatePropagation();
      setInputError('login-email');
      showAuthMainAlert('Please enter a valid email address.');
      return;
    }
    if (!pwd.value || pwd.value.length < 8) {
      e.preventDefault();
      e.stopImmediatePropagation();
      setInputError('login-password');
      showAuthMainAlert('Password must be at least 8 characters.');
      return;
    }
  }

  function validateRegister(e) {
    var email = document.getElementById('reg-email');
    var p1 = document.getElementById('reg-password');
    var p2 = document.getElementById('reg-password-confirm');
    var user = document.getElementById('reg-username');
    if (!email || !p1 || !p2 || !user) return;
    clearInputError('reg-email');
    clearInputError('reg-password');
    clearInputError('reg-password-confirm');
    clearInputError('reg-username');
    var ev = email.value.trim();
    if (!ev || !EMAIL_RE.test(ev)) {
      e.preventDefault();
      e.stopImmediatePropagation();
      setInputError('reg-email');
      showAuthMainAlert('Please enter a valid email address.');
      return;
    }
    if (!user.value.trim() || user.value.trim().length < 3) {
      e.preventDefault();
      e.stopImmediatePropagation();
      setInputError('reg-username');
      showAuthMainAlert('Username must be at least 3 characters.');
      return;
    }
    if (!p1.value || p1.value.length < 8) {
      e.preventDefault();
      e.stopImmediatePropagation();
      setInputError('reg-password');
      showAuthMainAlert('Password must be at least 8 characters.');
      return;
    }
    if (p1.value !== p2.value) {
      e.preventDefault();
      e.stopImmediatePropagation();
      setInputError('reg-password-confirm');
      showAuthMainAlert('Passwords do not match.');
      return;
    }
  }

  function init() {
    if (!document.body.classList.contains('knd-auth-split-page')) return;
    var loginForm = document.getElementById('form-login');
    if (loginForm) loginForm.addEventListener('submit', validateLogin, true);
    var regForm = document.getElementById('form-register');
    if (regForm) regForm.addEventListener('submit', validateRegister, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
