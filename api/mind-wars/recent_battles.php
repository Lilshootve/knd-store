<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 12;
    $limit = max(1, min(30, $limit));

    $stmt = $pdo->prepare(
        "SELECT
            b.id,
            b.mode,
            b.result,
            b.rank_gained,
            b.xp_gained,
            b.knowledge_energy_gained,
            b.updated_at,
            GREATEST(0, TIMESTAMPDIFF(SECOND, b.updated_at, NOW())) AS age_seconds,
            u.username AS owner_username,
            ai.name AS owner_avatar_name,
            ai.asset_path AS owner_avatar_asset,
            ea.name AS enemy_avatar_name,
            ea.asset_path AS enemy_avatar_asset,
            pu.username AS pvp_player_username,
            eu.username AS pvp_enemy_username
         FROM knd_mind_wars_battles b
         LEFT JOIN users u ON u.id = b.user_id
         LEFT JOIN knd_avatar_items ai ON ai.id = b.avatar_item_id
         LEFT JOIN knd_avatar_items ea ON ea.id = b.enemy_avatar_id
         LEFT JOIN knd_mind_wars_battle_participants pp ON pp.battle_id = b.id AND pp.side = 'player'
         LEFT JOIN users pu ON pu.id = pp.user_id
         LEFT JOIN knd_mind_wars_battle_participants ep ON ep.battle_id = b.id AND ep.side = 'enemy'
         LEFT JOIN users eu ON eu.id = ep.user_id
         WHERE b.result IS NOT NULL
         ORDER BY b.updated_at DESC, b.id DESC
         LIMIT {$limit}"
    );
    $stmt->execute();

    $battles = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mode = (string) ($row['mode'] ?? 'pve');
        $result = strtolower((string) ($row['result'] ?? 'draw'));
        if (!in_array($result, ['win', 'lose', 'draw'], true)) {
            $result = 'draw';
        }

        $playerUser = $mode === 'pvp_ranked'
            ? (string) ($row['pvp_player_username'] ?? $row['owner_username'] ?? 'Player')
            : (string) ($row['owner_username'] ?? 'Player');
        $opponentUser = $mode === 'pvp_ranked'
            ? (string) ($row['pvp_enemy_username'] ?? 'Opponent')
            : ('Bot: ' . (string) ($row['enemy_avatar_name'] ?? 'Enemy'));

        $ownerAsset = (string) ($row['owner_avatar_asset'] ?? '');
        $enemyAsset = (string) ($row['enemy_avatar_asset'] ?? '');
        $ownerAbs = substr($ownerAsset, 0, 1) === '/';
        $enemyAbs = substr($enemyAsset, 0, 1) === '/';

        $battles[] = [
            'battle_id' => (int) ($row['id'] ?? 0),
            'mode' => $mode,
            'result' => $result,
            'player_username' => $playerUser,
            'opponent_username' => $opponentUser,
            'player_avatar_name' => (string) ($row['owner_avatar_name'] ?? 'Avatar'),
            'enemy_avatar_name' => (string) ($row['enemy_avatar_name'] ?? 'Enemy'),
            'player_avatar_asset' => $ownerAsset !== '' ? ($ownerAbs ? $ownerAsset : '/assets/avatars/' . ltrim($ownerAsset, '/')) : '/assets/avatars/_placeholder.svg',
            'enemy_avatar_asset' => $enemyAsset !== '' ? ($enemyAbs ? $enemyAsset : '/assets/avatars/' . ltrim($enemyAsset, '/')) : '/assets/avatars/_placeholder.svg',
            'rank_gained' => (int) ($row['rank_gained'] ?? 0),
            'xp_gained' => (int) ($row['xp_gained'] ?? 0),
            'avatar_xp_gained' => (int) ($row['knowledge_energy_gained'] ?? 0),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'age_seconds' => max(0, (int) ($row['age_seconds'] ?? 0)),
        ];
    }

    json_success(['battles' => $battles]);
} catch (\Throwable $e) {
    error_log('mind-wars/recent_battles error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

