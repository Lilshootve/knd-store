<?php
/**
 * Remove Background - full integration inside Labs shell.
 * Reuses shared KNDLabs queue/polling pipeline and shell visual style.
 */
$balance = isset($balance) ? (int) $balance : 0;
?>
<span id="labs-balance" class="d-none"><?php echo number_format($balance); ?></span>

<div class="ln-t2i-workspace ln-tool-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title">Remove Background</h1>
    <p class="ln-editor-subtitle">Automatically remove the background from an image and return a transparent PNG.</p>
  </header>

  <form id="labs-remove-bg-form" class="labs-form ln-t2i-form" method="post" action="#" onsubmit="return false;">
    <input type="hidden" name="tool" value="remove-bg">
    <div class="ln-t2i-grid">
      <div class="ln-t2i-main-col">
        <div class="ln-t2i-meta">
          <span id="labs-cost-label" class="ln-t2i-cost"><?php echo t('labs.cost_label', 'Cost: 5 KP'); ?></span>
        </div>
        <div class="ln-t2i-prompt-block ln-tool-block">
          <label class="form-label ln-t2i-label">Source image</label>
          <div class="ai-dropzone ln-tool-dropzone" id="labs-remove-bg-dropzone">
            <input type="file" name="image" id="labs-remove-bg-file" accept="image/jpeg,image/jpg,image/png,image/webp" hidden>
            <div id="labs-remove-bg-content">
              <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-white-50"></i>
              <p class="mb-0 text-white-50">Upload image</p>
              <p class="small text-white-50">JPG, PNG, WebP. Max 10MB</p>
            </div>
            <div id="labs-remove-bg-preview" style="display:none;"><img id="labs-remove-bg-preview-img" src="" alt="" style="max-height:160px;"></div>
          </div>
        </div>
        <div class="ln-t2i-canvas-zone">
          <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap knd-labs-preview-with-deco" id="labs-result-wrapper">
            <?php require __DIR__ . '/studio_canvas_deco.php'; ?>
            <?php
            $labsStudioPhTitle = t('labs.studio.remove_bg_empty_title', 'Remove background');
            $labsStudioPhSub = t('labs.studio.remove_bg_empty_sub', 'Transparent PNG in one click.');
            $labsStudioTipsIcon = 'fa-eraser';
            $labsStudioTipsLine1 = t('labs.studio.remove_bg_tip', 'Upload an image and remove the background in one click.');
            $labsStudioTipsLine2 = null;
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
          <div class="ln-t2i-gen-area">
            <button type="submit" form="labs-remove-bg-form" class="ln-t2i-cta" id="labs-submit-btn" disabled>
              <i class="fas fa-eraser me-2"></i>Remove Background
            </button>
          </div>
          <div id="labs-result-actions" class="labs-result-actions-panel ln-t2i-actions" style="display:none;">
            <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i><?php echo t('ai.download', 'Download'); ?></a>
            <a href="/labs?tool=upscale" id="labs-use-input-btn" class="labs-action labs-action--primary"><i class="fas fa-search-plus"></i><?php echo t('labs.send_to_upscale', 'Send to Upscale'); ?></a>
            <a href="/labs?tool=3d_vertex" id="labs-send-3d-btn" class="labs-action labs-action--primary"><i class="fas fa-cube"></i><?php echo t('labs.send_to_3d_lab', 'Send to 3D Vertex'); ?></a>
            <button type="button" id="labs-retry-btn" class="labs-action labs-action--secondary"><i class="fas fa-redo"></i><?php echo t('labs.generate_again', 'Generate again'); ?></button>
          </div>
          <div id="labs-status-panel" class="ln-t2i-status" style="display:none;">
            <div class="d-flex align-items-center"><div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div><span id="labs-status-text"><?php echo t('ai.status.processing', 'Processing...'); ?></span></div>
          </div>
          <div id="labs-error-msg" class="alert alert-danger ln-t2i-error" style="display:none;"></div>
          <div class="ln-t2i-details">
            <?php require __DIR__ . '/image_details_panel.php'; ?>
          </div>
        </div>
      </div>
      <aside class="ln-t2i-params-col">
        <?php require __DIR__ . '/credits-card.php'; ?>
      </aside>
    </div>
  </form>
</div>

<script src="/assets/js/labs-lazy-history.js" defer></script>
<script src="/assets/js/kndlabs.js?v=<?php echo file_exists(__DIR__ . '/../../assets/js/kndlabs.js') ? filemtime(__DIR__ . '/../../assets/js/kndlabs.js') : time(); ?>"></script>
<script>
(function(){
  var dz = document.getElementById('labs-remove-bg-dropzone');
  var fileInput = document.getElementById('labs-remove-bg-file');
  var content = document.getElementById('labs-remove-bg-content');
  var preview = document.getElementById('labs-remove-bg-preview');
  var previewImg = document.getElementById('labs-remove-bg-preview-img');
  var params = new URLSearchParams(window.location.search);
  var sourceJobId = params.get('source_job_id');

  if (sourceJobId) {
    fetch('/api/labs/image.php?job_id=' + encodeURIComponent(sourceJobId) + '&t=' + Date.now(), { credentials: 'same-origin' })
      .then(function(r) {
        if (!r.ok) throw new Error('Could not load source image');
        return r.blob();
      })
      .then(function(blob) {
        var mime = (blob && blob.type && blob.type.indexOf('image/') === 0) ? blob.type : 'image/png';
        var f = new File([blob], 'source-' + sourceJobId + '.png', { type: mime });
        var dt = new DataTransfer();
        dt.items.add(f);
        if (fileInput) fileInput.files = dt.files;
        if (previewImg) previewImg.src = URL.createObjectURL(blob);
        if (preview) preview.style.display = 'block';
        if (content) content.style.display = 'none';
        var btn = document.getElementById('labs-submit-btn');
        if (btn) btn.disabled = false;
      })
      .catch(function() {});
  }

  if (dz) dz.addEventListener('click', function(){ if (fileInput) fileInput.click(); });
  if (fileInput) fileInput.addEventListener('change', function(){
    var f = this.files && this.files[0] ? this.files[0] : null;
    if (f && f.type.indexOf('image/') === 0) {
      if (previewImg) previewImg.src = URL.createObjectURL(f);
      if (preview) preview.style.display = 'block';
      if (content) content.style.display = 'none';
      var btn = document.getElementById('labs-submit-btn');
      if (btn) btn.disabled = false;
    }
  });
  function run() {
    if (typeof KNDLabs !== 'undefined') {
      KNDLabs.init({ formId: 'labs-remove-bg-form', jobType: 'remove-bg', costLabelId: 'labs-cost-label', pricingKey: 'remove_bg', balanceEl: '#labs-balance' });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
  function scheduleLazyHistory() {
    var fn = function() {
      if (window.LabsLazyHistory && window.LabsLazyHistory.load) {
        window.LabsLazyHistory.load({ tool: 'remove-bg', limit: 5, toolLabel: 'Remove Background', hasProviderFilter: false });
      }
    };
    if (window.requestIdleCallback) requestIdleCallback(fn, { timeout: 1500 }); else setTimeout(fn, 100);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scheduleLazyHistory); else scheduleLazyHistory();
})();
</script>
