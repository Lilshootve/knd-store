<?php
/**
 * 3D Vertex shell - unified 3D textured model generation via ComfyUI (8188).
 * Uses shared queue API (/api/labs/generate.php) with tool=3d_vertex.
 */
$balance = isset($balance) ? (int) $balance : 0;
$kpCostVertex = isset($kpCostVertex) ? (int) $kpCostVertex : 20;
?>
<span id="labs-balance" class="d-none"><?php echo number_format($balance); ?></span>

<div class="ln-t2i-workspace ln-tool-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title">3D Vertex</h1>
    <p class="ln-editor-subtitle">Generate textured 3D models from an image input using the dedicated 3D ComfyUI pipeline.</p>
  </header>

  <form id="labs-comfy-form" class="labs-form ln-t2i-form" method="post" action="#" onsubmit="return false;" enctype="multipart/form-data">
    <input type="hidden" name="tool" value="3d_vertex">
    <div class="ln-t2i-grid">
      <div class="ln-t2i-main-col">
        <div class="ln-t2i-meta">
          <span id="labs-cost-label" class="ln-t2i-cost">Cost: <?php echo (int) $kpCostVertex; ?> KP</span>
        </div>
        <div class="ln-t2i-prompt-block ln-tool-block">
          <label class="form-label ln-t2i-label">Source image</label>
          <div class="ai-dropzone ln-tool-dropzone" id="labs-vertex-dropzone">
            <input type="file" name="image" id="labs-vertex-file" accept="image/jpeg,image/jpg,image/png,image/webp" hidden>
            <div id="labs-vertex-content">
              <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-white-50"></i>
              <p class="mb-0 text-white-50">Upload source image</p>
              <p class="small text-white-50">JPG, PNG, WebP. Max 10MB</p>
            </div>
            <div id="labs-vertex-preview" style="display:none;"><img id="labs-vertex-preview-img" src="" alt="" style="max-height:160px;"></div>
          </div>
        </div>
        <div class="ln-t2i-canvas-zone">
          <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap" id="labs-result-wrapper">
            <div id="labs-result-preview" class="labs-result-preview ln-t2i-preview" style="min-height:380px;">
              <div class="labs-placeholder-tips">
                <i class="fas fa-cube ln-t2i-placeholder-icon"></i>
                <p class="text-white-50 mb-1 small">Upload an image to generate a textured 3D model.</p>
                <p class="text-white-50 mb-0 small">Best results: single subject, clean silhouette, plain background.</p>
              </div>
            </div>
          </div>
          <div class="ln-t2i-gen-area">
            <button type="submit" form="labs-comfy-form" class="ln-t2i-cta" id="labs-submit-btn" disabled>
              <i class="fas fa-cube me-2"></i>Generate 3D Vertex
            </button>
          </div>
          <div id="labs-result-actions" class="labs-result-actions-panel ln-t2i-actions" style="display:none;">
            <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i>Download GLB</a>
            <a href="#" id="labs-view-model-btn" class="labs-action labs-action--primary"><i class="fas fa-cube"></i>View in Model Viewer</a>
            <button type="button" id="labs-retry-btn" class="labs-action labs-action--secondary"><i class="fas fa-redo"></i>Generate again</button>
          </div>
          <div id="labs-status-panel" class="ln-t2i-status" style="display:none;">
            <div class="d-flex align-items-center"><div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div><span id="labs-status-text">Processing...</span></div>
          </div>
          <div id="labs-error-msg" class="alert alert-danger ln-t2i-error" style="display:none;"></div>
          <div class="ln-t2i-details">
            <?php require __DIR__ . '/image_details_panel.php'; ?>
          </div>
        </div>
      </div>
      <aside class="ln-t2i-params-col">
        <?php require __DIR__ . '/credits-card.php'; ?>
        <div class="ln-t2i-params-panel">
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label">Quality</label>
            <select name="quality" id="labs-vertex-quality" class="knd-select form-select text-white">
              <option value="standard" selected>Standard</option>
              <option value="high">High</option>
            </select>
          </div>
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label">Prompt (optional)</label>
            <textarea name="prompt" class="knd-textarea form-control text-white" rows="3" placeholder="Optional guidance for style/material details"></textarea>
          </div>
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label">Negative prompt (optional)</label>
            <input type="text" name="negative_prompt" class="knd-input form-control text-white" maxlength="500" placeholder="low quality, artifacts, broken mesh">
          </div>
          <div class="ln-t2i-param-group">
            <button type="button" class="btn btn-link btn-sm text-white-50 p-0" data-bs-toggle="collapse" data-bs-target="#labs-vertex-advanced">
              <i class="fas fa-chevron-down me-1"></i>Advanced
            </button>
            <div class="collapse mt-2" id="labs-vertex-advanced">
              <label class="form-label text-white-50 small">Seed</label>
              <input type="number" name="seed" class="knd-input form-control form-control-sm text-white mb-2" placeholder="Random">
              <label class="form-label text-white-50 small">Mesh steps</label>
              <input type="number" name="steps" class="knd-input form-control form-control-sm text-white mb-2" value="50" min="10" max="120" step="1">
              <label class="form-label text-white-50 small">Guidance</label>
              <input type="number" name="cfg" class="knd-input form-control form-control-sm text-white mb-2" value="7.5" min="1" max="20" step="0.5">
              <label class="form-label text-white-50 small">Texture size</label>
              <select name="texture_size" class="knd-select form-select form-select-sm text-white mb-2">
                <option value="1024">1024</option>
                <option value="2048" selected>2048</option>
              </select>
              <label class="form-label text-white-50 small">Max faces</label>
              <input type="number" name="max_faces" class="knd-input form-control form-control-sm text-white" value="200000" min="50000" max="500000" step="5000">
            </div>
          </div>
        </div>
      </aside>
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

  function updateCost() {
    if (!costEl || !qualitySel) return;
    var cost = qualitySel.value === 'high' ? 30 : 20;
    costEl.textContent = 'Cost: ' + cost + ' KP';
  }
  if (qualitySel) qualitySel.addEventListener('change', updateCost);
  updateCost();

  if (dz) dz.addEventListener('click', function(){ if (fileInput) fileInput.click(); });
  if (fileInput) fileInput.addEventListener('change', function(){
    var f = this.files && this.files[0] ? this.files[0] : null;
    if (f && f.type.indexOf('image/') === 0) {
      if (previewImg) previewImg.src = URL.createObjectURL(f);
      if (preview) preview.style.display = 'block';
      if (content) content.style.display = 'none';
      if (submitBtn) submitBtn.disabled = false;
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
