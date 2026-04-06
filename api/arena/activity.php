<?php
/**
 * Arena Activity Feed - Public API (no login required)
 * Returns recent Mind Wars battles for the activity feed
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');

require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/json.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        json_success(['activities' => []]);
        exit;
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 8;
    $limit = max(1, min(20, $limit));

    $stmt = $pdo->prepare(
        "SELECT
            b.id,
            b.mode,
            b.result,
            b.xp_gained,
            b.updated_at,
            GREATEST(0, TIMESTAMPDIFF(SECOND, b.updated_at, NOW())) AS age_seconds,
            u.username AS owner_username,
            ai.name AS owner_avatar_name,
            ea.name AS enemy_avatar_name,
            pu.username AS pvp_player_username,
            eu.username AS pvp_enemy_username
         FROM knd_mind_wars_battles b
         LEFT JOIN users u ON u.id = b.user_id
         LEFT JOIN knd_avatar_items ai ON ai.id = b.avatar_item_id
         LEFT JOIN knd_avatar_items ea ON ea.id = b.enemy_avatar_id
         LEFT JOIN knd_mind_wars_battle_participants pp ON pp.battle_id = b.id AND pp.side = 'player'
         LEFT JOIN knd_mind_wars_battle_participants ep ON ep.battle_id = b.id AND ep.side = 'enemy'
         LEFT JOIN users pu ON pu.id = pp.user_id
         LEFT JOIN users eu ON eu.id = ep.user_id
         WHERE b.result IS NOT NULL
         ORDER BY b.updated_at DESC, b.id DESC
         LIMIT " . (int) $limit
    );
    $stmt->execute();

    $activities = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mode = (string) ($row['mode'] ?? 'pve');
        $result = strtolower((string) ($row['result'] ?? 'draw'));
        $playerUser = $mode === 'pvp_ranked'
            ? (string) ($row['pvp_player_username'] ?? $row['owner_username'] ?? 'Player')
            : (string) ($row['owner_username'] ?? 'Player');
        $opponentUser = $mode === 'pvp_ranked'
            ? (string) ($row['pvp_enemy_username'] ?? 'Opponent')
            : ((string) ($row['enemy_avatar_name'] ?? 'Enemy'));
        $xp = (int) ($row['xp_gained'] ?? 0);
        $ageSec = max(0, (int) ($row['age_seconds'] ?? 0));

        $timeAgo = 'Just now';
        if ($ageSec >= 60) $timeAgo = floor($ageSec / 60) . ' min ago';
        if ($ageSec >= 3600) $timeAgo = floor($ageSec / 3600) . 'h ago';

        $text = $result === 'win'
            ? (htmlspecialchars($playerUser) . ' defeated ' . htmlspecialchars($opponentUser) . ' in Mind Wars')
            : (htmlspecialchars($playerUser) . ' vs ' . htmlspecialchars($opponentUser) . ' — Mind Wars');
        if ($xp > 0 && $result === 'win') {
            $text .= ' (+' . $xp . ' XP)';
        }

        $activities[] = [
            'type' => 'mind_wars',
            'icon' => 'fa-fist-raised',
            'text' => $text,
            'time_ago' => $timeAgo,
        ];
    }

    json_success(['activities' => $activities]);
} catch (\Throwable $e) {
    error_log('arena/activity error: ' . $e->getMessage());
    json_success(['activities' => []]);
}
