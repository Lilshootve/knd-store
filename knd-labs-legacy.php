<?php
/**
 * KND Labs - Legacy hub (card-based). Kept for migration/rollback.
 * Original /labs experience before app shell. Use /labs-legacy or link here if needed.
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
            ? comfyui_get_user_jobs($pdo, $userId, 8)
            : comfyui_get_recent_jobs_public($pdo, 24);
    } catch (\Throwable $e) {
        $recentJobs = [];
    }
}

$aiCss = __DIR__ . '/assets/css/ai-tools.css';
$labsCss = __DIR__ . '/assets/css/knd-labs.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
$seoTitle = t('labs.meta.title', 'KND Labs | KND Store');
$seoDesc = t('labs.meta.desc', 'AI-powered asset creation: Text to Image, Upscale, Character Lab, Texture Lab, Image→3D.');
echo generateHeader($seoTitle, $seoDesc, $extraCss);
?>

<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="hero-section labs-hub-hero" style="min-height:auto; padding-top:110px; padding-bottom:50px;">
  <div class="container">

    <div class="text-center mb-5">
      <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
        <span class="badge bg-warning text-dark fw-bold px-3 py-2" style="font-size:.85rem; letter-spacing:.05em;">
          <i class="fas fa-flask me-1"></i>BETA
        </span>
      </div>
      <h1 class="glow-text mb-3" style="font-size:2.8rem;">
        <i class="fas fa-microscope me-2"></i><?php echo t('labs.title', 'KND Labs'); ?>
      </h1>
      <p class="text-white-50 mx-auto mb-3" style="max-width:600px; font-size:1.1rem;">
        <?php echo t('labs.subtitle', 'AI-powered asset creation. Text to Image, Upscale, Character Lab, Texture Lab, and more.'); ?>
      </p>
      <div class="ai-balance-badge">
        <i class="fas fa-coins me-2"></i>
        <span id="ai-kp-balance"><?php echo t('ai.balance', 'Balance: {kp} KP', ['kp' => number_format($balance)]); ?></span>
      </div>
    </div>

    <!-- Tool Cards -->
    <div class="row g-4 justify-content-center mb-5">
      <div class="col-12 col-lg-6 col-xl-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card labs-tool-card labs-tool-card-main">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="arena-card-icon"><i class="fas fa-palette"></i></div>
            <span class="badge bg-primary px-2 py-1" style="font-size:.7rem;"><?php echo t('labs.main_tool', 'MAIN'); ?></span>
          </div>
          <h3 class="mb-2" style="font-size:1.35rem;"><?php echo t('labs.canvas.title', 'Canvas'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.canvas.card_desc', 'Main AI creation workspace. Generate, refine and direct visual output.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 3]); ?> · <?php echo t('labs.avg_time', '~{time}', ['time' => '10s']); ?></div>
          <a href="/labs?tool=text2img" class="btn btn-neon-primary w-100 mt-auto">
            <i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?>
          </a>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card labs-tool-card">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="arena-card-icon"><i class="fas fa-search-plus"></i></div>
            <span class="badge bg-success px-2 py-1" style="font-size:.7rem;"><?php echo t('arena.live', 'LIVE'); ?></span>
          </div>
          <h3 class="mb-2" style="font-size:1.25rem;"><?php echo t('ai.upscale.title'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.card_upscale_desc', 'Upscale images 2x or 4x.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 5]); ?> · <?php echo t('labs.avg_time', '~{time}', ['time' => '40s']); ?></div>
          <a href="/labs?tool=upscale" class="btn btn-neon-primary w-100 mt-auto">
            <i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?>
          </a>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card labs-tool-card">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="arena-card-icon"><i class="fas fa-lock"></i></div>
            <span class="badge bg-success px-2 py-1" style="font-size:.7rem;"><?php echo t('arena.live', 'LIVE'); ?></span>
          </div>
          <h3 class="mb-2" style="font-size:1.25rem;"><?php echo t('labs.consistency.title', 'Consistency System'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.consistency.card_desc', 'Generate images with locked style or character consistency.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 5]); ?> · <?php echo t('labs.avg_time', '~{time}', ['time' => '30s']); ?></div>
          <a href="/labs?tool=consistency" class="btn btn-neon-primary w-100 mt-auto">
            <i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?>
          </a>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card labs-tool-card">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="arena-card-icon"><i class="fas fa-cube"></i></div>
            <span class="badge bg-success px-2 py-1" style="font-size:.7rem;"><?php echo t('arena.live', 'LIVE'); ?></span>
          </div>
          <h3 class="mb-2" style="font-size:1.25rem;">3D Lab</h3>
          <p class="text-white-50 small flex-grow-1">Create optimized 3D models from text, images, or both. Generate clean GLB previews with smart presets, advanced controls, and a dedicated 3D pipeline.</p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 30]); ?> · <?php echo t('labs.avg_time', '~{time}', ['time' => '2m']); ?></div>
          <a href="/labs?tool=3d" class="btn btn-neon-primary w-100 mt-auto">
            <i class="fas fa-play me-2"></i>Open 3D Lab
          </a>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card labs-tool-card">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="arena-card-icon"><i class="fas fa-border-all"></i></div>
            <span class="badge bg-success px-2 py-1" style="font-size:.7rem;"><?php echo t('arena.live', 'LIVE'); ?></span>
          </div>
          <h3 class="mb-2" style="font-size:1.25rem;"><?php echo t('ai.texture.title'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.card_texture_desc', 'Generate seamless textures for 3D/games.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 4]); ?> · <?php echo t('labs.avg_time', '~{time}', ['time' => '15s']); ?></div>
          <a href="/labs?tool=texture" class="btn btn-neon-primary w-100 mt-auto">
            <i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?>
          </a>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card labs-tool-card">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="arena-card-icon"><i class="fas fa-user-astronaut"></i></div>
            <span class="badge bg-success px-2 py-1" style="font-size:.7rem;"><?php echo t('arena.live', 'LIVE'); ?></span>
          </div>
          <h3 class="mb-2" style="font-size:1.25rem;"><?php echo t('ai.character.title', 'Character Lab'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.card_character_desc', 'Create game/anime/realistic characters.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 15]); ?> · ~30s</div>
          <a href="/labs?tool=character" class="btn btn-neon-primary w-100 mt-auto">
            <i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?>
          </a>
        </div>
      </div>
    </div>

    <!-- Recent Jobs -->
    <div class="glass-card-neon p-4 mb-5" id="labs-recent-section">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h3 class="mb-0" style="font-size:1.15rem;"><i class="fas fa-history me-2" style="color:var(--knd-neon-blue);"></i><?php echo t('labs.recent_jobs', 'Recent Jobs'); ?></h3>
        <div class="d-flex align-items-center gap-2">
          <label class="d-flex align-items-center gap-2 text-white-50 small mb-0">
            <input type="checkbox" id="labs-recent-private" <?php echo $labsRecentPrivate ? 'checked' : ''; ?>>
            <?php echo t('labs.show_only_mine', 'Only my jobs'); ?>
          </label>
          <a href="/labs-jobs.php" class="btn btn-neon-primary btn-sm"><?php echo t('labs.view_all_jobs', 'View All Jobs'); ?></a>
        </div>
      </div>
      <?php if (empty($recentJobs)): ?>
      <p class="text-white-50 small mb-0" id="labs-recent-empty"><?php echo $labsRecentPrivate ? t('labs.no_recent_jobs', 'No jobs yet. Start with any tool above.') : t('labs.no_public_jobs', 'No public creations yet. Be the first!'); ?></p>
      <?php else: ?>
      <div class="row g-3" id="labs-recent-grid">
        <?php foreach ($recentJobs as $j):
          $status = $j['status'] ?? 'pending';
          $statusClass = $status === 'done' ? 'success' : ($status === 'failed' ? 'danger' : 'warning');
          $tool = $j['tool'] ?? 'text2img';
          $toolLabel = $tool === 'text2img' ? (t('labs.canvas.title', 'Canvas')) : ($tool === 'upscale' ? t('ai.upscale.title', 'Upscale') : ($tool === 'consistency' ? t('labs.consistency.title', 'Consistency System') : t('ai.character.title', 'Character Lab')));
          $toolIcon = $tool === 'text2img' ? 'font' : ($tool === 'upscale' ? 'search-plus' : ($tool === 'consistency' ? 'lock' : 'user-astronaut'));
          $hasImage = ($status === 'done') && !empty($j['image_url']);
          $imgSrc = $hasImage ? ('/api/labs/image.php?job_id=' . (int)$j['id']) : '';
          $downloadHref = $hasImage ? ('/api/labs/image.php?job_id=' . (int)$j['id'] . '&download=1') : '#';
        ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="labs-recent-job-card rounded overflow-hidden bg-dark" style="border:1px solid rgba(0,212,255,0.2);">
            <div class="d-flex align-items-center justify-content-center bg-black" style="aspect-ratio:1; min-height:100px;">
              <?php if ($hasImage): ?>
              <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="" class="img-fluid" style="object-fit:cover; width:100%; height:100%;">
              <?php else: ?>
              <i class="fas fa-<?php echo $toolIcon; ?> fa-2x text-white-50"></i>
              <?php endif; ?>
            </div>
            <div class="p-2 small">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                <span class="text-white-50"><?php echo date('M j, H:i', strtotime($j['created_at'])); ?></span>
                <span class="badge bg-<?php echo $statusClass; ?>" style="font-size:.7rem;"><?php echo htmlspecialchars($status); ?></span>
              </div>
              <div class="text-white-50" style="font-size:.75rem;"><i class="fas fa-<?php echo $toolIcon; ?> me-1"></i><?php echo htmlspecialchars($toolLabel); ?></div>
              <?php if ($hasImage): ?>
              <a href="<?php echo htmlspecialchars($downloadHref); ?>" class="btn btn-neon-primary btn-sm w-100 mt-1" download><i class="fas fa-download me-1"></i><?php echo t('ai.download', 'Download'); ?></a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="text-center">
      <p class="text-white-50 small mb-1" style="opacity:.6;">
        <i class="fas fa-info-circle me-1"></i><?php echo t('labs.disclaimer', 'BETA: AI tools may have rate limits. Uses KND Points (KP).'); ?>
      </p>
    </div>
  </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>
<script>
(function() {
  var cb = document.getElementById('labs-recent-private');
  if (!cb) return;
  cb.addEventListener('change', function() {
    var fd = new FormData();
    fd.set('private', cb.checked ? '1' : '0');
    fetch('/api/labs/preference.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok) location.reload();
      });
  });
})();
</script>
<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>
<?php } catch (\Throwable $e) {
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<h1>KND Labs - Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p><a href="/">Volver al inicio</a></p>';
} ?>
