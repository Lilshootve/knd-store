<?php
// KND Knowledge Duel MVP - core game/domain helpers.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/knd_avatar.php';

const KD_QUESTIONS_PER_BATTLE = 5;
const KD_USER_HP_START = 100;
const KD_ENEMY_HP_START = 100;
const KD_DAMAGE_CORRECT = 20;
const KD_DAMAGE_WRONG = 15;

const KD_XP_WIN = 50;
const KD_XP_LOSE = 15;
const KD_XP_DRAW = 25;

const KD_KE_WIN = 30;
const KD_KE_LOSE = 10;
const KD_KE_DRAW = 15;

const KD_RANK_WIN = 25;
const KD_RANK_LOSE = 5;
const KD_RANK_DRAW = 10;

function kd_allowed_categories(): array {
    return ['Tech', 'Gaming', 'Internet', 'AI', 'General Geek', 'Sports', 'Entertainment', 'Music', 'Science', 'History', 'Geography', 'Anime & Manga', 'Comics', 'Esports', 'Memes & Internet Culture', 'Startups & Business', 'Cybersecurity', 'Programming', 'Space & Astronomy', 'Mythology'];
}

function kd_allowed_difficulties(): array {
    return ['easy', 'medium', 'hard'];
}

function kd_rewards_matrix(): array {
    return [
        'easy' => [
            'win' => ['xp' => 40, 'knowledge_energy' => 20, 'rank' => 15],
            'lose' => ['xp' => 12, 'knowledge_energy' => 8, 'rank' => 3],
            'draw' => ['xp' => 20, 'knowledge_energy' => 12, 'rank' => 8],
        ],
        'medium' => [
            'win' => ['xp' => 50, 'knowledge_energy' => 30, 'rank' => 25],
            'lose' => ['xp' => 15, 'knowledge_energy' => 10, 'rank' => 5],
            'draw' => ['xp' => 25, 'knowledge_energy' => 15, 'rank' => 10],
        ],
        'hard' => [
            'win' => ['xp' => 70, 'knowledge_energy' => 45, 'rank' => 40],
            'lose' => ['xp' => 20, 'knowledge_energy' => 12, 'rank' => 7],
            'draw' => ['xp' => 35, 'knowledge_energy' => 20, 'rank' => 15],
        ],
    ];
}

function kd_normalize_category(?string $category): ?string {
    if ($category === null) return null;
    $clean = trim($category);
    if ($clean === '') return null;
    foreach (kd_allowed_categories() as $allowed) {
        if (strcasecmp($allowed, $clean) === 0) return $allowed;
    }
    return null;
}

function kd_normalize_difficulty(?string $difficulty): ?string {
    if ($difficulty === null) return null;
    $clean = strtolower(trim($difficulty));
    if ($clean === '') return null;
    return in_array($clean, kd_allowed_difficulties(), true) ? $clean : null;
}

function kd_enemy_pool(): array {
    return [
        [
            'name' => 'Shadow Bot',
            'quote' => 'Your knowledge will be tested.',
            'theme' => '#8b5cf6',
        ],
        [
            'name' => 'Nova Hacker',
            'quote' => 'Logic is my weapon.',
            'theme' => '#06b6d4',
        ],
        [
            'name' => 'Data Phantom',
            'quote' => 'Data flows through me.',
            'theme' => '#ec4899',
        ],
        [
            'name' => 'Glitch Hunter',
            'quote' => 'Find the flaw in your logic.',
            'theme' => '#f59e0b',
        ],
    ];
}

/**
 * Random mw_avatars.id with non-empty image (Knowledge Duel enemy portrait only from MW DB).
 */
function kd_pick_random_mw_avatar_id_with_image(PDO $pdo): int {
    try {
        $stmt = $pdo->query(
            'SELECT id FROM mw_avatars WHERE NULLIF(TRIM(image), \'\') IS NOT NULL ORDER BY RAND() LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0;
        }

        return max(0, (int) ($row['id'] ?? 0));
    } catch (\Throwable $e) {
        return 0;
    }
}

function kd_enemy_name_pool(): array {
    $pool = kd_enemy_pool();
    return array_column($pool, 'name');
}

/**
 * Narrative from pool; portrait URL from mw_avatars.image via id (never static asset paths).
 *
 * @return array{name:string,quote:string,theme:string,avatar_path:string,mw_avatar_id:int}
 */
function kd_pick_enemy(PDO $pdo): array {
    $pool = kd_enemy_pool();
    $n = count($pool);
    if ($n === 0) {
        $entry = ['name' => 'Neural Entity', 'quote' => '', 'theme' => '#d6cfc7'];
    } else {
        $entry = $pool[random_int(0, $n - 1)];
    }

    kd_ensure_mw_resolve_loaded();
    $mwId = kd_pick_random_mw_avatar_id_with_image($pdo);
    $url = '';
    if ($mwId > 0 && function_exists('mw_mw_avatar_image_url_by_id')) {
        $resolved = mw_mw_avatar_image_url_by_id($pdo, $mwId);
        $url = $resolved !== null ? (string) $resolved : '';
    }

    return [
        'name' => (string) ($entry['name'] ?? 'Neural Entity'),
        'quote' => (string) ($entry['quote'] ?? ''),
        'theme' => (string) ($entry['theme'] ?? '#d6cfc7'),
        'avatar_path' => $url,
        'mw_avatar_id' => $mwId,
    ];
}

function kd_pick_enemy_name(PDO $pdo): string {
    return kd_pick_enemy($pdo)['name'];
}

function kd_xp_required_for_level(int $level): int {
    $safeLevel = max(1, $level);
    return (int) ceil(100 * pow($safeLevel, 1.35));
}

function kd_ke_required_for_level(int $avatarLevel): int {
    $safeLevel = max(1, $avatarLevel);
    return (int) ceil(80 * pow($safeLevel, 1.3));
}

function kd_normalize_total_and_level(int $total, int $storedLevel, callable $requiredFn): array {
    $level = max(1, $storedLevel);
    $remaining = max(0, $total);
    $walkLevel = 1;

    // Reconstruct level from total using the configured threshold curve.
    while (true) {
        $need = (int) $requiredFn($walkLevel);
        if ($remaining >= $need) {
            $remaining -= $need;
            $walkLevel++;
            continue;
        }
        break;
    }

    // Respect greater stored level if legacy data was already advanced.
    $level = max($walkLevel, $level);
    $xpInto = $remaining;
    $needCurrent = (int) $requiredFn($level);

    // If stored level was greater, cap into-level to current threshold range.
    if ($xpInto >= $needCurrent) {
        $xpInto = $needCurrent > 0 ? ($xpInto % $needCurrent) : 0;
    }

    return [
        'level' => $level,
        'into' => $xpInto,
        'to_next' => max(0, $needCurrent - $xpInto),
        'required_current' => $needCurrent,
        'total' => max(0, $total),
    ];
}

function kd_user_progress(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT xp, level FROM knd_user_xp WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $xp = 0;
    $level = 1;
    if ($row) {
        $xp = (int) ($row['xp'] ?? 0);
        $level = max(1, (int) ($row['level'] ?? 1));
    } else {
        $fallback = $pdo->prepare('SELECT xp FROM user_xp WHERE user_id = ? LIMIT 1');
        $fallback->execute([$userId]);
        $ux = $fallback->fetch(PDO::FETCH_ASSOC);
        if ($ux) {
            $xp = max(0, (int) ($ux['xp'] ?? 0));
        }
    }

    return kd_normalize_total_and_level($xp, $level, 'kd_xp_required_for_level');
}

function kd_avatar_progress(array $row): array {
    $ke = max(0, (int) ($row['knowledge_energy'] ?? 0));
    $avatarLevel = max(1, (int) ($row['avatar_level'] ?? 1));
    return kd_normalize_total_and_level($ke, $avatarLevel, 'kd_ke_required_for_level');
}

function kd_ensure_mw_resolve_loaded(): void {
    if (!function_exists('mw_resolve_avatar_image_for_inventory')) {
        require_once __DIR__ . '/mind_wars.php';
    }
}

/**
 * Resolved portrait URL (Mind Wars mw_avatars), same rules as lobby inventory.
 *
 * @param array $row item_id, name, asset_path, optional mw_avatar_id, mw_image
 */
function kd_resolve_display_image_url(PDO $pdo, array $row): string {
    kd_ensure_mw_resolve_loaded();
    $mwId = (int) ($row['mw_avatar_id'] ?? 0);
    $nm = (string) ($row['name'] ?? '');
    $ap = (string) ($row['asset_path'] ?? '');
    $join = isset($row['mw_image']) ? trim((string) $row['mw_image']) : '';

    return mw_resolve_avatar_image_for_inventory(
        $pdo,
        $mwId > 0 ? $mwId : null,
        $nm,
        $ap,
        $join !== '' ? $join : null
    );
}

/** Display rarity: mw_rarity when joined row exists, else knd_avatar_items rarity. */
function kd_duel_rarity_from_row(array $row): string {
    $mwId = (int) ($row['mw_avatar_id'] ?? 0);
    $mwR = trim((string) ($row['mw_rarity'] ?? ''));
    if ($mwId > 0 && $mwR !== '') {
        return $mwR;
    }

    return (string) ($row['ai_rarity'] ?? $row['rarity'] ?? 'common');
}

function kd_ensure_active_season(PDO $pdo): array {
    $pdo->prepare("UPDATE knd_game_seasons SET status = 'finished' WHERE status = 'active' AND ends_at <= NOW()")->execute();

    $stmt = $pdo->prepare(
        "SELECT id, name, starts_at, ends_at, status
         FROM knd_game_seasons
         WHERE status = 'active' AND starts_at <= NOW() AND ends_at > NOW()
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute();
    $season = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($season) {
        return $season;
    }

    $insert = $pdo->prepare(
        "INSERT INTO knd_game_seasons (name, starts_at, ends_at, status)
         VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active')"
    );
    $insert->execute(['Knowledge Duel Season 1']);

    $stmt->execute();
    $created = $stmt->fetch(PDO::FETCH_ASSOC);
    return $created ?: [
        'id' => (int) $pdo->lastInsertId(),
        'name' => 'Knowledge Duel Season 1',
        'starts_at' => gmdate('Y-m-d H:i:s'),
        'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
        'status' => 'active',
    ];
}

function kd_get_user_duel_avatars(PDO $pdo, int $userId): array {
    avatar_sync_items_from_assets($pdo);

    $favoriteId = null;
    try {
        $fav = $pdo->prepare('SELECT favorite_avatar_id FROM users WHERE id = ? LIMIT 1');
        $fav->execute([$userId]);
        $favoriteId = (int) ($fav->fetchColumn() ?: 0);
    } catch (\Throwable $e) {
        $favoriteId = 0;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT ai.id AS item_id, ai.name, ai.rarity AS ai_rarity, ai.asset_path,
                    inv.knowledge_energy, inv.avatar_level, inv.acquired_at,
                    mw.id AS mw_avatar_id, mw.image AS mw_image, mw.rarity AS mw_rarity
             FROM knd_user_avatar_inventory inv
             JOIN knd_avatar_items ai ON ai.id = inv.item_id
             INNER JOIN mw_avatars mw ON LOWER(TRIM(mw.name)) = LOWER(TRIM(ai.name))
                AND NULLIF(TRIM(mw.image), '') IS NOT NULL
             WHERE inv.user_id = ? AND ai.is_active = 1
             ORDER BY inv.acquired_at DESC, ai.id DESC"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $rows = [];
    }

    $avatars = [];
    foreach ($rows as $row) {
        $displayUrl = kd_resolve_display_image_url($pdo, $row);
        if ($displayUrl === '') {
            continue;
        }
        $progress = kd_avatar_progress($row);
        $avatars[] = [
            'item_id' => (int) $row['item_id'],
            'name' => (string) ($row['name'] ?? 'KND Avatar'),
            'rarity' => kd_duel_rarity_from_row($row),
            'asset_path' => (string) ($row['asset_path'] ?? ''),
            'display_image_url' => $displayUrl,
            'avatar_level' => (int) $progress['level'],
            'knowledge_energy' => (int) $progress['total'],
            'knowledge_energy_into_level' => (int) $progress['into'],
            'knowledge_energy_to_next_level' => (int) $progress['to_next'],
            'knowledge_energy_required_current' => (int) $progress['required_current'],
            'is_favorite' => ((int) $row['item_id'] === $favoriteId),
        ];
    }

    return $avatars;
}

function kd_get_user_ranking(PDO $pdo, int $userId, int $seasonId): array {
    $stmt = $pdo->prepare(
        "SELECT rank_score, wins, losses, draws
         FROM knd_season_rankings
         WHERE user_id = ? AND season_id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId, $seasonId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'rank_score' => 0,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'estimated_position' => null,
        ];
    }

    $score = (int) ($row['rank_score'] ?? 0);
    $rankStmt = $pdo->prepare(
        "SELECT 1 + COUNT(*) AS pos
         FROM knd_season_rankings
         WHERE season_id = ? AND rank_score > ?"
    );
    $rankStmt->execute([$seasonId, $score]);

    return [
        'rank_score' => $score,
        'wins' => (int) ($row['wins'] ?? 0),
        'losses' => (int) ($row['losses'] ?? 0),
        'draws' => (int) ($row['draws'] ?? 0),
        'estimated_position' => (int) $rankStmt->fetchColumn(),
    ];
}

function kd_validate_owned_avatar(PDO $pdo, int $userId, int $avatarItemId): ?array {
    try {
        $stmt = $pdo->prepare(
            "SELECT ai.id AS item_id, ai.name, ai.rarity AS ai_rarity, ai.asset_path,
                    inv.knowledge_energy, inv.avatar_level,
                    mw.id AS mw_avatar_id, mw.image AS mw_image, mw.rarity AS mw_rarity
             FROM knd_user_avatar_inventory inv
             JOIN knd_avatar_items ai ON ai.id = inv.item_id
             INNER JOIN mw_avatars mw ON LOWER(TRIM(mw.name)) = LOWER(TRIM(ai.name))
                AND NULLIF(TRIM(mw.image), '') IS NOT NULL
             WHERE inv.user_id = ? AND inv.item_id = ? AND ai.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$userId, $avatarItemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return null;
    }
    if (!$row) {
        return null;
    }

    $progress = kd_avatar_progress($row);
    $displayUrl = kd_resolve_display_image_url($pdo, $row);
    if ($displayUrl === '') {
        return null;
    }

    return [
        'item_id' => (int) $row['item_id'],
        'name' => (string) ($row['name'] ?? 'KND Avatar'),
        'rarity' => kd_duel_rarity_from_row($row),
        'asset_path' => (string) ($row['asset_path'] ?? ''),
        'display_image_url' => $displayUrl,
        'avatar_level' => (int) $progress['level'],
        'knowledge_energy' => (int) $progress['total'],
    ];
}

function kd_load_random_questions(PDO $pdo, int $count = KD_QUESTIONS_PER_BATTLE, ?string $category = null, ?string $difficulty = null): array {
    $sql = "SELECT id, category, difficulty, question, option_a, option_b, option_c, option_d, correct_answer
            FROM knd_quiz_questions
            WHERE is_active = 1";
    $params = [];

    $normalizedCategory = kd_normalize_category($category);
    if ($normalizedCategory !== null) {
        $sql .= " AND category = ?";
        $params[] = $normalizedCategory;
    }

    $normalizedDifficulty = kd_normalize_difficulty($difficulty);
    if ($normalizedDifficulty !== null) {
        $sql .= " AND difficulty = ?";
        $params[] = $normalizedDifficulty;
    }

    $sql .= " ORDER BY RAND() LIMIT " . (int) $count;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function kd_build_public_questions(array $rows): array {
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) $row['id'],
            'category' => (string) ($row['category'] ?? 'General Geek'),
            'difficulty' => (string) ($row['difficulty'] ?? 'easy'),
            'question' => (string) ($row['question'] ?? ''),
            // UI-only immediate feedback. Rewards are always computed server-side from session answer_key_json.
            'correct_answer' => strtoupper((string) ($row['correct_answer'] ?? 'A')),
            'options' => [
                'A' => (string) ($row['option_a'] ?? ''),
                'B' => (string) ($row['option_b'] ?? ''),
                'C' => (string) ($row['option_c'] ?? ''),
                'D' => (string) ($row['option_d'] ?? ''),
            ],
        ];
    }
    return $out;
}

function kd_rewards_for_result(string $result, ?string $difficulty = 'medium'): array {
    $resultKey = in_array($result, ['win', 'lose', 'draw'], true) ? $result : 'draw';
    $difficultyKey = kd_normalize_difficulty($difficulty) ?? 'medium';
    $matrix = kd_rewards_matrix();
    if (!isset($matrix[$difficultyKey][$resultKey])) {
        return ['xp' => KD_XP_DRAW, 'knowledge_energy' => KD_KE_DRAW, 'rank' => KD_RANK_DRAW];
    }
    return $matrix[$difficultyKey][$resultKey];
}

function kd_evaluate_battle(array $questions, array $answerKey, array $submittedAnswers): array {
    $userHp = KD_USER_HP_START;
    $enemyHp = KD_ENEMY_HP_START;
    $correct = 0;
    $wrong = 0;
    $rounds = [];

    foreach ($questions as $idx => $q) {
        $qid = (int) ($q['id'] ?? 0);
        $correctAns = strtoupper((string) ($answerKey[$qid] ?? ''));
        $given = strtoupper(trim((string) ($submittedAnswers[$qid] ?? '')));
        if (!in_array($given, ['A', 'B', 'C', 'D'], true)) {
            $given = '';
        }

        $isCorrect = ($given !== '' && $given === $correctAns);
        if ($isCorrect) {
            $enemyHp = max(0, $enemyHp - KD_DAMAGE_CORRECT);
            $correct++;
        } else {
            $userHp = max(0, $userHp - KD_DAMAGE_WRONG);
            $wrong++;
        }

        $rounds[] = [
            'question_id' => $qid,
            'index' => $idx + 1,
            'selected' => $given,
            'correct_answer' => $correctAns,
            'is_correct' => $isCorrect,
            'user_hp' => $userHp,
            'enemy_hp' => $enemyHp,
        ];

        if ($userHp <= 0 || $enemyHp <= 0) {
            break;
        }
    }

    $result = 'draw';
    if ($enemyHp < $userHp) $result = 'win';
    if ($userHp < $enemyHp) $result = 'lose';

    return [
        'result' => $result,
        'correct_answers' => $correct,
        'wrong_answers' => $wrong,
        'user_hp_final' => $userHp,
        'enemy_hp_final' => $enemyHp,
        'rounds' => $rounds,
    ];
}

function kd_apply_progress_total(int $currentTotal, int $currentLevel, int $delta, callable $requiredFn): array {
    $newTotal = max(0, $currentTotal + $delta);
    $normalized = kd_normalize_total_and_level($newTotal, $currentLevel, $requiredFn);
    return [
        'total' => $newTotal,
        'level' => (int) $normalized['level'],
        'into' => (int) $normalized['into'],
        'to_next' => (int) $normalized['to_next'],
        'required_current' => (int) $normalized['required_current'],
    ];
}

