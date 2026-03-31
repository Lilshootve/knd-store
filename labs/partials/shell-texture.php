<?php
/**
 * Texture Lab - Labs shell. Dedicated pipeline: text / image / image+prompt to texture.
 * Uses api/labs/generate.php with tool=texture (10 KP). Workflows: texture_api.json / texture_img2img_api.json.
 */
$balance = isset($balance) ? (int) $balance : 0;
$costKp = 10;
$texturePresets = [
    'stone' => 'Seamless stone texture, rough surface',
    'wood' => 'Seamless wood grain texture',
    'brick' => 'Seamless brick wall texture',
    'metal' => 'Seamless metal surface, brushed steel',
    'fabric' => 'Seamless fabric texture, cloth weave',
    'concrete' => 'Seamless concrete texture, rough',
    'marble' => 'Seamless marble texture, veins',
    'sci-fi panel' => 'Seamless sci-fi panel, tech surface',
    'realistic game-ready' => 'Seamless PBR-ready game texture, realistic',
    'stylized' => 'Seamless stylized game texture, hand-painted',
];
?>
<span id="labs-balance" class="d-none"><?php echo number_format($balance); ?></span>

<div class="ln-t2i-workspace ln-tool-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title"><?php echo t('ai.texture.title', 'Texture Lab'); ?></h1>
    <p class="ln-editor-subtitle"><?php echo t('ai.texture.subtitle', 'Generate seamless textures for 3D and games. Text, image, or both.'); ?></p>
  </header>

  <form id="labs-texture-form" class="labs-form ln-t2i-form" method="post" action="#" enctype="multipart/form-data" onsubmit="return false;">
    <input type="hidden" name="tool" value="texture">
    <input type="hidden" name="texture_mode" id="labs-texture-mode" value="text">
    <div class="ln-t2i-grid">
      <div class="ln-t2i-main-col">
        <div class="ln-t2i-meta">
          <span class="ln-t2i-cost"><?php echo t('labs.cost_label', 'Cost'); ?>: <strong><?php echo $costKp; ?></strong> KP</span>
          <span class="ln-t2i-balance-after"><?php echo t('labs.balance_after', 'Balance'); ?>: <strong id="labs-texture-balance"><?php echo number_format($balance); ?></strong> KP</span>
        </div>

        <div class="ln-t2i-param-group mb-3">
          <label class="ln-t2i-param-label"><?php echo t('labs.texture.mode', 'Input mode'); ?></label>
          <div class="d-flex flex-wrap gap-2">
            <label class="knd-chip-wrapper"><input type="radio" name="texture_mode_radio" value="text" class="d-none" checked> <span class="knd-chip texture-mode-chip active" data-mode="text"><?php echo t('labs.texture.mode_text', 'Text to Texture'); ?></span></label>
            <label class="knd-chip-wrapper"><input type="radio" name="texture_mode_radio" value="image" class="d-none"> <span class="knd-chip texture-mode-chip" data-mode="image"><?php echo t('labs.texture.mode_image', 'Image to Texture'); ?></span></label>
            <label class="knd-chip-wrapper"><input type="radio" name="texture_mode_radio" value="image_prompt" class="d-none"> <span class="knd-chip texture-mode-chip" data-mode="image_prompt"><?php echo t('labs.texture.mode_image_prompt', 'Image + Prompt'); ?></span></label>
          </div>
        </div>

        <div class="ln-t2i-prompt-block ln-tool-block" id="labs-texture-prompt-block">
          <label class="form-label ln-t2i-label"><?php echo t('ai.text2img.prompt', 'Prompt'); ?></label>
          <textarea name="prompt" id="labs-texture-prompt" class="knd-textarea form-control text-white ln-t2i-prompt-input" rows="3" maxlength="500" placeholder="<?php echo t('ai.texture.prompt_placeholder', 'e.g. brick wall, wood grain...'); ?>"></textarea>
        </div>

        <div class="ln-t2i-param-group mb-3" id="labs-texture-image-block" style="display:none;">
          <label class="ln-t2i-param-label"><?php echo t('labs.texture.source_image', 'Source image'); ?></label>
          <input type="file" name="image" id="labs-texture-image" accept="image/jpeg,image/jpg,image/png,image/webp" class="form-control form-control-sm text-white">
          <div class="form-text text-white-50 small"><?php echo t('labs.texture.source_help', 'PNG, JPG or WebP. Used as base for texture.'); ?></div>
        </div>

        <div class="ln-t2i-canvas-zone">
          <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap knd-labs-preview-with-deco" id="labs-result-wrapper">
            <?php require __DIR__ . '/studio_canvas_deco.php'; ?>
            <?php
            $labsStudioPhTitle = t('labs.studio.texture_empty_title', 'Texture Lab');
            $labsStudioPhSub = t('labs.studio.texture_empty_sub', 'Seamless textures for 3D and games.');
            $labsStudioTipsIcon = 'fa-border-all';
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
            <button type="submit" form="labs-texture-form" class="ln-t2i-cta" id="labs-submit-btn">
              <i class="fas fa-border-all me-2"></i><?php echo t('ai.texture.generate', 'Generate texture'); ?>
            </button>
          </div>
          <div id="labs-result-actions" class="labs-result-actions-panel ln-t2i-actions" style="display:none;">
            <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i><?php echo t('ai.download', 'Download'); ?></a>
            <a href="/labs?tool=upscale" id="labs-use-input-btn" class="labs-action labs-action--primary"><i class="fas fa-search-plus"></i><?php echo t('labs.send_to_upscale', 'Send to Upscale'); ?></a>
            <button type="button" id="labs-retry-btn" class="labs-action labs-action--secondary"><i class="fas fa-redo"></i><?php echo t('labs.generate_again', 'Generate again'); ?></button>
          </div>
          <div id="labs-status-panel" class="ln-t2i-status" style="display:none;">
            <div class="d-flex align-items-center"><div class="ai-spinner me-2"><i class="fas fa-cog fa-spin"></i></div><span id="labs-status-text"><?php echo t('ai.status.processing', 'Processing...'); ?></span></div>
          </div>
          <div id="labs-error-msg" class="alert alert-danger ln-t2i-error" style="display:none;"></div>
        </div>
      </div>
      <aside class="ln-t2i-params-col">
        <?php require __DIR__ . '/credits-card.php'; ?>
        <div class="ln-t2i-params-panel">
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label"><?php echo t('labs.presets', 'Presets'); ?></label>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($texturePresets as $key => $promptText): ?>
                <button type="button" class="knd-chip preset-btn" data-prompt="<?php echo htmlspecialchars($promptText); ?>"><?php echo htmlspecialchars($key); ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="ln-t2i-param-group">
            <div class="form-check form-switch">
              <input type="checkbox" name="texture_seamless" id="labs-texture-seamless" class="form-check-input" value="1" checked>
              <label for="labs-texture-seamless" class="form-check-label text-white-50 small"><?php echo t('labs.texture.seamless', 'Seamless / tileable'); ?></label>
            </div>
          </div>
          <div class="ln-t2i-param-group">
            <label class="ln-t2i-param-label" for="labs-texture-negative"><?php echo t('labs.negative_prompt', 'Negative prompt'); ?></label>
            <input type="text" name="negative_prompt" id="labs-texture-negative" class="form-control form-control-sm text-white" maxlength="500" value="ugly, blurry, low quality">
          </div>
          <div class="ln-t2i-param-group">
            <p class="text-white-50 small mb-0"><?php echo t('labs.cost_fixed', '{cost} KP per generation.', ['cost' => $costKp]); ?></p>
          </div>
          <details class="ln-t2i-param-group">
            <summary class="ln-t2i-param-label text-white-50 small"><?php echo t('labs.advanced', 'Advanced'); ?></summary>
            <div class="mt-2">
              <label class="form-label text-white-50 small">steps</label>
              <input type="number" name="steps" class="form-control form-control-sm text-white" value="25" min="10" max="60">
              <label class="form-label text-white-50 small mt-1">cfg</label>
              <input type="number" name="cfg" class="form-control form-control-sm text-white" value="7.5" min="1" max="20" step="0.5">
              <label class="form-label text-white-50 small mt-1">denoise (img2img)</label>
              <input type="number" name="denoise" class="form-control form-control-sm text-white" value="0.75" min="0.4" max="0.95" step="0.05">
            </div>
          </details>
        </div>
      </aside>
    </div>
  </form>
</div>

<script src="/assets/js/labs-lazy-history.js" defer></script>
<script src="/assets/js/kndlabs.js?v=<?php echo file_exists(__DIR__ . '/../../assets/js/kndlabs.js') ? filemtime(__DIR__ . '/../../assets/js/kndlabs.js') : time(); ?>"></script>
<script>
(function() {
  var form = document.getElementById('labs-texture-form');
  var modeInput = document.getElementById('labs-texture-mode');
  var promptBlock = document.getElementById('labs-texture-prompt-block');
  var imageBlock = document.getElementById('labs-texture-image-block');
  var imageInput = document.getElementById('labs-texture-image');
  var promptEl = document.getElementById('labs-texture-prompt');

  function setMode(mode) {
    if (!modeInput) return;
    modeInput.value = mode;
    if (promptBlock) promptBlock.style.display = (mode === 'image') ? 'none' : 'block';
    if (imageBlock) imageBlock.style.display = (mode === 'text') ? 'none' : 'block';
    document.querySelectorAll('.texture-mode-chip').forEach(function(ch) {
      ch.classList.toggle('active', ch.getAttribute('data-mode') === mode);
    });
    document.querySelectorAll('input[name="texture_mode_radio"]').forEach(function(r) { r.checked = r.value === mode; });
  }

  document.querySelectorAll('.texture-mode-chip').forEach(function(ch) {
    ch.addEventListener('click', function() { setMode(ch.getAttribute('data-mode')); });
  });
  document.querySelectorAll('input[name="texture_mode_radio"]').forEach(function(r) {
    r.addEventListener('change', function() { setMode(r.value); });
  });

  if (form) form.querySelectorAll('.preset-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (promptEl && btn.dataset.prompt) promptEl.value = btn.dataset.prompt;
    });
  });

  function run() {
    if (typeof KNDLabs !== 'undefined') {
      KNDLabs.init({ formId: 'labs-texture-form', jobType: 'texture', balanceEl: '#labs-balance' });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();

  function scheduleLazyHistory() {
    var fn = function() {
      if (window.LabsLazyHistory && window.LabsLazyHistory.load) {
        window.LabsLazyHistory.load({ tool: 'texture', limit: 5, toolLabel: 'Texture Lab', hasProviderFilter: false });
      }
    };
    if (window.requestIdleCallback) requestIdleCallback(fn, { timeout: 1500 }); else setTimeout(fn, 100);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scheduleLazyHistory); else scheduleLazyHistory();
})();
</script>
