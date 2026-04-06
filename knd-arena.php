<?php
require_once __DIR__ . '/config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';
require_once BASE_PATH . '/includes/knd_progress_leaderboard_panel.php';

$isLoggedIn = !empty($_SESSION['dr_user_id']);
$csrfToken = $isLoggedIn ? csrf_token() : '';

// Player progress data (for right panel)
$profileData = null;
$balance = 0;
$avatarCount = 0;
if ($isLoggedIn) {
    require_once BASE_PATH . '/includes/support_credits.php';
    require_once BASE_PATH . '/includes/knd_profile.php';
    require_once BASE_PATH . '/includes/knd_avatar.php';
    $pdo = getDBConnection();
    if ($pdo) {
        $userId = current_user_id();
        $profileData = profile_get_data($pdo, $userId);
        $balance = get_available_points($pdo, $userId);
        $inventory = avatar_get_inventory($pdo, $userId);
        $avatarCount = $inventory ? count($inventory) : 0;
    }
}

$seoTitle = 'KND Arena | KND Store';
$seoDesc  = 'KND Arena — next-gen death roll duels, promo drops, and seasonal badges. Play KND LastRoll 1v1, KND Insight, and more.';
$ogHead   = '    <meta property="og:title" content="' . htmlspecialchars($seoTitle) . '">' . "\n";
$ogHead  .= '    <meta property="og:description" content="' . htmlspecialchars($seoDesc) . '">' . "\n";
$ogHead  .= '    <meta property="og:type" content="website">' . "\n";
$ogHead  .= '    <meta property="og:url" content="https://kndstore.com/arena">' . "\n";
$ogHead  .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
$ogHead  .= '    <meta name="twitter:title" content="' . htmlspecialchars($seoTitle) . '">' . "\n";
$ogHead  .= '    <meta name="twitter:description" content="' . htmlspecialchars($seoDesc) . '">' . "\n";
$arenaCss = __DIR__ . '/assets/css/knd-labs.css';
$arenaHubCss = __DIR__ . '/assets/css/arena-hub.css';
$ogHead  .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($arenaCss) ? filemtime($arenaCss) : time()) . '">';
$ogHead  .= '<link rel="stylesheet" href="/assets/css/arena-hub.css?v=' . (file_exists($arenaHubCss) ? filemtime($arenaHubCss) : time()) . '">';
echo generateHeader($seoTitle, $seoDesc, $ogHead);
?>

<script>document.documentElement.classList.add('arena-hub-page'); document.body.classList.add('arena-hub-page');</script>
<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<div class="arena-app" id="arena-app">
  <aside class="arena-sidebar" id="arena-sidebar">
    <div class="arena-sidebar-head">
      <a href="/knd-arena.php" class="arena-brand"><span class="arena-brand-icon"><i class="fas fa-gamepad"></i></span><span class="arena-brand-text">KND Arena</span></a>
    </div>
    <nav class="arena-nav" aria-label="Arena navigation">
      <ul class="arena-nav-list">
        <li><a href="#" class="arena-nav-item arena-nav-collection" data-game="collection"><i class="fas fa-layer-group"></i><span>Avatar Collection</span></a></li>
        <li class="arena-nav-section">Games</li>
        <li><a href="#" class="arena-nav-item" data-game="mind-wars"><i class="fas fa-fist-raised"></i><span>Mind Wars</span><span class="arena-nav-badge">HOT</span></a></li>
        <li><a href="#" class="arena-nav-item" data-game="knowledge-duel"><i class="fas fa-brain"></i><span>Knowledge Duel</span></a></li>
        <li><a href="#" class="arena-nav-item" data-game="drop-chamber"><i class="fas fa-box-open"></i><span>Drop Chamber</span></a></li>
        <li class="arena-nav-section">RNG Games</li>
        <li><a href="#" class="arena-nav-item" data-game="lastroll"><i class="fas fa-dice-d20"></i><span>LastRoll</span></a></li>
        <li><a href="#" class="arena-nav-item" data-game="insight"><i class="fas fa-eye"></i><span>Insight</span></a></li>
        <li class="arena-nav-section">Community</li>
        <li><a href="#" class="arena-nav-item" data-game="leaderboard"><i class="fas fa-trophy"></i><span>Leaderboard</span></a></li>
        <li><a href="#" class="arena-nav-item" data-game="recent-battles"><i class="fas fa-scroll"></i><span>Recent Battles</span></a></li>
      </ul>
    </nav>
    <section class="arena-activity-feed" aria-label="Arena Activity">
      <div class="arena-activity-header">
        <h3 class="arena-activity-title"><span class="arena-activity-live"></span> Arena Activity</h3>
      </div>
      <div class="arena-activity-list" id="arena-activity-list">
        <div class="arena-activity-loading" id="arena-activity-loading"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
      </div>
    </section>
  </aside>

  <main class="arena-body">
    <div class="arena-main-row">
      <div class="arena-game-area">
        <div class="arena-header-badges">
          <span class="badge bg-warning text-dark fw-bold px-3 py-2 arena-beta-badge"><i class="fas fa-flask me-1"></i>BETA</span>
          <a href="/game-fairness" class="badge arena-fair-badge px-3 py-2 text-decoration-none"><i class="fas fa-shield-alt me-1"></i>Fair &amp; Transparent System</a>
        </div>
        <h2 class="arena-game-title" id="arena-game-title">Select a game</h2>
        <div class="arena-game-content" id="arena-game-content">
          <div class="arena-hero" id="arena-select-prompt">
            <div class="arena-hero-visual">
              <div class="arena-hero-portal">
                <div class="arena-hero-ring arena-hero-ring-1"></div>
                <div class="arena-hero-ring arena-hero-ring-2"></div>
                <div class="arena-hero-ring arena-hero-ring-3"></div>
                <div class="arena-hero-core"><i class="fas fa-gamepad"></i></div>
              </div>
            </div>
            <h2 class="arena-hero-title">Welcome to the Arena</h2>
            <p class="arena-hero-subtitle"><?php echo t('arena.subtitle', 'Choose your battle. Earn XP. Climb the ranks.'); ?></p>
            <div class="arena-hero-featured">
              <div class="arena-featured-card arena-featured-primary" data-game="mind-wars">
                <span class="arena-featured-badge">Featured</span>
                <div class="arena-featured-icon"><i class="fas fa-fist-raised"></i></div>
                <h3>Mind Wars</h3>
                <p>Turn-based combat with your avatar. Attack, Defend, use Abilities.</p>
                <button type="button" class="arena-cta-primary"><i class="fas fa-play"></i> Enter Battle</button>
              </div>
              <div class="arena-featured-grid">
                <button type="button" class="arena-featured-mini" data-game="knowledge-duel">
                  <i class="fas fa-brain"></i>
                  <span>Knowledge Duel</span>
                </button>
                <button type="button" class="arena-featured-mini" data-game="drop-chamber">
                  <i class="fas fa-box-open"></i>
                  <span>Drop Chamber</span>
                </button>
                <button type="button" class="arena-featured-mini" data-game="lastroll">
                  <i class="fas fa-dice-d20"></i>
                  <span>LastRoll</span>
                </button>
              </div>
            </div>
            <div class="arena-hero-quick-stats" id="arena-hero-stats">
              <span class="arena-stat-pill"><i class="fas fa-users"></i> <span id="arena-online-count">—</span> online</span>
              <span class="arena-stat-pill"><i class="fas fa-trophy"></i> Season active</span>
            </div>
          </div>
          <div id="arena-launcher-card" class="arena-launcher-card arena-hidden"></div>
          <div id="arena-game-iframe-wrap" class="arena-iframe-wrap arena-hidden">
            <iframe id="arena-game-iframe" class="arena-game-iframe" title="Game content"></iframe>
          </div>
        </div>
      </div>

      <aside class="arena-right-panel">
        <?php render_knd_progress_panel('arena', $profileData, $balance, $avatarCount, $isLoggedIn); ?>
        <?php render_knd_leaderboard_panel('arena'); ?>

        <div class="arena-progress-panel glass-card-neon arena-insight-quick">
          <h3 class="arena-panel-title mb-2"><i class="fas fa-eye me-2 arena-icon-accent"></i>KND Insight</h3>
          <p class="small text-white-50 mb-3"><?php echo t('arena.insight_blurb', 'Predict above or under on a 1–10 roll. Fast rounds, KND Points on the line.'); ?></p>
          <button type="button" class="btn btn-neon-primary w-100 arena-hub-game-trigger" data-game="insight">
            <i class="fas fa-play me-2"></i><?php echo t('arena.insight_play', 'Play'); ?>
          </button>
        </div>

        <?php if ($isLoggedIn): ?>
        <div class="arena-progress-panel glass-card-neon arena-daily-section">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="arena-panel-title mb-0"><i class="fas fa-calendar-check me-2 arena-icon-accent"></i>Daily Streak</h3>
            <span id="daily-streak-badge" class="badge arena-daily-streak-badge">Day —/7</span>
          </div>
          <div id="daily-dots" class="arena-daily-dots">
            <?php for ($d = 1; $d <= 7; $d++): ?>
            <div class="text-center flex-fill">
              <div class="arena-daily-dot daily-dot" id="daily-dot-<?php echo $d; ?>"><?php echo $d; ?></div>
              <div class="arena-daily-kp" id="daily-kp-<?php echo $d; ?>">—</div>
            </div>
            <?php endfor; ?>
          </div>
          <div class="text-center">
            <button id="daily-claim-btn" class="btn btn-neon-primary px-4" disabled>
              <i class="fas fa-gift me-2"></i><span id="daily-claim-text">Loading…</span>
            </button>
            <div id="daily-msg" class="mt-2 small arena-daily-msg"></div>
          </div>
        </div>

        <div class="arena-progress-panel glass-card-neon">
          <h3 class="arena-panel-title"><i class="fas fa-tasks me-2 arena-icon-accent"></i>Daily Missions</h3>
          <div id="missions-list">
            <div class="text-center text-white-50 py-3 small"><i class="fas fa-spinner fa-spin me-2"></i>Loading missions…</div>
          </div>
        </div>
        <?php endif; ?>

        <div class="arena-disclaimer">
          <p class="arena-disclaimer-dim"><i class="fas fa-info-circle me-1"></i><?php echo t('arena.disclaimer', 'BETA: mechanics and balances may change while we stabilize the ecosystem.'); ?></p>
          <p class="arena-disclaimer-italic"><?php echo t('arena.inspired', 'Inspired by the classic Death Roll format. KND Arena is a next-gen death roll experience.'); ?></p>
        </div>
      </aside>
    </div>
  </main>
</div>

<script src="/assets/js/navigation-extend.js"></script>
<script src="/assets/js/arena-hub.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/arena-hub.js'); ?>" defer></script>
<?php if ($isLoggedIn): ?>
<script>
var ARENA_CSRF = <?php echo json_encode($csrfToken); ?>;
var ARENA_USER_ID = <?php echo (int) current_user_id(); ?>;
</script>
<script src="/assets/js/arena-daily.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/arena-daily.js'); ?>" defer></script>
<?php endif; ?>

<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>
