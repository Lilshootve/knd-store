<?php
/**
 * Consistency — studio left panel + full-width canvas (matches 3D Vertex layout).
 */
$refJobId = isset($refJobId) ? (int) $refJobId : 0;
$preloadMode = isset($preloadMode) ? $preloadMode : 'style';
$preloadFromJob = isset($preloadFromJob) && is_array($preloadFromJob) ? $preloadFromJob : [];
$refJobs = isset($refJobs) && is_array($refJobs) ? $refJobs : [];
$balance = isset($balance) ? (int) $balance : 0;
?>
<span id="labs-balance" class="d-none"><?php echo number_format($balance); ?></span>

<div class="ln-t2i-workspace ln-tool-workspace knd-labs-consistency-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title"><?php echo htmlspecialchars(t('labs.consistency.title', 'Consistency System'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="ln-editor-subtitle"><?php echo htmlspecialchars(t('labs.consistency.desc', 'Lock style or character across multiple generations.'), ENT_QUOTES, 'UTF-8'); ?></p>
  </header>

  <form id="labs-comfy-form" class="labs-form ln-t2i-form" method="post" action="#" onsubmit="return false;">
    <input type="hidden" name="tool" value="consistency">
    <div class="ln-t2i-grid">
      <aside class="ln-t2i-params-col knd-labs-studio-controls-panel" aria-label="<?php echo htmlspecialchars(t('labs.consistency.settings_aria', 'Consistency settings'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="knd-labs-panel-header">
          <span class="knd-labs-panel-header-icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
          <span class="knd-labs-panel-title"><?php echo htmlspecialchars(t('labs.consistency.panel_lock', 'Style'), ENT_QUOTES, 'UTF-8'); ?> <span class="knd-labs-panel-title-accent"><?php echo htmlspecialchars(t('labs.consistency.panel_lock_accent', 'Lock'), ENT_QUOTES, 'UTF-8'); ?></span></span>
        </div>

        <span id="labs-cost-label" class="knd-labs-sr-only"></span>

        <div class="knd-labs-ctrl-field">
          <label class="knd-labs-ctrl-label" for="labs-base-prompt"><?php echo htmlspecialchars(t('labs.consistency.base_prompt', 'Base prompt'), ENT_QUOTES, 'UTF-8'); ?></label>
          <textarea name="base_prompt" id="labs-base-prompt" class="knd-labs-ctrl-textarea" rows="3" maxlength="500" placeholder="<?php echo htmlspecialchars(t('labs.consistency.base_ph', 'Identity / style (persistent)…'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($preloadFromJob['base_prompt'] ?? ''); ?></textarea>
        </div>
        <div class="knd-labs-ctrl-field">
          <label class="knd-labs-ctrl-label" for="labs-scene-prompt"><?php echo htmlspecialchars(t('labs.consistency.scene_prompt', 'Scene prompt'), ENT_QUOTES, 'UTF-8'); ?></label>
          <textarea name="scene_prompt" id="labs-scene-prompt" class="knd-labs-ctrl-textarea" rows="3" maxlength="500" placeholder="<?php echo htmlspecialchars(t('labs.consistency.scene_ph', 'Scene / variation for this generation…'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($preloadFromJob['scene_prompt'] ?? ''); ?></textarea>
        </div>

        <div class="knd-labs-ctrl-divider"></div>

        <div class="knd-labs-ctrl-field">
          <label class="knd-labs-ctrl-label" for="labs-mode"><?php echo htmlspecialchars(t('labs.consistency.mode', 'Mode'), ENT_QUOTES, 'UTF-8'); ?></label>
          <select name="mode" id="labs-mode" class="knd-labs-ctrl-select">
            <option value="style" <?php echo $preloadMode === 'style' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('labs.consistency.mode_style', 'Style lock'), ENT_QUOTES, 'UTF-8'); ?></option>
            <option value="character" <?php echo $preloadMode === 'character' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('labs.consistency.mode_character', 'Character lock'), ENT_QUOTES, 'UTF-8'); ?></option>
            <option value="both" <?php echo $preloadMode === 'both' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('labs.consistency.mode_both', 'Style + character'), ENT_QUOTES, 'UTF-8'); ?></option>
          </select>
        </div>

        <div class="knd-labs-ctrl-field">
          <span class="knd-labs-ctrl-label"><?php echo htmlspecialchars(t('labs.consistency.reference_source', 'Reference source'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="knd-labs-ref-radios">
            <div class="form-check">
              <input type="radio" name="reference_source" id="ref-recent" value="recent" class="form-check-input" <?php echo $refJobId > 0 ? 'checked' : ''; ?>>
              <label for="ref-recent" class="form-check-label"><?php echo htmlspecialchars(t('labs.consistency.ref_recent', 'Select from recent jobs'), ENT_QUOTES, 'UTF-8'); ?></label>
            </div>
            <div class="form-check">
              <input type="radio" name="reference_source" id="ref-upload" value="upload" class="form-check-input" <?php echo $refJobId <= 0 ? 'checked' : ''; ?>>
              <label for="ref-upload" class="form-check-label"><?php echo htmlspecialchars(t('labs.consistency.ref_upload', 'Upload reference image'), ENT_QUOTES, 'UTF-8'); ?></label>
            </div>
          </div>
          <div id="labs-ref-recent-area" class="mt-2" style="display:<?php echo $refJobId > 0 ? 'block' : 'none'; ?>;">
            <select name="reference_job_id" id="labs-reference-job" class="knd-labs-ctrl-select">
              <option value=""><?php echo htmlspecialchars(t('labs.consistency.select_job', 'Select a job…'), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php foreach ($refJobs as $j): $jid = $j['id'] ?? 0; $label = '#' . $jid . ' — ' . date('M j, H:i', strtotime($j['created_at'] ?? 'now')) . ' (' . ($j['tool'] ?? '') . ')'; ?>
              <option value="<?php echo (int) $jid; ?>" <?php echo $jid === $refJobId ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($refJobs)): ?>
              <p class="knd-labs-file-pick-hint mt-1"><?php echo htmlspecialchars(t('labs.consistency.no_ref_jobs', 'No completed jobs yet. Use Canvas or Upscale first.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
          </div>
          <div id="labs-ref-upload-area" class="mt-2" style="display:<?php echo $refJobId <= 0 ? 'block' : 'none'; ?>;">
            <div class="knd-labs-file-pick">
              <input type="file" name="reference_image" id="labs-reference-file" accept="image/jpeg,image/jpg,image/png,image/webp" class="knd-labs-file-pick-native">
              <button type="button" class="knd-labs-file-pick-btn" id="labs-ref-file-trigger"><?php echo htmlspecialchars(t('labs.choose_file', 'Choose file'), ENT_QUOTES, 'UTF-8'); ?></button>
              <span class="knd-labs-file-pick-name" id="labs-ref-file-label"><?php echo htmlspecialchars(t('labs.no_file_chosen', 'No file chosen'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <p class="knd-labs-file-pick-hint"><?php echo htmlspecialchars(t('labs.consistency.upload_hint', 'PNG, JPG, WebP · max 5MB · up to 2048px'), ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>

        <div class="knd-labs-ctrl-field">
          <label class="knd-labs-ctrl-label" for="labs-consistency-neg"><?php echo htmlspecialchars(t('labs.negative_prompt', 'Negative prompt'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input type="text" name="negative_prompt" id="labs-consistency-neg" class="knd-labs-ctrl-input" maxlength="500" value="<?php echo htmlspecialchars($preloadFromJob['negative_prompt'] ?? 'ugly, blurry, low quality'); ?>">
        </div>

        <div class="knd-labs-ctrl-field">
          <span class="knd-labs-ctrl-label"><?php echo htmlspecialchars(t('labs.consistency.lock_settings', 'Lock settings'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="knd-labs-lock-row">
            <div class="form-check form-check-inline">
              <input type="checkbox" name="lock_seed" id="labs-lock-seed" class="form-check-input" value="1">
              <label for="labs-lock-seed" class="form-check-label text-white-50 small"><?php echo htmlspecialchars(t('labs.consistency.lock_seed', 'Lock seed'), ENT_QUOTES, 'UTF-8'); ?></label>
            </div>
            <div class="form-check form-check-inline">
              <input type="checkbox" name="inherit_model" id="labs-inherit-model" class="form-check-input" value="1" checked>
              <label for="labs-inherit-model" class="form-check-label text-white-50 small"><?php echo htmlspecialchars(t('labs.consistency.inherit_model', 'Inherit model'), ENT_QUOTES, 'UTF-8'); ?></label>
            </div>
            <div class="form-check form-check-inline">
              <input type="checkbox" name="inherit_resolution" id="labs-inherit-res" class="form-check-input" value="1" checked>
              <label for="labs-inherit-res" class="form-check-label text-white-50 small"><?php echo htmlspecialchars(t('labs.consistency.inherit_resolution', 'Inherit resolution'), ENT_QUOTES, 'UTF-8'); ?></label>
            </div>
            <div class="form-check form-check-inline">
              <input type="checkbox" name="inherit_sampling" id="labs-inherit-sampling" class="form-check-input" value="1" checked>
              <label for="labs-inherit-sampling" class="form-check-label text-white-50 small"><?php echo htmlspecialchars(t('labs.consistency.inherit_sampling', 'Inherit sampling'), ENT_QUOTES, 'UTF-8'); ?></label>
            </div>
          </div>
        </div>

        <button type="button" class="knd-labs-advanced-toggle collapsed" id="labs-advanced-toggle" data-bs-toggle="collapse" data-bs-target="#labs-advanced" aria-expanded="false" aria-controls="labs-advanced">
          <i class="fas fa-chevron-down knd-labs-advanced-toggle-icon" aria-hidden="true"></i><?php echo htmlspecialchars(t('labs.advanced', 'Advanced'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <div class="collapse knd-labs-advanced-collapse" id="labs-advanced">
          <div class="row g-2 mb-2 px-2">
            <div class="col-6">
              <label class="knd-labs-ctrl-label" for="c-w"><?php echo htmlspecialchars(t('labs.width', 'Width'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input type="number" name="width" id="c-w" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm" value="<?php echo (int) ($preloadFromJob['width'] ?? 1024); ?>" min="256" max="2048" step="8">
            </div>
            <div class="col-6">
              <label class="knd-labs-ctrl-label" for="c-h"><?php echo htmlspecialchars(t('labs.height', 'Height'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input type="number" name="height" id="c-h" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm" value="<?php echo (int) ($preloadFromJob['height'] ?? 1024); ?>" min="256" max="2048" step="8">
            </div>
            <div class="col-6">
              <label class="knd-labs-ctrl-label" for="c-seed"><?php echo htmlspecialchars(t('labs.seed', 'Seed'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input type="number" name="seed" id="c-seed" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm" placeholder="<?php echo htmlspecialchars(t('labs.vertex.random', 'Random'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo isset($preloadFromJob['seed']) && $preloadFromJob['seed'] !== '' && $preloadFromJob['seed'] !== null ? (int) $preloadFromJob['seed'] : ''; ?>">
            </div>
            <div class="col-6">
              <label class="knd-labs-ctrl-label" for="c-steps"><?php echo htmlspecialchars(t('labs.steps', 'Steps'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input type="number" name="steps" id="c-steps" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm" value="<?php echo (int) ($preloadFromJob['steps'] ?? 28); ?>" min="1" max="100">
            </div>
            <div class="col-6">
              <label class="knd-labs-ctrl-label" for="c-cfg"><?php echo htmlspecialchars(t('labs.cfg', 'CFG'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input type="number" name="cfg" id="c-cfg" class="knd-labs-ctrl-input knd-labs-ctrl-input-sm" value="<?php echo (float) ($preloadFromJob['cfg'] ?? 7); ?>" min="1" max="30" step="0.5">
            </div>
            <div class="col-6">
              <label class="knd-labs-ctrl-label" for="c-sampler"><?php echo htmlspecialchars(t('labs.sampler', 'Sampler'), ENT_QUOTES, 'UTF-8'); ?></label>
              <?php $ps = $preloadFromJob['sampler'] ?? 'dpmpp_2m'; ?>
              <select name="sampler" id="c-sampler" class="knd-labs-ctrl-select knd-labs-ctrl-input-sm">
                <option value="dpmpp_2m" <?php echo $ps === 'dpmpp_2m' ? 'selected' : ''; ?>>DPM++ 2M</option>
                <option value="euler" <?php echo $ps === 'euler' ? 'selected' : ''; ?>>Euler</option>
                <option value="euler_ancestral" <?php echo $ps === 'euler_ancestral' ? 'selected' : ''; ?>>Euler ancestral</option>
                <option value="ddim" <?php echo $ps === 'ddim' ? 'selected' : ''; ?>>DDIM</option>
                <option value="lcm" <?php echo $ps === 'lcm' ? 'selected' : ''; ?>>LCM</option>
              </select>
            </div>
          </div>
          <div class="px-2 pb-2">
            <label class="knd-labs-ctrl-label" for="c-model"><?php echo htmlspecialchars(t('labs.model', 'Model'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select name="model" id="c-model" class="knd-labs-ctrl-select knd-labs-ctrl-input-sm">
              <option value="juggernaut_v8" selected>Juggernaut XL v8</option>
              <option value="sd_xl_base">SD XL base</option>
              <option value="waiANINSFWPONY">PONY XL</option>
            </select>
          </div>
        </div>

        <div class="knd-labs-ctrl-divider"></div>

        <div class="knd-labs-controls-balance">
          <span class="knd-labs-controls-balance-label"><?php echo htmlspecialchars(t('labs.balance', 'Balance'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="knd-labs-controls-balance-val"><?php echo number_format($balance); ?> <span class="knd-labs-kp">KP</span></span>
          <a href="/support-credits.php" class="knd-labs-controls-balance-link"><?php echo htmlspecialchars(t('labs.get_credits', 'Get credits'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>

        <button type="submit" class="knd-labs-btn-generate" id="generateBtn">
          <i class="fas fa-bolt" aria-hidden="true"></i>
          <span><?php echo htmlspecialchars(t('labs.consistency.generate', 'Generate'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="knd-labs-btn-credits" aria-hidden="true"><span class="knd-labs-btn-credits-dot">●</span> 5</span>
        </button>
      </aside>

      <div class="ln-t2i-main-col knd-labs-vertex-main">
        <div class="ln-t2i-canvas-zone">
          <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap knd-labs-preview-with-deco" id="labs-result-wrapper">
            <?php require __DIR__ . '/studio_canvas_deco.php'; ?>
            <?php
            $labsStudioPhTitle = t('labs.studio.consistency_empty_title', 'Consistency');
            $labsStudioPhSub = t('labs.studio.consistency_empty_sub', 'Keep style or character across generations.');
            $labsStudioTipsIcon = 'fa-lock';
            $labsStudioTipsLine1 = t('labs.consistency.tip1', 'Use a reference from recent jobs or upload an image.');
            $labsStudioTipsLine2 = t('labs.consistency.tip2', 'Base prompt stays fixed; scene prompt changes each output.');
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
            <div class="labs-result-actions__header"><span class="labs-result-actions__title"><?php echo htmlspecialchars(t('labs.result_actions', 'Output actions'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="labs-result-actions__primary">
              <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i><?php echo htmlspecialchars(t('ai.download', 'Download'), ENT_QUOTES, 'UTF-8'); ?></a>
              <a href="#" id="labs-generate-variations-btn" class="labs-action labs-action--primary"><i class="fas fa-images"></i><?php echo htmlspecialchars(t('labs.generate_variations', 'Generate variations'), ENT_QUOTES, 'UTF-8'); ?></a>
              <a href="/labs?tool=upscale" id="labs-use-input-btn" class="labs-action labs-action--primary"><i class="fas fa-search-plus"></i><?php echo htmlspecialchars(t('labs.send_to_upscale', 'Send to upscale'), ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <div class="labs-result-actions__secondary">
              <a href="#" id="labs-use-style-btn" class="labs-action labs-action--secondary"><i class="fas fa-palette"></i><?php echo htmlspecialchars(t('labs.consistency.use_style', 'Use as style reference'), ENT_QUOTES, 'UTF-8'); ?></a>
              <a href="#" id="labs-use-char-btn" class="labs-action labs-action--secondary"><i class="fas fa-user"></i><?php echo htmlspecialchars(t('labs.consistency.use_char', 'Use as character reference'), ENT_QUOTES, 'UTF-8'); ?></a>
              <button type="button" id="labs-regenerate-btn" class="labs-action labs-action--secondary"><i class="fas fa-redo"></i><?php echo htmlspecialchars(t('labs.regenerate', 'Regenerate'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <div class="labs-result-actions__more">
              <div class="dropdown">
                <button type="button" class="labs-action labs-action--more dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-ellipsis-h"></i><?php echo htmlspecialchars(t('labs.more_actions', 'More'), ENT_QUOTES, 'UTF-8'); ?></button>
                <ul class="dropdown-menu dropdown-menu-dark">
                  <li><button type="button" class="dropdown-item" id="labs-variations-btn"><i class="fas fa-random me-2"></i><?php echo htmlspecialchars(t('labs.variations', 'Variations'), ENT_QUOTES, 'UTF-8'); ?></button></li>
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
            <p class="text-white-50 small mb-1"><?php echo htmlspecialchars(t('labs.queued_leave', 'Generation is queued. You can leave this page.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="d-flex align-items-center"><div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div><span id="labs-status-text"><?php echo htmlspecialchars(t('ai.status.processing'), ENT_QUOTES, 'UTF-8'); ?></span></div>
          </div>
          <div id="labs-error-msg" class="alert alert-danger ln-t2i-error" style="display:none;"></div>
          <div class="ln-t2i-details"><?php require __DIR__ . '/image_details_panel.php'; ?></div>
        </div>
      </div>
    </div>
  </form>
</div>

<script src="/assets/js/labs-lazy-history.js" defer></script>
<script src="/assets/js/kndlabs.js?v=<?php echo file_exists(__DIR__ . '/../../assets/js/kndlabs.js') ? filemtime(__DIR__ . '/../../assets/js/kndlabs.js') : time(); ?>"></script>
<script>
(function() {
  var refRecent = document.getElementById('ref-recent');
  var refUpload = document.getElementById('ref-upload');
  var areaRecent = document.getElementById('labs-ref-recent-area');
  var areaUpload = document.getElementById('labs-ref-upload-area');
  if (refRecent) refRecent.addEventListener('change', function() { if (areaRecent) areaRecent.style.display = 'block'; if (areaUpload) areaUpload.style.display = 'none'; });
  if (refUpload) refUpload.addEventListener('change', function() { if (areaRecent) areaRecent.style.display = 'none'; if (areaUpload) areaUpload.style.display = 'block'; });

  var fileIn = document.getElementById('labs-reference-file');
  var trig = document.getElementById('labs-ref-file-trigger');
  var label = document.getElementById('labs-ref-file-label');
  var noFile = <?php echo json_encode(t('labs.no_file_chosen', 'No file chosen')); ?>;
  if (trig && fileIn) trig.addEventListener('click', function() { fileIn.click(); });
  if (fileIn && label) fileIn.addEventListener('change', function() {
    var f = this.files && this.files[0];
    label.textContent = f ? f.name : noFile;
  });

  var useStyleBtn = document.getElementById('labs-use-style-btn');
  var useCharBtn = document.getElementById('labs-use-char-btn');
  var genVarBtn = document.getElementById('labs-generate-variations-btn');
  function goConsistency(mode) {
    var img = document.querySelector('#labs-result-preview img[data-job-id]');
    if (img) {
      var jid = img.getAttribute('data-job-id');
      var m = mode || img.getAttribute('data-job-mode') || 'style';
      if (jid) window.location.href = '/labs?tool=consistency&reference_job_id=' + encodeURIComponent(jid) + '&mode=' + encodeURIComponent(m);
    }
  }
  if (useStyleBtn) useStyleBtn.addEventListener('click', function(e) { e.preventDefault(); goConsistency('style'); });
  if (useCharBtn) useCharBtn.addEventListener('click', function(e) { e.preventDefault(); goConsistency('character'); });
  if (genVarBtn) genVarBtn.addEventListener('click', function(e) { e.preventDefault(); goConsistency(); });

  function run() {
    if (typeof KNDLabs !== 'undefined') {
      KNDLabs.init({ formId: 'labs-comfy-form', jobType: 'consistency', costLabelId: 'labs-cost-label', pricingKey: 'consistency', balanceEl: '#labs-balance', apiConsistency: '/api/labs/consistency_create.php' });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
