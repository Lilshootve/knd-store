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
    $extraHead .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap">';
    $extraHead .= '<link rel="stylesheet" href="/assets/css/knd-labs-concept-theme.css?v=' . (file_exists($labsConceptTheme) ? filemtime($labsConceptTheme) : time()) . '">';

    $seoTitle = t('labs.meta.title', 'KND Labs | KND Store');
    $seoDesc = t('labs.meta.desc', 'AI-powered asset creation: Text to Image, Upscale, Character Lab, Texture Lab, Image→3D.');
    echo generateHeader($seoTitle, $seoDesc, $extraHead);
?>
<script>document.body.classList.add('ln-page', 'knd-labs-next', 'knd-labs-concept');</script>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<div class="ln-app" id="ln-app">
  <aside class="ln-sidebar" id="ln-sidebar">
    <div class="ln-sidebar-head">
      <a href="/labs" class="ln-brand" aria-label="KND Labs">
        <span class="ln-brand-icon"><i class="fas fa-microscope"></i></span>
        <span class="ln-brand-text">KND Labs</span>
      </a>
    </div>
    <div class="ln-balance-chip" aria-live="polite" title="<?php echo t('labs.balance.title', 'Available KND Points'); ?>">
      <i class="fas fa-coins ln-balance-icon" aria-hidden="true"></i>
      <span class="ln-balance-val"><?php echo number_format((int) $balance); ?></span>
      <span class="ln-balance-lbl"><?php echo t('labs.balance.kp', 'KP'); ?></span>
    </div>
    <nav class="ln-nav" aria-label="Tools">
      <ul class="ln-tools-list">
        <li class="ln-nav-section-label"><?php echo t('labs.sidebar.ai_tools', 'AI TOOLS'); ?></li>
        <li><a href="/labs?tool=text2img" class="ln-tool<?php echo $currentTool === 'text2img' ? ' ln-tool-active' : ''; ?>"<?php echo $currentTool === 'text2img' ? ' aria-current="page"' : ''; ?>><i class="fas fa-palette"></i><span>Text2Img</span></a></li>
        <li><a href="/labs?tool=upscale" class="ln-tool<?php echo $currentTool === 'upscale' ? ' ln-tool-active' : ''; ?>"<?php echo $currentTool === 'upscale' ? ' aria-current="page"' : ''; ?>><i class="fas fa-search-plus"></i><span>Upscale</span></a></li>
        <li><a href="/labs?tool=consistency" class="ln-tool<?php echo $currentTool === 'consistency' ? ' ln-tool-active' : ''; ?>"<?php echo $currentTool === 'consistency' ? ' aria-current="page"' : ''; ?>><i class="fas fa-lock"></i><span>Consistency</span></a></li>
        <li><a href="/labs?tool=remove-bg" class="ln-tool<?php echo $currentTool === 'remove-bg' ? ' ln-tool-active' : ''; ?>"<?php echo $currentTool === 'remove-bg' ? ' aria-current="page"' : ''; ?>><i class="fas fa-eraser"></i><span>Remove Background</span></a></li>
        <li><a href="/labs?tool=texture" class="ln-tool<?php echo $currentTool === 'texture' ? ' ln-tool-active' : ''; ?>"<?php echo $currentTool === 'texture' ? ' aria-current="page"' : ''; ?>><i class="fas fa-border-all"></i><span>Texture Lab</span></a></li>
        <li class="ln-nav-section-label"><?php echo t('labs.sidebar.3d_tools', '3D TOOLS'); ?></li>
        <li><a href="/labs?tool=3d_vertex" class="ln-tool<?php echo $currentTool === '3d_vertex' ? ' ln-tool-active' : ''; ?>"<?php echo $currentTool === '3d_vertex' ? ' aria-current="page"' : ''; ?>><i class="fas fa-cube"></i><span>3D Vertex</span></a></li>
        <li><a href="/labs?tool=model_viewer" class="ln-tool<?php echo $currentTool === 'model_viewer' ? ' ln-tool-active' : ''; ?>"<?php echo $currentTool === 'model_viewer' ? ' aria-current="page"' : ''; ?>><i class="fas fa-cube"></i><span>Model Viewer</span></a></li>
        <li><button type="button" class="ln-tool-viewer-link" id="ln-open-viewer" aria-label="Open job details viewer"><i class="fas fa-external-link-alt"></i><span>View job details</span></button></li>
      </ul>
    </nav>
    <div class="ln-sidebar-sep" aria-hidden="true"></div>
    <ul class="ln-secondary-list">
      <li><a href="/labs-jobs.php" class="ln-sec-item"><i class="fas fa-folder-open"></i><span>My Jobs</span></a></li>
      <li><a href="/labs-legacy" class="ln-sec-item"><i class="fas fa-box-archive"></i><span>Legacy hub</span></a></li>
      <li><button type="button" class="ln-sec-item ln-sec-settings" aria-label="Settings"><i class="fas fa-cog"></i><span>Settings</span></button></li>
    </ul>
  </aside>

  <main class="ln-body">
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

    <section class="ln-recent" aria-label="Recent jobs">
      <div class="ln-recent-head">
        <h2 class="ln-recent-title">Recent</h2>
        <div class="d-flex align-items-center gap-2">
          <label class="d-flex align-items-center gap-2 text-white-50 small mb-0">
            <input type="checkbox" id="labs-recent-private" <?php echo $labsRecentPrivate ? 'checked' : ''; ?>>
            <?php echo t('labs.show_only_mine', 'Only my jobs'); ?>
          </label>
          <a href="/labs-jobs.php" class="ln-recent-link">View all</a>
        </div>
      </div>
      <div class="ln-recent-track" id="ln-recent-track">
        <?php if (empty($recentJobs)): ?>
          <div class="ln-recent-empty">
            <i class="fas fa-history"></i>
            <p>No jobs yet</p>
            <span>Use any tool above to create</span>
          </div>
        <?php else: ?>
          <?php foreach ($recentJobs as $j):
            $status = $j['status'] ?? 'pending';
            $tool = $j['tool'] ?? 'text2img';
            $toolIcon = $tool === 'text2img' ? 'palette' : ($tool === 'upscale' ? 'search-plus' : ($tool === 'consistency' ? 'lock' : ($tool === 'remove-bg' ? 'eraser' : ($tool === 'texture' ? 'border-all' : ($tool === '3d_vertex' ? 'cube' : 'user-astronaut')))));
            $hasImage = ($status === 'done') && !empty($j['image_url']);
            $imgSrc = $hasImage ? ('/api/labs/image.php?job_id=' . (int)$j['id']) : '';
          ?>
          <a href="#" class="ln-job-card labs-view-details" data-job-id="<?php echo (int)($j['id'] ?? 0); ?>" data-status="<?php echo htmlspecialchars($status); ?>" data-tool="<?php echo htmlspecialchars($tool); ?>" aria-label="View job details">
            <div class="ln-job-card-thumb">
              <?php if ($hasImage): ?>
                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="ln-job-card-icon"><i class="fas fa-<?php echo $toolIcon; ?>"></i></span>
              <?php endif; ?>
            </div>
            <div class="ln-job-card-meta">
              <span class="ln-job-card-date"><?php echo date('M j, H:i', strtotime($j['created_at'])); ?></span>
              <span class="ln-job-card-status"><?php echo htmlspecialchars($status); ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>
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
      body.innerHTML = '<p class="text-white-50 mb-0">Click a job in <strong>Recent</strong> below to view its details.</p>';
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
