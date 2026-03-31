<?php
/**
 * KND Labs Workspace Demo - Text to Image (3-column layout)
 * Herramienta premium tipo workspace. Vista aislada.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

$extraCss = '';
echo generateHeader('Canvas | KND Labs Demo', 'Main AI creation workspace', $extraCss);
?>
<?php echo generateNavigation(); ?>

<div class="knd-demo-shell">
  <main class="py-5">
    <div class="container">
      <nav class="knd-muted small mb-2">
        <a href="/index.php" class="text-decoration-none" style="color:var(--knd-accent-soft);">Home</a> / <a href="/knd-labs.php" class="text-decoration-none" style="color:var(--knd-accent-soft);">Labs</a> / <span>Canvas</span>
      </nav>
      <h1 class="text-white mb-1" style="font-size:1.75rem;">Canvas</h1>
      <p class="knd-muted mb-4">Create epic visuals from text prompts</p>

      <div class="knd-workspace">
        <!-- Left: Settings -->
        <aside class="knd-panel">
          <div class="knd-section-title">Prompt</div>
          <textarea class="knd-textarea mb-3" rows="3" placeholder="Describe your vision...">Ultra realistic portrait, professional lighting</textarea>
          <div class="knd-section-title">Presets</div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="knd-chip">Game</button>
            <button type="button" class="knd-chip is-active">Realistic</button>
            <button type="button" class="knd-chip">Anime</button>
            <button type="button" class="knd-chip">Cyberpunk</button>
          </div>
          <div class="knd-divider"></div>
          <label class="knd-label">Negative prompt</label>
          <input type="text" class="knd-input mb-3" value="ugly, blurry, low quality">
          <label class="knd-label">Aspect ratio</label>
          <select class="knd-select mb-3">
            <option>1:1</option>
            <option selected>16:9</option>
            <option>9:16</option>
          </select>
          <label class="knd-label">Quality</label>
          <select class="knd-select mb-3">
            <option>Standard</option>
            <option>High</option>
          </select>
          <label class="knd-label">Model</label>
          <select class="knd-select mb-3">
            <option>Juggernaut XL V8</option>
            <option>SDXL Base</option>
          </select>
          <details class="mt-3">
            <summary class="knd-muted small" style="cursor:pointer;">Advanced</summary>
            <div class="mt-2">
              <label class="knd-label">Steps</label>
              <input type="number" class="knd-input mb-2" value="28">
              <label class="knd-label">CFG</label>
              <input type="number" class="knd-input" value="7">
            </div>
          </details>
        </aside>

        <!-- Center: Canvas -->
        <div class="d-flex flex-column">
          <div class="knd-canvas knd-panel--active flex-grow-1 mb-3">
            <div class="knd-canvas__empty">
              <i class="fas fa-wand-magic-sparkles"></i>
              <p>Your result will appear here</p>
            </div>
          </div>
          <div class="text-center">
            <button type="button" class="knd-btn-primary" id="knd-gen-btn"><i class="fas fa-bolt me-2"></i>Generate</button>
          </div>
        </div>

        <!-- Right: History / Info -->
        <aside class="knd-panel">
          <div class="knd-section-title">Credits</div>
          <p class="text-white mb-3"><strong>8,866</strong> KP</p>
          <button class="knd-btn-secondary w-100 mb-4">+ Add Credits</button>
          <div class="knd-divider"></div>
          <div class="knd-section-title">Generation History</div>
          <div class="d-flex justify-content-between align-items-center py-2">
            <span class="knd-muted small">Today, 10:25</span>
            <span class="knd-badge knd-badge--success">Done</span>
          </div>
          <div class="knd-divider"></div>
          <div class="d-flex justify-content-between align-items-center py-2">
            <span class="knd-muted small">Yesterday, 23:40</span>
            <span class="knd-badge knd-badge--success">Done</span>
          </div>
          <div class="knd-divider"></div>
          <div class="d-flex justify-content-between align-items-center py-2">
            <span class="knd-muted small">Mar 20, 14:15</span>
            <span class="knd-badge knd-badge--warning">Processing</span>
          </div>
        </aside>
      </div>

      <!-- Mini gallery -->
      <div class="mt-5">
        <div class="knd-section-title mb-3">Recent Creations</div>
        <div class="knd-card-grid">
          <?php for ($i = 0; $i < 4; $i++): ?>
          <div class="knd-showcase-card">
            <div class="knd-showcase-card__img"><i class="fas fa-image"></i></div>
            <div class="knd-showcase-card__body">
              <div class="knd-showcase-card__title">Creation</div>
              <div class="knd-showcase-card__meta">1024×1024</div>
            </div>
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<?php echo generateFooter(); echo generateScripts(); ?>
