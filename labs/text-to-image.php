<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_init.php';
labs_perf_checkpoint('t2i_after_init');

require_once BASE_PATH . '/includes/comfyui.php';
labs_perf_checkpoint('t2i_after_comfyui');

$toolName = t('labs.canvas.title', 'Canvas');
$jobType = 'text2img';
$providerFilter = trim($_GET['provider'] ?? '');
$historyJobs = [];
labs_perf_checkpoint('t2i_after_history_fetch');

$aiCss = __DIR__ . '/../assets/css/ai-tools.css';
$labsCss = __DIR__ . '/../assets/css/knd-labs.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
$extraCss .= '<script>window.KND_PRICING={"text2img":{"standard":3,"high":6},"upscale":{"2x":5,"4x":8},"character":{"base":15},"consistency":{"base":5}};</script>';
labs_perf_checkpoint('t2i_before_header');
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
            <input type="hidden" name="tool" value="text2img">
            <div class="mb-3">
              <label class="form-label text-white-50 knd-label"><?php echo t('ai.text2img.prompt'); ?></label>
              <textarea name="prompt" class="knd-textarea form-control text-white" id="labs-prompt-input" rows="3" maxlength="500" placeholder="Describe the image..."></textarea>
              <button type="button" class="btn btn-link btn-sm text-white-50 p-0 mt-1" id="labs-use-last-prompt-btn" style="display:none;"><i class="fas fa-history me-1"></i><?php echo t('labs.use_last_prompt', 'Use last prompt'); ?></button>
              <div class="form-text text-white-50 small" id="labs-prompt-hint"></div>
              <div class="form-text text-white-50 small mt-1"><?php echo t('labs.presets'); ?></div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="button" class="knd-chip preset-btn" data-prompt="Game character concept, fantasy armor, portrait" data-negative="ugly, blurry, deformed" data-steps="25" data-cfg="7.5" data-sampler="dpmpp_2m" data-width="1024" data-height="1024"><?php echo t('labs.preset_game', 'Game concept'); ?></button>
                <button type="button" class="knd-chip preset-btn" data-prompt="Anime portrait, detailed face, soft lighting" data-negative="ugly, blurry, bad anatomy" data-steps="25" data-cfg="7.5" data-sampler="euler" data-width="1024" data-height="1024"><?php echo t('labs.preset_anime', 'Anime portrait'); ?></button>
                <button type="button" class="knd-chip preset-btn" data-prompt="Landscape, mountains, sunset, atmospheric" data-negative="ugly, blurry, people" data-steps="25" data-cfg="7" data-sampler="dpmpp_2m" data-width="1024" data-height="768"><?php echo t('labs.preset_landscape', 'Landscape'); ?></button>
              </div>
              <div class="form-text text-white-50 small mt-1" id="labs-preset-summary"></div>
            </div>
            <div class="mb-3">
              <label class="form-label text-white-50 knd-label"><?php echo t('labs.negative_prompt', 'Negative prompt'); ?></label>
              <input type="text" name="negative_prompt" class="knd-input form-control text-white" id="labs-negative-input" maxlength="500" value="ugly, blurry, low quality" placeholder="ugly, blurry, low quality">
              <div class="d-flex flex-wrap gap-2 mt-2" id="preset-neg-btns">
                <button type="button" class="knd-chip preset-neg-btn" data-value="ugly, blurry, low quality"><?php echo t('labs.neg_default', 'Default'); ?></button>
                <button type="button" class="knd-chip preset-neg-btn" data-value="text, watermark, signature"><?php echo t('labs.neg_text', 'No text'); ?></button>
                <button type="button" class="knd-chip preset-neg-btn" data-value="bad anatomy, extra limbs, mutated"><?php echo t('labs.neg_anatomy', 'Anatomy'); ?></button>
                <button type="button" class="knd-chip preset-neg-btn" data-value="disfigured, deformed, cartoon"><?php echo t('labs.neg_realistic', 'Realistic'); ?></button>
              </div>
            </div>
            <div class="mb-3">
              <h6 class="text-white-50 mb-2 small"><?php echo t('labs.cond_section', 'Reference / Conditioning'); ?></h6>
              <div class="form-check form-switch mb-2">
                <input type="checkbox" name="ipadapter_enabled" id="ipadapter-enabled" class="form-check-input" value="1">
                <label for="ipadapter-enabled" class="form-check-label text-white-50 small"><?php echo t('labs.ipadapter_toggle', 'Enable IPAdapter (style reference)'); ?></label>
              </div>
              <div id="ipadapter-fields" class="ms-3 mb-2" style="display:none;">
                <div class="mb-2">
                  <label class="form-label text-white-50 small"><?php echo t('labs.ref_image', 'Reference image'); ?></label>
                  <input type="file" name="ipadapter_image" id="ipadapter-image" accept="image/jpeg,image/jpg,image/png,image/webp" class="knd-input form-control form-control-sm text-white">
                  <div class="form-text text-white-50 small"><?php echo t('labs.ref_help', 'Required when enabled'); ?></div>
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-4">
                    <label class="form-label text-white-50 small"><?php echo t('labs.weight', 'Weight'); ?></label>
                    <input type="number" name="ipadapter_weight" class="knd-input form-control form-control-sm text-white" value="0.70" min="0" max="1.20" step="0.05">
                  </div>
                  <div class="col-4">
                    <label class="form-label text-white-50 small">start_at</label>
                    <input type="number" name="ipadapter_start_at" class="knd-input form-control form-control-sm text-white" value="0" min="0" max="1" step="0.05">
                  </div>
                  <div class="col-4">
                    <label class="form-label text-white-50 small">end_at</label>
                    <input type="number" name="ipadapter_end_at" class="knd-input form-control form-control-sm text-white" value="1" min="0" max="1" step="0.05">
                  </div>
                </div>
                <div class="mb-2">
                  <label class="form-label text-white-50 small"><?php echo t('labs.mode', 'Mode'); ?></label>
                  <select name="ipadapter_mode" id="ipadapter-mode" class="knd-select form-select form-select-sm text-white">
                    <option value="balanced" selected>Balanced</option>
                    <option value="style">Style</option>
                    <option value="composition">Composition</option>
                  </select>
                </div>
              </div>
              <div class="form-check form-switch mb-2">
                <input type="checkbox" name="controlnet_enabled" id="controlnet-enabled" class="form-check-input" value="1">
                <label for="controlnet-enabled" class="form-check-label text-white-50 small"><?php echo t('labs.controlnet_toggle', 'Enable ControlNet (pose/edges)'); ?></label>
              </div>
              <div id="controlnet-fields" class="ms-3 mb-2" style="display:none;">
                <div class="mb-2">
                  <label class="form-label text-white-50 small"><?php echo t('labs.control_image', 'Control image'); ?></label>
                  <input type="file" name="controlnet_image" id="controlnet-image" accept="image/jpeg,image/jpg,image/png,image/webp" class="knd-input form-control form-control-sm text-white">
                  <div class="form-text text-white-50 small"><?php echo t('labs.control_help', 'Required when enabled'); ?></div>
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-4">
                    <label class="form-label text-white-50 small"><?php echo t('labs.strength', 'Strength'); ?></label>
                    <input type="number" name="controlnet_strength" class="knd-input form-control form-control-sm text-white" value="0.75" min="0" max="1.20" step="0.05">
                  </div>
                  <div class="col-4">
                    <label class="form-label text-white-50 small">start_at</label>
                    <input type="number" name="controlnet_start_at" class="knd-input form-control form-control-sm text-white" value="0" min="0" max="1" step="0.05">
                  </div>
                  <div class="col-4">
                    <label class="form-label text-white-50 small">end_at</label>
                    <input type="number" name="controlnet_end_at" class="knd-input form-control form-control-sm text-white" value="0.80" min="0" max="1" step="0.05">
                  </div>
                </div>
                <div class="mb-2">
                  <label class="form-label text-white-50 small"><?php echo t('labs.control_mode', 'Control mode'); ?></label>
                  <select name="controlnet_control_mode" id="controlnet-control-mode" class="knd-select form-select form-select-sm text-white">
                    <option value="balanced" selected>Balanced</option>
                    <option value="prompt_strict">Prompt strict</option>
                    <option value="control_strict">Control strict</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-white-50 knd-label"><?php echo t('ai.text2img.mode_label', 'Quality'); ?></label>
              <select name="quality" id="labs-quality-select" class="knd-select form-select text-white">
                <option value="standard" selected>Standard (3 KP)</option>
                <option value="high">High (6 KP)</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label text-white-50 small knd-label"><?php echo t('labs.model', 'Model'); ?></label>
              <select name="model" id="labs-model-select" class="knd-select form-select form-select-sm text-white">
                <option value="sd_xl_base">SD XL Base 1.0</option>
                <option value="sd_xl_refiner">SD XL Refiner 1.0</option>
                <option value="juggernaut_ragnarok">Juggernaut XL Ragnarok</option>
                <option value="juggernaut_v8" selected>Juggernaut XL v8</option>
                <option value="pornmaster_asian">PornMaster Asian SDXL</option>
                <option value="waiANINSFWPONY">PONY XL v1.30</option>
                <option value="waiNSFW_v120">Illustrious v1.20</option>
                <option value="waiNSFW_v150">Illustrious v1.50</option>
              </select>
            </div>
            <div class="mb-3">
              <button type="button" class="btn btn-link btn-sm text-white-50 p-0" id="labs-advanced-toggle" data-bs-toggle="collapse" data-bs-target="#labs-advanced">
                <i class="fas fa-chevron-down me-1"></i><?php echo t('labs.advanced', 'Advanced'); ?>
              </button>
            </div>
            <div class="collapse mb-3" id="labs-advanced">
            <div class="row g-2 mb-3">
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.seed', 'Seed'); ?></label>
                <input type="number" name="seed" class="knd-input form-control form-control-sm text-white" placeholder="Random">
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.steps', 'Steps'); ?></label>
                <input type="number" name="steps" class="knd-input form-control form-control-sm text-white" value="30" min="1" max="100">
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.cfg', 'CFG'); ?></label>
                <input type="number" name="cfg" class="knd-input form-control form-control-sm text-white" value="6" min="1" max="30" step="0.5">
              </div>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.sampler', 'Sampler'); ?></label>
                <select name="sampler_name" class="knd-select form-select form-select-sm text-white">
                  <option value="euler">Euler</option>
                  <option value="euler_ancestral">Euler Ancestral</option>
                  <option value="heun">Heun</option>
                  <option value="dpm_2">DPM 2</option>
                  <option value="dpm_2_ancestral">DPM 2 Ancestral</option>
                  <option value="lms">LMS</option>
                  <option value="dpmpp_2m" selected>DPM++ 2M</option>
                  <option value="dpmpp_2m_sde">DPM++ 2M SDE</option>
                  <option value="dpmpp_sde">DPM++ SDE</option>
                  <option value="ddim">DDIM</option>
                  <option value="lcm">LCM</option>
                  <option value="uni_pc">UniPC</option>
                </select>
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.scheduler', 'Scheduler'); ?></label>
                <select name="scheduler" class="knd-select form-select form-select-sm text-white">
                  <option value="normal">Normal</option>
                  <option value="karras" selected>Karras</option>
                  <option value="exponential">Exponential</option>
                  <option value="sgm_uniform">SGM Uniform</option>
                  <option value="simple">Simple</option>
                </select>
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.denoise', 'Denoise'); ?></label>
                <input type="number" name="denoise" class="knd-input form-control form-control-sm text-white" value="1" min="0.01" max="1" step="0.01">
              </div>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.width', 'Width'); ?></label>
                <select name="width" id="labs-width-select" class="knd-select form-select form-select-sm text-white">
                  <option value="256">256</option>
                  <option value="512" selected>512</option>
                  <option value="768">768</option>
                  <option value="1024">1024</option>
                  <option value="1152">1152</option>
                  <option value="1280">1280</option>
                  <option value="1536">1536</option>
                  <option value="2048">2048</option>
                </select>
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.height', 'Height'); ?></label>
                <select name="height" id="labs-height-select" class="knd-select form-select form-select-sm text-white">
                  <option value="256">256</option>
                  <option value="512" selected>512</option>
                  <option value="768">768</option>
                  <option value="1024">1024</option>
                  <option value="1152">1152</option>
                  <option value="1280">1280</option>
                  <option value="1536">1536</option>
                  <option value="2048">2048</option>
                </select>
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.batch', 'Batch'); ?></label>
                <select name="batch_size" class="knd-select form-select form-select-sm text-white">
                  <option value="1" selected>1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                  <option value="4">4</option>
                </select>
              </div>
            </div>
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" name="private_image" id="labs-private-check" class="form-check-input" value="1">
              <label for="labs-private-check" class="form-check-label text-white-50 small"><?php echo t('labs.private_toggle', 'Keep this generation private'); ?></label>
            </div>
            <p class="text-white-50 small mb-3" id="labs-microcopy-private"><?php echo t('labs.images_stored', 'Your images are private. Stored for 30 days.'); ?></p>
          </form>
      </aside>

      <div class="d-flex flex-column flex-grow-1">
        <div class="knd-canvas knd-panel-soft flex-grow-1 mb-0" id="labs-result-wrapper">
          <div id="labs-result-preview" class="labs-result-preview text-center py-5" style="min-height:320px;">
            <div id="labs-placeholder-tips" class="labs-placeholder-tips">
              <i class="fas fa-wand-magic-sparkles fa-3x mb-3" style="color:var(--knd-accent-soft);opacity:.4;"></i>
              <p class="text-white-50 mb-1 small"><?php echo t('labs.tip_prompt', 'Use 1 subject + 1 style + 1 lighting'); ?></p>
              <p class="text-white-50 mb-0 small"><?php echo t('labs.tip_example', 'e.g. "Warrior, oil painting, golden hour"'); ?></p>
            </div>
          </div>
        </div>
        <div class="labs-gen-area text-center py-4">
          <button type="submit" form="labs-comfy-form" class="labs-gen-btn" id="generateBtn">
            <i class="fas fa-bolt me-2"></i><?php echo t('ai.text2img.generate'); ?>
          </button>
        </div>
        <div id="labs-result-actions" class="labs-result-actions-panel mt-4 px-3" style="display:none;">
          <div class="labs-result-actions__header">
            <span class="labs-result-actions__title"><?php echo t('labs.result_actions', 'Output Actions'); ?></span>
          </div>
          <div class="labs-result-actions__primary">
            <a href="#" id="labs-download-btn" class="labs-action labs-action--primary" download><i class="fas fa-download"></i><?php echo t('ai.download'); ?></a>
            <a href="#" id="labs-generate-variations-btn" class="labs-action labs-action--primary"><i class="fas fa-images"></i><?php echo t('labs.generate_variations', 'Generate Variations'); ?></a>
            <a href="/labs-upscale.php" id="labs-use-input-btn" class="labs-action labs-action--primary"><i class="fas fa-search-plus"></i><?php echo t('labs.send_to_upscale', 'Send to Upscale'); ?></a>
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
        <div id="labs-status-panel" class="mt-3 px-3" style="display:none;">
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
        <div id="labs-error-msg" class="alert alert-danger mt-3 mx-3" style="display:none;"></div>
        <div class="px-3">
          <?php require __DIR__ . '/partials/image_details_panel.php'; ?>
        </div>
      </div>

      <aside class="knd-panel">
        <div class="knd-section-title"><?php echo t('labs.credits', 'Credits'); ?></div>
        <p class="text-white mb-2"><strong id="labs-balance"><?php echo number_format($balance); ?></strong> KP</p>
        <p class="knd-muted small mb-3" id="labs-cost-label"><?php echo t('labs.cost_label', 'Cost: 3 KP'); ?></p>

        <a href="/support-credits.php" class="knd-btn-secondary w-100 mb-4">+ <?php echo t('labs.add_credits', 'Add Credits'); ?></a>
        <div class="knd-divider"></div>
        <div class="knd-section-title"><?php echo t('labs.tool_history', 'Recent'); ?></div>
        <div id="labs-history-sidebar-placeholder">
          <p class="knd-muted small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading recent jobs...</p>
        </div>
        <ul class="list-unstyled mb-0" id="labs-recent-list" style="display:none;"></ul>
      </aside>
    </div>

    <!-- Recent Creations - below workspace (lazy-loaded) -->
    <div class="labs-recent-creations mt-5">
      <div class="knd-section-title mb-3"><?php echo t('labs.recent_creations', 'Recent Creations'); ?></div>
      <div class="knd-card-grid" id="labs-recent-creations-grid">
        <p class="knd-muted small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading recent jobs...</p>
      </div>
    </div>
  </div>
</section>

<!-- Details drawer - KND HUD style (replaces Bootstrap modal) -->
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
<?php $kndlabsJs = __DIR__ . '/../assets/js/kndlabs.js'; ?>
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
      if (jid) window.location.href = '/labs-consistency.php?reference_job_id=' + encodeURIComponent(jid) + '&mode=' + encodeURIComponent(m);
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
  // Init KNDLabs
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
<?php echo generateFooter(); echo generateScripts(); echo labs_perf_comment(); labs_perf_log(); ?>
