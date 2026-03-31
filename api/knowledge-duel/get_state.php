<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/knowledge_duel.php';

try {
    api_require_login();
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    $season = kd_ensure_active_season($pdo);
    $avatars = kd_get_user_duel_avatars($pdo, $userId);
    $userProgress = kd_user_progress($pdo, $userId);
    $ranking = kd_get_user_ranking($pdo, $userId, (int) $season['id']);

    $selectedAvatar = null;
    foreach ($avatars as $avatar) {
        if (!empty($avatar['is_favorite'])) {
            $selectedAvatar = $avatar;
            break;
        }
    }
    if (!$selectedAvatar && !empty($avatars)) {
        $selectedAvatar = $avatars[0];
    }

    json_success([
        'user' => [
            'id' => $userId,
            'xp' => (int) $userProgress['total'],
            'level' => (int) $userProgress['level'],
            'xp_into_level' => (int) $userProgress['into'],
            'xp_to_next_level' => (int) $userProgress['to_next'],
            'xp_required_current' => (int) $userProgress['required_current'],
        ],
        'season' => [
            'id' => (int) $season['id'],
            'name' => (string) $season['name'],
            'starts_at' => (string) $season['starts_at'],
            'ends_at' => (string) $season['ends_at'],
            'status' => (string) $season['status'],
        ],
        'ranking' => $ranking,
        'avatars' => $avatars,
        'selected_avatar' => $selectedAvatar,
        'categories' => kd_allowed_categories(),
        'difficulties' => kd_allowed_difficulties(),
        'difficulty_rewards' => kd_rewards_matrix(),
    ]);
} catch (\Throwable $e) {
    error_log('knowledge-duel/get_state error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

