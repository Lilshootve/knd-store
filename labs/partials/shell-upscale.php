<?php
/**
 * Upscale tool - full integration inside Labs shell. Same workspace layout as Text2Img.
 * Reuses kndlabs.js, same IDs/names as labs/upscale.php. source_job_id from GET.
 */
$balance = isset($balance) ? (int) $balance : 0;
?>
<span id="labs-balance" class="d-none"><?php echo number_format($balance); ?></span>

<div class="ln-t2i-workspace ln-tool-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title"><?php echo t('ai.upscale.title', 'Upscale'); ?></h1>
    <p class="ln-editor-subtitle"><?php echo t('ai.upscale.subtitle', 'Improve resolution and clarity. 2× or 4× upscaling.'); ?></p>
  </header>

  <form id="labs-comfy-form" class="labs-form ln-t2i-form" method="post" action="#" onsubmit="return false;">
    <input type="hidden" name="tool" value="upscale">
    <div class="ln-t2i-grid">
      <div class="ln-t2i-main-col">
        <div class="ln-t2i-meta">
          <span id="labs-cost-label" class="ln-t2i-cost"><?php echo t('labs.cost_label', 'Cost: 5 KP'); ?></span>
        </div>
        <div class="ln-t2i-prompt-block ln-tool-block">
          <label class="form-label ln-t2i-label"><?php echo t('ai.upscale.upload', 'Source image'); ?></label>
          <div class="ai-dropzone ln-tool-dropzone" id="labs-upscale-dropzone">
            <input type="file" name="image" id="labs-upscale-file" accept="image/jpeg,image/jpg,image/png,image/webp" hidden>
            <div id="labs-upscale-content">
              <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-white-50"></i>
              <p class="mb-0 text-white-50"><?php echo t('ai.upscale.upload'); ?></p>
              <p class="small text-white-50">JPG, PNG, WebP. Max 10MB</p>
            </div>
            <div id="labs-upscale-preview" style="display:none;"><img id="labs-upscale-preview-img" src="" alt="" style="max-height:160px;"></div>
          </div>
        </div>
        <div class="ln-t2i-canvas-zone">
          <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap knd-labs-preview-with-deco" id="labs-result-wrapper">
            <?php require __DIR__ . '/studio_canvas_deco.php'; ?>
            <?php
            $labsStudioPhTitle = t('labs.studio.upscale_empty_title', 'Upscale');
            $labsStudioPhSub = t('labs.studio.upscale_empty_sub', 'Upload an image and boost resolution and detail.');
            $labsStudioTipsIcon = 'fa-search-plus';
            $labsStudioTipsLine1 = t('labs.no_result_yet', 'Submit to generate');
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
            <button type="submit" form="labs-comfy-form" class="ln-t2i-cta" id="labs-submit-btn" disabled>
              <i class="fas fa-search-plus me-2"></i><?php echo t('ai.upscale.title'); ?>
            </button>
          </div>
          <div id="labs-result-actions" class="labs-result-actions-panel ln-t2i-actions" style="display:none;">
            <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i><?php echo t('ai.download'); ?></a>
            <button type="button" id="labs-retry-btn" class="labs-action labs-action--secondary"><i class="fas fa-redo"></i><?php echo t('labs.generate_again', 'Generate again'); ?></button>
          </div>
          <div id="labs-status-panel" class="ln-t2i-status" style="display:none;">
            <div class="d-flex align-items-center"><div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div><span id="labs-status-text"><?php echo t('ai.status.processing'); ?></span></div>
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
            <label class="ln-t2i-param-label"><?php echo t('ai.upscale.scale'); ?></label>
            <select name="scale" id="labs-scale-select" class="knd-select form-select text-white">
              <option value="2">2x (5 KP)</option>
              <option value="4" selected>4x (8 KP)</option>
            </select>
            <div class="form-text text-white-50 small mt-1"><?php echo t('labs.upscale_scale_help', 'Higher scale = more detail, higher cost'); ?></div>
          </div>
          <div class="ln-t2i-param-group">
            <button type="button" class="btn btn-link btn-sm text-white-50 p-0" data-bs-toggle="collapse" data-bs-target="#labs-upscale-advanced">
              <i class="fas fa-chevron-down me-1"></i><?php echo t('labs.advanced', 'Advanced'); ?>
            </button>
            <div class="collapse mt-2" id="labs-upscale-advanced">
              <label class="form-label text-white-50 small"><?php echo t('labs.denoise', 'Denoise'); ?></label>
              <input type="number" name="upscale_denoise" class="knd-input form-control form-control-sm text-white mb-2" value="0.10" min="0" max="0.35" step="0.05">
              <label class="form-label text-white-50 small"><?php echo t('labs.upscaler_model', 'Upscaler model'); ?></label>
              <select name="upscale_model" class="knd-select form-select form-select-sm text-white">
                <option value="4x-UltraSharp.pth" selected>4x-UltraSharp</option>
              </select>
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
  var dz = document.getElementById('labs-upscale-dropzone');
  var fileInput = document.getElementById('labs-upscale-file');
  var content = document.getElementById('labs-upscale-content');
  var preview = document.getElementById('labs-upscale-preview');
  var previewImg = document.getElementById('labs-upscale-preview-img');
  var params = new URLSearchParams(window.location.search);
  var sourceJobId = params.get('source_job_id');
  if (sourceJobId) {
    fetch('/api/labs/image.php?job_id=' + encodeURIComponent(sourceJobId) + '&t=' + Date.now(), { credentials: 'same-origin' })
      .then(function(r) { return r.blob(); })
      .then(function(blob) {
        var f = new File([blob], 'source.png', { type: 'image/png' });
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
  if (dz) dz.addEventListener('click', function(){ fileInput && fileInput.click(); });
  if (fileInput) fileInput.addEventListener('change', function(){
    var f = this.files[0];
    if (f && f.type.startsWith('image/')) {
      if (previewImg) previewImg.src = URL.createObjectURL(f);
      if (preview) preview.style.display = 'block';
      if (content) content.style.display = 'none';
      var btn = document.getElementById('labs-submit-btn');
      if (btn) btn.disabled = false;
    }
  });
  function run() {
    if (typeof KNDLabs !== 'undefined') {
      KNDLabs.init({ formId: 'labs-comfy-form', jobType: 'upscale', costLabelId: 'labs-cost-label', pricingKey: 'upscale', scaleSelectId: 'labs-scale-select', balanceEl: '#labs-balance' });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
  function scheduleLazyHistory() {
    var fn = function() {
      if (window.LabsLazyHistory && window.LabsLazyHistory.load) {
        window.LabsLazyHistory.load({ tool: 'upscale', limit: 5, toolLabel: 'Upscale', hasProviderFilter: false });
      }
    };
    if (window.requestIdleCallback) requestIdleCallback(fn, { timeout: 1500 }); else setTimeout(fn, 100);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scheduleLazyHistory); else scheduleLazyHistory();
})();
</script>
