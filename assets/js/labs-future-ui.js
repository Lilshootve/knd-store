/**
 * KND Labs Future UI - Microinteracciones
 * Chips, toggles, collapse, states.
 */
(function() {
  'use strict';

  function init() {
    initChips();
    initToggles();
    initCollapse();
    initAspectButtons();
  }

  function initChips() {
    document.querySelectorAll('.lfu-chip[data-prompt]').forEach(function(chip) {
      chip.addEventListener('click', function() {
        var group = this.closest('.lfu-chip-group');
        if (group) group.querySelectorAll('.lfu-chip').forEach(function(c) { c.classList.remove('active'); });
        this.classList.add('active');
        var prompt = this.getAttribute('data-prompt');
        var ta = document.getElementById('lfu-prompt-input');
        if (ta && prompt) ta.value = prompt;
      });
    });
  }

  function initToggles() {
    document.querySelectorAll('.lfu-toggle-switch').forEach(function(sw) {
      sw.addEventListener('click', function() {
        this.classList.toggle('active');
      });
    });
  }

  function initCollapse() {
    document.querySelectorAll('.lfu-collapse-trigger[data-target]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var id = this.getAttribute('data-target');
        var target = id ? document.getElementById(id) : null;
        if (!target) return;
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        target.style.display = expanded ? 'none' : 'block';
      });
    });
  }

  function initAspectButtons() {
    document.querySelectorAll('.lfu-aspect-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var group = this.closest('.lfu-aspect-group');
        if (group) group.querySelectorAll('.lfu-aspect-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
