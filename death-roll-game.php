<?php
require_once __DIR__ . '/config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/mw_lobby.php';

require_login();
require_verified_email();

$code = strtoupper(trim($_GET['code'] ?? ''));
if (!preg_match('/^[A-Z0-9]{8}$/', $code)) {
    header('Location: /death-roll-lobby.php');
    exit;
}

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
$csrfToken = csrf_token();
$lobbyUrl = $embed ? '/death-roll-lobby.php?embed=1' : '/death-roll-lobby.php';

$pdo = getDBConnection();
$userId = (int) current_user_id();
$L = ['user' => ['username' => '', 'level' => 1, 'xp_fill_pct' => 0], 'currencies' => ['knd_points_available' => 0, 'fragments_total' => 0], 'season' => [], 'ranking' => [], 'selected_avatar' => null, 'hero_image_url' => null, 'hero_model_url' => null];
if ($pdo && $userId > 0) {
    try {
        $L = mw_build_lobby_data_payload($pdo, $userId);
    } catch (Throwable $e) {
        error_log('death-roll-game mw_build_lobby_data_payload: ' . $e->getMessage());
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

$holoV = file_exists(__DIR__ . '/assets/css/lastroll-holo.css') ? filemtime(__DIR__ . '/assets/css/lastroll-holo.css') : 0;
$sfxV = file_exists(__DIR__ . '/assets/js/lastroll-sfx.js') ? filemtime(__DIR__ . '/assets/js/lastroll-sfx.js') : 0;
$drJsV = file_exists(__DIR__ . '/assets/js/deathroll-1v1.js') ? filemtime(__DIR__ . '/assets/js/deathroll-1v1.js') : 0;

if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    $seoTitle = 'KND LastRoll — ' . $code;
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
    <link rel="stylesheet" href="/assets/css/lastroll-holo.css?v=<?php echo $holoV; ?>">
    <link rel="stylesheet" href="/assets/css/arena-embed.css?v=<?php echo file_exists(__DIR__ . '/assets/css/arena-embed.css') ? filemtime(__DIR__ . '/assets/css/arena-embed.css') : 0; ?>">
<?php
require_once BASE_PATH . '/includes/holo_orb_assets.php';
holo_orb_emit_stylesheet_link();
?>
</head>
<body class="arena-embed lastroll-context lastroll-game">
<div class="arena-embed-inner">
<?php
    require __DIR__ . '/games/lastroll/partials/center-game.php';
?>
<script src="/assets/js/navigation-extend.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const GAME_CODE = <?php echo json_encode($code); ?>;
const CSRF = <?php echo json_encode($csrfToken); ?>;
const MY_USER_ID = <?php echo json_encode(current_user_id()); ?>;
const MY_USERNAME = <?php echo json_encode(current_username()); ?>;
const TEXTS = {
    yourTurn:      <?php echo json_encode(t('dr.game.your_turn', 'Your turn! Roll the dice!')); ?>,
    opponentTurn:  <?php echo json_encode(t('dr.game.opponent_turn', "Waiting for opponent's roll...")); ?>,
    waitingP2:     <?php echo json_encode(t('dr.game.waiting_opponent', 'Waiting for opponent...')); ?>,
    youWin:        <?php echo json_encode(t('dr.game.you_win', 'YOU WIN!')); ?>,
    youLose:       <?php echo json_encode(t('dr.game.you_lose', 'YOU LOSE!')); ?>,
    rolled:        <?php echo json_encode(t('dr.game.rolled', 'rolled')); ?>,
    outOf:         <?php echo json_encode(t('dr.game.out_of', 'out of')); ?>,
    copied:        <?php echo json_encode(t('dr.game.link_copied', 'Link copied!')); ?>,
    playing:       <?php echo json_encode(t('dr.game.status_playing', 'Game in progress')); ?>,
    waiting:       <?php echo json_encode(t('dr.game.status_waiting', 'Waiting for opponent')); ?>,
    finished:      <?php echo json_encode(t('dr.game.status_finished', 'Game over')); ?>,
    rematchWaiting: <?php echo json_encode(t('dr.game.rematch_waiting', 'Waiting for opponent to accept...')); ?>,
    rematchDeclined: <?php echo json_encode(t('dr.game.rematch_declined', 'Opponent declined the rematch.')); ?>,
    rematchRequested: <?php echo json_encode(t('dr.game.rematch_incoming', 'wants a rematch!')); ?>,
    timeoutYou:      <?php echo json_encode(t('dr.game.timeout_you', 'You lost by timeout!')); ?>,
    timeoutOpponent: <?php echo json_encode(t('dr.game.timeout_opponent', 'Opponent timed out!')); ?>,
    turnTimer:       <?php echo json_encode(t('dr.game.turn_timer', 'Time left')); ?>,
    abandoned:       <?php echo json_encode(t('dr.game.abandoned', 'Game abandoned')); ?>,
};
const lobbyUrl = <?php echo json_encode($lobbyUrl); ?>;
</script>
<script src="/assets/js/lastroll-sfx.js?v=<?php echo $sfxV; ?>" defer></script>
<script src="/assets/js/deathroll-1v1.js?v=<?php echo $drJsV; ?>" defer></script>
<?php holo_orb_emit_init_script(); ?>
</div></body></html>
<?php
    exit;
}

$LOBBY_PAGE_TITLE = 'KND LastRoll — Match ' . $code;
$LOBBY_LOADING_LOGO = 'LASTROLL';
$LOBBY_CENTER_PARTIAL = __DIR__ . '/games/lastroll/partials/center-game.php';
$LOBBY_SHELL_GAME = 'lastroll';
$LOBBY_EXTRA_HEAD_HTML =
    '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">' .
    '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">' .
    '<link rel="stylesheet" href="/assets/css/lastroll-holo.css?v=' . $holoV . '">';
$LOBBY_EXTRA_SCRIPTS_AFTER_LOBBY_JS =
    '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>' .
    '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>' .
    '<script src="/assets/js/navigation-extend.js"></script>' .
    '<script>' .
    'const GAME_CODE = ' . json_encode($code) . ';' .
    'const CSRF = ' . json_encode($csrfToken, JSON_UNESCAPED_UNICODE) . ';' .
    'const MY_USER_ID = ' . json_encode(current_user_id()) . ';' .
    'const MY_USERNAME = ' . json_encode(current_username(), JSON_UNESCAPED_UNICODE) . ';' .
    'const lobbyUrl = ' . json_encode($lobbyUrl, JSON_UNESCAPED_UNICODE) . ';' .
    'const TEXTS = {' .
    'yourTurn:' . json_encode(t('dr.game.your_turn', 'Your turn! Roll the dice!'), JSON_UNESCAPED_UNICODE) . ',' .
    'opponentTurn:' . json_encode(t('dr.game.opponent_turn', "Waiting for opponent's roll..."), JSON_UNESCAPED_UNICODE) . ',' .
    'waitingP2:' . json_encode(t('dr.game.waiting_opponent', 'Waiting for opponent...'), JSON_UNESCAPED_UNICODE) . ',' .
    'youWin:' . json_encode(t('dr.game.you_win', 'YOU WIN!'), JSON_UNESCAPED_UNICODE) . ',' .
    'youLose:' . json_encode(t('dr.game.you_lose', 'YOU LOSE!'), JSON_UNESCAPED_UNICODE) . ',' .
    'rolled:' . json_encode(t('dr.game.rolled', 'rolled'), JSON_UNESCAPED_UNICODE) . ',' .
    'outOf:' . json_encode(t('dr.game.out_of', 'out of'), JSON_UNESCAPED_UNICODE) . ',' .
    'copied:' . json_encode(t('dr.game.link_copied', 'Link copied!'), JSON_UNESCAPED_UNICODE) . ',' .
    'playing:' . json_encode(t('dr.game.status_playing', 'Game in progress'), JSON_UNESCAPED_UNICODE) . ',' .
    'waiting:' . json_encode(t('dr.game.status_waiting', 'Waiting for opponent'), JSON_UNESCAPED_UNICODE) . ',' .
    'finished:' . json_encode(t('dr.game.status_finished', 'Game over'), JSON_UNESCAPED_UNICODE) . ',' .
    'rematchWaiting:' . json_encode(t('dr.game.rematch_waiting', 'Waiting for opponent to accept...'), JSON_UNESCAPED_UNICODE) . ',' .
    'rematchDeclined:' . json_encode(t('dr.game.rematch_declined', 'Opponent declined the rematch.'), JSON_UNESCAPED_UNICODE) . ',' .
    'rematchRequested:' . json_encode(t('dr.game.rematch_incoming', 'wants a rematch!'), JSON_UNESCAPED_UNICODE) . ',' .
    'timeoutYou:' . json_encode(t('dr.game.timeout_you', 'You lost by timeout!'), JSON_UNESCAPED_UNICODE) . ',' .
    'timeoutOpponent:' . json_encode(t('dr.game.timeout_opponent', 'Opponent timed out!'), JSON_UNESCAPED_UNICODE) . ',' .
    'turnTimer:' . json_encode(t('dr.game.turn_timer', 'Time left'), JSON_UNESCAPED_UNICODE) . ',' .
    'abandoned:' . json_encode(t('dr.game.abandoned', 'Game abandoned'), JSON_UNESCAPED_UNICODE) .
    '};' .
    '</script>' .
    '<script src="/assets/js/lastroll-sfx.js?v=' . $sfxV . '" defer></script>' .
    '<script src="/assets/js/deathroll-1v1.js?v=' . $drJsV . '" defer></script>';

require __DIR__ . '/games/mind-wars/lobby-frame.php';
