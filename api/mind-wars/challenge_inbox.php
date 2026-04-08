<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/games/mindwars1v1/bootstrap.php';
require_once BASE_PATH . '/includes/mind_wars_challenges.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }
    $userId = (int) current_user_id();
    rate_limit_guard($pdo, "mw_challenge_inbox_user:{$userId}", 240, 60);

    mw_challenges_ensure_table($pdo);
    mw_challenges_cleanup_expired($pdo);
    $season = mw_ensure_season($pdo);
    $seasonId = (int) ($season['id'] ?? 0);

    $incomingStmt = $pdo->prepare(
        "SELECT c.id, c.challenge_token, c.challenger_user_id, c.challenger_avatar_item_id, c.expires_at, c.created_at,
                u.username AS challenger_username,
                ai.name AS avatar_name,
                ai.asset_path AS avatar_asset,
                COALESCE(r.rank_score, 0) AS challenger_rank
         FROM knd_mind_wars_challenges c
         JOIN users u ON u.id = c.challenger_user_id
         LEFT JOIN knd_avatar_items ai ON ai.id = c.challenger_avatar_item_id
         LEFT JOIN knd_mind_wars_rankings r ON r.user_id = c.challenger_user_id AND r.season_id = c.season_id
         WHERE c.season_id = ?
           AND c.challenged_user_id = ?
           AND c.status = 'pending'
           AND c.expires_at > NOW()
         ORDER BY c.id DESC
         LIMIT 1"
    );
    $incomingStmt->execute([$seasonId, $userId]);
    $incoming = $incomingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $outgoingStmt = $pdo->prepare(
        "SELECT c.id, c.challenge_token, c.challenged_user_id, c.expires_at, c.created_at, u.username AS challenged_username
         FROM knd_mind_wars_challenges c
         JOIN users u ON u.id = c.challenged_user_id
         WHERE c.season_id = ?
           AND c.challenger_user_id = ?
           AND c.status = 'pending'
           AND c.expires_at > NOW()
         ORDER BY c.id DESC
         LIMIT 1"
    );
    $outgoingStmt->execute([$seasonId, $userId]);
    $outgoing = $outgoingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $acceptedStmt = $pdo->prepare(
        "SELECT c.challenge_token, c.battle_token, c.updated_at
         FROM knd_mind_wars_challenges c
         JOIN knd_mind_wars_battles b ON b.battle_token = c.battle_token
         WHERE c.season_id = ?
           AND c.status = 'accepted'
           AND c.battle_token IS NOT NULL
           AND b.result IS NULL
           AND (c.challenger_user_id = ? OR c.challenged_user_id = ?)
           AND c.updated_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
         ORDER BY c.id DESC
         LIMIT 1"
    );
    $acceptedStmt->execute([$seasonId, $userId, $userId]);
    $accepted = $acceptedStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $formatAsset = static function (?string $asset): string {
        $path = (string) ($asset ?? '');
        if ($path === '') return '/assets/avatars/_placeholder.svg';
        return (substr($path, 0, 1) === '/') ? $path : '/assets/avatars/' . ltrim($path, '/');
    };

    json_success([
        'season_id' => $seasonId,
        'incoming' => $incoming ? [
            'challenge_token' => (string) ($incoming['challenge_token'] ?? ''),
            'challenger_user_id' => (int) ($incoming['challenger_user_id'] ?? 0),
            'challenger_username' => (string) ($incoming['challenger_username'] ?? ''),
            'challenger_rank_score' => (int) ($incoming['challenger_rank'] ?? 0),
            'challenger_avatar_item_id' => (int) ($incoming['challenger_avatar_item_id'] ?? 0),
            'challenger_avatar_name' => (string) ($incoming['avatar_name'] ?? 'Avatar'),
            'challenger_avatar_asset' => $formatAsset($incoming['avatar_asset'] ?? ''),
            'expires_at' => (string) ($incoming['expires_at'] ?? ''),
            'created_at' => (string) ($incoming['created_at'] ?? ''),
        ] : null,
        'outgoing' => $outgoing ? [
            'challenge_token' => (string) ($outgoing['challenge_token'] ?? ''),
            'challenged_user_id' => (int) ($outgoing['challenged_user_id'] ?? 0),
            'challenged_username' => (string) ($outgoing['challenged_username'] ?? ''),
            'expires_at' => (string) ($outgoing['expires_at'] ?? ''),
            'created_at' => (string) ($outgoing['created_at'] ?? ''),
        ] : null,
        'accepted' => $accepted ? [
            'challenge_token' => (string) ($accepted['challenge_token'] ?? ''),
            'battle_token' => (string) ($accepted['battle_token'] ?? ''),
            'updated_at' => (string) ($accepted['updated_at'] ?? ''),
        ] : null,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/challenge_inbox error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

