<?php
/**
 * KND Labs HUB - /labs/ serves this as directory index
 */
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/bootstrap.php';
require_once KND_ROOT . '/includes/session.php';
require_once KND_ROOT . '/includes/config.php';
require_once KND_ROOT . '/includes/auth.php';
require_once KND_ROOT . '/includes/support_credits.php';
require_once KND_ROOT . '/includes/ai.php';
require_once KND_ROOT . '/includes/header.php';
require_once KND_ROOT . '/includes/footer.php';

require_login();

$pdo = getDBConnection();
$balance = 0;
$recentJobs = [];
if ($pdo) {
    $userId = current_user_id();
    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $balance = get_available_points($pdo, $userId);
    $recentJobs = ai_get_recent_jobs($pdo, $userId, 5);
}

$aiCss = __DIR__ . '/../assets/css/ai-tools.css';
$labsCss = __DIR__ . '/../assets/css/knd-labs.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
echo generateHeader(t('labs.meta.title', 'KND Labs | KND Store'), t('labs.meta.desc', 'AI-powered asset creation'), $extraCss);
?>
<script>document.body.classList.add('labs-hub-page');</script>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="hero-section labs-hub-hero" style="min-height:auto; padding-top:110px; padding-bottom:50px;">
  <div class="container">
    <div class="text-center mb-5">
      <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
        <span class="badge bg-warning text-dark fw-bold px-3 py-2" style="font-size:.85rem;"><i class="fas fa-flask me-1"></i>BETA</span>
      </div>
      <h1 class="glow-text mb-3" style="font-size:2.8rem;"><i class="fas fa-microscope me-2"></i><?php echo t('labs.title', 'KND Labs'); ?></h1>
      <p class="text-white-50 mx-auto mb-3" style="max-width:600px; font-size:1.1rem;"><?php echo t('labs.subtitle', 'AI-powered asset creation.'); ?></p>
      <div class="ai-balance-badge"><i class="fas fa-coins me-2"></i><span id="ai-kp-balance"><?php echo t('ai.balance', 'Balance: {kp} KP', ['kp' => number_format($balance)]); ?></span></div>
    </div>

    <div class="row g-4 justify-content-center mb-5">
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card">
          <div class="d-flex align-items-start justify-content-between mb-3"><div class="arena-card-icon"><i class="fas fa-font"></i></div><span class="badge bg-success px-2 py-1" style="font-size:.7rem;">LIVE</span></div>
          <h3 class="mb-2" style="font-size:1.25rem;"><?php echo t('ai.text2img.title'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.card_text2img_desc', 'Generate images from text prompts.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 3]); ?> &middot; ~10s</div>
          <a href="/labs-text-to-image.php" class="btn btn-neon-primary w-100 mt-auto"><i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?></a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card">
          <div class="d-flex align-items-start justify-content-between mb-3"><div class="arena-card-icon"><i class="fas fa-search-plus"></i></div><span class="badge bg-success px-2 py-1" style="font-size:.7rem;">LIVE</span></div>
          <h3 class="mb-2" style="font-size:1.25rem;"><?php echo t('ai.upscale.title'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.card_upscale_desc', 'Upscale images 2x or 4x.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 5]); ?> &middot; ~40s</div>
          <a href="/labs-upscale.php" class="btn btn-neon-primary w-100 mt-auto"><i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?></a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card">
          <div class="d-flex align-items-start justify-content-between mb-3"><div class="arena-card-icon"><i class="fas fa-user-astronaut"></i></div><span class="badge bg-success px-2 py-1" style="font-size:.7rem;">LIVE</span></div>
          <h3 class="mb-2" style="font-size:1.25rem;"><?php echo t('ai.character.title'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.card_character_desc', 'Create game/anime/realistic characters.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 15]); ?> &middot; ~30s</div>
          <a href="/labs-character-lab.php" class="btn btn-neon-primary w-100 mt-auto"><i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?></a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card">
          <div class="d-flex align-items-start justify-content-between mb-3"><div class="arena-card-icon"><i class="fas fa-border-all"></i></div><span class="badge bg-success px-2 py-1" style="font-size:.7rem;">LIVE</span></div>
          <h3 class="mb-2" style="font-size:1.25rem;"><?php echo t('ai.texture.title'); ?></h3>
          <p class="text-white-50 small flex-grow-1"><?php echo t('labs.card_texture_desc', 'Generate seamless textures for 3D/games.'); ?></p>
          <div class="labs-card-meta text-white-50 small mb-2"><?php echo t('labs.from_kp', 'From {kp} KP', ['kp' => 4]); ?> &middot; ~15s</div>
          <a href="/labs-texture-lab.php" class="btn btn-neon-primary w-100 mt-auto"><i class="fas fa-play me-2"></i><?php echo t('arena.enter', 'Enter'); ?></a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-4">
        <div class="glass-card-neon p-4 h-100 d-flex flex-column arena-card">
          <div class="d-flex align-items-start justify-content-between mb-3"><div class="arena-card-icon"><i class="fas fa-cube"></i></div><span class="badge bg-success px-2 py-1" style="font-size:.7rem;">LIVE</span></div>
          <h3 class="mb-2" style="font-size:1.25rem;">3D Lab</h3>
          <p class="text-white-50 small flex-grow-1">Create optimized 3D models from text, images, or both. Clean GLB output.</p>
          <div class="labs-card-meta text-white-50 small mb-2">30 KP &middot; ~2m</div>
          <a href="/labs-3d-lab.php" class="btn btn-neon-primary w-100 mt-auto"><i class="fas fa-play me-2"></i>Open 3D Lab</a>
        </div>
      </div>
    </div>

    <div class="glass-card-neon p-4 mb-5">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0" style="font-size:1.15rem;"><i class="fas fa-history me-2" style="color:var(--knd-neon-blue);"></i><?php echo t('labs.recent_jobs', 'Recent Jobs'); ?></h3>
        <a href="/labs-jobs.php" class="btn btn-outline-neon btn-sm"><?php echo t('labs.view_all_jobs', 'View All Jobs'); ?></a>
      </div>
      <?php if (empty($recentJobs)): ?>
      <p class="text-white-50 small mb-0"><?php echo t('labs.no_recent_jobs', 'No jobs yet. Start with any tool above.'); ?></p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-sm mb-0">
          <thead><tr><th><?php echo t('labs.job_tool', 'Tool'); ?></th><th><?php echo t('labs.job_status', 'Status'); ?></th><th><?php echo t('labs.job_cost', 'Cost'); ?></th><th><?php echo t('labs.job_created', 'Created'); ?></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($recentJobs as $j):
              $toolLabel = t('labs.job_type_' . ($j['job_type'] ?? 'img23d'), $j['job_type'] ?? 'img23d');
              $sc = ($j['status'] ?? '') === 'completed' ? 'success' : (($j['status'] ?? '') === 'failed' ? 'danger' : 'warning');
            ?>
            <tr>
              <td><?php echo htmlspecialchars($toolLabel); ?></td>
              <td><span class="badge bg-<?php echo $sc; ?>"><?php echo htmlspecialchars($j['status'] ?? ''); ?></span></td>
              <td><?php echo (int)($j['cost_kp'] ?? 0); ?> KP</td>
              <td class="text-white-50 small"><?php echo date('M j, H:i', strtotime($j['created_at'] ?? 'now')); ?></td>
              <td><?php if (($j['status'] ?? '') === 'completed'): ?><a href="/api/ai/download.php?job_id=<?php echo urlencode($j['job_uuid'] ?? ''); ?>" class="btn btn-sm btn-success" target="_blank"><i class="fas fa-download"></i></a><?php else: ?><a href="/labs-job.php?job_id=<?php echo urlencode($j['job_uuid'] ?? ''); ?>" class="btn btn-sm btn-outline-secondary"><?php echo t('labs.view', 'View'); ?></a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="text-center"><p class="text-white-50 small mb-1" style="opacity:.6;"><i class="fas fa-info-circle me-1"></i><?php echo t('labs.disclaimer', 'BETA: AI tools may have rate limits. Uses KND Points (KP).'); ?></p></div>
  </div>
</section>

<?php echo generateFooter(); echo generateScripts(); ?>
