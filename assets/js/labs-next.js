/**
 * KND Labs Next - App behavior: tool switching, no full reload.
 * Scoped to .ln-app; does not affect other pages.
 */
(function () {
  'use strict';

  var app = document.getElementById('ln-app');
  if (!app) return;

  if (document.body) {
    document.body.classList.add('ln-page', 'knd-labs-next');
  }

  var sidebar = document.getElementById('ln-sidebar');
  var editorLayout = document.getElementById('ln-editor-layout');
  var characterLayout = document.getElementById('ln-character-layout');
  var recentTrack = document.getElementById('ln-recent-track');

  var toolButtons = app.querySelectorAll('.ln-tool[data-tool]');
  var views = app.querySelectorAll('.ln-view[data-tool]');

  var currentTool = 'text2img';

  function setActiveTool(toolId) {
    currentTool = toolId || 'text2img';

    toolButtons.forEach(function (btn) {
      var id = btn.getAttribute('data-tool');
      if (id === currentTool) {
        btn.classList.add('ln-tool-active');
        btn.setAttribute('aria-current', 'true');
      } else {
        btn.classList.remove('ln-tool-active');
        btn.removeAttribute('aria-current');
      }
    });

    views.forEach(function (view) {
      var id = view.getAttribute('data-tool');
      if (id === currentTool) {
        view.classList.add('ln-view-visible');
      } else {
        view.classList.remove('ln-view-visible');
      }
    });

    if (currentTool === 'character') {
      if (editorLayout) editorLayout.hidden = true;
      if (characterLayout) characterLayout.hidden = false;
    } else {
      if (characterLayout) characterLayout.hidden = true;
      if (editorLayout) editorLayout.hidden = false;
    }
  }

  function initSidebar() {
    if (!toolButtons.length) return;
    toolButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tool = btn.getAttribute('data-tool');
        if (tool) setActiveTool(tool);
      });
    });
  }

  function initHash() {
    var hash = (window.location.hash || '').replace(/^#/, '');
    var allowed = ['text2img', 'upscale', 'consistency', 'texture', '3d', 'character'];
    if (hash && allowed.indexOf(hash) !== -1) {
      setActiveTool(hash);
    }
  }

  if (sidebar && editorLayout && characterLayout) {
    initSidebar();
    initHash();
  }

  window.addEventListener('hashchange', initHash);
})();
