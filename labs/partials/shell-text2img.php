<?php
/**
 * Text2Img tool content for Labs app shell. Workspace layout: main (prompt + canvas) | params panel.
 * Same form/IDs as labs/text-to-image.php for kndlabs.js. Expects: $balance, $providerFilter (optional)
 */
$providerFilter = isset($providerFilter) ? $providerFilter : '';
?>
<span id="labs-balance" class="d-none"><?php echo number_format($balance); ?></span>

<div class="ln-t2i-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title"><?php echo t('labs.canvas.title', 'Canvas'); ?></h1>
    <p class="ln-editor-subtitle"><?php echo t('labs.canvas.card_desc', 'Main AI creation workspace. Generate, refine and direct visual output.'); ?></p>
  </header>

  <form id="labs-comfy-form" class="labs-form ln-t2i-form" method="post" action="#" onsubmit="return false;">
    <input type="hidden" name="tool" value="text2img">
    <div class="ln-t2i-grid">
      <div class="ln-t2i-main-col">
        <?php require __DIR__ . '/shell-text2img-form-main.php'; ?>
        <div class="ln-t2i-canvas-zone">
          <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap knd-labs-preview-with-deco" id="labs-result-wrapper">
            <?php require __DIR__ . '/studio_canvas_deco.php'; ?>
            <?php
            $labsStudioPhTitle = t('labs.studio.canvas_empty_title', '¿Listo para crear?');
            $labsStudioPhSub = t('labs.studio.canvas_empty_sub', 'Describe tu imagen y pulsa Generar');
            $labsStudioTipsIcon = 'fa-wand-magic-sparkles';
            $labsStudioTipsLine1 = t('labs.tip_prompt', 'Use 1 subject + 1 style + 1 lighting');
            $labsStudioTipsLine2 = t('labs.tip_example', 'e.g. "Warrior, oil painting, golden hour"');
            $labsStudioGradientSuffix = '';
            ob_start();
            require __DIR__ . '/studio_canvas_placeholder_inner.php';
            $kndLabsPlaceholderHtml = ob_get_clean();
            $labsStudioGradientSuffix = '_tmpl';
            ob_start();
            require __DIR__ . '/studio_canvas_placeholder_inner.php';
            $kndLabsPlaceholderTmplHtml = ob_get_clean();
            ?>
            <div id="labs-result-preview" class="labs-result-preview ln-t2i-preview" style="min-height:380px;"><?php echo $kndLabsPlaceholderHtml; ?></div>
            <template id="knd-labs-studio-placeholder-tmpl"><?php echo $kndLabsPlaceholderTmplHtml; ?></template>
          </div>
          <div class="ln-t2i-gen-area">
            <button type="submit" form="labs-comfy-form" class="ln-t2i-cta" id="generateBtn">
              <i class="fas fa-wand-magic-sparkles me-2"></i><?php echo t('ai.text2img.generate'); ?>
            </button>
          </div>
          <div id="labs-result-actions" class="labs-result-actions-panel ln-t2i-actions" style="display:none;">
            <div class="labs-result-actions__header">
              <span class="labs-result-actions__title"><?php echo t('labs.result_actions', 'Output Actions'); ?></span>
            </div>
            <div class="labs-result-actions__primary">
              <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i><?php echo t('ai.download'); ?></a>
              <a href="#" id="labs-generate-variations-btn" class="labs-action labs-action--primary"><i class="fas fa-images"></i><?php echo t('labs.generate_variations', 'Generate Variations'); ?></a>
              <a href="/labs?tool=upscale" id="labs-use-input-btn" class="labs-action labs-action--primary"><i class="fas fa-search-plus"></i><?php echo t('labs.send_to_upscale', 'Send to Upscale'); ?></a>
              <a href="/labs?tool=remove-bg" id="labs-remove-bg-btn" class="labs-action labs-action--primary"><i class="fas fa-eraser"></i><?php echo t('labs.send_to_remove_bg', 'Remove Background'); ?></a>
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
              <span class="labs-stepper-dot" data-step="queued"></span>
              <span class="labs-stepper-line"></span>
              <span class="labs-stepper-dot" data-step="picked"></span>
              <span class="labs-stepper-line"></span>
              <span class="labs-stepper-dot" data-step="generating"></span>
              <span class="labs-stepper-line"></span>
              <span class="labs-stepper-dot" data-step="done"></span>
            </div>
            <p class="text-white-50 small mb-1"><?php echo t('labs.queued_leave', 'Generation is queued. You can leave this page.'); ?></p>
            <div class="d-flex align-items-center">
              <div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div>
              <span id="labs-status-text"><?php echo t('ai.status.processing'); ?></span>
            </div>
          </div>
          <div id="labs-error-msg" class="alert alert-danger ln-t2i-error" style="display:none;"></div>
          <div class="ln-t2i-details">
            <?php require __DIR__ . '/image_details_panel.php'; ?>
          </div>
        </div>
      </div>
      <aside class="ln-t2i-params-col">
        <?php require __DIR__ . '/credits-card.php'; ?>
        <?php require __DIR__ . '/shell-text2img-form-params.php'; ?>
      </aside>
    </div>
  </form>
</div>

<script src="/assets/js/labs-lazy-history.js" defer></script>
<?php $kndlabsJs = __DIR__ . '/../../assets/js/kndlabs.js'; ?>
<script src="/assets/js/kndlabs.js?v=<?php echo file_exists($kndlabsJs) ? filemtime($kndlabsJs) : time(); ?>"></script>
<script>
(function() {
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

  var form = document.getElementById('labs-comfy-form');
  if (form) {
    form.querySelectorAll('.preset-neg-btn').forEach(function(btn) {
      btn.onclick = function() {
        var inp = form.querySelector('[name="negative_prompt"]');
        if (inp && btn.dataset.value) inp.value = btn.dataset.value;
      };
    });
    var ipEn = document.getElementById('ipadapter-enabled');
    var ipFields = document.getElementById('ipadapter-fields');
    var ipMode = document.getElementById('ipadapter-mode');
    var ipWeight = form.querySelector('[name="ipadapter_weight"]');
    if (ipEn) ipEn.addEventListener('change', function() {
      if (ipFields) ipFields.style.display = ipEn.checked ? 'block' : 'none';
    });
    if (ipMode && ipWeight) ipMode.addEventListener('change', function() {
      if (ipMode.value === 'style') ipWeight.value = '0.85';
      else if (ipMode.value === 'composition') ipWeight.value = '0.65';
      else ipWeight.value = '0.70';
    });
    var cnEn = document.getElementById('controlnet-enabled');
    var cnFields = document.getElementById('controlnet-fields');
    var cnMode = document.getElementById('controlnet-control-mode');
    var cnStrength = form.querySelector('[name="controlnet_strength"]');
    var cnEnd = form.querySelector('[name="controlnet_end_at"]');
    if (cnEn) cnEn.addEventListener('change', function() {
      if (cnFields) cnFields.style.display = cnEn.checked ? 'block' : 'none';
    });
    if (cnMode && cnStrength && cnEnd) cnMode.addEventListener('change', function() {
      if (cnMode.value === 'prompt_strict') { cnStrength.value = '0.60'; cnEnd.value = '0.80'; }
      else if (cnMode.value === 'control_strict') { cnStrength.value = '0.90'; cnEnd.value = '1'; }
      else { cnStrength.value = '0.75'; cnEnd.value = '0.80'; }
    });
  }
  function run() {
    if (typeof KNDLabs !== 'undefined') {
      KNDLabs.init({ formId: 'labs-comfy-form', jobType: 'text2img', costLabelId: 'labs-cost-label', pricingKey: 'text2img', qualitySelectId: 'labs-quality-select', balanceEl: '#labs-balance' });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
  function scheduleLazyHistory() {
    var fn = function() {
      if (window.LabsLazyHistory && window.LabsLazyHistory.load) {
        window.LabsLazyHistory.load({ tool: 'text2img', limit: 5, toolLabel: 'Canvas', hasProviderFilter: true, provider: '<?php echo addslashes($providerFilter); ?>' });
      }
    };
    if (window.requestIdleCallback) requestIdleCallback(fn, { timeout: 1500 }); else setTimeout(fn, 100);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scheduleLazyHistory); else scheduleLazyHistory();
})();
</script>
