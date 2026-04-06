<?php
/**
 * 3D Lab - Unified 3D generation (text, image, text+image, recent)
 * Dedicated pipeline, separate ComfyUI. Safe mode only.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_init.php';
require_once BASE_PATH . '/includes/labs_3d_helpers.php';

labs_perf_checkpoint('3d_lab_after_init');

$toolName = '3D Lab';
$balance = 0;
if ($pdo) {
    $balance = get_available_points($pdo, current_user_id());
}
$kpCost = labs_3d_kp_cost();
$categories = LABS_3D_CATEGORIES;
$styles = LABS_3D_STYLES;
$qualities = LABS_3D_QUALITY;

$aiCss = __DIR__ . '/../assets/css/ai-tools.css';
$labsCss = __DIR__ . '/../assets/css/knd-labs.css';
$tdCss = __DIR__ . '/../assets/css/labs/3d-lab.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/labs/3d-lab.css?v=' . (file_exists($tdCss) ? filemtime($tdCss) : time()) . '">';
$extraCss .= '<script type="module" src="https://cdn.jsdelivr.net/npm/@google/model-viewer/dist/model-viewer.min.js"></script>';

echo generateHeader($toolName . ' | KND Labs', 'Create optimized 3D models from text, images, or both. Clean GLB output.', $extraCss);
?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="labs-tool-section py-5">
  <div class="container">
    <?php labs_breadcrumb($toolName); ?>

    <div class="labs-3d-hero glass-card-neon p-4 mt-4 mb-4">
      <h2 class="text-white mb-2"><i class="fas fa-cube me-2"></i>3D Lab</h2>
      <p class="text-white-50 mb-2">Create optimized 3D models from text, images, or both. Generate clean GLB previews with smart presets and a dedicated 3D pipeline.</p>
      <div class="d-flex flex-wrap gap-2 mb-0">
        <span class="badge bg-success">Safe mode only</span>
        <span class="badge bg-secondary">No celebrity likeness</span>
        <span class="badge bg-secondary">No copyrighted content</span>
        <span class="badge bg-secondary">Single subject preferred</span>
      </div>
    </div>

    <div class="knd-workspace mt-4">
      <aside class="knd-panel">
        <div class="knd-section-title">3D Lab</div>
        <form id="labs-3d-form" class="labs-form" method="post" action="#" onsubmit="return false;" enctype="multipart/form-data">
          <input type="hidden" name="mode" id="l3d-mode" value="image">
          <input type="hidden" name="source_recent_job_id" id="l3d-source-id" value="">
          <input type="hidden" name="source_recent_type" id="l3d-source-type" value="3d_lab">

          <div class="mb-3">
            <label class="form-label text-white-50 knd-label">Input mode</label>
            <select id="l3d-mode-select" class="form-select knd-select text-white">
              <option value="image" selected>Image</option>
              <option value="text_image">Text + Image</option>
              <option value="recent">Recent jobs</option>
              <option value="text" disabled>Text only (coming soon)</option>
            </select>
          </div>

          <div id="l3d-prompt-wrap" class="mb-3">
            <label class="form-label text-white-50 knd-label">Prompt</label>
            <textarea name="prompt" id="l3d-prompt" class="knd-textarea form-control text-white" rows="3" maxlength="2000" placeholder="Describe your 3D model..."></textarea>
          </div>
          <div id="l3d-negative-wrap" class="mb-3">
            <label class="form-label text-white-50 knd-label small">Negative prompt (optional)</label>
            <input type="text" name="negative_prompt" id="l3d-negative" class="knd-input form-control text-white" maxlength="1000" placeholder="low quality, blurry">
          </div>

          <div id="l3d-upload-wrap" class="mb-3" style="display:none;">
            <label class="form-label text-white-50 knd-label">Image or GLB upload</label>
            <div id="l3d-dropzone" class="labs-3d-dropzone rounded border border-secondary p-4 text-center">
              <input type="file" id="l3d-file" name="image" accept="image/jpeg,image/jpg,image/png,image/webp,.glb,model/gltf-binary" hidden>
              <div id="l3d-dropzone-content">
                <i class="fas fa-cloud-upload-alt fa-2x text-white-50 mb-2"></i>
                <p class="mb-1 text-white-50">Drop image or GLB, or click</p>
                <small class="text-white-50">Images: JPG, PNG, WEBP · 3D: GLB · max 10MB</small>
              </div>
              <div id="l3d-preview-wrap" style="display:none;">
                <img id="l3d-preview" alt="Preview" class="img-fluid rounded" style="max-height:160px;">
                <button type="button" id="l3d-remove-img" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-times"></i> Remove</button>
              </div>
            </div>
          </div>

          <div id="l3d-recent-wrap" class="mb-3" style="display:none;">
            <label class="form-label text-white-50 knd-label">Pick from your recent creations</label>
            <div id="l3d-recent-grid" class="row g-2">
              <p class="text-white-50 small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</p>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label text-white-50 small">Category</label>
              <select name="category" id="l3d-category" class="knd-select form-select form-select-sm text-white">
                <?php foreach ($categories as $k => $v): ?>
                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $k === 'Stylized Asset' ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label text-white-50 small">Style</label>
              <select name="style" id="l3d-style" class="knd-select form-select form-select-sm text-white">
                <?php foreach ($styles as $k => $v): ?>
                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $k === 'Stylized' ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label text-white-50 small">Quality</label>
            <select name="quality" id="l3d-quality" class="knd-select form-select form-select-sm text-white">
              <?php foreach ($qualities as $k => $v): ?>
              <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $k === 'Standard' ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <button type="button" class="btn btn-link btn-sm text-white-50 p-0" id="l3d-advanced-toggle" data-bs-toggle="collapse" data-bs-target="#l3d-advanced">
              <i class="fas fa-chevron-down me-1"></i>Advanced Controls
            </button>
          </div>
          <div class="collapse mb-3" id="l3d-advanced">
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label text-white-50 small">Seed</label>
                <input type="number" name="seed" id="l3d-seed" class="knd-input form-control form-control-sm text-white" placeholder="Random">
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small">Guidance</label>
                <input type="number" name="guidance" id="l3d-guidance" class="knd-input form-control form-control-sm text-white" value="7.5" min="1" max="20" step="0.5">
              </div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label text-white-50 small">Image Influence</label>
                <input type="number" name="image_influence" id="l3d-img-influence" class="knd-input form-control form-control-sm text-white" value="0.7" min="0" max="1" step="0.05">
              </div>
            </div>
          </div>

          <div class="d-flex flex-column gap-2">
            <button type="button" class="btn btn-outline-light btn-sm" id="l3d-view-image" style="display:none;">
              <i class="fas fa-eye me-2"></i>View in viewer
            </button>
            <button type="submit" class="labs-gen-btn w-100" id="l3d-submit">
              <i class="fas fa-cube me-2"></i>Generate 3D
            </button>
          </div>
        </form>
      </aside>

      <div class="d-flex flex-column flex-grow-1">
        <div class="knd-canvas knd-panel-soft flex-grow-1 mb-0" id="l3d-result-wrapper">
          <div id="l3d-placeholder" class="labs-result-preview text-center py-5" style="min-height:320px;">
            <div id="l3d-placeholder-empty" class="labs-placeholder-tips knd-canvas__empty">
              <i class="fas fa-cube fa-4x mb-3" style="color:var(--knd-accent-soft);opacity:.4;"></i>
              <p class="text-white-50 mb-1">3D Lab – Image or Text+Image</p>
              <p class="text-white-50 small mb-0">Generate a clean GLB model. Single subject works best.</p>
            </div>
            <div id="l3d-placeholder-loading" class="d-none">
              <div class="labs-stepper mb-2">
                <span class="labs-stepper-dot" data-step="queued"></span>
                <span class="labs-stepper-line"></span>
                <span class="labs-stepper-dot" data-step="picked"></span>
                <span class="labs-stepper-line"></span>
                <span class="labs-stepper-dot" data-step="generating"></span>
                <span class="labs-stepper-line"></span>
                <span class="labs-stepper-dot" data-step="done"></span>
              </div>
              <p class="text-white-50 small mb-2">Generation is queued. You can leave this page.</p>
              <div class="ai-spinner mb-2"><i class="fas fa-cog fa-spin fa-2x"></i></div>
              <p class="text-white-50 mb-0" id="l3d-placeholder-status-text">Processing...</p>
            </div>
          </div>
          <div id="l3d-viewer-wrap" style="display:none; min-height:320px;">
            <div id="l3d-image-preview-wrap" class="d-none d-flex align-items-center justify-content-center" style="min-height:320px; background:#1a1a2e;">
              <img id="l3d-viewer-image" alt="Preview" class="img-fluid" style="max-height:320px; max-width:100%; object-fit:contain;">
            </div>
            <model-viewer id="l3d-model-viewer" camera-controls auto-rotate interaction-prompt="none" style="width:100%; height:320px;"></model-viewer>
            <div id="l3d-viewer-toolbar" class="d-flex flex-wrap gap-2 mt-2">
              <button type="button" id="l3d-wireframe" class="btn btn-sm btn-outline-secondary">Wireframe</button>
              <button type="button" id="l3d-textures" class="btn btn-sm btn-outline-secondary">Textures Off</button>
              <button type="button" id="l3d-fullscreen" class="btn btn-sm btn-outline-secondary">Fullscreen</button>
              <span id="l3d-stats" class="text-white-50 small align-self-center"></span>
            </div>
          </div>
        </div>
        <div class="labs-gen-area text-center py-4">
          <p class="knd-muted small mb-2">Cost: <strong id="l3d-cost"><?php echo (int) $kpCost; ?></strong> KP</p>
        </div>
        <div id="l3d-result-actions" class="labs-result-actions-panel mt-4 px-3" style="display:none;">
          <a href="#" id="l3d-download" class="labs-action labs-action--primary" download><i class="fas fa-download"></i>Download GLB</a>
          <a href="#" id="l3d-view-model" class="labs-action labs-action--primary"><i class="fas fa-cube"></i>View Model</a>
        </div>
        <div id="l3d-status-panel" class="mt-3 px-3" style="display:none;">
          <div class="d-flex align-items-center">
            <div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div>
            <span id="l3d-status-text">Processing...</span>
          </div>
        </div>
        <div id="l3d-error" class="alert alert-danger mt-3 mx-3" style="display:none;"></div>
      </div>

      <aside class="knd-panel">
        <div class="knd-section-title">Credits</div>
        <p class="text-white mb-2"><strong id="l3d-balance"><?php echo number_format($balance); ?></strong> KP</p>
        <p class="knd-muted small mb-3">Cost: <?php echo (int) $kpCost; ?> KP per generation</p>
        <a href="/support-credits.php" class="knd-btn-secondary w-100 mb-4">+ Add Credits</a>
        <div class="knd-divider"></div>
        <div class="knd-section-title">Recent Creations</div>
        <div id="l3d-history-placeholder">
          <p class="knd-muted small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</p>
        </div>
        <div id="l3d-history-list" class="list-unstyled mb-0" style="display:none;"></div>
      </aside>
    </div>

    <div class="labs-recent-creations mt-5">
      <div class="knd-section-title mb-3">Recent Creations</div>
      <div class="knd-card-grid" id="l3d-recent-creations-grid">
        <p class="knd-muted small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</p>
      </div>
      <p class="text-white-50 small mt-3 mb-0" style="opacity:0.85;">Best results: single subject, clean images, transparent background when possible. Tool optimizes for usable GLB output, not AAA production assets.</p>
    </div>
  </div>
</section>

<script>
window.KND_3D_LAB = {
  cost: <?php echo (int) $kpCost; ?>,
  balance: <?php echo (int) $balance; ?>,
  endpoints: { create: '/api/labs/3d-lab/create.php', status: '/api/labs/3d-lab/status.php', history: '/api/labs/3d-lab/history.php', download: '/api/labs/3d-lab/download.php' }
};
</script>
<script src="/assets/js/navigation-extend.js"></script>
<script src="/assets/js/labs/3d-lab.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/labs/3d-lab.js') ? filemtime(__DIR__ . '/../assets/js/labs/3d-lab.js') : time(); ?>"></script>
<?php echo generateFooter(); echo generateScripts(); echo labs_perf_comment(); labs_perf_log(); ?>
