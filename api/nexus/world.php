<?php
// api/nexus/world.php
// GET — devuelve el estado completo del mundo para cargar el nexo.
// No requiere login para los datos públicos del mundo.
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('METHOD_NOT_ALLOWED', 'Only GET allowed', 405);
}

$pdo = getDBConnection();
$uid = is_logged_in() ? (int)$_SESSION['user_id'] : null;

try {
    // 1. Distritos + echo status por distrito
    $districts = $pdo->query("
        SELECT
            d.id, d.name, d.era, d.tag, d.color_hex, d.icon, d.game_url,
            d.pos_x, d.pos_z,
            COUNT(e.avatar_id)                       AS total_echoes,
            ROUND(AVG(e.resonance), 1)               AS avg_resonance,
            SUM(e.status = 'active')                 AS echoes_active,
            SUM(e.status IN ('ghost','forgotten'))   AS echoes_fading
        FROM nexus_districts d
        LEFT JOIN nexus_echo e ON e.district_id = d.id
        GROUP BY d.id
        ORDER BY d.sort_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ecos por distrito (los 5 con más resonancia por zona)
    $echos_raw = $pdo->query("
        SELECT
            e.avatar_id, e.district_id, e.resonance, e.status,
            a.name, a.rarity, a.class, a.subrole, a.image
        FROM nexus_echo e
        JOIN mw_avatars a ON a.id = e.avatar_id
        ORDER BY e.district_id, e.resonance DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $echoes_by_district = [];
    foreach ($echos_raw as $row) {
        $did = $row['district_id'];
        if (!isset($echoes_by_district[$did])) $echoes_by_district[$did] = [];
        if (count($echoes_by_district[$did]) < 5) {
            $echoes_by_district[$did][] = $row;
        }
    }

    // 3. Top 6 jugadores (Memory Wall) — basado en knd_mind_wars_rankings
    $top_players = $pdo->query("
        SELECT
            u.username,
            u.id AS user_id,
            kux.level,
            r.rank_score,
            r.wins,
            COALESCE(a.image, '') AS avatar_image,
            COALESCE(a.name, '')  AS avatar_name
        FROM knd_mind_wars_rankings r
        JOIN knd_mind_wars_seasons s ON s.id = r.season_id AND s.status = 'active'
        JOIN users u ON u.id = r.user_id
        LEFT JOIN knd_user_xp kux ON kux.user_id = r.user_id
        LEFT JOIN mw_avatars a ON a.id = u.favorite_avatar_id
        ORDER BY r.rank_score DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Última batalla (para el event feed del nexo)
    $last_battle = $pdo->query("
        SELECT
            b.id, b.result, b.created_at,
            u1.username AS player1,
            u2.username AS player2,
            a1.name AS avatar1,
            a2.name AS avatar2
        FROM knd_mind_wars_battles b
        JOIN users u1 ON u1.id = b.attacker_id
        LEFT JOIN users u2 ON u2.id = b.defender_id
        LEFT JOIN knd_user_avatar_inventory i1 ON i1.user_id = b.attacker_id AND i1.item_id = b.attacker_avatar_item_id
        LEFT JOIN knd_avatar_items ai1 ON ai1.id = i1.item_id
        LEFT JOIN mw_avatars a1 ON a1.id = ai1.mw_avatar_id
        LEFT JOIN knd_user_avatar_inventory i2 ON i2.user_id = b.defender_id AND i2.item_id = b.defender_avatar_item_id
        LEFT JOIN knd_avatar_items ai2 ON ai2.id = i2.item_id
        LEFT JOIN mw_avatars a2 ON a2.id = ai2.mw_avatar_id
        WHERE b.status = 'finished'
        ORDER BY b.created_at DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    // 5. Jugadores online ahora (last_active en los últimos 3 minutos)
    $online_count = $pdo->query("
        SELECT COUNT(*) FROM nexus_player_state
        WHERE last_active >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)
    ")->fetchColumn();

    // 6. Apariencia y estado del jugador autenticado
    $player_data = null;
    if ($uid) {
        $stmt = $pdo->prepare("
            SELECT
                u.username,
                COALESCE(npa.display_name, u.username) AS display_name,
                COALESCE(npa.color_body,  '#00e8ff')   AS color_body,
                COALESCE(npa.color_visor, '#00e8ff')   AS color_visor,
                COALESCE(npa.color_echo,  '#ffd600')   AS color_echo,
                COALESCE(nps.pos_x, 0)                 AS pos_x,
                COALESCE(nps.pos_z, 0)                 AS pos_z,
                COALESCE(kux.level, 1)                 AS level,
                kux.xp
            FROM users u
            LEFT JOIN nexus_player_appearance npa ON npa.user_id = u.id
            LEFT JOIN nexus_player_state nps      ON nps.user_id = u.id
            LEFT JOIN knd_user_xp kux             ON kux.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$uid]);
        $player_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // KP balance
        $kp_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0) AS kp
            FROM points_ledger
            WHERE user_id = ? AND status IN ('available') AND entry_type = 'earn'
              AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $kp_stmt->execute([$uid]);
        $kp_spent = $pdo->prepare("
            SELECT COALESCE(SUM(ABS(points)), 0) AS spent
            FROM points_ledger
            WHERE user_id = ? AND entry_type = 'spend'
        ");
        $kp_spent->execute([$uid]);
        if ($player_data) {
            $earned = (int)$kp_stmt->fetchColumn();
            $spent  = (int)$kp_spent->fetchColumn();
            $player_data['kp'] = max(0, $earned - $spent);
        }
    }

    json_success([
        'districts'          => $districts,
        'echoes_by_district' => $echoes_by_district,
        'top_players'        => $top_players,
        'last_battle'        => $last_battle ?: null,
        'online_count'       => (int)$online_count,
        'player'             => $player_data,
    ]);

} catch (PDOException $e) {
    error_log('nexus/world.php error: ' . $e->getMessage());
    json_error('DB_ERROR', 'Failed to load world state', 500);
}
