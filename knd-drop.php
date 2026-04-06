<?php
require_once __DIR__ . '/config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';
require_once BASE_PATH . '/includes/support_credits.php';
require_once BASE_PATH . '/includes/knd_drop.php';

require_login();
require_verified_email();

$csrfToken = csrf_token();
$userId = current_user_id();

$balance = 0;
$season = null;
$configs = [];
$history = [];

try {
    $pdo = getDBConnection();
    if ($pdo) {
        release_available_points_if_due($pdo, $userId);
        expire_points_if_due($pdo, $userId);
        $balance = get_available_points($pdo, $userId);

        $season = get_active_drop_season($pdo);
        if ($season) {
            $configs = get_drop_configs_for_display($pdo, (int)$season['id']);
        }

        $stmt = $pdo->prepare(
            "SELECT d.rarity, COALESCE(i.rarity, d.rarity) AS rarity_display, d.reward_kp, d.entry_kp, d.xp_awarded, d.created_at,
                    i.name AS item_name,
                    i.asset_path AS item_asset_path,
                    r.was_duplicate,
                    r.fragments_awarded
             FROM knd_drops d
             LEFT JOIN knd_user_drop_rewards r ON r.drop_id = d.id AND r.user_id = d.user_id
             LEFT JOIN knd_avatar_items i ON i.id = r.reward_item_id
             WHERE d.user_id = ?
             ORDER BY d.id DESC
             LIMIT 10"
        );
        $stmt->execute([$userId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    error_log('knd-drop page error: ' . $e->getMessage());
}

$seoTitle = 'KND Drop Chamber | KND Arena';
$seoDesc  = 'KND Drop Chamber — open capsules and discover rewards. A next-gen drop experience in KND Arena.';
$ogHead   = '    <meta property="og:title" content="' . htmlspecialchars($seoTitle) . '">' . "\n";
$ogHead  .= '    <meta property="og:description" content="' . htmlspecialchars($seoDesc) . '">' . "\n";
$ogHead  .= '    <meta property="og:type" content="website">' . "\n";
$ogHead  .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
$ogHead  .= '    <link rel="stylesheet" href="/assets/css/knd-drop.css?v=' . (file_exists(__DIR__ . '/assets/css/knd-drop.css') ? filemtime(__DIR__ . '/assets/css/knd-drop.css') : 0) . '">' . "\n";

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seoTitle); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/knd-tokens.css?v=<?php echo file_exists(__DIR__ . '/assets/css/knd-tokens.css') ? filemtime(__DIR__ . '/assets/css/knd-tokens.css') : 0; ?>">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="/assets/css/knd-ui.css?v=<?php echo file_exists(__DIR__ . '/assets/css/knd-ui.css') ? filemtime(__DIR__ . '/assets/css/knd-ui.css') : 0; ?>">
    <link rel="stylesheet" href="/assets/css/knd-drop.css?v=<?php echo file_exists(__DIR__ . '/assets/css/knd-drop.css') ? filemtime(__DIR__ . '/assets/css/knd-drop.css') : 0; ?>">
<link rel="stylesheet" href="/assets/css/arena-embed.css?v=<?php echo file_exists(__DIR__ . '/assets/css/arena-embed.css') ? filemtime(__DIR__ . '/assets/css/arena-embed.css') : 0; ?>">
</head>
<body class="arena-embed">
<div class="arena-embed-inner">
<?php
}
if (!$embed) {
    echo generateHeader($seoTitle, $seoDesc, $ogHead);
}

$entryKp = defined('DROP_ENTRY_KP') ? DROP_ENTRY_KP : 100;

$rarityColors = [
  'common' => [
    'bg' => 'rgba(160,174,192,.15)',
    'border' => 'rgba(160,174,192,.35)',
    'text' => '#a0aec0'
  ],
  'special' => [
    'bg' => 'rgba(139,92,246,.15)',
    'border' => 'rgba(139,92,246,.35)',
    'text' => '#8b5cf6'
  ],
  'rare' => [
    'bg' => 'rgba(66,153,225,.15)',
    'border' => 'rgba(66,153,225,.35)',
    'text' => '#4299e1'
  ],
  'epic' => [
    'bg' => 'rgba(159,122,234,.15)',
    'border' => 'rgba(159,122,234,.35)',
    'text' => '#9f7aea'
  ],
  'legendary' => [
    'bg' => 'rgba(236,201,75,.15)',
    'border' => 'rgba(236,201,75,.35)',
    'text' => '#ecc94b'
  ],
];
?>

<?php if (!$embed): ?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>
<?php endif; ?>

<section class="hero-section" style="min-height:100vh; padding-top:110px; padding-bottom:60px;">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-9 col-lg-8">

      <!-- Header -->
      <div class="text-center mb-4">
        <span class="badge bg-warning text-dark fw-bold px-3 py-1 mb-2" style="font-size:.75rem;">BETA</span>
        <?php if ($season): ?>
        <p class="mb-1" style="color:var(--knd-neon-blue,#00d4ff); font-size:1rem; font-weight:600;">
          <?php echo htmlspecialchars($season['name']); ?> (Season I)
        </p>
        <p class="text-white-50 small mb-0">
          Ends in: <span id="drop-countdown" style="font-family:'Orbitron',monospace; color:#fb923c;">—</span>
        </p>
        <div id="drop-season-progress" class="drop-season-progress" style="display:none;">
          <div id="drop-season-progress-bar" class="drop-season-progress-bar" style="width:0%;"></div>
        </div>
        <?php else: ?>
        <p class="text-white-50">No active season right now. Check back soon.</p>
        <?php endif; ?>
      </div>

      <?php if ($season): ?>

      <!-- Balance + Entry + Fragments -->
      <div id="drop-balance-strip" class="glass-card-neon p-3 mb-4 text-center drop-balance-strip">
        <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap">
          <div>
            <span class="text-white-50 small">Your KP</span><br>
            <span id="drop-balance" style="font-size:1.6rem; font-weight:900; font-family:'Orbitron',monospace; color:var(--knd-neon-blue,#00d4ff);"><?php echo number_format($balance); ?></span>
          </div>
          <div style="border-left:1px solid rgba(255,255,255,.1); padding-left:16px;">
            <span class="text-white-50 small">Entry Cost</span><br>
            <span style="font-size:1.4rem; font-weight:700; color:#fb923c;"><?php echo number_format($entryKp); ?> KP</span>
          </div>
          <div style="border-left:1px solid rgba(255,255,255,.1); padding-left:16px;">
            <span class="text-white-50 small" title="Duplicates convert to fragments for the Fragment Shop"><i class="fas fa-gem me-1"></i>Fragments</span><br>
            <span id="drop-fragments" style="font-size:1.4rem; font-weight:700; color:#a78bfa;">—</span>
          </div>
          <div id="drop-pity-wrap" style="border-left:1px solid rgba(255,255,255,.1); padding-left:16px; display:none;">
            <span class="text-white-50 small" title="Increases rare+ chance after common/special drops"><i class="fas fa-arrow-trend-up me-1"></i>Pity</span><br>
            <span id="drop-pity" style="font-size:1.2rem; font-weight:700; color:#a78bfa;">—</span>
          </div>
          <div id="drop-limit-wrap" style="border-left:1px solid rgba(255,255,255,.1); padding-left:16px;">
            <span class="text-white-50 small" title="Max 10 drops per hour"><i class="fas fa-gauge-high me-1"></i>Drops</span><br>
            <span id="drop-limit" style="font-size:1.2rem; font-weight:700; color:#22c55e;">—</span>
            <span id="drop-limit-reset" class="text-white-50 small mt-0" style="font-size:.7rem; display:none;"></span>
          </div>
        </div>
        <div class="drop-economy-links">
          <a href="/support-credits.php">How to get KP</a>
          <span class="text-white-50 mx-1">·</span>
          <a href="/rewards.php">Redeem rewards</a>
        </div>
      </div>

<!-- Reward Table (mini-cards) -->
<div class="glass-card-neon p-3 mb-4">
  <h5 class="mb-3 text-center" style="font-size:.95rem;">
    <i class="fas fa-gem me-2" style="color:var(--knd-neon-blue,#00d4ff);"></i>
    Possible Rewards
  </h5>

  <?php
    $rewardChances = [
      'common' => 55,
      'special' => 25,
      'rare' => 12,
      'epic' => 6,
      'legendary' => 2,
    ];
    $fragmentTooltip = 'Duplicates convert to fragments for the Fragment Shop';
  ?>

  <div class="row g-2 text-center">
    <?php foreach ($rarityColors as $rarity => $colors):
      $chance = $rewardChances[$rarity] ?? 0;
    ?>
    <div class="col-6 col-md-3">
      <div class="drop-reward-mini" style="background:<?php echo $colors['bg']; ?>; border:1px solid <?php echo $colors['border']; ?>;" data-tooltip="<?php echo htmlspecialchars($fragmentTooltip); ?>">
        <div class="fw-bold text-uppercase" style="font-size:.7rem; color:<?php echo $colors['text']; ?>; letter-spacing:.05em;">
          <?php echo $rarity; ?>
        </div>
        <div class="mt-1" style="font-size:.8rem;">
          Avatar Item
        </div>
        <div class="small text-white-50 mt-1">
          <?php echo $chance; ?>% chance
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

      <!-- Capsules Grid -->
      <div class="glass-card-neon p-4 mb-4">
        <div class="text-center mb-3">
          <span class="text-white-50 small">Select a capsule to open</span>
        </div>
        <div id="drop-capsules" class="d-flex flex-wrap justify-content-center gap-3">
          <?php for ($i = 1; $i <= 12; $i++): ?>
          <div class="drop-capsule" data-idx="<?php echo $i; ?>">
            <div class="drop-capsule-inner">
              <i class="fas fa-box-open"></i>
            </div>
            <span class="drop-capsule-hint"><?php echo number_format($entryKp); ?> KP</span>
          </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Result (fallback when modal closed) -->
      <div id="drop-result" class="mb-4" style="display:none;"></div>

      <!-- Reveal Modal (full-screen) -->
      <div id="drop-reveal-overlay" class="drop-reveal-overlay" aria-hidden="true">
        <button type="button" class="drop-reveal-close" id="drop-reveal-close" aria-label="Close">&times;</button>
        <div class="drop-reveal-stage">
          <div id="drop-reveal-capsule" class="drop-reveal-capsule">
            <div class="knd-drop-scanner" aria-hidden="true">
              <div class="knd-drop-scanner-ring"></div>
              <div class="knd-drop-scanner-ring knd-drop-scanner-ring--delay"></div>
              <div class="knd-drop-scanner-core"></div>
            </div>
          </div>
          <div id="drop-reveal-item" class="drop-reveal-item">
            <div class="drop-reveal-rays" aria-hidden="true">
              <svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
                <line x1="200" y1="200" x2="200" y2="20" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="350" y2="80" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="350" y2="320" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="200" y2="380" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="50" y2="320" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="50" y2="80" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="80" y2="50" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="320" y2="50" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="320" y2="350" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                <line x1="200" y1="200" x2="80" y2="350" stroke="currentColor" stroke-width="2" opacity="0.4"/>
              </svg>
            </div>
            <div id="drop-reveal-card" class="drop-reveal-card"></div>
          </div>
        </div>
      </div>

      <!-- Onboarding Modal -->
      <div id="drop-intro-overlay" class="drop-intro-overlay" aria-hidden="true">
        <div class="drop-intro-modal">
          <h3><i class="fas fa-box-open me-2"></i>Welcome to Drop Chamber</h3>
          <p>Spend <strong><?php echo number_format($entryKp); ?> KP</strong> to open a capsule and discover avatar items.</p>
          <p><strong>KP</strong> = web credits. <strong>XP</strong> = account experience. <strong>KE</strong> = avatar experience.</p>
          <p>Duplicates convert to <strong>fragments</strong> you can redeem in the Fragment Shop.</p>
          <button type="button" class="btn btn-neon-primary drop-intro-cta" id="drop-intro-close">Got it</button>
        </div>
      </div>

      <!-- History (cards) -->
      <div class="glass-card-neon p-3">
        <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Drops</h5>
        <div id="drop-history" class="drop-history-cards">
              <?php foreach ($history as $h):
                $rowRarity = $h['rarity_display'] ?? $h['rarity'];
                $rc = $rarityColors[$rowRarity] ?? $rarityColors['common'];
                $itemName = trim((string)($h['item_name'] ?? ''));
                $isDuplicate = !empty($h['was_duplicate']);
                $fragmentsAwarded = (int)($h['fragments_awarded'] ?? 0);
                $assetPath = trim((string)($h['item_asset_path'] ?? ''));
              ?>
              <div class="drop-history-card">
                <?php if ($assetPath): ?>
                <img src="<?php echo htmlspecialchars($assetPath); ?>" alt="" class="drop-history-card-thumb" style="border:1px solid <?php echo $rc['border']; ?>;">
                <?php else: ?>
                <div class="drop-history-card-thumb" style="background:<?php echo $rc['bg']; ?>; border:1px solid <?php echo $rc['border']; ?>; display:flex; align-items:center; justify-content:center;"><i class="fas fa-image text-white-50"></i></div>
                <?php endif; ?>
                <div class="drop-history-card-info">
                  <div class="drop-history-card-name" style="color:<?php echo $rc['text']; ?>;">
                    <?php echo $itemName !== '' ? htmlspecialchars($itemName) : 'Avatar Item'; ?>
                    <?php if ($isDuplicate && $fragmentsAwarded > 0): ?>
                      <span style="color:#a78bfa;"> +<?php echo $fragmentsAwarded; ?> frags</span>
                    <?php endif; ?>
                  </div>
                  <div class="drop-history-card-meta">
                    <span class="badge" style="background:<?php echo $rc['bg']; ?>; color:<?php echo $rc['text']; ?>; border:1px solid <?php echo $rc['border']; ?>; font-size:.65rem;"><?php echo ucfirst((string)$rowRarity); ?></span>
                    +<?php echo (int)$h['xp_awarded']; ?> XP · <?php echo date('M j H:i', strtotime($h['created_at'])); ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if (empty($history)): ?>
              <div class="text-center text-white-50 py-3 drop-history-empty">No drops yet.</div>
              <?php endif; ?>
        </div>
      </div>

      <div class="text-center mt-4">
        <a href="/knd-arena.php" class="text-white-50 small" style="text-decoration:underline;">
          <i class="fas fa-arrow-left me-1"></i>Back to Arena
        </a>
      </div>

      <?php endif; ?>

    </div>
  </div>
</div>
</section>

<?php if ($embed): ?>
<script src="/assets/js/navigation-extend.js"></script>
<script src="/assets/js/knd-drop-audio.js?v=<?php echo file_exists(__DIR__ . '/assets/js/knd-drop-audio.js') ? filemtime(__DIR__ . '/assets/js/knd-drop-audio.js') : 0; ?>"></script>
<script>
var DROP_CSRF = <?php echo json_encode($csrfToken); ?>;
var DROP_ENDS_AT = <?php echo $season ? json_encode($season['ends_at']) : 'null'; ?>;
var DROP_STARTS_AT = <?php echo $season && !empty($season['starts_at']) ? json_encode($season['starts_at']) : 'null'; ?>;
var DROP_ENTRY = <?php echo $entryKp; ?>;
</script>
<script src="/assets/js/knd-drop.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/knd-drop.js'); ?>"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</div></body></html>
<?php exit; endif; ?>

<script src="/assets/js/navigation-extend.js"></script>
<script src="/assets/js/knd-drop-audio.js?v=<?php echo file_exists(__DIR__ . '/assets/js/knd-drop-audio.js') ? filemtime(__DIR__ . '/assets/js/knd-drop-audio.js') : 0; ?>"></script>
<?php echo generateFooter(); ?>

<script>
var DROP_CSRF = <?php echo json_encode($csrfToken); ?>;
var DROP_ENDS_AT = <?php echo $season ? json_encode($season['ends_at']) : 'null'; ?>;
var DROP_STARTS_AT = <?php echo $season && !empty($season['starts_at']) ? json_encode($season['starts_at']) : 'null'; ?>;
var DROP_ENTRY = <?php echo $entryKp; ?>;
</script>
<script src="/assets/js/knd-drop.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/knd-drop.js'); ?>"></script>

<?php echo generateScripts(); ?>
