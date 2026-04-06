<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/knowledge_duel.php';

function kd_parse_answers_payload(): array {
    $answers = [];
    if (isset($_POST['answers'])) {
        $raw = $_POST['answers'];
        if (is_array($raw)) {
            $answers = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) $answers = $decoded;
        }
    }
    return is_array($answers) ? $answers : [];
}

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
    rate_limit_guard($pdo, "kd_submit_user:{$userId}", 15, 60);
    rate_limit_guard($pdo, "kd_submit_ip:{$ip}", 30, 60);

    $battleToken = trim((string) ($_POST['battle_token'] ?? ''));
    if ($battleToken === '' || strlen($battleToken) < 32) {
        json_error('INVALID_BATTLE_TOKEN', 'Missing or invalid battle token.');
    }

    $submittedAnswers = kd_parse_answers_payload();

    $pdo->beginTransaction();
    try {
        $sessionStmt = $pdo->prepare(
            "SELECT *
             FROM knd_quiz_battle_sessions
             WHERE battle_token = ? AND user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $sessionStmt->execute([$battleToken, $userId]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            $pdo->rollBack();
            json_error('BATTLE_NOT_FOUND', 'Battle session not found.', 404);
        }
        if (!empty($session['submitted_at'])) {
            $pdo->rollBack();
            json_error('BATTLE_ALREADY_SUBMITTED', 'This battle was already submitted.', 409);
        }

        $avatarItemId = (int) $session['avatar_item_id'];
        $seasonId = (int) $session['season_id'];
        $enemyName = (string) $session['enemy_name'];
        $selectedCategory = (string) ($session['selected_category'] ?? '');
        $selectedDifficulty = kd_normalize_difficulty($session['selected_difficulty'] ?? null) ?? 'medium';

        $questionIds = json_decode((string) $session['question_ids_json'], true);
        $answerKey = json_decode((string) $session['answer_key_json'], true);
        if (!is_array($questionIds) || !is_array($answerKey) || count($questionIds) < 1) {
            $pdo->rollBack();
            json_error('CORRUPTED_BATTLE', 'Battle data is invalid.', 500);
        }

        $in = implode(',', array_fill(0, count($questionIds), '?'));
        $qStmt = $pdo->prepare(
            "SELECT id, category, difficulty, question, option_a, option_b, option_c, option_d
             FROM knd_quiz_questions
             WHERE id IN ($in)"
        );
        $qStmt->execute(array_map('intval', $questionIds));
        $dbQuestions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($dbQuestions as $q) {
            $byId[(int) $q['id']] = $q;
        }

        $orderedQuestions = [];
        foreach ($questionIds as $qid) {
            $qid = (int) $qid;
            if (isset($byId[$qid])) {
                $orderedQuestions[] = $byId[$qid];
            }
        }
        if (count($orderedQuestions) < 1) {
            $pdo->rollBack();
            json_error('QUESTIONS_NOT_FOUND', 'Battle questions could not be loaded.', 500);
        }

        $battle = kd_evaluate_battle($orderedQuestions, $answerKey, $submittedAnswers);
        $rewards = kd_rewards_for_result($battle['result'], $selectedDifficulty);

        $avatarLock = $pdo->prepare(
            "SELECT knowledge_energy, avatar_level
             FROM knd_user_avatar_inventory
             WHERE user_id = ? AND item_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $avatarLock->execute([$userId, $avatarItemId]);
        $avatarRow = $avatarLock->fetch(PDO::FETCH_ASSOC);
        if (!$avatarRow) {
            $pdo->rollBack();
            json_error('AVATAR_NOT_OWNED', 'Selected avatar no longer belongs to this user.', 403);
        }

        $avatarBefore = kd_avatar_progress($avatarRow);
        $avatarAfter = kd_apply_progress_total(
            (int) $avatarBefore['total'],
            (int) $avatarBefore['level'],
            (int) $rewards['knowledge_energy'],
            'kd_ke_required_for_level'
        );
        $avatarLevelUp = $avatarAfter['level'] > (int) $avatarBefore['level'];

        $updAvatar = $pdo->prepare(
            "UPDATE knd_user_avatar_inventory
             SET knowledge_energy = ?, avatar_level = ?
             WHERE user_id = ? AND item_id = ?"
        );
        $updAvatar->execute([
            (int) $avatarAfter['total'],
            (int) $avatarAfter['level'],
            $userId,
            $avatarItemId,
        ]);

        $userXpStmt = $pdo->prepare(
            "SELECT xp, level
             FROM knd_user_xp
             WHERE user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $userXpStmt->execute([$userId]);
        $userXpRow = $userXpStmt->fetch(PDO::FETCH_ASSOC);

        $userTotalXpBefore = 0;
        $userLevelBeforeRaw = 1;
        if ($userXpRow) {
            $userTotalXpBefore = (int) ($userXpRow['xp'] ?? 0);
            $userLevelBeforeRaw = max(1, (int) ($userXpRow['level'] ?? 1));
        } else {
            $legacyStmt = $pdo->prepare("SELECT xp FROM user_xp WHERE user_id = ? LIMIT 1");
            $legacyStmt->execute([$userId]);
            $legacyXp = $legacyStmt->fetchColumn();
            if ($legacyXp !== false) {
                $userTotalXpBefore = max(0, (int) $legacyXp);
            }
        }

        $userBefore = kd_normalize_total_and_level(
            $userTotalXpBefore,
            $userLevelBeforeRaw,
            'kd_xp_required_for_level'
        );
        $userAfter = kd_apply_progress_total(
            (int) $userBefore['total'],
            (int) $userBefore['level'],
            (int) $rewards['xp'],
            'kd_xp_required_for_level'
        );
        $userLevelUp = $userAfter['level'] > (int) $userBefore['level'];

        $updUserXp = $pdo->prepare(
            "INSERT INTO knd_user_xp (user_id, xp, level, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE xp = VALUES(xp), level = VALUES(level), updated_at = NOW()"
        );
        $updUserXp->execute([$userId, (int) $userAfter['total'], (int) $userAfter['level']]);

        $syncLegacyXp = $pdo->prepare(
            "INSERT INTO user_xp (user_id, xp, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE xp = VALUES(xp), updated_at = NOW()"
        );
        $syncLegacyXp->execute([$userId, (int) $userAfter['total']]);

        $wins = $battle['result'] === 'win' ? 1 : 0;
        $losses = $battle['result'] === 'lose' ? 1 : 0;
        $draws = $battle['result'] === 'draw' ? 1 : 0;

        $rankingUpsert = $pdo->prepare(
            "INSERT INTO knd_season_rankings
             (season_id, user_id, rank_score, wins, losses, draws, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               rank_score = rank_score + VALUES(rank_score),
               wins = wins + VALUES(wins),
               losses = losses + VALUES(losses),
               draws = draws + VALUES(draws),
               updated_at = NOW()"
        );
        $rankingUpsert->execute([
            $seasonId,
            $userId,
            (int) $rewards['rank'],
            $wins,
            $losses,
            $draws,
        ]);

        $battleInsert = $pdo->prepare(
            "INSERT INTO knd_quiz_battles
            (user_id, season_id, avatar_item_id, enemy_name, selected_category, selected_difficulty, result, correct_answers, wrong_answers,
             user_hp_final, enemy_hp_final, xp_gained, knowledge_energy_gained, rank_gained, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $battleInsert->execute([
            $userId,
            $seasonId,
            $avatarItemId,
            $enemyName,
            $selectedCategory !== '' ? $selectedCategory : null,
            $selectedDifficulty,
            $battle['result'],
            (int) $battle['correct_answers'],
            (int) $battle['wrong_answers'],
            (int) $battle['user_hp_final'],
            (int) $battle['enemy_hp_final'],
            (int) $rewards['xp'],
            (int) $rewards['knowledge_energy'],
            (int) $rewards['rank'],
        ]);

        $markSubmitted = $pdo->prepare(
            "UPDATE knd_quiz_battle_sessions
             SET submitted_at = NOW()
             WHERE id = ?"
        );
        $markSubmitted->execute([(int) $session['id']]);

        $pdo->commit();

        $ranking = kd_get_user_ranking($pdo, $userId, $seasonId);

        json_success([
            'battle' => [
                'result' => $battle['result'],
                'enemy_name' => $enemyName,
                'selected_category' => $selectedCategory,
                'selected_difficulty' => $selectedDifficulty,
                'correct_answers' => (int) $battle['correct_answers'],
                'wrong_answers' => (int) $battle['wrong_answers'],
                'user_hp_final' => (int) $battle['user_hp_final'],
                'enemy_hp_final' => (int) $battle['enemy_hp_final'],
                'rounds' => $battle['rounds'],
            ],
            'rewards' => [
                'xp' => (int) $rewards['xp'],
                'knowledge_energy' => (int) $rewards['knowledge_energy'],
                'rank' => (int) $rewards['rank'],
            ],
            'user_progress' => [
                'level_before' => (int) $userBefore['level'],
                'level_after' => (int) $userAfter['level'],
                'level_up' => $userLevelUp,
                'xp_before' => (int) $userBefore['total'],
                'xp_after' => (int) $userAfter['total'],
                'xp_into_level' => (int) $userAfter['into'],
                'xp_to_next_level' => (int) $userAfter['to_next'],
                'xp_required_current' => (int) $userAfter['required_current'],
            ],
            'avatar_progress' => [
                'level_before' => (int) $avatarBefore['level'],
                'level_after' => (int) $avatarAfter['level'],
                'level_up' => $avatarLevelUp,
                'knowledge_energy_before' => (int) $avatarBefore['total'],
                'knowledge_energy_after' => (int) $avatarAfter['total'],
                'knowledge_energy_into_level' => (int) $avatarAfter['into'],
                'knowledge_energy_to_next_level' => (int) $avatarAfter['to_next'],
                'knowledge_energy_required_current' => (int) $avatarAfter['required_current'],
            ],
            'ranking' => $ranking,
        ]);
    } catch (\Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }
} catch (\Throwable $e) {
    error_log('knowledge-duel/submit_battle error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

