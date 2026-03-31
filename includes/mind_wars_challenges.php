<?php

if (!function_exists('mw_challenges_ensure_table')) {
    function mw_challenges_ensure_table(PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS knd_mind_wars_challenges (
                id BIGINT NOT NULL AUTO_INCREMENT,
                challenge_token CHAR(64) NOT NULL,
                season_id BIGINT NOT NULL,
                challenger_user_id BIGINT NOT NULL,
                challenged_user_id BIGINT NOT NULL,
                challenger_avatar_item_id INT NOT NULL,
                challenged_avatar_item_id INT NULL,
                accepted_by_user_id BIGINT NULL,
                battle_id BIGINT NULL,
                battle_token CHAR(64) NULL,
                status ENUM('pending','accepted','declined','cancelled','expired') NOT NULL DEFAULT 'pending',
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_challenge_token (challenge_token),
                KEY idx_challenged_status (challenged_user_id, status, expires_at, updated_at),
                KEY idx_challenger_status (challenger_user_id, status, expires_at, updated_at),
                KEY idx_season_status (season_id, status, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('mw_challenges_cleanup_expired')) {
    function mw_challenges_cleanup_expired(PDO $pdo): int {
        $stmt = $pdo->prepare(
            "UPDATE knd_mind_wars_challenges
             SET status = 'expired', updated_at = NOW()
             WHERE status = 'pending' AND expires_at <= NOW()"
        );
        $stmt->execute();
        return (int) $stmt->rowCount();
    }
}

if (!function_exists('mw_challenges_generate_token')) {
    function mw_challenges_generate_token(): string {
        return hash('sha256', bin2hex(random_bytes(32)) . '|' . microtime(true));
    }
}

if (!function_exists('mw_challenges_get_user_avatar')) {
    function mw_challenges_get_user_avatar(PDO $pdo, int $userId): ?array {
        if ($userId <= 0) return null;
        $favStmt = $pdo->prepare("SELECT favorite_avatar_id FROM users WHERE id = ? LIMIT 1");
        $favStmt->execute([$userId]);
        $favId = (int) ($favStmt->fetchColumn() ?: 0);
        if ($favId > 0) {
            $avatar = mw_validate_owned_avatar($pdo, $userId, $favId);
            if ($avatar) return $avatar;
        }

        $stmt = $pdo->prepare(
            "SELECT ai.id AS item_id, ai.name, ai.rarity, ai.asset_path, inv.knowledge_energy, inv.avatar_level
             FROM knd_user_avatar_inventory inv
             JOIN knd_avatar_items ai ON ai.id = inv.item_id
             WHERE inv.user_id = ? AND ai.is_active = 1
             ORDER BY inv.avatar_level DESC, inv.acquired_at DESC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mw_challenges_find_active_battle_between_users')) {
    function mw_challenges_find_active_battle_between_users(PDO $pdo, int $userA, int $userB): ?array {
        $stmt = $pdo->prepare(
            "SELECT b.id, b.battle_token
             FROM knd_mind_wars_battles b
             JOIN knd_mind_wars_battle_participants p1 ON p1.battle_id = b.id
             JOIN knd_mind_wars_battle_participants p2 ON p2.battle_id = b.id
             WHERE b.mode = 'pvp_ranked'
               AND b.result IS NULL
               AND p1.user_id = ?
               AND p2.user_id = ?
             ORDER BY b.id DESC
             LIMIT 1"
        );
        $stmt->execute([$userA, $userB]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mw_challenges_create_battle')) {
    function mw_challenges_create_battle(PDO $pdo, int $playerUserId, int $enemyUserId, array $playerAvatar, array $enemyAvatar): array {
        $playerFighter = mw_build_fighter($playerAvatar, false);
        $enemyFighter = mw_build_fighter($enemyAvatar, false);
        $playerFirst = mw_roll_initiative($playerFighter, $enemyFighter);
        $battleToken = bin2hex(random_bytes(32));
        $state = mw_normalize_battle_state([
            'turn' => 1,
            'max_turns' => MW_MAX_TURNS,
            'player_first' => $playerFirst,
            'player' => $playerFighter,
            'enemy' => $enemyFighter,
            'log' => [[
                'type' => 'info',
                'msg' => 'PvP challenge accepted. Battle initialized.',
            ]],
            'next_actor' => $playerFirst ? 'player' : 'enemy',
            'player_next_attack_crit' => false,
            'meta' => [
                'mode' => 'pvp_ranked',
                'difficulty' => 'normal',
            ],
        ]);

        $insBattle = $pdo->prepare(
            "INSERT INTO knd_mind_wars_battles
             (battle_token, user_id, avatar_item_id, enemy_avatar_id, mode, state_json, turns_played)
             VALUES (?, ?, ?, ?, 'pvp_ranked', ?, 0)"
        );
        $insBattle->execute([
            $battleToken,
            $playerUserId,
            (int) ($playerAvatar['item_id'] ?? 0),
            (int) ($enemyAvatar['item_id'] ?? 0),
            json_encode($state, JSON_UNESCAPED_UNICODE),
        ]);
        $battleId = (int) $pdo->lastInsertId();

        $insPart = $pdo->prepare(
            "INSERT INTO knd_mind_wars_battle_participants (battle_id, user_id, avatar_item_id, side)
             VALUES (?, ?, ?, ?)"
        );
        $insPart->execute([$battleId, $playerUserId, (int) ($playerAvatar['item_id'] ?? 0), 'player']);
        $insPart->execute([$battleId, $enemyUserId, (int) ($enemyAvatar['item_id'] ?? 0), 'enemy']);

        return [
            'battle_id' => $battleId,
            'battle_token' => $battleToken,
            'state' => $state,
        ];
    }
}

