<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/knowledge_duel.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "kd_start_user:{$userId}", 15, 60);
    rate_limit_guard($pdo, "kd_start_ip:{$ip}", 30, 60);

    $avatarItemId = isset($_POST['avatar_item_id']) ? (int) $_POST['avatar_item_id'] : 0;
    if ($avatarItemId <= 0) {
        json_error('INVALID_AVATAR', 'Please select a valid avatar.');
    }

    $avatar = kd_validate_owned_avatar($pdo, $userId, $avatarItemId);
    if (!$avatar) {
        json_error('AVATAR_NOT_OWNED', 'Selected avatar is not owned by this user.', 403);
    }

    $selectedCategory = kd_normalize_category($_POST['category'] ?? null);
    if ($selectedCategory === null) {
        json_error('INVALID_CATEGORY', 'Please select a valid category.');
    }
    $selectedDifficulty = kd_normalize_difficulty($_POST['difficulty'] ?? null);
    if ($selectedDifficulty === null) {
        json_error('INVALID_DIFFICULTY', 'Please select a valid difficulty.');
    }

    $questions = kd_load_random_questions($pdo, KD_QUESTIONS_PER_BATTLE, $selectedCategory, null);
    if (count($questions) < KD_QUESTIONS_PER_BATTLE) {
        json_error('NOT_ENOUGH_QUESTIONS', 'Knowledge Duel needs at least 5 active questions.', 500);
    }

    $season = kd_ensure_active_season($pdo);
    $battleToken = hash('sha256', bin2hex(random_bytes(32)) . '|' . $userId . '|' . microtime(true));
    $enemy = kd_pick_enemy($pdo);
    $enemyName = $enemy['name'];

    $questionIds = [];
    $answerKey = [];
    foreach ($questions as $q) {
        $qid = (int) $q['id'];
        $questionIds[] = $qid;
        $answerKey[$qid] = strtoupper((string) $q['correct_answer']);
    }

    $insert = $pdo->prepare(
        "INSERT INTO knd_quiz_battle_sessions
        (battle_token, user_id, season_id, avatar_item_id, enemy_name, selected_category, selected_difficulty, question_ids_json, answer_key_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $insert->execute([
        $battleToken,
        $userId,
        (int) $season['id'],
        $avatarItemId,
        $enemyName,
        $selectedCategory,
        $selectedDifficulty,
        json_encode($questionIds, JSON_UNESCAPED_UNICODE),
        json_encode($answerKey, JSON_UNESCAPED_UNICODE),
    ]);

    json_success([
        'battle_token' => $battleToken,
        'enemy_name' => $enemyName,
        'enemy_avatar_path' => (string) ($enemy['avatar_path'] ?? ''),
        'enemy_quote' => (string) ($enemy['quote'] ?? ''),
        'enemy_theme' => (string) ($enemy['theme'] ?? '#ff6b9f'),
        'avatar' => [
            'item_id' => (int) $avatar['item_id'],
            'name' => (string) $avatar['name'],
            'rarity' => (string) $avatar['rarity'],
            'asset_path' => (string) $avatar['asset_path'],
            'display_image_url' => (string) ($avatar['display_image_url'] ?? ''),
            'avatar_level' => (int) $avatar['avatar_level'],
            'knowledge_energy' => (int) $avatar['knowledge_energy'],
        ],
        'battle_config' => [
            'questions_total' => KD_QUESTIONS_PER_BATTLE,
            'user_hp_start' => KD_USER_HP_START,
            'enemy_hp_start' => KD_ENEMY_HP_START,
            'damage_correct' => KD_DAMAGE_CORRECT,
            'damage_wrong' => KD_DAMAGE_WRONG,
        ],
        'selected_category' => $selectedCategory,
        'selected_difficulty' => $selectedDifficulty,
        'difficulty_rewards' => kd_rewards_matrix()[$selectedDifficulty] ?? kd_rewards_matrix()['medium'],
        'questions' => kd_build_public_questions($questions),
    ]);
} catch (\Throwable $e) {
    error_log('knowledge-duel/start_battle error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

