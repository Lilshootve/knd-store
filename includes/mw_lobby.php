<?php
/**
 * Mind Wars lobby: aggregate payload for lobby.php / get_lobby_data API.
 */

require_once __DIR__ . '/mind_wars.php';
require_once __DIR__ . '/mw_avatar_models.php';
require_once __DIR__ . '/knowledge_duel.php';
require_once __DIR__ . '/support_credits.php';
require_once __DIR__ . '/knd_drop.php';
require_once __DIR__ . '/knd_daily.php';
require_once __DIR__ . '/mind_wars_challenges.php';

/**
 * Lower = higher tier (matches FIELD(..., 'legendary','epic','rare','special','common') elsewhere).
 */
function mw_lobby_rarity_sort_rank(string $rarity): int {
    static $order = [
        'legendary' => 0,
        'epic' => 1,
        'rare' => 2,
        'special' => 3,
        'common' => 4,
    ];
    $k = strtolower(trim($rarity));

    return $order[$k] ?? 99;
}

/**
 * @return array<string,mixed>
 */
function mw_build_lobby_data_payload(PDO $pdo, int $userId): array {
    $uStmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $uStmt->execute([$userId]);
    $username = (string) ($uStmt->fetchColumn() ?: 'Player');

    $avatars = mw_get_user_avatars($pdo, $userId);
    $mwIdsForStats = [];
    foreach ($avatars as $a) {
        $mid = (int) ($a['mw_avatar_id'] ?? 0);
        if ($mid > 0) {
            $mwIdsForStats[] = $mid;
        }
    }
    $statsByMwId = mw_batch_avatar_stats_by_ids($pdo, $mwIdsForStats);
    foreach ($avatars as $i => $a) {
        $nm = (string) ($a['name'] ?? '');
        $ap = (string) ($a['asset_path'] ?? '');
        $mid = (int) ($a['mw_avatar_id'] ?? 0);
        $mwJoinImg = isset($a['mw_image']) && $a['mw_image'] !== null && trim((string) $a['mw_image']) !== ''
            ? trim((string) $a['mw_image'])
            : null;
        $avatars[$i]['display_image_url'] = mw_resolve_avatar_image_for_inventory(
            $pdo,
            $mid > 0 ? $mid : null,
            $nm,
            $ap,
            $mwJoinImg
        );
        $rar = (string) ($a['rarity'] ?? 'common');
        $avatars[$i]['display_model_url'] = mw_resolve_avatar_model_url($mid > 0 ? $mid : null, $nm, $rar);
        $avatars[$i]['mw_stats'] = $mid > 0 && isset($statsByMwId[$mid])
            ? $statsByMwId[$mid]
            : ['mnd' => 0, 'fcs' => 0, 'spd' => 0, 'lck' => 0];
    }

    usort($avatars, static function (array $a, array $b): int {
        $ra = mw_lobby_rarity_sort_rank((string) ($a['rarity'] ?? 'common'));
        $rb = mw_lobby_rarity_sort_rank((string) ($b['rarity'] ?? 'common'));
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        $la = (int) ($a['avatar_level'] ?? 1);
        $lb = (int) ($b['avatar_level'] ?? 1);
        if ($la !== $lb) {
            return $lb <=> $la;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $season = mw_ensure_season($pdo);
    $seasonId = (int) ($season['id'] ?? 0);

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
        'SELECT rank_score, wins, losses FROM knd_mind_wars_rankings WHERE user_id = ? AND season_id = ? LIMIT 1'
    );
    $stmt->execute([$userId, $seasonId]);
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
            'SELECT 1 + COUNT(*) AS pos
             FROM knd_mind_wars_rankings
             WHERE season_id = ? AND rank_score > ?'
        );
        $posStmt->execute([$seasonId, $score]);
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

    $xpReq = max(1, (int) ($userProgress['required_current'] ?? 1));
    $xpInto = (int) ($userProgress['into'] ?? 0);
    $xpFillPct = (int) min(100, max(0, round(($xpInto / $xpReq) * 100)));

    try {
        release_available_points_if_due($pdo, $userId);
    } catch (\Throwable $e) { /* ignore */ }
    try {
        expire_points_if_due($pdo, $userId);
    } catch (\Throwable $e) { /* ignore */ }
    $kndPointsNet = 0;
    try {
        $kndPointsNet = (int) get_available_points($pdo, $userId);
    } catch (\Throwable $e) {
        $kndPointsNet = 0;
    }
    $pointsBal = ['pending' => 0, 'available' => 0];
    try {
        $pointsBal = get_points_balance($pdo, $userId);
    } catch (\Throwable $e) { /* ignore */ }
    $fragmentsTotal = 0;
    try {
        $fragmentsTotal = get_user_fragments($pdo, $userId);
    } catch (\Throwable $e) {
        $fragmentsTotal = 0;
    }

    $equipped_mw_stats = null;
    $equipped_mw_skills = null;
    $hero_image_url = null;
    $hero_model_url = null;
    if ($selectedAvatar) {
        $name = (string) ($selectedAvatar['name'] ?? '');
        $ap = (string) ($selectedAvatar['asset_path'] ?? '');
        $mwIdSel = (int) ($selectedAvatar['mw_avatar_id'] ?? 0);
        $mwJoinSel = trim((string) ($selectedAvatar['mw_image'] ?? ''));
        $hero_image_url = mw_resolve_avatar_image_for_inventory(
            $pdo,
            $mwIdSel > 0 ? $mwIdSel : null,
            $name,
            $ap,
            $mwJoinSel !== '' ? $mwJoinSel : null
        );
        $hero_model_url = $selectedAvatar['display_model_url'] ?? null;
        $mwId = $mwIdSel;
        if ($mwId > 0) {
            try {
                $prof = mw_get_combat_profile_from_db($pdo, $mwId);
                $equipped_mw_stats = [
                    'mind' => (int) ($prof['mind'] ?? 0),
                    'focus' => (int) ($prof['focus'] ?? 0),
                    'speed' => (int) ($prof['speed'] ?? 0),
                    'luck' => (int) ($prof['luck'] ?? 0),
                ];
                $equipped_mw_skills = [
                    'passive_code' => (string) ($prof['passive_code'] ?? ''),
                    'ability_code' => (string) ($prof['ability_code'] ?? ''),
                    'special_code' => (string) ($prof['special_code'] ?? ''),
                ];
            } catch (\Throwable $e) {
                $equipped_mw_stats = null;
                $equipped_mw_skills = null;
            }
        }
    }

    $missions = [];
    try {
        $missions = missions_get_today($pdo, $userId);
    } catch (\Throwable $e) {
        $missions = [];
    }

    $notifications = [
        'items' => [],
        'unread_count' => 0,
    ];
    try {
        mw_challenges_ensure_table($pdo);
        mw_challenges_cleanup_expired($pdo);
        $incomingStmt = $pdo->prepare(
            "SELECT c.challenge_token, c.challenger_user_id, u.username AS challenger_username,
                    c.expires_at, c.created_at
             FROM knd_mind_wars_challenges c
             JOIN users u ON u.id = c.challenger_user_id
             WHERE c.season_id = ?
               AND c.challenged_user_id = ?
               AND c.status = 'pending'
               AND c.expires_at > NOW()
             ORDER BY c.id DESC
             LIMIT 5"
        );
        $incomingStmt->execute([$seasonId, $userId]);
        while ($row = $incomingStmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications['items'][] = [
                'type' => 'challenge_incoming',
                'icon' => '⚔',
                'title' => 'Challenge',
                'message' => (string) ($row['challenger_username'] ?? 'Player') . ' challenged you.',
                'time' => (string) ($row['created_at'] ?? ''),
                'unread' => true,
            ];
            $notifications['unread_count']++;
        }
    } catch (\Throwable $e) {
        /* ignore */
    }

    try {
        $daily = daily_get_status($pdo, $userId);
        if (!empty($daily['can_claim'])) {
            $notifications['items'][] = [
                'type' => 'daily_login',
                'icon' => '🎁',
                'title' => 'Daily reward',
                'message' => 'Claim your daily KND Points.',
                'time' => '',
                'unread' => true,
            ];
            $notifications['unread_count']++;
        }
    } catch (\Throwable $e) {
        /* ignore */
    }

    $online_hint = 0;
    try {
        $oc = $pdo->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM knd_mind_wars_matchmaking_queue
             WHERE season_id = ? AND status IN ('queued','matched')"
        );
        $oc->execute([$seasonId]);
        $online_hint = (int) $oc->fetchColumn();
    } catch (\Throwable $e) {
        $online_hint = 0;
    }

    $seasonEndTs = strtotime((string) ($season['ends_at'] ?? 'now')) ?: time();
    $secondsRemaining = max(0, $seasonEndTs - time());

    return [
        'user' => [
            'id' => $userId,
            'username' => $username,
            'xp' => (int) $userProgress['total'],
            'level' => (int) $userProgress['level'],
            'xp_into_level' => (int) ($userProgress['into'] ?? 0),
            'xp_to_next_level' => (int) ($userProgress['to_next'] ?? 0),
            'xp_required_current' => (int) ($userProgress['required_current'] ?? 0),
            'xp_fill_pct' => $xpFillPct,
        ],
        'currencies' => [
            'knd_points_available' => $kndPointsNet,
            'knd_points_pending' => (int) ($pointsBal['pending'] ?? 0),
            'fragments_total' => $fragmentsTotal,
        ],
        'season' => [
            'id' => $seasonId,
            'name' => (string) ($season['name'] ?? 'Mind Wars Season'),
            'starts_at' => (string) ($season['starts_at'] ?? ''),
            'ends_at' => (string) ($season['ends_at'] ?? ''),
            'seconds_remaining' => $secondsRemaining,
        ],
        'avatars' => $avatars,
        'selected_avatar' => $selectedAvatar,
        'hero_image_url' => $hero_image_url,
        'hero_model_url' => $hero_model_url,
        'equipped_mw_stats' => $equipped_mw_stats,
        'equipped_mw_skills' => $equipped_mw_skills,
        'ranking' => $ranking,
        'missions' => $missions,
        'notifications' => $notifications,
        'online_hint' => $online_hint,
    ];
}
