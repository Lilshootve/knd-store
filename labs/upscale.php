<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_init.php';
labs_perf_checkpoint('upscale_after_init');

require_once BASE_PATH . '/includes/comfyui.php';
labs_perf_checkpoint('upscale_after_comfyui');

$toolName = t('ai.upscale.title', 'Upscale');
$jobType = 'upscale';
$historyJobs = [];
labs_perf_checkpoint('upscale_after_history_fetch');

$aiCss = __DIR__ . '/../assets/css/ai-tools.css';
$labsCss = __DIR__ . '/../assets/css/knd-labs.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
$extraCss .= '<script>window.KND_PRICING={"text2img":{"standard":3,"high":6},"upscale":{"2x":5,"4x":8},"character":{"base":15},"consistency":{"base":5}};</script>';
labs_perf_checkpoint('upscale_before_header');
echo generateHeader(t('labs.tool_page_title', '{tool} | KND Labs', ['tool' => $toolName]), t('labs.tool_page_desc', 'Create with AI'), $extraCss);
?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="labs-tool-section py-5">
  <div class="container">
    <?php labs_breadcrumb($toolName); ?>

    <div class="knd-workspace mt-4">
      <aside class="knd-panel">
        <div class="knd-section-title"><?php echo htmlspecialchars($toolName); ?></div>
        <form id="labs-comfy-form" class="labs-form" method="post" action="#" onsubmit="return false;">
            <input type="hidden" name="tool" value="upscale">
            <div class="mb-3">
              <label class="form-label text-white-50 knd-label"><?php echo t('ai.upscale.scale'); ?></label>
              <select name="scale" id="labs-scale-select" class="knd-select form-select text-white mb-2">
                <option value="2">2x (5 KP)</option>
                <option value="4" selected>4x (8 KP)</option>
              </select>
              <div class="form-text text-white-50 small"><?php echo t('labs.upscale_scale_help', 'Higher scale = more detail, higher cost'); ?></div>
            </div>
            <div class="mb-3 collapse" id="labs-upscale-advanced">
              <label class="form-label text-white-50 small knd-label"><?php echo t('labs.denoise', 'Denoise'); ?></label>
              <input type="number" name="upscale_denoise" class="knd-input form-control form-control-sm text-white" value="0.10" min="0" max="0.35" step="0.05" placeholder="0.10">
              <div class="form-text text-white-50 small"><?php echo t('labs.upscale_denoise_help', '0–0.35. Lower = preserve detail'); ?></div>
              <label class="form-label text-white-50 small mt-2"><?php echo t('labs.upscaler_model', 'Upscaler model'); ?></label>
              <select name="upscale_model" class="knd-select form-select form-select-sm text-white">
                <option value="4x-UltraSharp.pth" selected>4x-UltraSharp</option>
              </select>
            </div>
            <button type="button" class="btn btn-link btn-sm text-white-50 p-0 mb-2" data-bs-toggle="collapse" data-bs-target="#labs-upscale-advanced">
              <i class="fas fa-cog me-1"></i><?php echo t('labs.advanced', 'Advanced'); ?>
            </button>
            <div class="mb-3">
              <div class="ai-dropzone" id="labs-upscale-dropzone">
                <input type="file" name="image" id="labs-upscale-file" accept="image/jpeg,image/jpg,image/png,image/webp" hidden>
                <div id="labs-upscale-content">
                  <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-white-50"></i>
                  <p class="mb-0 text-white-50"><?php echo t('ai.upscale.upload'); ?></p>
                  <p class="small text-white-50">JPG, PNG, WebP. Max 10MB</p>
                </div>
                <div id="labs-upscale-preview" style="display:none;"><img id="labs-upscale-preview-img" src="" alt="" style="max-height:120px;"></div>
              </div>
            </div>
          </form>
      </aside>

      <div class="d-flex flex-column flex-grow-1">
        <div class="knd-canvas knd-panel-soft flex-grow-1 mb-0">
          <div id="labs-result-preview" class="labs-result-preview text-center py-5" style="min-height:320px;">
            <div id="labs-upscale-empty" class="knd-canvas__empty">
              <i class="fas fa-search-plus fa-3x mb-3" style="color:var(--knd-accent-soft);opacity:.4;"></i>
              <p class="mb-0"><?php echo t('labs.no_result_yet', 'Submit to generate'); ?></p>
            </div>
          </div>
        </div>
        <div class="labs-gen-area text-center py-4">
          <button type="submit" form="labs-comfy-form" class="labs-gen-btn" id="labs-submit-btn" disabled>
            <i class="fas fa-search-plus me-2"></i><?php echo t('ai.upscale.title'); ?>
          </button>
        </div>
        <div id="labs-result-actions" class="mt-3 px-3" style="display:none;">
            <a href="#" id="labs-download-btn" class="btn btn-success me-2" download><i class="fas fa-download me-1"></i><?php echo t('ai.download'); ?></a>
            <button type="button" id="labs-retry-btn" class="btn btn-outline-primary"><i class="fas fa-redo me-1"></i><?php echo t('labs.generate_again', 'Generate again'); ?></button>
          </div>
        <div id="labs-status-panel" class="mt-3 px-3" style="display:none;">
          <div class="d-flex align-items-center"><div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div><span id="labs-status-text"><?php echo t('ai.status.processing'); ?></span></div>
        </div>
        <div id="labs-error-msg" class="alert alert-danger mt-3 mx-3" style="display:none;"></div>
        <div class="px-3">
          <?php require __DIR__ . '/partials/image_details_panel.php'; ?>
        </div>
      </div>

      <aside class="knd-panel">
        <div class="knd-section-title"><?php echo t('labs.credits', 'Credits'); ?></div>
        <p class="text-white mb-2"><strong id="labs-balance"><?php echo number_format($balance); ?></strong> KP</p>
        <p class="knd-muted small mb-4" id="labs-cost-label"><?php echo t('labs.cost_label', 'Cost: 5 KP'); ?></p>
        <a href="/support-credits.php" class="knd-btn-secondary w-100 mb-4">+ <?php echo t('labs.add_credits', 'Add Credits'); ?></a>
        <div class="knd-divider"></div>
        <div class="knd-section-title"><?php echo t('labs.tool_history', 'Recent'); ?></div>
        <div id="labs-history-sidebar-placeholder">
          <p class="knd-muted small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading recent jobs...</p>
        </div>
        <ul class="list-unstyled mb-0" id="labs-recent-list" style="display:none;"></ul>
      </aside>
    </div>

    <div class="labs-recent-creations mt-5">
      <div class="knd-section-title mb-3"><?php echo t('labs.recent_creations', 'Recent Creations'); ?></div>
      <div class="knd-card-grid" id="labs-recent-creations-grid">
        <p class="knd-muted small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading recent jobs...</p>
      </div>
    </div>
  </div>
</section>

<div class="knd-details-drawer__backdrop" id="labs-details-backdrop"></div>
<div class="knd-details-drawer" id="labs-details-drawer" tabindex="-1">
  <div class="knd-details-drawer__header d-flex justify-content-between align-items-center">
    <h5 class="text-white mb-0"><?php echo t('labs.view_details', 'View details'); ?></h5>
    <button type="button" class="btn btn-sm btn-link text-white-50 p-0" id="labs-details-close" aria-label="Close"><i class="fas fa-times"></i></button>
  </div>
  <div class="knd-details-drawer__body" id="labs-details-body"></div>
</div>

<script src="/assets/js/navigation-extend.js"></script>
<script src="/assets/js/labs-lazy-history.js" defer></script>
<script src="/assets/js/kndlabs.js"></script>
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
  KNDLabs.init({ formId: 'labs-comfy-form', jobType: 'upscale', costLabelId: 'labs-cost-label', pricingKey: 'upscale', scaleSelectId: 'labs-scale-select', balanceEl: '#labs-balance' });
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
<?php echo generateFooter(); echo generateScripts(); echo labs_perf_comment(); labs_perf_log(); ?>
