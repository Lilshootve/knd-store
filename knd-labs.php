<?php
/**
 * KND Labs - App shell (new UI). Replaces hub at /labs.
 * Sidebar + dynamic tool content + recent jobs. Real integration with existing tools.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $bootstrap = __DIR__ . '/includes/bootstrap.php';
    if (!is_file($bootstrap)) {
        $bootstrap = __DIR__ . '/../includes/bootstrap.php';
    }
    require_once $bootstrap;
    require_once KND_ROOT . '/includes/session.php';
    require_once KND_ROOT . '/includes/config.php';
    require_once KND_ROOT . '/includes/auth.php';
    require_once KND_ROOT . '/includes/support_credits.php';
    require_once KND_ROOT . '/includes/ai.php';
    require_once KND_ROOT . '/includes/comfyui.php';
    require_once KND_ROOT . '/includes/header.php';
    require_once KND_ROOT . '/includes/footer.php';

    require_login();

    $pdo = getDBConnection();
    $balance = 0;
    $recentJobs = [];
    $labsRecentPrivate = false;
    if ($pdo) {
        $userId = current_user_id();
        release_available_points_if_due($pdo, $userId);
        expire_points_if_due($pdo, $userId);
        $balance = get_available_points($pdo, $userId);
        $labsRecentPrivate = comfyui_user_prefers_private_recent($pdo, $userId);
        try {
            $recentJobs = $labsRecentPrivate
                ? comfyui_get_user_jobs($pdo, $userId, 16)
                : comfyui_get_recent_jobs_public($pdo, 20);
        } catch (\Throwable $e) {
            $recentJobs = [];
        }
    }

    $currentTool = isset($_GET['tool']) ? trim($_GET['tool']) : 'text2img';
    $allowedTools = ['text2img', 'upscale', 'consistency', 'remove-bg', 'texture', '3d_vertex', 'model_viewer'];
    if (!in_array($currentTool, $allowedTools, true)) {
        $currentTool = 'text2img';
    }

    if ($currentTool === 'consistency') {
        require_once KND_ROOT . '/includes/labs_display_helper.php';
        $refJobId = isset($_GET['reference_job_id']) ? (int) $_GET['reference_job_id'] : 0;
        $preloadMode = trim($_GET['mode'] ?? '');
        if (!in_array($preloadMode, ['style', 'character', 'both'], true)) $preloadMode = 'style';
        $preloadFromJob = [];
        $refJobs = [];
        if ($pdo) {
            try {
                $uid = current_user_id();
                $refJobs = comfyui_get_user_jobs($pdo, $uid, 20);
                $refJobs = array_filter($refJobs, fn($j) => ($j['status'] ?? '') === 'done');
            } catch (\Throwable $e) {
                $refJobs = [];
            }
            if ($refJobId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM knd_labs_jobs WHERE id = ? AND user_id = ? AND status = 'done' LIMIT 1");
                if ($stmt && $stmt->execute([$refJobId, $uid])) {
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
        }
    }

    if ($currentTool === '3d_vertex') {
        $balance = $pdo ? get_available_points($pdo, current_user_id()) : 0;
        $kpCostVertex = 20;
    }

    $providerFilter = ($currentTool === 'text2img' && isset($_GET['provider'])) ? trim($_GET['provider']) : '';

    $labsNextCss = __DIR__ . '/assets/css/labs-next.css';
    $aiCss = __DIR__ . '/assets/css/ai-tools.css';
    $labsCss = __DIR__ . '/assets/css/knd-labs.css';
    $labsConceptTheme = __DIR__ . '/assets/css/knd-labs-concept-theme.css';
    $knd3dStudioCss = __DIR__ . '/assets/css/knd-3d-studio.css';
    $extraHead = '<script>window.KND_PRICING={"text2img":{"standard":3,"high":6},"upscale":{"2x":5,"4x":8},"character":{"base":15},"consistency":{"base":5},"remove_bg":{"base":5},"texture":{"base":10},"3d_vertex":{"standard":20,"high":30}};</script>';
    $extraHead .= '<link rel="stylesheet" href="/assets/css/labs-next.css?v=' . (file_exists($labsNextCss) ? filemtime($labsNextCss) : time()) . '">';
    $extraHead .= '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
    $extraHead .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
    /* model-viewer for 3D jobs in in-shell viewer drawer */
    $extraHead .= '<script type="module" src="https://cdn.jsdelivr.net/npm/@google/model-viewer/dist/model-viewer.min.js"></script>';
    if ($currentTool === '3d_vertex') {
        $tdCss = __DIR__ . '/assets/css/labs/3d-lab.css';
        $extraHead .= '<link rel="stylesheet" href="/assets/css/labs/3d-lab.css?v=' . (file_exists($tdCss) ? filemtime($tdCss) : time()) . '">';
    }
    $extraHead .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&family=Chakra+Petch:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">';
    $extraHead .= '<link rel="stylesheet" href="/assets/css/knd-labs-concept-theme.css?v=' . (file_exists($labsConceptTheme) ? filemtime($labsConceptTheme) : time()) . '">';
    $extraHead .= '<link rel="stylesheet" href="/assets/css/knd-3d-studio.css?v=' . (file_exists($knd3dStudioCss) ? filemtime($knd3dStudioCss) : time()) . '">';

    $seoTitle = t('labs.meta.title', 'KND Labs | KND Store');
    $seoDesc = t('labs.meta.desc', 'AI-powered asset creation: Text to Image, Upscale, Character Lab, Texture Lab, Image→3D.');
    echo generateHeader($seoTitle, $seoDesc, $extraHead);
?>
<script>document.body.classList.add('ln-page', 'knd-labs-next', 'knd-labs-concept');</script>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>
<?php
$labsStudioUsername = function_exists('current_username') ? (string) (current_username() ?? '') : '';
$labsStudioInitial = $labsStudioUsername !== ''
    ? strtoupper(function_exists('mb_substr') ? mb_substr($labsStudioUsername, 0, 1, 'UTF-8') : substr($labsStudioUsername, 0, 1))
    : '?';
$labsStudioNav = [
    ['tool' => 'text2img', 'icon' => 'fa-palette', 'label' => 'Text2Img'],
    ['tool' => 'upscale', 'icon' => 'fa-search-plus', 'label' => 'Upscale'],
    ['tool' => 'consistency', 'icon' => 'fa-lock', 'label' => 'Lock'],
    ['tool' => 'remove-bg', 'icon' => 'fa-eraser', 'label' => 'Rem BG'],
    ['tool' => 'texture', 'icon' => 'fa-border-all', 'label' => 'Texture'],
    ['tool' => '3d_vertex', 'icon' => 'fa-cube', 'label' => '3D'],
    ['tool' => 'model_viewer', 'icon' => 'fa-eye', 'label' => 'Viewer'],
];
?>
<div class="ln-app knd-labs-3d-studio" id="ln-app">
  <div class="knd-labs-app-shell app-shell">
    <aside class="knd-labs-icon-sidebar icon-sidebar" aria-label="KND Labs tools">
      <a href="/labs" class="knd-labs-icon-sidebar-brand" title="KND Labs" aria-label="KND Labs"><i class="fas fa-microscope" aria-hidden="true"></i></a>
      <div class="knd-labs-icon-sidebar-scroll">
        <?php foreach ($labsStudioNav as $navItem):
            $tid = $navItem['tool'];
            $isActive = $currentTool === $tid;
            $q = $tid === 'text2img' && $providerFilter !== '' ? ('?tool=text2img&provider=' . rawurlencode($providerFilter)) : ('?tool=' . rawurlencode($tid));
            ?>
        <a href="/labs<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
           class="knd-labs-icon-btn<?php echo $isActive ? ' knd-labs-icon-btn--active' : ''; ?>"
           <?php echo $isActive ? 'aria-current="page"' : ''; ?>
           title="<?php echo htmlspecialchars($navItem['label'], ENT_QUOTES, 'UTF-8'); ?>">
          <i class="fas <?php echo htmlspecialchars($navItem['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
          <span class="knd-labs-icon-btn-label"><?php echo htmlspecialchars($navItem['label'], ENT_QUOTES, 'UTF-8'); ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <div class="knd-labs-icon-sidebar-foot">
        <div class="knd-labs-studio-credits" title="<?php echo htmlspecialchars(t('labs.balance.title', 'Available KND Points'), ENT_QUOTES, 'UTF-8'); ?>">
          <span id="knd-labs-studio-balance" class="knd-labs-studio-credits-val"><?php echo number_format((int) $balance); ?></span>
          <span class="knd-labs-studio-credits-lbl"><?php echo htmlspecialchars(t('labs.balance.kp', 'KP'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <a href="/points" class="btn btn-sm text-white-50 border border-secondary rounded-pill px-2 py-1" style="font-size:8px;letter-spacing:0.06em;text-decoration:none;" title="Get more points"><?php echo htmlspecialchars(t('labs.studio.upgrade', 'KP+'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="/labs-jobs.php" class="knd-labs-studio-avatar" title="<?php echo htmlspecialchars($labsStudioUsername !== '' ? $labsStudioUsername : 'Account', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('labs.studio.account', 'My jobs'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($labsStudioInitial, ENT_QUOTES, 'UTF-8'); ?></a>
      </div>
    </aside>

    <main class="ln-body knd-labs-studio-main">
      <div class="ln-main ln-editor-layout">
        <div class="ln-editor ln-tool-content-wrap">
          <?php
          $partial = __DIR__ . '/labs/partials/shell-' . $currentTool . '.php';
          if (file_exists($partial)) {
              include $partial;
          } else {
              echo '<div class="ln-editor-header"><h1 class="ln-editor-title">' . htmlspecialchars($currentTool) . '</h1><p class="ln-editor-subtitle">Tool not found. <a href="/labs?tool=text2img">Go to Text2Img</a>.</p></div>';
          }
          ?>
        </div>
      </div>
    </main>

    <aside class="knd-labs-tools-sidebar tools-sidebar" aria-label="Quick actions">
      <button type="button" id="ln-open-viewer" title="<?php echo htmlspecialchars(t('labs.view_details', 'View job details'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('labs.view_details', 'View job details'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-external-link-alt" aria-hidden="true"></i></button>
      <a href="/labs-jobs.php" title="My Jobs" aria-label="My Jobs"><i class="fas fa-folder-open" aria-hidden="true"></i></a>
      <a href="/labs-legacy" title="Legacy hub" aria-label="Legacy hub"><i class="fas fa-box-archive" aria-hidden="true"></i></a>
      <button type="button" class="ln-sec-settings knd-labs-tools-sidebar-spacer" aria-label="Settings" title="Settings"><i class="fas fa-cog" aria-hidden="true"></i></button>
    </aside>

    <aside class="recent-panel" aria-label="Recent jobs">
      <div class="recent-header">
        <span class="recent-title"><?php echo htmlspecialchars(t('labs.recent.title', 'Recent'), ENT_QUOTES, 'UTF-8'); ?></span>
        <div class="knd-labs-recent-prefs">
          <label>
            <input type="checkbox" id="labs-recent-private" <?php echo $labsRecentPrivate ? 'checked' : ''; ?>>
            <span><?php echo htmlspecialchars(t('labs.show_only_mine', 'Only my jobs'), ENT_QUOTES, 'UTF-8'); ?></span>
          </label>
          <a href="/labs-jobs.php" class="knd-labs-recent-viewall"><?php echo htmlspecialchars(t('labs.view_all_jobs', 'View all'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
      </div>
      <div class="recent-grid" id="ln-recent-track">
        <?php if (empty($recentJobs)): ?>
          <div class="knd-labs-recent-empty">
            <div><i class="fas fa-history" aria-hidden="true"></i></div>
            <div><?php echo htmlspecialchars(t('labs.recent.empty', 'No jobs yet'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        <?php else: ?>
          <?php foreach ($recentJobs as $j):
              $status = $j['status'] ?? 'pending';
              $tool = $j['tool'] ?? 'text2img';
              $toolIcon = $tool === 'text2img' ? 'palette' : ($tool === 'upscale' ? 'search-plus' : ($tool === 'consistency' ? 'lock' : ($tool === 'remove-bg' ? 'eraser' : ($tool === 'texture' ? 'border-all' : ($tool === '3d_vertex' ? 'cube' : 'user-astronaut')))));
              $hasImage = ($status === 'done') && !empty($j['image_url']);
              $imgSrc = $hasImage ? ('/api/labs/image.php?job_id=' . (int) $j['id']) : '';
              if ($status === 'done') {
                  $badgeClass = 'recent-item-badge--done';
              } elseif ($status === 'failed') {
                  $badgeClass = 'recent-item-badge--failed';
              } elseif ($status === 'processing') {
                  $badgeClass = 'recent-item-badge--processing';
              } elseif ($status === 'queued' || $status === 'pending') {
                  $badgeClass = 'recent-item-badge--queued';
              } else {
                  $badgeClass = 'recent-item-badge--pending';
              }
              ?>
          <a href="#" class="recent-item ln-job-card labs-view-details" data-job-id="<?php echo (int) ($j['id'] ?? 0); ?>" data-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" data-tool="<?php echo htmlspecialchars($tool, ENT_QUOTES, 'UTF-8'); ?>" aria-label="View job details">
            <span class="recent-item-badge <?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
            <div class="recent-item-inner">
              <?php if ($hasImage): ?>
                <img src="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="recent-item-icon"><i class="fas fa-<?php echo htmlspecialchars($toolIcon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
              <?php endif; ?>
            </div>
            <div class="recent-item-meta">
              <span><?php echo htmlspecialchars(date('M j', strtotime($j['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></span>
              <span><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<div class="knd-details-drawer__backdrop" id="labs-details-backdrop"></div>
<div class="knd-details-drawer" id="labs-details-drawer" tabindex="-1">
  <div class="knd-details-drawer__header d-flex justify-content-between align-items-center">
    <h5 class="text-white mb-0"><?php echo t('labs.view_details', 'View details'); ?></h5>
    <button type="button" class="btn btn-sm btn-link text-white-50 p-0" id="labs-details-close" aria-label="Close"><i class="fas fa-times"></i></button>
  </div>
  <div class="knd-details-drawer__body" id="labs-details-body"></div>
</div>

<?php $kndlabsJs = __DIR__ . '/assets/js/kndlabs.js'; ?>
<script src="/assets/js/kndlabs.js?v=<?php echo file_exists($kndlabsJs) ? filemtime($kndlabsJs) : time(); ?>"></script>
<script>
(function() {
  function run() {
    if (typeof KNDLabs === 'undefined') return;
    var currentTool = '<?php echo addslashes($currentTool); ?>';
    // Only init here for tools that do NOT have their own KNDLabs.init in the partial.
    // text2img, upscale, consistency, texture already call KNDLabs.init in shell-*.php; a second init would add a duplicate submit listener and send the job twice to the queue.
    if (currentTool === 'model_viewer') {
      if (!window.__labsShellViewBound) {
        window.__labsShellViewBound = true;
        KNDLabs.init({});
      }
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
(function() {
  var cb = document.getElementById('labs-recent-private');
  if (cb) cb.addEventListener('change', function() {
    var fd = new FormData();
    fd.set('private', cb.checked ? '1' : '0');
    fetch('/api/labs/preference.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.ok) window.location.reload(); });
  });
})();
(function() {
  var btn = document.getElementById('ln-open-viewer');
  var drawer = document.getElementById('labs-details-drawer');
  var backdrop = document.getElementById('labs-details-backdrop');
  var body = document.getElementById('labs-details-body');
  var closeBtn = document.getElementById('labs-details-close');
  function closeViewer() {
    if (drawer) drawer.classList.remove('is-open');
    if (backdrop) backdrop.classList.remove('is-visible');
  }
  if (btn && drawer && body) {
    btn.addEventListener('click', function() {
      body.innerHTML = '<p class="text-white-50 mb-0">Click a job in the <strong>Recent</strong> panel to view its details.</p>';
      if (drawer) drawer.classList.add('is-open');
      if (backdrop) backdrop.classList.add('is-visible');
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeViewer);
  if (closeBtn) closeBtn.addEventListener('click', closeViewer);
})();
</script>
<?php
    echo generateFooter();
    echo generateScripts();
} catch (\Throwable $e) {
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<h1>KND Labs – Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p><a href="/labs">Back to Labs</a></p>';
}
?>
