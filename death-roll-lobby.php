<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mw_lobby.php';
require_once __DIR__ . '/includes/support_credits.php';

require_login();
require_verified_email();

$csrfToken = csrf_token();
$username = htmlspecialchars(current_username());

$myKpBalance = 0;
try {
    $pdoLobby = getDBConnection();
    if ($pdoLobby) {
        $myKpBalance = get_available_points($pdoLobby, current_user_id());
    }
} catch (\Throwable $e) { /* graceful */ }

$pdo = getDBConnection();
$userId = (int) current_user_id();
$L = ['user' => ['username' => '', 'level' => 1, 'xp_fill_pct' => 0], 'currencies' => ['knd_points_available' => 0, 'fragments_total' => 0], 'season' => [], 'ranking' => [], 'selected_avatar' => null, 'hero_image_url' => null, 'hero_model_url' => null];
if ($pdo && $userId > 0) {
    try {
        $L = mw_build_lobby_data_payload($pdo, $userId);
    } catch (Throwable $e) {
        error_log('death-roll-lobby mw_build_lobby_data_payload: ' . $e->getMessage());
    }
}

$lobbyCss = __DIR__ . '/games/mind-wars/lobby.css';
$lobbyJs = __DIR__ . '/games/mind-wars/lobby.js';
$mwCardCss = __DIR__ . '/games/mind-wars/mw-avatar-cards.css';
$mwCardJs = __DIR__ . '/games/mind-wars/mw-avatar-card.js';
$levelsCss = __DIR__ . '/assets/css/levels.css';
$cssV = file_exists($lobbyCss) ? filemtime($lobbyCss) : 0;
$jsV = file_exists($lobbyJs) ? filemtime($lobbyJs) : 0;
$mwCardCssV = file_exists($mwCardCss) ? filemtime($mwCardCss) : 0;
$mwCardJsV = file_exists($mwCardJs) ? filemtime($mwCardJs) : 0;
$levelsCssV = file_exists($levelsCss) ? filemtime($levelsCss) : 0;

$lastrollLobbyHolo = __DIR__ . '/games/lastroll/lastroll-lobby-holo.css';
$lastrollLobbyHoloV = file_exists($lastrollLobbyHolo) ? filemtime($lastrollLobbyHolo) : 0;
$drJsV = file_exists(__DIR__ . '/assets/js/deathroll-1v1.js') ? filemtime(__DIR__ . '/assets/js/deathroll-1v1.js') : 0;

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    $seoTitle = 'KND LastRoll | Lobby';
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
    <link rel="stylesheet" href="/games/lastroll/lastroll-lobby-holo.css?v=<?php echo $lastrollLobbyHoloV; ?>">
    <link rel="stylesheet" href="/assets/css/arena-embed.css?v=<?php echo file_exists(__DIR__ . '/assets/css/arena-embed.css') ? filemtime(__DIR__ . '/assets/css/arena-embed.css') : 0; ?>">
<?php
require_once __DIR__ . '/includes/holo_orb_assets.php';
holo_orb_emit_stylesheet_link();
?>
</head>
<body class="arena-embed lastroll-context lastroll-page">
<div class="arena-embed-inner">
<?php
    require __DIR__ . '/games/lastroll/partials/center-lobby.php';
?>
<script src="/assets/js/navigation-extend.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = <?php echo json_encode($csrfToken); ?>;
const MY_USERNAME = <?php echo json_encode(current_username()); ?>;
const MY_KP_BALANCE = <?php echo (int)$myKpBalance; ?>;
</script>
<script src="/assets/js/deathroll-1v1.js?v=<?php echo $drJsV; ?>" defer></script>
<?php holo_orb_emit_init_script(); ?>
</div></body></html>
<?php
    exit;
}

$LOBBY_PAGE_TITLE = 'KND LastRoll — Lobby';
$LOBBY_LOADING_LOGO = 'LASTROLL';
$LOBBY_CENTER_PARTIAL = __DIR__ . '/games/lastroll/partials/center-lobby.php';
$LOBBY_SHELL_GAME = 'lastroll';
$LOBBY_EXTRA_HEAD_HTML =
    '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">' .
    '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">' .
    '<link rel="stylesheet" href="/games/lastroll/lastroll-lobby-holo.css?v=' . $lastrollLobbyHoloV . '">';
$LOBBY_EXTRA_SCRIPTS_AFTER_LOBBY_JS =
    '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>' .
    '<script src="/assets/js/navigation-extend.js"></script>' .
    '<script>const CSRF = ' . json_encode($csrfToken, JSON_UNESCAPED_UNICODE) .
    '; const MY_USERNAME = ' . json_encode(current_username(), JSON_UNESCAPED_UNICODE) .
    '; const MY_KP_BALANCE = ' . (int) $myKpBalance . ';</script>' .
    '<script src="/assets/js/deathroll-1v1.js?v=' . $drJsV . '" defer></script>';

require __DIR__ . '/games/mind-wars/lobby-frame.php';
