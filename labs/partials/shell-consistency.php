<?php
/**
 * Consistency System - full integration inside Labs shell. Same workspace layout.
 * Expects $refJobId, $preloadMode, $preloadFromJob, $refJobs, $balance from knd-labs.php.
 */
$refJobId = isset($refJobId) ? (int) $refJobId : 0;
$preloadMode = isset($preloadMode) ? $preloadMode : 'style';
$preloadFromJob = isset($preloadFromJob) && is_array($preloadFromJob) ? $preloadFromJob : [];
$refJobs = isset($refJobs) && is_array($refJobs) ? $refJobs : [];
$balance = isset($balance) ? (int) $balance : 0;
?>
<span id="labs-balance" class="d-none"><?php echo number_format($balance); ?></span>

<div class="ln-t2i-workspace ln-tool-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title"><?php echo t('labs.consistency.title', 'Consistency System'); ?></h1>
    <p class="ln-editor-subtitle"><?php echo t('labs.consistency.desc', 'Lock style or character across multiple generations.'); ?></p>
  </header>

  <form id="labs-comfy-form" class="labs-form ln-t2i-form" method="post" action="#" onsubmit="return false;">
    <input type="hidden" name="tool" value="consistency">
    <div class="ln-t2i-grid">
      <div class="ln-t2i-main-col">
        <div class="ln-t2i-meta">
          <span id="labs-cost-label" class="ln-t2i-cost"><?php echo t('labs.cost_label', 'Cost: 5 KP'); ?></span>
        </div>
        <div class="ln-t2i-prompt-block ln-tool-block">
          <label class="form-label ln-t2i-label"><?php echo t('labs.consistency.base_prompt', 'Base Prompt'); ?></label>
          <textarea name="base_prompt" id="labs-base-prompt" class="knd-textarea form-control text-white ln-t2i-prompt-input" rows="2" maxlength="500" placeholder="Identity / style description (persistent)..."><?php echo htmlspecialchars($preloadFromJob['base_prompt'] ?? ''); ?></textarea>
          <label class="form-label ln-t2i-param-label mt-2"><?php echo t('labs.consistency.scene_prompt', 'Scene Prompt'); ?></label>
          <textarea name="scene_prompt" id="labs-scene-prompt" class="knd-textarea form-control text-white" rows="2" maxlength="500" placeholder="Scene / variation for this generation..."><?php echo htmlspecialchars($preloadFromJob['scene_prompt'] ?? ''); ?></textarea>
        </div>
        <div class="ln-t2i-canvas-zone">
          <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap" id="labs-result-wrapper">
            <div id="labs-result-preview" class="labs-result-preview ln-t2i-preview" style="min-height:380px;">
              <div id="labs-placeholder-tips" class="labs-placeholder-tips">
                <i class="fas fa-lock ln-t2i-placeholder-icon"></i>
                <p class="text-white-50 mb-1 small"><?php echo t('labs.consistency.tip1', 'Use a reference image from Canvas or Upload.'); ?></p>
                <p class="text-white-50 mb-0 small"><?php echo t('labs.consistency.tip2', 'Base prompt = persistent style/identity, Scene prompt = variation.'); ?></p>
              </div>
            </div>
          </div>
          <div class="ln-t2i-gen-area">
            <button type="submit" form="labs-comfy-form" class="ln-t2i-cta" id="generateBtn">
              <i class="fas fa-bolt me-2"></i><?php echo t('labs.consistency.generate', 'Generate'); ?>
            </button>
          </div>
          <div id="labs-result-actions" class="labs-result-actions-panel ln-t2i-actions" style="display:none;">
            <div class="labs-result-actions__header"><span class="labs-result-actions__title"><?php echo t('labs.result_actions', 'Output Actions'); ?></span></div>
            <div class="labs-result-actions__primary">
              <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i><?php echo t('ai.download'); ?></a>
              <a href="#" id="labs-generate-variations-btn" class="labs-action labs-action--primary"><i class="fas fa-images"></i><?php echo t('labs.generate_variations', 'Generate Variations'); ?></a>
              <a href="/labs?tool=upscale" id="labs-use-input-btn" class="labs-action labs-action--primary"><i class="fas fa-search-plus"></i><?php echo t('labs.send_to_upscale', 'Send to Upscale'); ?></a>
            </div>
            <div class="labs-result-actions__secondary">
              <a href="#" id="labs-use-style-btn" class="labs-action labs-action--secondary"><i class="fas fa-palette"></i><?php echo t('labs.consistency.use_style', 'Use as Style Reference'); ?></a>
              <a href="#" id="labs-use-char-btn" class="labs-action labs-action--secondary"><i class="fas fa-user"></i><?php echo t('labs.consistency.use_char', 'Use as Character Reference'); ?></a>
              <button type="button" id="labs-regenerate-btn" class="labs-action labs-action--secondary"><i class="fas fa-redo"></i><?php echo t('labs.regenerate', 'Regenerate'); ?></button>
            </div>
            <div class="labs-result-actions__more">
              <div class="dropdown">
                <button type="button" class="labs-action labs-action--more dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-ellipsis-h"></i><?php echo t('labs.more_actions', 'More'); ?></button>
                <ul class="dropdown-menu dropdown-menu-dark">
                  <li><button type="button" class="dropdown-item" id="labs-variations-btn"><i class="fas fa-random me-2"></i><?php echo t('labs.variations', 'Variations'); ?></button></li>
                </ul>
              </div>
            </div>
          </div>
          <div id="labs-status-panel" class="ln-t2i-status" style="display:none;">
            <div class="labs-stepper mb-2">
              <span class="labs-stepper-dot" data-step="queued"></span><span class="labs-stepper-line"></span>
              <span class="labs-stepper-dot" data-step="picked"></span><span class="labs-stepper-line"></span>
              <span class="labs-stepper-dot" data-step="generating"></span><span class="labs-stepper-line"></span>
              <span class="labs-stepper-dot" data-step="done"></span>
            </div>
            <p class="text-white-50 small mb-1"><?php echo t('labs.queued_leave', 'Generation is queued. You can leave this page.'); ?></p>
            <div class="d-flex align-items-center"><div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div><span id="labs-status-text"><?php echo t('ai.status.processing'); ?></span></div>
          </div>
          <div id="labs-error-msg" class="alert alert-danger ln-t2i-error" style="display:none;"></div>
          <div class="ln-t2i-details"><?php require __DIR__ . '/image_details_panel.php'; ?></div>
        </div>
      </div>
      <aside class="ln-t2i-params-col">
        <?php require __DIR__ . '/credits-card.php'; ?>
        <div class="ln-t2i-params-panel">
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label"><?php echo t('labs.consistency.mode', 'Mode'); ?></label>
            <select name="mode" id="labs-mode" class="knd-select form-select text-white">
              <option value="style" <?php echo $preloadMode === 'style' ? 'selected' : ''; ?>><?php echo t('labs.consistency.mode_style', 'Style Lock'); ?></option>
              <option value="character" <?php echo $preloadMode === 'character' ? 'selected' : ''; ?>><?php echo t('labs.consistency.mode_character', 'Character Lock'); ?></option>
              <option value="both" <?php echo $preloadMode === 'both' ? 'selected' : ''; ?>><?php echo t('labs.consistency.mode_both', 'Style + Character'); ?></option>
            </select>
          </div>
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label"><?php echo t('labs.consistency.reference_source', 'Reference Source'); ?></label>
            <div class="form-check"><input type="radio" name="reference_source" id="ref-recent" value="recent" class="form-check-input" <?php echo $refJobId > 0 ? 'checked' : ''; ?>><label for="ref-recent" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.ref_recent', 'Select from Recent Jobs'); ?></label></div>
            <div class="form-check"><input type="radio" name="reference_source" id="ref-upload" value="upload" class="form-check-input" <?php echo $refJobId <= 0 ? 'checked' : ''; ?>><label for="ref-upload" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.ref_upload', 'Upload Reference Image'); ?></label></div>
            <div id="labs-ref-recent-area" class="mt-2" style="display:<?php echo $refJobId > 0 ? 'block' : 'none'; ?>;">
              <select name="reference_job_id" id="labs-reference-job" class="knd-select form-select form-select-sm text-white">
                <option value=""><?php echo t('labs.consistency.select_job', 'Select a job...'); ?></option>
                <?php foreach ($refJobs as $j): $jid = $j['id'] ?? 0; $label = '#' . $jid . ' - ' . date('M j, H:i', strtotime($j['created_at'] ?? 'now')) . ' (' . ($j['tool'] ?? '') . ')'; ?>
                <option value="<?php echo (int)$jid; ?>" <?php echo $jid === $refJobId ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($refJobs)): ?><p class="text-white-50 small mt-1 mb-0"><?php echo t('labs.consistency.no_ref_jobs', 'No completed jobs. Use Canvas or Upscale first.'); ?></p><?php endif; ?>
            </div>
            <div id="labs-ref-upload-area" class="mt-2" style="display:<?php echo $refJobId <= 0 ? 'block' : 'none'; ?>;">
              <input type="file" name="reference_image" id="labs-reference-file" accept="image/jpeg,image/jpg,image/png,image/webp" class="knd-input form-control form-control-sm text-white">
              <div class="form-text text-white-50 small">PNG, JPG, WebP. Max 5MB, 2048px</div>
            </div>
          </div>
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label"><?php echo t('labs.negative_prompt', 'Negative Prompt'); ?></label>
            <input type="text" name="negative_prompt" class="knd-input form-control text-white" maxlength="500" value="<?php echo htmlspecialchars($preloadFromJob['negative_prompt'] ?? 'ugly, blurry, low quality'); ?>">
          </div>
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label small"><?php echo t('labs.consistency.lock_settings', 'Lock Settings'); ?></label>
            <div class="form-check form-check-inline"><input type="checkbox" name="lock_seed" id="labs-lock-seed" class="form-check-input" value="1"><label for="labs-lock-seed" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.lock_seed', 'Lock Seed'); ?></label></div>
            <div class="form-check form-check-inline"><input type="checkbox" name="inherit_model" id="labs-inherit-model" class="form-check-input" value="1" checked><label for="labs-inherit-model" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.inherit_model', 'Inherit Model'); ?></label></div>
            <div class="form-check form-check-inline"><input type="checkbox" name="inherit_resolution" id="labs-inherit-res" class="form-check-input" value="1" checked><label for="labs-inherit-res" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.inherit_resolution', 'Inherit Resolution'); ?></label></div>
            <div class="form-check form-check-inline"><input type="checkbox" name="inherit_sampling" id="labs-inherit-sampling" class="form-check-input" value="1" checked><label for="labs-inherit-sampling" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.inherit_sampling', 'Inherit Sampling'); ?></label></div>
          </div>
          <div class="ln-t2i-param-group">
            <button type="button" class="btn btn-link btn-sm text-white-50 p-0" id="labs-advanced-toggle" data-bs-toggle="collapse" data-bs-target="#labs-advanced"><i class="fas fa-chevron-down me-1"></i><?php echo t('labs.advanced', 'Advanced'); ?></button>
            <div class="collapse mt-2" id="labs-advanced">
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label text-white-50 small"><?php echo t('labs.width', 'Width'); ?></label><input type="number" name="width" class="knd-input form-control form-control-sm text-white" value="<?php echo (int)($preloadFromJob['width'] ?? 1024); ?>" min="256" max="2048" step="8"></div>
                <div class="col-6"><label class="form-label text-white-50 small"><?php echo t('labs.height', 'Height'); ?></label><input type="number" name="height" class="knd-input form-control form-control-sm text-white" value="<?php echo (int)($preloadFromJob['height'] ?? 1024); ?>" min="256" max="2048" step="8"></div>
                <div class="col-6"><label class="form-label text-white-50 small"><?php echo t('labs.seed', 'Seed'); ?></label><input type="number" name="seed" class="knd-input form-control form-control-sm text-white" placeholder="Random" value="<?php echo isset($preloadFromJob['seed']) && $preloadFromJob['seed'] !== '' && $preloadFromJob['seed'] !== null ? (int)$preloadFromJob['seed'] : ''; ?>"></div>
                <div class="col-6"><label class="form-label text-white-50 small"><?php echo t('labs.steps', 'Steps'); ?></label><input type="number" name="steps" class="knd-input form-control form-control-sm text-white" value="<?php echo (int)($preloadFromJob['steps'] ?? 28); ?>" min="1" max="100"></div>
                <div class="col-6"><label class="form-label text-white-50 small"><?php echo t('labs.cfg', 'CFG'); ?></label><input type="number" name="cfg" class="knd-input form-control form-control-sm text-white" value="<?php echo (float)($preloadFromJob['cfg'] ?? 7); ?>" min="1" max="30" step="0.5"></div>
                <div class="col-6"><label class="form-label text-white-50 small"><?php echo t('labs.sampler', 'Sampler'); ?></label><select name="sampler" class="knd-select form-select form-select-sm text-white"><?php $ps = $preloadFromJob['sampler'] ?? 'dpmpp_2m'; ?><option value="dpmpp_2m" <?php echo $ps === 'dpmpp_2m' ? 'selected' : ''; ?>>DPM++ 2M</option><option value="euler" <?php echo $ps === 'euler' ? 'selected' : ''; ?>>Euler</option><option value="euler_ancestral" <?php echo $ps === 'euler_ancestral' ? 'selected' : ''; ?>>Euler Ancestral</option><option value="ddim" <?php echo $ps === 'ddim' ? 'selected' : ''; ?>>DDIM</option><option value="lcm" <?php echo $ps === 'lcm' ? 'selected' : ''; ?>>LCM</option></select></div>
              </div>
              <label class="form-label text-white-50 small"><?php echo t('labs.model', 'Model'); ?></label>
              <select name="model" class="knd-select form-select form-select-sm text-white"><option value="juggernaut_v8" selected>Juggernaut XL v8</option><option value="sd_xl_base">SD XL Base</option><option value="waiANINSFWPONY">PONY XL</option></select>
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
(function() {
  var refRecent = document.getElementById('ref-recent'); var refUpload = document.getElementById('ref-upload');
  var areaRecent = document.getElementById('labs-ref-recent-area'); var areaUpload = document.getElementById('labs-ref-upload-area');
  if (refRecent) refRecent.addEventListener('change', function() { if (areaRecent) areaRecent.style.display = 'block'; if (areaUpload) areaUpload.style.display = 'none'; });
  if (refUpload) refUpload.addEventListener('change', function() { if (areaRecent) areaRecent.style.display = 'none'; if (areaUpload) areaUpload.style.display = 'block'; });
  var useStyleBtn = document.getElementById('labs-use-style-btn'); var useCharBtn = document.getElementById('labs-use-char-btn'); var genVarBtn = document.getElementById('labs-generate-variations-btn');
  function goConsistency(mode) {
    var img = document.querySelector('#labs-result-preview img[data-job-id]');
    if (img) { var jid = img.getAttribute('data-job-id'); var m = mode || img.getAttribute('data-job-mode') || 'style'; if (jid) window.location.href = '/labs?tool=consistency&reference_job_id=' + encodeURIComponent(jid) + '&mode=' + encodeURIComponent(m); }
  }
  if (useStyleBtn) useStyleBtn.addEventListener('click', function(e) { e.preventDefault(); goConsistency('style'); });
  if (useCharBtn) useCharBtn.addEventListener('click', function(e) { e.preventDefault(); goConsistency('character'); });
  if (genVarBtn) genVarBtn.addEventListener('click', function(e) { e.preventDefault(); goConsistency(); });
  function run() {
    if (typeof KNDLabs !== 'undefined') KNDLabs.init({ formId: 'labs-comfy-form', jobType: 'consistency', costLabelId: 'labs-cost-label', pricingKey: 'consistency', balanceEl: '#labs-balance', apiConsistency: '/api/labs/consistency_create.php' });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
