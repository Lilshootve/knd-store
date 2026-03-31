<?php
/**
 * 3D Vertex shell — left control panel (studio layout) + full-width canvas.
 */
$balance = isset($balance) ? (int) $balance : 0;
$kpCostVertex = isset($kpCostVertex) ? (int) $kpCostVertex : 20;
?>
<span id="labs-balance" class="d-none"><?php echo number_format($balance); ?></span>

<div class="ln-t2i-workspace ln-tool-workspace knd-labs-vertex-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title"><?php echo htmlspecialchars(t('labs.vertex.title', '3D Studio'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="ln-editor-subtitle"><?php echo htmlspecialchars(t('labs.vertex.subtitle', 'Generate textured meshes from a single image.'), ENT_QUOTES, 'UTF-8'); ?></p>
  </header>

  <form id="labs-comfy-form" class="labs-form ln-t2i-form" method="post" action="#" onsubmit="return false;" enctype="multipart/form-data">
    <input type="hidden" name="tool" value="3d_vertex">
    <div class="ln-t2i-grid">
      <aside class="ln-t2i-params-col knd-labs-studio-controls-panel" aria-label="<?php echo htmlspecialchars(t('labs.vertex.controls_aria', 'Generation settings'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="knd-labs-panel-header">
          <span class="knd-labs-panel-header-icon" aria-hidden="true"><i class="fas fa-cube"></i></span>
          <span class="knd-labs-panel-title"><?php echo htmlspecialchars(t('labs.vertex.panel_generate', 'Generate'), ENT_QUOTES, 'UTF-8'); ?> <span class="knd-labs-panel-title-accent"><?php echo htmlspecialchars(t('labs.vertex.panel_model', 'Model'), ENT_QUOTES, 'UTF-8'); ?></span></span>
        </div>

        <div class="knd-labs-mode-tabs" role="tablist" aria-label="<?php echo htmlspecialchars(t('labs.vertex.mode_tabs', 'Output mode'), ENT_QUOTES, 'UTF-8'); ?>">
          <button type="button" class="knd-labs-mode-tab active" data-quality="standard" role="tab" aria-selected="true"><?php echo htmlspecialchars(t('labs.vertex.mode_hd', 'HD Model'), ENT_QUOTES, 'UTF-8'); ?></button>
          <button type="button" class="knd-labs-mode-tab" data-quality="high" role="tab" aria-selected="false"><?php echo htmlspecialchars(t('labs.vertex.mode_smart', 'Smart Mesh'), ENT_QUOTES, 'UTF-8'); ?> <span class="knd-labs-tab-emoji" aria-hidden="true">⚡</span></button>
        </div>

        <label class="knd-labs-sr-only" for="labs-vertex-quality"><?php echo htmlspecialchars(t('labs.vertex.quality', 'Quality'), ENT_QUOTES, 'UTF-8'); ?></label>
        <select name="quality" id="labs-vertex-quality" class="knd-labs-hidden-select" tabindex="-1" aria-hidden="true">
          <option value="standard" selected>Standard</option>
          <option value="high">High</option>
        </select>

        <div class="knd-labs-upload-zone ln-tool-dropzone" id="labs-vertex-dropzone">
          <input type="file" name="image" id="labs-vertex-file" accept="image/jpeg,image/jpg,image/png,image/webp" hidden>
          <div id="labs-vertex-content" class="knd-labs-upload-zone-inner">
            <span class="knd-labs-upload-zone-icon" aria-hidden="true"><i class="fas fa-cloud-upload-alt"></i></span>
            <span class="knd-labs-upload-zone-title"><?php echo htmlspecialchars(t('labs.vertex.upload_title', 'Upload image'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="knd-labs-upload-zone-sub"><?php echo htmlspecialchars(t('labs.vertex.upload_formats', 'JPG, PNG, WebP · max 10MB'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div id="labs-vertex-preview" class="knd-labs-upload-preview" hidden><img id="labs-vertex-preview-img" src="" alt=""></div>
        </div>

        <div class="knd-labs-model-selector" aria-hidden="true">
          <div class="knd-labs-model-selector-icon"><i class="fas fa-brain" aria-hidden="true"></i></div>
          <div class="knd-labs-model-selector-info">
            <div class="knd-labs-model-selector-name"><?php echo htmlspecialchars(t('labs.vertex.pipeline_name', '3D Vertex pipeline'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="knd-labs-model-selector-sub"><?php echo htmlspecialchars(t('labs.vertex.pipeline_sub', 'Image → textured GLB'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>

        <div class="knd-labs-ctrl-divider"></div>

        <div class="knd-labs-ctrl-field">
          <label class="knd-labs-ctrl-label" for="labs-vertex-prompt"><?php echo htmlspecialchars(t('labs.vertex.prompt_opt', 'Prompt (optional)'), ENT_QUOTES, 'UTF-8'); ?></label>
          <textarea name="prompt" id="labs-vertex-prompt" class="knd-labs-ctrl-textarea" rows="3" placeholder="<?php echo htmlspecialchars(t('labs.vertex.prompt_ph', 'Style or material hints…'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
        </div>
        <div class="knd-labs-ctrl-field">
          <label class="knd-labs-ctrl-label" for="labs-vertex-neg"><?php echo htmlspecialchars(t('labs.vertex.negative_opt', 'Negative (optional)'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input type="text" name="negative_prompt" id="labs-vertex-neg" class="knd-labs-ctrl-input" maxlength="500" placeholder="<?php echo htmlspecialchars(t('labs.vertex.negative_ph', 'low quality, broken mesh…'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <button type="button" class="knd-labs-advanced-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#labs-vertex-advanced" aria-expanded="false" aria-controls="labs-vertex-advanced">
          <i class="fas fa-chevron-down knd-labs-advanced-toggle-icon" aria-hidden="true"></i><?php echo htmlspecialchars(t('labs.vertex.advanced', 'Advanced'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <div class="collapse knd-labs-advanced-collapse" id="labs-vertex-advanced">
          <label class="knd-labs-ctrl-label" for="v-seed"><?php echo htmlspecialchars(t('labs.vertex.seed', 'Seed'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input type="number" name="seed" id="v-seed" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm mb-2" placeholder="<?php echo htmlspecialchars(t('labs.vertex.random', 'Random'), ENT_QUOTES, 'UTF-8'); ?>">
          <label class="knd-labs-ctrl-label" for="v-steps"><?php echo htmlspecialchars(t('labs.vertex.mesh_steps', 'Mesh steps'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input type="number" name="steps" id="v-steps" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm mb-2" value="50" min="10" max="120" step="1">
          <label class="knd-labs-ctrl-label" for="v-cfg"><?php echo htmlspecialchars(t('labs.vertex.guidance', 'Guidance'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input type="number" name="cfg" id="v-cfg" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm mb-2" value="7.5" min="1" max="20" step="0.5">
          <label class="knd-labs-ctrl-label" for="v-tex"><?php echo htmlspecialchars(t('labs.vertex.texture_size', 'Texture size'), ENT_QUOTES, 'UTF-8'); ?></label>
          <select name="texture_size" id="v-tex" class="knd-labs-ctrl-select mb-2">
            <option value="1024">1024</option>
            <option value="2048" selected>2048</option>
          </select>
          <label class="knd-labs-ctrl-label" for="v-faces"><?php echo htmlspecialchars(t('labs.vertex.max_faces', 'Max faces'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input type="number" name="max_faces" id="v-faces" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm" value="200000" min="50000" max="500000" step="5000">
        </div>

        <div class="knd-labs-ctrl-divider"></div>

        <div class="knd-labs-controls-balance">
          <span class="knd-labs-controls-balance-label"><?php echo htmlspecialchars(t('labs.balance', 'Balance'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="knd-labs-controls-balance-val"><?php echo number_format($balance); ?> <span class="knd-labs-kp">KP</span></span>
          <a href="/support-credits.php" class="knd-labs-controls-balance-link"><?php echo htmlspecialchars(t('labs.get_credits', 'Get credits'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>

        <span id="labs-cost-label" class="knd-labs-sr-only"></span>
        <button type="submit" class="knd-labs-btn-generate" id="labs-submit-btn" disabled>
          <i class="fas fa-bolt" aria-hidden="true"></i>
          <span><?php echo htmlspecialchars(t('labs.vertex.generate_cta', 'Generate model'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="knd-labs-btn-credits" aria-hidden="true"><span class="knd-labs-btn-credits-dot">●</span> <span id="labs-gen-kp"><?php echo (int) $kpCostVertex; ?></span></span>
        </button>
      </aside>

      <div class="ln-t2i-main-col knd-labs-vertex-main">
        <div class="ln-t2i-canvas-zone">
          <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap knd-labs-preview-with-deco" id="labs-result-wrapper">
            <?php require __DIR__ . '/studio_canvas_deco.php'; ?>
            <?php
            $labsStudioPhTitle = t('labs.studio.3d_empty_title', 'Ready for a new 3D model?');
            $labsStudioPhSub = t('labs.studio.3d_empty_sub', 'Generate 3D instantly from image or text.');
            $labsStudioTipsIcon = 'fa-cube';
            $labsStudioTipsLine1 = t('labs.studio.3d_tip1', 'Upload an image in the left panel to start.');
            $labsStudioTipsLine2 = t('labs.studio.3d_tip2', 'Best results: single subject, clean silhouette, plain background.');
            $labsStudioGradientSuffix = '';
            ob_start();
            require __DIR__ . '/studio_canvas_placeholder_inner.php';
            $kndLabsPlaceholderHtml = ob_get_clean();
            $labsStudioGradientSuffix = '_tmpl';
            ob_start();
            require __DIR__ . '/studio_canvas_placeholder_inner.php';
            $kndLabsPlaceholderTmplHtml = ob_get_clean();
            ?>
            <div id="labs-result-preview" class="labs-result-preview ln-t2i-preview"><?php echo $kndLabsPlaceholderHtml; ?></div>
            <template id="knd-labs-studio-placeholder-tmpl"><?php echo $kndLabsPlaceholderTmplHtml; ?></template>
          </div>
          <div id="labs-result-actions" class="labs-result-actions-panel ln-t2i-actions" style="display:none;">
            <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i><?php echo htmlspecialchars(t('labs.vertex.download_glb', 'Download GLB'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a href="#" id="labs-view-model-btn" class="labs-action labs-action--primary"><i class="fas fa-cube"></i><?php echo htmlspecialchars(t('labs.vertex.view_viewer', 'Open in Model Viewer'), ENT_QUOTES, 'UTF-8'); ?></a>
            <button type="button" id="labs-retry-btn" class="labs-action labs-action--secondary"><i class="fas fa-redo"></i><?php echo htmlspecialchars(t('labs.vertex.again', 'Generate again'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
          <div id="labs-status-panel" class="ln-t2i-status" style="display:none;">
            <div class="d-flex align-items-center"><div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div><span id="labs-status-text"><?php echo htmlspecialchars(t('ai.status.processing'), ENT_QUOTES, 'UTF-8'); ?></span></div>
          </div>
          <div id="labs-error-msg" class="alert alert-danger ln-t2i-error" style="display:none;"></div>
          <div class="ln-t2i-details">
            <?php require __DIR__ . '/image_details_panel.php'; ?>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script src="/assets/js/labs-lazy-history.js" defer></script>
<script src="/assets/js/kndlabs.js?v=<?php echo file_exists(__DIR__ . '/../../assets/js/kndlabs.js') ? filemtime(__DIR__ . '/../../assets/js/kndlabs.js') : time(); ?>"></script>
<script>
(function(){
  var dz = document.getElementById('labs-vertex-dropzone');
  var fileInput = document.getElementById('labs-vertex-file');
  var content = document.getElementById('labs-vertex-content');
  var preview = document.getElementById('labs-vertex-preview');
  var previewImg = document.getElementById('labs-vertex-preview-img');
  var qualitySel = document.getElementById('labs-vertex-quality');
  var costEl = document.getElementById('labs-cost-label');
  var submitBtn = document.getElementById('labs-submit-btn');
  var viewBtn = document.getElementById('labs-view-model-btn');
  var kpBadge = document.getElementById('labs-gen-kp');
  var modeTabs = document.querySelectorAll('.knd-labs-vertex-workspace .knd-labs-mode-tab');

  function syncTabsFromSelect() {
    if (!qualitySel) return;
    var v = qualitySel.value;
    modeTabs.forEach(function(tab) {
      var on = tab.getAttribute('data-quality') === v;
      tab.classList.toggle('active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
  }

  modeTabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
      var q = tab.getAttribute('data-quality');
      if (!q || !qualitySel) return;
      qualitySel.value = q;
      qualitySel.dispatchEvent(new Event('change', { bubbles: true }));
      syncTabsFromSelect();
    });
  });

  function updateCost() {
    if (!qualitySel) return;
    var cost = qualitySel.value === 'high' ? 30 : 20;
    if (costEl) costEl.textContent = 'Cost: ' + cost + ' KP';
    if (kpBadge) kpBadge.textContent = String(cost);
  }
  if (qualitySel) qualitySel.addEventListener('change', updateCost);
  syncTabsFromSelect();
  updateCost();

  if (dz) dz.addEventListener('click', function(){ if (fileInput) fileInput.click(); });
  if (fileInput) fileInput.addEventListener('change', function(){
    var f = this.files && this.files[0] ? this.files[0] : null;
    if (f && f.type.indexOf('image/') === 0) {
      if (previewImg) previewImg.src = URL.createObjectURL(f);
      if (preview) preview.hidden = false;
      if (content) content.hidden = true;
      if (submitBtn) submitBtn.disabled = false;
      if (dz) dz.classList.add('knd-labs-upload-zone--has-file');
    }
  });

  function run() {
    if (typeof KNDLabs !== 'undefined') {
      KNDLabs.init({ formId: 'labs-comfy-form', jobType: '3d_vertex', costLabelId: 'labs-cost-label', pricingKey: '3d_vertex', balanceEl: '#labs-balance' });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();

  if (viewBtn) {
    viewBtn.addEventListener('click', function(e) {
      var href = viewBtn.getAttribute('href');
      if (!href || href === '#') return;
      e.preventDefault();
      window.location.href = href;
    });
  }

  function scheduleLazyHistory() {
    var fn = function() {
      if (window.LabsLazyHistory && window.LabsLazyHistory.load) {
        window.LabsLazyHistory.load({ tool: '3d_vertex', limit: 5, toolLabel: '3D Vertex', hasProviderFilter: false });
      }
    };
    if (window.requestIdleCallback) requestIdleCallback(fn, { timeout: 1500 }); else setTimeout(fn, 100);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scheduleLazyHistory); else scheduleLazyHistory();
})();
</script>
