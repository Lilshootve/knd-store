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
require_once BASE_PATH . '/games/mindwars1v1/bootstrap.php';
require_once BASE_PATH . '/includes/knowledge_duel.php';

try {
    api_require_login();
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) (current_user_id() ?? $_SESSION['user_id'] ?? 0);
    if ($userId < 1) {
        json_error('AUTH_REQUIRED', 'You must be logged in.', 401);
    }
    $avatars = mw_get_user_avatars($pdo, $userId);
    $season = mw_ensure_season($pdo);

    $selectedAvatar = null;
    foreach ($avatars as $a) {
        if (!empty($a['is_favorite'])) {
            $selectedAvatar = $a;
            break;
        }
    }
    if (!$selectedAvatar && !empty($avatars)) {
        $selectedAvatar = $avatars[0];
    }

    $userProgress = kd_user_progress($pdo, $userId);

    $stmt = $pdo->prepare(
        "SELECT rank_score, wins, losses FROM knd_mind_wars_rankings WHERE user_id = ? AND season_id = ? LIMIT 1"
    );
    $stmt->execute([$userId, (int) $season['id']]);
    $rankRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $wins = (int) ($rankRow['wins'] ?? 0);
    $losses = (int) ($rankRow['losses'] ?? 0);
    $totalMatches = max(0, $wins + $losses);
    $winRate = $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 2) : 0.0;
    $ranking = [
        'rank_score' => (int) ($rankRow['rank_score'] ?? 0),
        'wins' => $wins,
        'losses' => $losses,
        'win_rate' => $winRate,
        'estimated_position' => null,
    ];
    if ($rankRow) {
        $score = (int) ($rankRow['rank_score'] ?? 0);
        $posStmt = $pdo->prepare(
            "SELECT 1 + COUNT(*) AS pos
             FROM knd_mind_wars_rankings
             WHERE season_id = ? AND rank_score > ?"
        );
        $posStmt->execute([(int) $season['id'], $score]);
        $ranking['estimated_position'] = (int) ($posStmt->fetchColumn() ?: 1);
    }

    if ($selectedAvatar) {
        $keTotal = max(0, (int) ($selectedAvatar['knowledge_energy'] ?? 0));
        $avatarLevel = max(1, (int) ($selectedAvatar['avatar_level'] ?? 1));
        $keRequired = (int) ceil(80 * pow($avatarLevel, 1.3));
        $keInto = $keRequired > 0 ? ($keTotal % $keRequired) : 0;
        $selectedAvatar['knowledge_energy_into_level'] = $keInto;
        $selectedAvatar['knowledge_energy_required_current'] = $keRequired;
        $selectedAvatar['knowledge_energy_to_next_level'] = max(0, $keRequired - $keInto);
    }

    json_success([
        'user' => [
            'id' => $userId,
            'xp' => (int) $userProgress['total'],
            'level' => (int) $userProgress['level'],
            'xp_into_level' => (int) ($userProgress['into'] ?? 0),
            'xp_to_next_level' => (int) ($userProgress['to_next'] ?? 0),
            'xp_required_current' => (int) ($userProgress['required_current'] ?? 0),
        ],
        'season' => [
            'id' => (int) $season['id'],
            'name' => (string) $season['name'],
            'starts_at' => (string) $season['starts_at'],
            'ends_at' => (string) $season['ends_at'],
        ],
        'avatars' => $avatars,
        'selected_avatar' => $selectedAvatar,
        'ranking' => $ranking,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/get_state error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
