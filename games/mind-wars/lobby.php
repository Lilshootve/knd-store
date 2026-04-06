<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/mw_lobby.php';

try {
    require_login();
    $csrfToken = csrf_token();
} catch (Throwable $e) {
    error_log('lobby.php bootstrap: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body>';
    echo '<h1>Service temporarily unavailable</h1></body></html>';
    exit;
}

$pdo = getDBConnection();
$userId = (int) current_user_id();
$L = ['user' => ['username' => '', 'level' => 1, 'xp_fill_pct' => 0], 'currencies' => ['knd_points_available' => 0, 'fragments_total' => 0], 'season' => [], 'ranking' => [], 'selected_avatar' => null, 'hero_image_url' => null, 'hero_model_url' => null];
if ($pdo && $userId > 0) {
    try {
        $L = mw_build_lobby_data_payload($pdo, $userId);
    } catch (Throwable $e) {
        error_log('lobby.php mw_build_lobby_data_payload: ' . $e->getMessage());
    }
}

$lobbyCss = __DIR__ . '/lobby.css';
$lobbyJs = __DIR__ . '/lobby.js';
$mwCardCss = __DIR__ . '/mw-avatar-cards.css';
$mwCardJs = __DIR__ . '/mw-avatar-card.js';
$levelsCss = __DIR__ . '/../../assets/css/levels.css';
$cssV = file_exists($lobbyCss) ? filemtime($lobbyCss) : 0;
$jsV = file_exists($lobbyJs) ? filemtime($lobbyJs) : 0;
$mwCardCssV = file_exists($mwCardCss) ? filemtime($mwCardCss) : 0;
$mwCardJsV = file_exists($mwCardJs) ? filemtime($mwCardJs) : 0;
$levelsCssV = file_exists($levelsCss) ? filemtime($levelsCss) : 0;

$LOBBY_PAGE_TITLE = 'KND Games — Mind Wars Lobby';
$LOBBY_CENTER_PARTIAL = __DIR__ . '/lobby-partials/avatar_stage.php';
$LOBBY_SHELL_GAME = 'mind-wars';
require __DIR__ . '/lobby-frame.php';
