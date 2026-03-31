<?php
/**
 * KND Labs Next - Experimental app-style UI for KND Labs.
 * Route: /labs-next
 * Does NOT replace or modify the current KND Labs implementation.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    require_once __DIR__ . '/includes/session.php';
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/support_credits.php';
    require_once __DIR__ . '/includes/ai.php';
    require_once __DIR__ . '/includes/comfyui.php';
    require_once __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/footer.php';

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
                ? comfyui_get_user_jobs($pdo, $userId, 12)
                : comfyui_get_recent_jobs_public($pdo, 16);
        } catch (\Throwable $e) {
            $recentJobs = [];
        }
    }

    $labsNextCss = __DIR__ . '/assets/css/labs-next.css';
    $labsNextJs = __DIR__ . '/assets/js/labs-next.js';
    $extraHead = '<link rel="stylesheet" href="/assets/css/labs-next.css?v=' . (file_exists($labsNextCss) ? filemtime($labsNextCss) : time()) . '">';
    $extraHead .= '<script src="/assets/js/labs-next.js?v=' . (file_exists($labsNextJs) ? filemtime($labsNextJs) : time()) . '" defer></script>';

    $seoTitle = t('labs.next.meta.title', 'KND Labs Next | KND Store');
    $seoDesc = t('labs.next.meta.desc', 'Next-gen AI creation app: Text to Image, Upscale, Character Lab, Texture Lab, 3D Lab.');
    echo generateHeader($seoTitle, $seoDesc, $extraHead);
?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<div class="ln-app" id="ln-app">
  <aside class="ln-sidebar" id="ln-sidebar">
    <div class="ln-sidebar-head">
      <a href="/labs" class="ln-brand" aria-label="KND Labs">
        <span class="ln-brand-icon"><i class="fas fa-microscope"></i></span>
        <span class="ln-brand-text">KND Labs <em>Next</em></span>
      </a>
    </div>
    <nav class="ln-nav" aria-label="Tools">
      <ul class="ln-tools-list">
        <li><button type="button" class="ln-tool ln-tool-active" data-tool="text2img" aria-current="true"><i class="fas fa-palette"></i><span>Text2Img</span></button></li>
        <li><button type="button" class="ln-tool" data-tool="upscale"><i class="fas fa-search-plus"></i><span>Upscale</span></button></li>
        <li><button type="button" class="ln-tool" data-tool="consistency"><i class="fas fa-lock"></i><span>Consistency</span></button></li>
        <li><button type="button" class="ln-tool" data-tool="texture"><i class="fas fa-border-all"></i><span>Texture Lab</span></button></li>
        <li><button type="button" class="ln-tool" data-tool="3d"><i class="fas fa-cube"></i><span>3D Lab</span></button></li>
        <li><button type="button" class="ln-tool" data-tool="character"><i class="fas fa-user-astronaut"></i><span>Character Lab</span></button></li>
      </ul>
    </nav>
    <div class="ln-sidebar-sep" aria-hidden="true"></div>
    <ul class="ln-secondary-list">
      <li><a href="/labs-jobs.php" class="ln-sec-item"><i class="fas fa-folder-open"></i><span>My Jobs</span></a></li>
      <li><a href="/labs" class="ln-sec-item"><i class="fas fa-box-archive"></i><span>Assets</span></a></li>
      <li><button type="button" class="ln-sec-item ln-sec-settings" aria-label="Settings"><i class="fas fa-cog"></i><span>Settings</span></button></li>
    </ul>
    <div class="ln-credits-card">
      <div class="ln-credits-label">Balance</div>
      <div class="ln-credits-value" id="ln-balance"><?php echo number_format($balance); ?> <span class="ln-kp">KP</span></div>
      <a href="/support-credits.php" class="ln-credits-link">Get credits</a>
    </div>
  </aside>

  <main class="ln-body">
    <!-- Default editor layout (Text2Img, Upscale, Consistency, Texture, 3D) -->
    <div class="ln-main ln-editor-layout" id="ln-editor-layout">
      <div class="ln-editor" role="region" aria-label="Editor">
        <div class="ln-view ln-view-visible" id="ln-view-text2img" data-tool="text2img">
          <header class="ln-editor-header">
            <h1 class="ln-editor-title">Text to Image</h1>
            <p class="ln-editor-subtitle">Generate images from a text prompt. Central hub for image creation.</p>
          </header>
          <div class="ln-editor-hero">
            <div class="ln-composer-wrap">
              <textarea class="ln-composer" id="ln-prompt-text2img" placeholder="Describe your image…" rows="3"></textarea>
              <div class="ln-composer-actions">
                <div class="ln-pills">
                  <button type="button" class="ln-pill">Portrait</button>
                  <button type="button" class="ln-pill">Landscape</button>
                  <button type="button" class="ln-pill">Fantasy</button>
                </div>
                <button type="button" class="ln-cta" id="ln-generate-text2img"><i class="fas fa-wand-magic-sparkles"></i> Generate</button>
              </div>
            </div>
          </div>
        </div>
        <div class="ln-view" id="ln-view-upscale" data-tool="upscale">
          <header class="ln-editor-header">
            <h1 class="ln-editor-title">Upscale</h1>
            <p class="ln-editor-subtitle">Improve resolution and clarity. 2× or 4× upscaling.</p>
          </header>
          <div class="ln-editor-hero">
            <div class="ln-upload-zone ln-upload-zone-placeholder">
              <i class="fas fa-cloud-arrow-up"></i>
              <p>Drop image or click to upload</p>
            </div>
            <div class="ln-composer-actions ln-actions-bottom">
              <button type="button" class="ln-cta"><i class="fas fa-search-plus"></i> Upscale</button>
            </div>
          </div>
        </div>
        <div class="ln-view" id="ln-view-consistency" data-tool="consistency">
          <header class="ln-editor-header">
            <h1 class="ln-editor-title">Consistency System</h1>
            <p class="ln-editor-subtitle">Lock style or character across multiple generations.</p>
          </header>
          <div class="ln-editor-hero">
            <div class="ln-composer-wrap">
              <textarea class="ln-composer" placeholder="Scene or variation prompt…" rows="3"></textarea>
              <div class="ln-composer-actions">
                <button type="button" class="ln-cta"><i class="fas fa-lock"></i> Generate</button>
              </div>
            </div>
          </div>
        </div>
        <div class="ln-view" id="ln-view-texture" data-tool="texture">
          <header class="ln-editor-header">
            <h1 class="ln-editor-title">Texture Lab</h1>
            <p class="ln-editor-subtitle">Seamless textures for 3D and games.</p>
          </header>
          <div class="ln-editor-hero">
            <div class="ln-composer-wrap">
              <textarea class="ln-composer" placeholder="Describe the texture…" rows="3"></textarea>
              <div class="ln-composer-actions">
                <button type="button" class="ln-cta"><i class="fas fa-border-all"></i> Generate</button>
              </div>
            </div>
          </div>
        </div>
        <div class="ln-view" id="ln-view-3d" data-tool="3d">
          <header class="ln-editor-header">
            <h1 class="ln-editor-title">3D Lab</h1>
            <p class="ln-editor-subtitle">Create 3D models from text or images. Clean GLB output.</p>
          </header>
          <div class="ln-editor-hero">
            <div class="ln-composer-wrap">
              <textarea class="ln-composer" placeholder="Describe the 3D object or upload reference…" rows="3"></textarea>
              <div class="ln-composer-actions">
                <button type="button" class="ln-cta"><i class="fas fa-cube"></i> Generate</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Character Lab: viewer + right panel -->
    <div class="ln-main ln-character-layout" id="ln-character-layout" hidden>
      <div class="ln-character-viewer">
        <div class="ln-character-viewer-inner">
          <div class="ln-character-placeholder">
            <i class="fas fa-user-astronaut"></i>
            <p>Character preview</p>
            <span>Generate a character to see it here</span>
          </div>
        </div>
      </div>
      <aside class="ln-character-panel">
        <header class="ln-panel-header">
          <h2 class="ln-panel-title">Character Lab</h2>
          <p class="ln-panel-subtitle">Stylized game-ready character → 3D</p>
        </header>
        <div class="ln-panel-section">
          <label class="ln-label">Style</label>
          <div class="ln-pills ln-pills-block">
            <button type="button" class="ln-pill ln-pill-active">Stylized</button>
            <button type="button" class="ln-pill">Realistic</button>
          </div>
        </div>
        <div class="ln-panel-section">
          <label class="ln-label">Prompt</label>
          <textarea class="ln-input ln-input-area" placeholder="Describe your character…" rows="4"></textarea>
        </div>
        <div class="ln-panel-section ln-panel-credits">
          <div class="ln-credits-inline"><span class="ln-credits-inline-value"><?php echo number_format($balance); ?></span> KP</div>
        </div>
        <button type="button" class="ln-cta ln-cta-block"><i class="fas fa-wand-magic-sparkles"></i> Generate Character</button>
      </aside>
    </div>

    <!-- Recent Jobs (shared) -->
    <section class="ln-recent" aria-label="Recent jobs">
      <div class="ln-recent-head">
        <h2 class="ln-recent-title">Recent</h2>
        <a href="/labs-jobs.php" class="ln-recent-link">View all</a>
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
            $toolIcon = $tool === 'text2img' ? 'palette' : ($tool === 'upscale' ? 'search-plus' : ($tool === 'consistency' ? 'lock' : 'user-astronaut'));
            $hasImage = ($status === 'done') && !empty($j['image_url']);
            $imgSrc = $hasImage ? ('/api/labs/image.php?job_id=' . (int)$j['id']) : '';
          ?>
          <div class="ln-job-card" data-status="<?php echo htmlspecialchars($status); ?>">
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
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>
</div>

<script>
window.LN_DATA = {
  balance: <?php echo (int) $balance; ?>,
  recentCount: <?php echo count($recentJobs); ?>
};
</script>
<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>
<?php
} catch (\Throwable $e) {
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<h1>KND Labs Next – Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p><a href="/labs">Back to Labs</a></p>';
}
?>
