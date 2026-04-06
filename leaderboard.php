<?php
require_once __DIR__ . '/config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

$isLoggedIn = !empty($_SESSION['dr_user_id']);

$seoTitle = 'Leaderboard | KND Arena';
$seoDesc  = 'Global rankings by XP — season standings and Hall of Fame. Compete for the top spot across KND Arena games.';
$ogHead   = '    <meta property="og:title" content="' . htmlspecialchars($seoTitle) . '">' . "\n";
$ogHead  .= '    <meta property="og:description" content="' . htmlspecialchars($seoDesc) . '">' . "\n";
$ogHead  .= '    <meta property="og:type" content="website">' . "\n";
$ogHead  .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
echo generateHeader($seoTitle, $seoDesc, $ogHead);
?>

<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<section class="hero-section" style="min-height:100vh; padding-top:110px; padding-bottom:60px;">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">

      <!-- Header -->
      <div class="text-center mb-4">
        <span class="badge bg-success px-3 py-1 mb-2" style="font-size:.75rem;"><?php echo t('arena.live', 'LIVE'); ?></span>
        <h1 class="glow-text mb-2" style="font-size:2.2rem;">
          <i class="fas fa-trophy me-2"></i><?php echo t('arena.card_leaderboard', 'Leaderboard'); ?>
        </h1>
        <p class="text-white-50 mb-1">
          <span id="lb-season-name">—</span>
        </p>
        <p class="text-white-50 small mb-0">
          <?php echo t('lb.ends_in', 'Ends in'); ?>: <span id="lb-countdown" style="font-family:'Orbitron',monospace; color:#fb923c;">—</span>
        </p>
      </div>

      <!-- Note -->
      <div class="text-center mb-3">
        <p class="text-white-50 small mb-0" style="opacity:.9;">
          <i class="fas fa-info-circle me-1"></i><?php echo t('lb.xp_note', 'Rankings are based on XP (not Points).'); ?>
        </p>
      </div>

      <!-- Your Rank (if logged in) -->
      <?php if ($isLoggedIn): ?>
      <div id="lb-your-rank" class="glass-card-neon p-3 mb-4" style="display:none;">
        <h5 class="mb-2" style="font-size:1rem;"><i class="fas fa-user me-2" style="color:var(--knd-neon-blue,#00d4ff);"></i><?php echo t('lb.your_rank', 'Your Rank'); ?></h5>
        <div class="row g-2">
          <div class="col-6 col-md-3">
            <div class="text-white-50 small"><?php echo t('lb.season', 'Season'); ?></div>
            <div id="lb-my-season" class="fw-bold" style="color:#00d4ff;">—</div>
          </div>
          <div class="col-6 col-md-3">
            <div class="text-white-50 small"><?php echo t('lb.hall_of_fame', 'Hall of Fame'); ?></div>
            <div id="lb-my-alltime" class="fw-bold" style="color:#00d4ff;">—</div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-3" id="lb-tabs" style="border-color:rgba(0,212,255,.2);">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-season" type="button">
            <i class="fas fa-calendar-alt me-1"></i><?php echo t('lb.season', 'Season'); ?>
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-hof" type="button">
            <i class="fas fa-crown me-1"></i><?php echo t('lb.hall_of_fame', 'Hall of Fame'); ?>
          </button>
        </li>
      </ul>

      <!-- Tab content -->
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-season">
          <div class="glass-card-neon p-3">
            <div class="table-responsive">
              <table class="table table-sm table-dark mb-0" style="--bs-table-bg:transparent; font-size:.85rem;">
                <thead><tr><th>#</th><th><?php echo t('lb.player', 'Player'); ?></th><th><?php echo t('lb.level', 'Level'); ?></th><th>XP</th><th>W-L</th><th><?php echo t('lb.winrate', 'Winrate'); ?></th></tr></thead>
                <tbody id="lb-season-tbody">
                  <tr><td colspan="6" class="text-center text-white-50 py-4"><i class="fas fa-spinner fa-spin me-2"></i><?php echo t('lb.loading', 'Loading…'); ?></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="tab-hof">
          <div class="glass-card-neon p-3">
            <div class="table-responsive">
              <table class="table table-sm table-dark mb-0" style="--bs-table-bg:transparent; font-size:.85rem;">
                <thead><tr><th>#</th><th><?php echo t('lb.player', 'Player'); ?></th><th><?php echo t('lb.level', 'Level'); ?></th><th>XP</th><th>W-L</th><th><?php echo t('lb.winrate', 'Winrate'); ?></th></tr></thead>
                <tbody id="lb-hof-tbody">
                  <tr><td colspan="6" class="text-center text-white-50 py-4"><i class="fas fa-spinner fa-spin me-2"></i><?php echo t('lb.loading', 'Loading…'); ?></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="text-center mt-4">
        <a href="/knd-arena.php" class="text-white-50 small" style="text-decoration:underline;">
          <i class="fas fa-arrow-left me-1"></i><?php echo t('arena.back', 'Back to Arena'); ?>
        </a>
      </div>

    </div>
  </div>
</div>
</section>

<style>
.nav-tabs .nav-link { color: rgba(255,255,255,.6); border: none; border-bottom: 2px solid transparent; }
.nav-tabs .nav-link:hover { color: #00d4ff; }
.nav-tabs .nav-link.active { color: #00d4ff; background: transparent; border-bottom-color: #00d4ff; }
</style>

<script src="/assets/js/navigation-extend.js"></script>
<?php echo generateFooter(); ?>
<script src="/assets/js/leaderboard.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/leaderboard.js'); ?>"></script>
<?php echo generateScripts(); ?>
