<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_init.php';
labs_perf_checkpoint('consistency_after_init');

require_once BASE_PATH . '/includes/comfyui.php';
require_once BASE_PATH . '/includes/labs_display_helper.php';
labs_perf_checkpoint('consistency_after_comfyui');

$toolName = t('labs.consistency.title', 'Consistency System');
$jobType = 'consistency';
$historyJobs = [];
$refJobs = []; // Jobs usable as reference (done, any tool)
if ($pdo) {
    try {
        $userId = current_user_id();
        $historyJobs = comfyui_get_user_jobs($pdo, $userId, 12);
        $historyJobs = array_filter($historyJobs, fn($j) => ($j['tool'] ?? '') === 'consistency');
        $refJobs = comfyui_get_user_jobs($pdo, $userId, 20);
        $refJobs = array_filter($refJobs, fn($j) => ($j['status'] ?? '') === 'done');
    } catch (\Throwable $e) {
        $historyJobs = [];
        $refJobs = [];
    }
}
labs_perf_checkpoint('consistency_after_history_refjobs');

$refJobId = isset($_GET['reference_job_id']) ? (int) $_GET['reference_job_id'] : 0;
$preloadMode = trim($_GET['mode'] ?? '');
if (!in_array($preloadMode, ['style', 'character', 'both'], true)) $preloadMode = 'style';

$preloadFromJob = [];
if ($refJobId > 0 && $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM knd_labs_jobs WHERE id = ? AND user_id = ? AND status = 'done' LIMIT 1");
    if ($stmt && $stmt->execute([$refJobId, current_user_id()])) {
        $refRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($refRow) {
            $refPayload = json_decode($refRow['payload_json'] ?? '{}', true) ?: [];
            $preloadFromJob = [
                'base_prompt' => ($refRow['tool'] ?? '') === 'consistency'
                    ? ($refPayload['base_prompt'] ?? '')
                    : ($refRow['prompt'] ?? ''),
                'negative_prompt' => $refRow['negative_prompt'] ?? ($refPayload['negative_prompt'] ?? 'ugly, blurry, low quality'),
                'width' => $refPayload['width'] ?? 1024,
                'height' => $refPayload['height'] ?? 1024,
                'steps' => $refPayload['steps'] ?? 28,
                'cfg' => $refPayload['cfg'] ?? 7,
                'sampler' => $refPayload['sampler_name'] ?? ($refPayload['sampler'] ?? 'dpmpp_2m'),
                'seed' => $refPayload['seed'] ?? '',
            ];
            if (($refRow['tool'] ?? '') === 'consistency') {
                $preloadFromJob['scene_prompt'] = $refPayload['scene_prompt'] ?? '';
                if (!empty($refPayload['mode']) && in_array($refPayload['mode'], ['style', 'character', 'both'], true)) $preloadMode = $refPayload['mode'];
            } else {
                $preloadFromJob['scene_prompt'] = '';
            }
        }
    }
}
labs_perf_checkpoint('consistency_after_preload');

$aiCss = __DIR__ . '/../assets/css/ai-tools.css';
$labsCss = __DIR__ . '/../assets/css/knd-labs.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
labs_perf_checkpoint('consistency_before_header');
echo generateHeader(t('labs.tool_page_title', '{tool} | KND Labs', ['tool' => $toolName]), t('labs.consistency.desc', 'Generate images with locked style or character consistency.'), $extraCss);
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
            <input type="hidden" name="tool" value="consistency">

            <div class="mb-3">
              <label class="form-label text-white-50 knd-label"><?php echo t('labs.consistency.mode', 'Mode'); ?></label>
              <select name="mode" id="labs-mode" class="knd-select form-select text-white">
                <option value="style" <?php echo $preloadMode === 'style' ? 'selected' : ''; ?>><?php echo t('labs.consistency.mode_style', 'Style Lock'); ?></option>
                <option value="character" <?php echo $preloadMode === 'character' ? 'selected' : ''; ?>><?php echo t('labs.consistency.mode_character', 'Character Lock'); ?></option>
                <option value="both" <?php echo $preloadMode === 'both' ? 'selected' : ''; ?>><?php echo t('labs.consistency.mode_both', 'Style + Character'); ?></option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label text-white-50"><?php echo t('labs.consistency.reference_source', 'Reference Source'); ?></label>
              <div class="form-check">
                <input type="radio" name="reference_source" id="ref-recent" value="recent" class="form-check-input" <?php echo $refJobId > 0 ? 'checked' : ''; ?>>
                <label for="ref-recent" class="form-check-label text-white-50"><?php echo t('labs.consistency.ref_recent', 'Select from Recent Jobs'); ?></label>
              </div>
              <div class="form-check">
                <input type="radio" name="reference_source" id="ref-upload" value="upload" class="form-check-input" <?php echo $refJobId <= 0 ? 'checked' : ''; ?>>
                <label for="ref-upload" class="form-check-label text-white-50"><?php echo t('labs.consistency.ref_upload', 'Upload Reference Image'); ?></label>
              </div>
              <div id="labs-ref-recent-area" class="mt-2" style="display:<?php echo $refJobId > 0 ? 'block' : 'none'; ?>;">
                <select name="reference_job_id" id="labs-reference-job" class="knd-select form-select form-select-sm text-white">
                  <option value=""><?php echo t('labs.consistency.select_job', 'Select a job...'); ?></option>
                  <?php foreach ($refJobs as $j):
                    $jid = $j['id'] ?? 0;
                    $label = '#' . $jid . ' - ' . date('M j, H:i', strtotime($j['created_at'] ?? 'now')) . ' (' . ($j['tool'] ?? '') . ')';
                  ?>
                  <option value="<?php echo (int)$jid; ?>" <?php echo $jid === $refJobId ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (empty($refJobs)): ?>
                <p class="text-white-50 small mt-1 mb-0"><?php echo t('labs.consistency.no_ref_jobs', 'No completed jobs. Use Canvas or Upscale first.'); ?></p>
                <?php endif; ?>
              </div>
              <div id="labs-ref-upload-area" class="mt-2" style="display:<?php echo $refJobId <= 0 ? 'block' : 'none'; ?>;">
                <input type="file" name="reference_image" id="labs-reference-file" accept="image/jpeg,image/jpg,image/png,image/webp" class="knd-input form-control form-control-sm text-white">
                <div class="form-text text-white-50 small">PNG, JPG, WebP. Max 5MB, 2048px</div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label text-white-50 knd-label"><?php echo t('labs.consistency.base_prompt', 'Base Prompt'); ?></label>
              <textarea name="base_prompt" id="labs-base-prompt" class="knd-textarea form-control text-white" rows="2" maxlength="500" placeholder="Identity / style description (persistent)..."><?php echo htmlspecialchars($preloadFromJob['base_prompt'] ?? ''); ?></textarea>
              <div class="form-text text-white-50 small"><?php echo t('labs.consistency.base_help', 'Persistent identity or style'); ?></div>
            </div>
            <div class="mb-3">
              <label class="form-label text-white-50 knd-label"><?php echo t('labs.consistency.scene_prompt', 'Scene Prompt'); ?></label>
              <textarea name="scene_prompt" id="labs-scene-prompt" class="knd-textarea form-control text-white" rows="2" maxlength="500" placeholder="Scene / variation for this generation..."><?php echo htmlspecialchars($preloadFromJob['scene_prompt'] ?? ''); ?></textarea>
              <div class="form-text text-white-50 small"><?php echo t('labs.consistency.scene_help', 'What changes in this image'); ?></div>
            </div>
            <div class="mb-3">
              <label class="form-label text-white-50 knd-label"><?php echo t('labs.negative_prompt', 'Negative Prompt'); ?></label>
              <input type="text" name="negative_prompt" class="knd-input form-control text-white" maxlength="500" value="<?php echo htmlspecialchars($preloadFromJob['negative_prompt'] ?? 'ugly, blurry, low quality'); ?>">
            </div>

            <div class="mb-3">
              <label class="form-label text-white-50 small"><?php echo t('labs.consistency.lock_settings', 'Lock Settings'); ?></label>
              <div class="form-check form-check-inline">
                <input type="checkbox" name="lock_seed" id="labs-lock-seed" class="form-check-input" value="1">
                <label for="labs-lock-seed" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.lock_seed', 'Lock Seed'); ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input type="checkbox" name="inherit_model" id="labs-inherit-model" class="form-check-input" value="1" checked>
                <label for="labs-inherit-model" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.inherit_model', 'Inherit Model'); ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input type="checkbox" name="inherit_resolution" id="labs-inherit-res" class="form-check-input" value="1" checked>
                <label for="labs-inherit-res" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.inherit_resolution', 'Inherit Resolution'); ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input type="checkbox" name="inherit_sampling" id="labs-inherit-sampling" class="form-check-input" value="1" checked>
                <label for="labs-inherit-sampling" class="form-check-label text-white-50 small"><?php echo t('labs.consistency.inherit_sampling', 'Inherit Sampling'); ?></label>
              </div>
            </div>

            <div class="mb-3">
              <button type="button" class="btn btn-link btn-sm text-white-50 p-0" id="labs-advanced-toggle" data-bs-toggle="collapse" data-bs-target="#labs-advanced">
                <i class="fas fa-chevron-down me-1"></i><?php echo t('labs.advanced', 'Advanced'); ?>
              </button>
            </div>
            <div class="collapse mb-3" id="labs-advanced">
              <div class="row g-2 mb-3">
                <div class="col-4">
                  <label class="form-label text-white-50 small"><?php echo t('labs.width', 'Width'); ?></label>
                  <input type="number" name="width" class="knd-input form-control form-control-sm text-white" value="<?php echo (int)($preloadFromJob['width'] ?? 1024); ?>" min="256" max="2048" step="8">
                </div>
                <div class="col-4">
                  <label class="form-label text-white-50 small"><?php echo t('labs.height', 'Height'); ?></label>
                  <input type="number" name="height" class="knd-input form-control form-control-sm text-white" value="<?php echo (int)($preloadFromJob['height'] ?? 1024); ?>" min="256" max="2048" step="8">
                </div>
                <div class="col-4">
                  <label class="form-label text-white-50 small"><?php echo t('labs.seed', 'Seed'); ?></label>
                  <input type="number" name="seed" class="knd-input form-control form-control-sm text-white" placeholder="Random" value="<?php echo isset($preloadFromJob['seed']) && $preloadFromJob['seed'] !== '' && $preloadFromJob['seed'] !== null ? (int)$preloadFromJob['seed'] : ''; ?>">
                </div>
              </div>
              <div class="row g-2 mb-3">
                <div class="col-4">
                  <label class="form-label text-white-50 small"><?php echo t('labs.steps', 'Steps'); ?></label>
                  <input type="number" name="steps" class="knd-input form-control form-control-sm text-white" value="<?php echo (int)($preloadFromJob['steps'] ?? 28); ?>" min="1" max="100">
                </div>
                <div class="col-4">
                  <label class="form-label text-white-50 small"><?php echo t('labs.cfg', 'CFG'); ?></label>
                  <input type="number" name="cfg" class="knd-input form-control form-control-sm text-white" value="<?php echo (float)($preloadFromJob['cfg'] ?? 7); ?>" min="1" max="30" step="0.5">
                </div>
                <div class="col-4">
                  <label class="form-label text-white-50 small"><?php echo t('labs.sampler', 'Sampler'); ?></label>
                  <select name="sampler" class="knd-select form-select form-select-sm text-white">
                    <?php $preloadSampler = $preloadFromJob['sampler'] ?? 'dpmpp_2m'; ?>
                    <option value="dpmpp_2m" <?php echo $preloadSampler === 'dpmpp_2m' ? 'selected' : ''; ?>>DPM++ 2M</option>
                    <option value="euler" <?php echo $preloadSampler === 'euler' ? 'selected' : ''; ?>>Euler</option>
                    <option value="euler_ancestral" <?php echo $preloadSampler === 'euler_ancestral' ? 'selected' : ''; ?>>Euler Ancestral</option>
                    <option value="ddim" <?php echo $preloadSampler === 'ddim' ? 'selected' : ''; ?>>DDIM</option>
                    <option value="lcm" <?php echo $preloadSampler === 'lcm' ? 'selected' : ''; ?>>LCM</option>
                  </select>
                </div>
              </div>
              <div class="mb-2">
                <label class="form-label text-white-50 small"><?php echo t('labs.model', 'Model'); ?></label>
                <select name="model" class="knd-select form-select form-select-sm text-white">
                  <option value="juggernaut_v8" selected>Juggernaut XL v8</option>
                  <option value="sd_xl_base">SD XL Base</option>
                  <option value="waiANINSFWPONY">PONY XL</option>
                </select>
              </div>
            </div>

          </form>
      </aside>

      <div class="d-flex flex-column flex-grow-1">
        <div class="knd-canvas knd-panel-soft flex-grow-1 mb-0">
          <div id="labs-result-preview" class="labs-result-preview text-center py-5" style="min-height:320px;">
            <div id="labs-placeholder-tips" class="labs-placeholder-tips">
              <i class="fas fa-lock fa-3x mb-3" style="color:var(--knd-accent-soft);opacity:.4;"></i>
              <p class="text-white-50 mb-1 small"><?php echo t('labs.consistency.tip1', 'Use a reference image from Canvas or Upload.'); ?></p>
              <p class="text-white-50 mb-0 small"><?php echo t('labs.consistency.tip2', 'Base prompt = persistent style/identity, Scene prompt = variation.'); ?></p>
            </div>
          </div>
        </div>
        <div class="labs-gen-area text-center py-4">
          <button type="submit" form="labs-comfy-form" class="labs-gen-btn" id="generateBtn">
            <i class="fas fa-bolt me-2"></i><?php echo t('labs.consistency.generate', 'Generate'); ?>
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
        <p class="knd-muted small mb-4" id="labs-cost-label"><?php echo t('labs.cost_label', 'Cost: 5 KP'); ?></p>
        <a href="/support-credits.php" class="knd-btn-secondary w-100 mb-4">+ <?php echo t('labs.add_credits', 'Add Credits'); ?></a>
        <div class="knd-divider"></div>
        <div class="knd-section-title"><?php echo t('labs.tool_history', 'Recent'); ?></div>
        <?php if (!empty($historyJobs)): ?>
        <ul class="list-unstyled mb-0" id="labs-recent-list">
            <?php foreach (array_slice($historyJobs, 0, 8) as $j):
              $jid = $j['id'] ?? 0;
              $imgUrl = !empty($j['image_url']) ? $j['image_url'] : '/api/labs/image.php?job_id=' . $jid;
              if (strpos($imgUrl, 'job_id=') === false && $jid) $imgUrl = '/api/labs/image.php?job_id=' . $jid;
            ?>
            <li class="labs-recent-item d-flex align-items-center justify-content-between py-2 border-bottom border-secondary flex-wrap gap-2" data-job-id="<?php echo (int)$jid; ?>" data-status="<?php echo htmlspecialchars($j['status'] ?? ''); ?>">
              <span class="text-white-50 small"><?php echo date('M j, H:i', strtotime($j['created_at'])); ?></span>
              <span class="badge bg-<?php echo ($j['status'] ?? '') === 'done' ? 'success' : (($j['status'] ?? '') === 'failed' ? 'danger' : 'warning'); ?>"><?php echo htmlspecialchars($j['status'] ?? 'pending'); ?></span>
              <?php if (($j['status'] ?? '') === 'done'): ?>
              <div class="labs-recent-thumb" style="position:relative;">
                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="" class="rounded" style="width:48px;height:48px;object-fit:cover;" onerror="this.parentElement.classList.add('labs-img-error')">
              </div>
              <a href="<?php echo htmlspecialchars($imgUrl); ?>" class="btn btn-sm btn-outline-success" target="_blank" download><i class="fas fa-download"></i></a>
              <a href="/labs-consistency.php?reference_job_id=<?php echo (int)$jid; ?>&mode=style" class="btn btn-sm btn-outline-secondary"><?php echo t('labs.consistency.reuse', 'Use as reference'); ?></a>
              <?php endif; ?>
              <button type="button" class="btn btn-sm btn-outline-secondary labs-view-details" data-job-id="<?php echo (int)$jid; ?>"><?php echo t('labs.view_details', 'View details'); ?></button>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="knd-muted small mb-0"><?php echo t('labs.no_result_yet', 'Submit to generate'); ?></p>
        <?php endif; ?>
      </aside>
    </div>

    <div class="labs-recent-creations mt-5">
      <div class="knd-section-title mb-3"><?php echo t('labs.recent_creations', 'Recent Creations'); ?></div>
      <div class="knd-card-grid" id="labs-recent-creations-grid">
        <?php foreach (array_slice($historyJobs, 0, 8) as $j):
          $jid = $j['id'] ?? 0;
          $imgUrl = !empty($j['image_url']) ? $j['image_url'] : '/api/labs/image.php?job_id=' . $jid;
          if (strpos($imgUrl, 'job_id=') === false && $jid) $imgUrl = '/api/labs/image.php?job_id=' . $jid;
          $status = $j['status'] ?? 'pending';
          $statusClass = $status === 'done' ? 'knd-badge-success' : ($status === 'failed' ? 'knd-badge--danger' : 'knd-badge--warning');
        ?>
        <div class="knd-showcase-card labs-creation-card" data-job-id="<?php echo (int)$jid; ?>">
          <div class="knd-showcase-card__img">
            <?php if (!empty($imgUrl) && $status === 'done'): ?>
            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <?php endif; ?>
            <span class="knd-showcase-card__placeholder" style="<?php echo (!empty($imgUrl) && $status === 'done') ? 'display:none;' : ''; ?>"><i class="fas fa-lock"></i></span>
          </div>
          <div class="knd-showcase-card__body">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="knd-showcase-card__title"><?php echo t('labs.consistency.title', 'Consistency'); ?></span>
              <span class="knd-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
            </div>
            <div class="knd-showcase-card__meta"><?php echo date('M j, H:i', strtotime($j['created_at'])); ?></div>
            <button type="button" class="btn btn-sm knd-btn-secondary mt-2 w-100 labs-view-details" data-job-id="<?php echo (int)$jid; ?>">
              <i class="fas fa-info-circle me-1"></i><?php echo t('labs.details', 'Details'); ?>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (empty($historyJobs)): ?>
      <p class="knd-muted small mb-0"><?php echo t('labs.no_creations_yet', 'Generate your first image to see it here.'); ?></p>
      <?php endif; ?>
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
<?php $kndlabsJs = __DIR__ . '/../assets/js/kndlabs.js'; ?>
<script src="/assets/js/kndlabs.js?v=<?php echo file_exists($kndlabsJs) ? filemtime($kndlabsJs) : time(); ?>"></script>
<script>
(function() {
  var refRecent = document.getElementById('ref-recent');
  var refUpload = document.getElementById('ref-upload');
  var areaRecent = document.getElementById('labs-ref-recent-area');
  var areaUpload = document.getElementById('labs-ref-upload-area');
  if (refRecent) refRecent.addEventListener('change', function() {
    if (areaRecent) areaRecent.style.display = 'block';
    if (areaUpload) areaUpload.style.display = 'none';
  });
  if (refUpload) refUpload.addEventListener('change', function() {
    if (areaRecent) areaRecent.style.display = 'none';
    if (areaUpload) areaUpload.style.display = 'block';
  });

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

  function run() {
    if (typeof KNDLabs !== 'undefined') {
      KNDLabs.init({
        formId: 'labs-comfy-form',
        jobType: 'consistency',
        costLabelId: 'labs-cost-label',
        pricingKey: 'consistency',
        balanceEl: '#labs-balance',
        apiConsistency: '/api/labs/consistency_create.php'
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
</script>
<?php echo generateFooter(); echo generateScripts(); echo labs_perf_comment(); labs_perf_log(); ?>
