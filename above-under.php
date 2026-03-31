<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/support_credits.php';

require_login();
require_verified_email();

$csrfToken = csrf_token();
$userId = current_user_id();

$balance = 0;
$history = [];
try {
    $pdo = getDBConnection();
    if ($pdo) {
        release_available_points_if_due($pdo, $userId);
        expire_points_if_due($pdo, $userId);
        $balance = get_available_points($pdo, $userId);

        $stmt = $pdo->prepare(
            'SELECT choice, rolled_value, is_win, entry_points, payout_points, xp_awarded, created_at
             FROM above_under_rolls WHERE user_id = ? ORDER BY id DESC LIMIT 10'
        );
        $stmt->execute([$userId]);
        $history = $stmt->fetchAll();
    }
} catch (\Throwable $e) {
    error_log('above-under page error: ' . $e->getMessage());
}

$au_entry_options = [
    ['v' => 10, 'label' => '10'],
    ['v' => 25, 'label' => '25'],
    ['v' => 50, 'label' => '50'],
    ['v' => 100, 'label' => '100'],
    ['v' => 200, 'label' => '200'],
    ['v' => 500, 'label' => '500'],
    ['v' => 1000, 'label' => '1K'],
    ['v' => 2500, 'label' => '2.5K'],
    ['v' => 5000, 'label' => '5K'],
];

$seoTitle = t('au.page_title', 'KND Insight') . ' | KND Games';
$seoDesc  = t('au.page_desc', 'KND Insight — predict if the number is above or under, and win KND Points.');

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

/* Lobby payload (Mind Wars shell — same as Knowledge Duel) */
$L = [
    'user' => ['username' => '', 'level' => 1, 'xp_fill_pct' => 0],
    'currencies' => ['knd_points_available' => 0, 'fragments_total' => 0],
    'season' => [],
    'ranking' => [],
    'selected_avatar' => null,
    'hero_image_url' => null,
    'missions' => [],
    'avatars' => [],
    'notifications' => ['unread_count' => 0],
    'online_hint' => 0,
];
if (!$embed) {
    require_once __DIR__ . '/includes/mw_lobby.php';
    $pdoL = getDBConnection();
    $uidL = (int) current_user_id();
    if ($pdoL && $uidL > 0) {
        try {
            $L = mw_build_lobby_data_payload($pdoL, $uidL);
        } catch (\Throwable $e) {
            error_log('above-under lobby payload: ' . $e->getMessage());
        }
    }
}

$auArenaCss = __DIR__ . '/assets/css/knd-insight-arena.css';
$insightLobbyCss = __DIR__ . '/assets/css/insight-lobby.css';
$lobbyCss = __DIR__ . '/games/mind-wars/lobby.css';
$mwCardCss = __DIR__ . '/games/mind-wars/mw-avatar-cards.css';
$levelsCss = __DIR__ . '/assets/css/levels.css';
$vArena = file_exists($auArenaCss) ? filemtime($auArenaCss) : 0;
$vLobbyShell = file_exists($insightLobbyCss) ? filemtime($insightLobbyCss) : 0;
$vLo = file_exists($lobbyCss) ? filemtime($lobbyCss) : 0;
$vMw = file_exists($mwCardCss) ? filemtime($mwCardCss) : 0;
$vL = file_exists($levelsCss) ? filemtime($levelsCss) : 0;
$mwCardJs = __DIR__ . '/games/mind-wars/mw-avatar-card.js';
$kdShellJs = __DIR__ . '/games/mind-wars/kd-lobby-shell.js';
$vMwJ = file_exists($mwCardJs) ? filemtime($mwCardJs) : 0;
$vKs = file_exists($kdShellJs) ? filemtime($kdShellJs) : 0;

$gamePartial = __DIR__ . '/games/knd-insight/partials/game_board.php';

if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    $ogHead = '';
    $ogHead .= '<link rel="stylesheet" href="/assets/css/knd-insight-arena.css?v=' . $vArena . '">' . "\n";
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seoTitle); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="/assets/css/knd-ui.css?v=<?php echo file_exists(__DIR__ . '/assets/css/knd-ui.css') ? filemtime(__DIR__ . '/assets/css/knd-ui.css') : 0; ?>">
    <link rel="stylesheet" href="/assets/css/knd-insight-arena.css?v=<?php echo $vArena; ?>">
    <link rel="stylesheet" href="/assets/css/arena-embed.css?v=<?php echo file_exists(__DIR__ . '/assets/css/arena-embed.css') ? filemtime(__DIR__ . '/assets/css/arena-embed.css') : 0; ?>">
</head>
<body class="arena-embed insight-context insight-page">
<div class="arena-embed-inner">
<div class="insight-page">
<section class="hero-section" style="min-height:100vh; padding-top:12px; padding-bottom:32px;">
  <div class="container-fluid px-2 px-md-3">
    <div class="text-center mb-2">
      <span class="badge rounded-pill mb-2" style="font-size:0.7rem;letter-spacing:0.1em;background:rgba(139,92,246,.2);border:1px solid rgba(139,92,246,.35);color:#c4b5fd;">BETA</span>
      <p class="text-white-50 small mb-3 mb-md-4"><?php echo t('au.subtitle', 'Pick a side. Roll the number. 1–5 = Under, 6–10 = Above.'); ?></p>
    </div>
<?php
    require $gamePartial;
?>
  </div>
</section>
</div>
<script src="/assets/js/navigation-extend.js"></script>
<script>var AU_CSRF = <?php echo json_encode($csrfToken); ?>;</script>
<script src="/assets/js/above-under.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/above-under.js'); ?>"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</div></body></html>
<?php
    exit;
}

require_once __DIR__ . '/includes/favicon_links.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($seoDesc, ENT_QUOTES, 'UTF-8'); ?>">
<?php echo generateFaviconLinks(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/levels.css?v=<?php echo (int) $vL; ?>">
<link rel="stylesheet" href="/games/mind-wars/lobby.css?v=<?php echo (int) $vLo; ?>">
<link rel="stylesheet" href="/games/mind-wars/mw-avatar-cards.css?v=<?php echo (int) $vMw; ?>">
<link rel="stylesheet" href="/assets/css/knd-insight-arena.css?v=<?php echo (int) $vArena; ?>">
<link rel="stylesheet" href="/assets/css/insight-lobby.css?v=<?php echo (int) $vLobbyShell; ?>">
</head>
<body>
<div id="toast-container"></div>
<div id="loading-screen">
  <div class="ls-logo"><?php echo htmlspecialchars(t('au.loading_logo', 'KND INSIGHT'), ENT_QUOTES, 'UTF-8'); ?></div>
  <div class="ls-sub">KND Games</div>
  <div class="ls-bar-wrap">
    <div class="ls-bar"><div class="ls-fill" id="ls-fill"></div></div>
    <div class="ls-msg" id="ls-msg">LOADING…</div>
  </div>
</div>
<div id="bg-layer">
  <canvas id="star-canvas"></canvas>
  <div class="horizon"></div>
  <div class="persp-floor"></div>
</div>
<div class="lobby-shell">
<?php require __DIR__ . '/games/mind-wars/lobby-partials/topbar.php'; ?>
<div class="lobby-content">
<?php require __DIR__ . '/games/mind-wars/lobby-partials/panels_left.php'; ?>
<div class="center-col insight-lobby-center">
  <div class="insight-lobby-hero">
    <span class="insight-lobby-badge">BETA</span>
    <h1 class="insight-lobby-title"><?php echo htmlspecialchars(t('au.hero_title', 'PREDICTION ARENA'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="insight-lobby-sub"><?php echo t('au.subtitle', 'Pick a side. Roll the number. 1–5 = Under, 6–10 = Above.'); ?></p>
  </div>
<?php require $gamePartial; ?>
</div>
<?php
$mwShellGame = 'insight';
require __DIR__ . '/games/mind-wars/lobby-partials/panels_right.php';
?>
</div>
<nav class="bottom-nav" aria-label="KND Games navigation">
  <a class="bnav-item" href="/games/mind-wars/lobby.php"><div class="bnav-icon">🏠</div><div class="bnav-label">LOBBY</div></a>
  <a class="bnav-item" href="/tools/cards/index.html"><div class="bnav-icon">⬡</div><div class="bnav-label">AVATARS</div></a>
  <a class="bnav-item bnav-item--neural-link" href="/games/knd-neural-link/drops.php" title="KND Neural Link"><div class="bnav-icon" aria-hidden="true">🧬</div><div class="bnav-label">NEURAL LINK</div></a>
  <a class="bnav-item" href="/leaderboard.php"><div class="bnav-icon">🏆</div><div class="bnav-label">RANKS</div></a>
  <a class="bnav-item" href="/games/mind-wars/lobby.php"><div class="bnav-icon">🎒</div><div class="bnav-label">BAG</div></a>
</nav>
</div>
<script>
window.MW_LOBBY_CSRF = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;
window.MW_LOBBY_INITIAL = <?php echo json_encode($L, JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)); ?>;
window.MW_SHELL_GAME = 'insight';
var AU_CSRF = <?php echo json_encode($csrfToken); ?>;
</script>
<script src="/games/mind-wars/mw-avatar-card.js?v=<?php echo (int) $vMwJ; ?>"></script>
<script src="/games/mind-wars/kd-lobby-shell.js?v=<?php echo (int) $vKs; ?>"></script>
<script src="/assets/js/above-under.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/above-under.js'); ?>"></script>
</body>
</html>
