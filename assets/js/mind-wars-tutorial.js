/**
 * Mind Wars - Interactive tutorial for first-time visitors
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'mindWarsTutorialDone';

  function isDone() {
    try {
      return localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function markDone() {
    try {
      localStorage.setItem(STORAGE_KEY, '1');
    } catch (e) {}
  }

  function createOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'mw-tutorial-overlay';
    overlay.className = 'mw-tutorial-overlay';
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML =
      '<div class="mw-tutorial-backdrop"></div>' +
      '<div class="mw-tutorial-card">' +
        '<div class="mw-tutorial-content"></div>' +
        '<div class="mw-tutorial-actions">' +
          '<button type="button" class="btn btn-sm btn-outline-light mw-tutorial-skip">Skip tutorial</button>' +
          '<button type="button" class="btn btn-neon-primary mw-tutorial-next">Next</button>' +
        '</div>' +
      '</div>';
    return overlay;
  }

  const STEPS = [
    {
      title: 'Welcome to Mind Wars',
      body: 'Turn-based combat with your avatars. Use Mind, Focus, Speed, and Luck to defeat the enemy.',
      highlight: null,
      tip: 'Click Next to continue.',
    },
    {
      title: 'Choose your avatar',
      body: 'Select your fighter from your collection. Each avatar has unique stats and abilities.',
      highlight: '#mw-current-duelist-card',
      tip: 'Click "Select Avatar" to pick your fighter.',
    },
    {
      title: 'Stats: Mind, Focus, Speed, Luck',
      body: 'Mind increases damage. Focus reduces incoming damage. Speed affects turn order. Luck increases critical hits.',
      highlight: '#mw-stats-preview',
      tip: 'Hover over stats for details.',
    },
    {
      title: 'Combat actions',
      body: 'During battle you\'ll use: Attack (basic hit), Defend (reduce damage), Ability (3-turn cooldown), Special (requires 5 Energy).',
      highlight: '#mw-start-battle-btn',
      tip: 'Energy builds each turn. Save Special for decisive moments!',
    },
    {
      title: 'Ready to fight!',
      body: 'Select your avatar and click Start Battle (PvE) or Find Match (PvP). Good luck!',
      highlight: '#mw-start-battle-btn',
      tip: 'Click when ready to begin.',
    },
  ];

  let currentStep = 0;
  let overlayEl = null;
  let highlightEl = null;

  function showStep(step) {
    if (!overlayEl) return;
    const content = overlayEl.querySelector('.mw-tutorial-content');
    const nextBtn = overlayEl.querySelector('.mw-tutorial-next');
    if (!content || !nextBtn) return;

    content.innerHTML = '<h4 class="mb-2">' + (step.title || '') + '</h4><p class="text-white-50 mb-0">' + (step.body || '') + '</p>';
    if (step.tip) {
      content.innerHTML += '<p class="small mt-2 mb-0 text-info">' + step.tip + '</p>';
    }

    nextBtn.textContent = currentStep >= STEPS.length - 1 ? 'Got it!' : 'Next';

    removeHighlight();
    if (step.highlight) {
      highlightEl = document.querySelector(step.highlight);
      if (highlightEl) {
        highlightEl.classList.add('mw-tutorial-highlight');
        highlightEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  }

  function removeHighlight() {
    if (highlightEl) {
      highlightEl.classList.remove('mw-tutorial-highlight');
      highlightEl = null;
    }
  }

  function closeTutorial() {
    removeHighlight();
    if (overlayEl && overlayEl.parentNode) {
      overlayEl.parentNode.removeChild(overlayEl);
    }
    overlayEl = null;
    markDone();
  }

  function nextStep() {
    currentStep += 1;
    if (currentStep >= STEPS.length) {
      closeTutorial();
      return;
    }
    showStep(STEPS[currentStep]);
  }

  function startTutorial() {
    if (isDone()) return;
    overlayEl = createOverlay();
    document.body.appendChild(overlayEl);

    overlayEl.querySelector('.mw-tutorial-backdrop').addEventListener('click', closeTutorial);
    overlayEl.querySelector('.mw-tutorial-skip').addEventListener('click', closeTutorial);
    overlayEl.querySelector('.mw-tutorial-next').addEventListener('click', nextStep);

    currentStep = 0;
    showStep(STEPS[0]);
  }

  window.MindWarsTutorial = {
    start: startTutorial,
    reset: function () {
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch (e) {}
    },
  };
})();
