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
require_once BASE_PATH . '/includes/nexus_world_builder_gate.php';
require_once BASE_PATH . '/includes/nexus_district_room_registry.php';
require_once BASE_PATH . '/includes/json.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('METHOD_NOT_ALLOWED', 'Only GET allowed', 405);
}

$pdo = getDBConnection();
// Usar current_user_id(): sesión unificada dr_user_id / user_id (ver includes/auth.php)
$uid = is_logged_in() ? current_user_id() : null;

try {
    // 1. Distritos + echo status por distrito
    try {
        $distCols = $pdo->query('SHOW COLUMNS FROM nexus_districts')->fetchAll(PDO::FETCH_COLUMN);
        $hasCityMesh = in_array('city_glb_url', $distCols, true);
        $citySelect = $hasCityMesh
            ? ', d.city_glb_url, d.city_mesh_pos_x, d.city_mesh_pos_y, d.city_mesh_pos_z, d.city_mesh_rot_y, d.city_mesh_scale'
            : '';

        $districts = $pdo->query("
            SELECT
                d.id, d.name, d.era, d.tag, d.color_hex, d.icon, d.game_url,
                d.pos_x, d.pos_z
                {$citySelect},
                COUNT(e.avatar_id)                       AS total_echoes,
                ROUND(AVG(e.resonance), 1)               AS avg_resonance,
                SUM(e.status = 'active')                 AS echoes_active,
                SUM(e.status IN ('ghost','forgotten'))   AS echoes_fading
            FROM nexus_districts d
            LEFT JOIN nexus_echo e ON e.district_id = d.id
            GROUP BY d.id
            ORDER BY d.sort_order
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (!$hasCityMesh) {
            foreach ($districts as &$drow) {
                $drow['city_glb_url'] = null;
                $drow['city_mesh_pos_x'] = null;
                $drow['city_mesh_pos_y'] = null;
                $drow['city_mesh_pos_z'] = null;
                $drow['city_mesh_rot_y'] = null;
                $drow['city_mesh_scale'] = null;
            }
            unset($drow);
        }
    } catch (PDOException $_) { $districts = []; }

    $districts = nexus_district_room_apply_game_urls($districts);

    // 2. Ecos por distrito (los 5 con más resonancia por zona)
    try {
        $echos_raw = $pdo->query("
            SELECT
                e.avatar_id, e.district_id, e.resonance, e.status,
                a.name, a.rarity, a.class, a.subrole, a.image
            FROM nexus_echo e
            JOIN mw_avatars a ON a.id = e.avatar_id
            ORDER BY e.district_id, e.resonance DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $_) { $echos_raw = []; }

    $echoes_by_district = [];
    foreach ($echos_raw as $row) {
        $did = $row['district_id'];
        if (!isset($echoes_by_district[$did])) $echoes_by_district[$did] = [];
        if (count($echoes_by_district[$did]) < 5) {
            $echoes_by_district[$did][] = $row;
        }
    }

    // 3. Top 6 jugadores (Memory Wall) — basado en knd_mind_wars_rankings
    try {
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
    } catch (PDOException $_) { $top_players = []; }

    // 4. Última batalla (para el event feed del nexo)
    try {
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
    } catch (PDOException $_) { $last_battle = null; }

    // 5. Jugadores online ahora (last_active en los últimos 3 minutos)
    try {
        $online_count = $pdo->query("
            SELECT COUNT(*) FROM nexus_player_state
            WHERE last_active >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)
        ")->fetchColumn();
    } catch (PDOException $_) { $online_count = 0; }

    // 6. Apariencia y estado del jugador autenticado
    $player_data = null;
    if ($uid) {
        // Use a fallback query without nexus tables in case migration hasn't run
        try {
            $stmt = $pdo->prepare("
                SELECT
                    u.id                                  AS user_id,
                    u.username,
                    COALESCE(npa.display_name, u.username) AS display_name,
                    COALESCE(npa.color_body,  '#00e8ff')   AS color_body,
                    COALESCE(npa.color_visor, '#00e8ff')   AS color_visor,
                    COALESCE(npa.color_echo,  '#ffd600')   AS color_echo,
                    COALESCE(nps.pos_x, 0)                 AS pos_x,
                    COALESCE(nps.pos_z, 0)                 AS pos_z,
                    COALESCE(kux.level, 1)                 AS level,
                    kux.xp,
                    fa.id    AS mw_avatar_id,
                    fa.name  AS avatar_name,
                    fa.rarity AS avatar_rarity
                FROM users u
                LEFT JOIN nexus_player_appearance npa ON npa.user_id = u.id
                LEFT JOIN nexus_player_state nps      ON nps.user_id = u.id
                LEFT JOIN knd_user_xp kux             ON kux.user_id = u.id
                LEFT JOIN knd_avatar_items fai        ON fai.id = u.favorite_avatar_id AND fai.mw_avatar_id IS NOT NULL
                LEFT JOIN mw_avatars fa               ON fa.id = fai.mw_avatar_id
                WHERE u.id = ?
            ");
            $stmt->execute([$uid]);
            $player_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $_) {
            // Nexus tables may not exist yet — degrade gracefully with minimal user data
            try {
                $stmt2 = $pdo->prepare("SELECT username, COALESCE(kux.level,1) AS level, kux.xp FROM users u LEFT JOIN knd_user_xp kux ON kux.user_id=u.id WHERE u.id=?");
                $stmt2->execute([$uid]);
                $row = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $_2) { $row = []; }
            $player_data = [
                'user_id'      => (int) $uid,
                'display_name' => $row['username'] ?? '',
                'color_body'   => '#00e8ff',
                'color_visor'  => '#00e8ff',
                'color_echo'   => '#ffd600',
                'pos_x'        => 0,
                'pos_z'        => 0,
                'level'        => $row['level'] ?? 1,
                'xp'           => $row['xp'] ?? null,
                'username'     => $row['username'] ?? '',
            ];
        }
        if ($player_data) {
            $player_data['user_id'] = (int) ($player_data['user_id'] ?? $uid);
            // Resolve avatar GLB URL — wrapped defensively so any failure doesn't break the endpoint
            $player_data['hero_model_url'] = null;
            try {
                require_once BASE_PATH . '/includes/mw_avatar_models.php';
                if (function_exists('mw_resolve_avatar_model_url')) {
                    $player_data['hero_model_url'] = mw_resolve_avatar_model_url(
                        $player_data['mw_avatar_id'] ? (int)$player_data['mw_avatar_id'] : null,
                        (string)($player_data['avatar_name'] ?? ''),
                        (string)($player_data['avatar_rarity'] ?? 'common')
                    );
                    // Fallback 1: any inventory item that has a linked mw_avatar
                    if (!$player_data['hero_model_url']) {
                        try {
                            $sf = $pdo->prepare("SELECT fa.id, fa.name, fa.rarity FROM knd_user_avatar_inventory ui JOIN knd_avatar_items ai ON ai.id = ui.item_id AND ai.mw_avatar_id IS NOT NULL JOIN mw_avatars fa ON fa.id = ai.mw_avatar_id WHERE ui.user_id = ? LIMIT 1");
                            $sf->execute([$uid]);
                            $avf = $sf->fetch(PDO::FETCH_ASSOC);
                            if ($avf && $avf['id']) {
                                $player_data['hero_model_url'] = mw_resolve_avatar_model_url((int)$avf['id'], (string)($avf['name']??''), (string)($avf['rarity']??'common'));
                            }
                        } catch (Throwable $_f) {}
                    }
                    // Fallback 2: first mw_avatar with a GLB on disk
                    if (!$player_data['hero_model_url']) {
                        try {
                            $sa = $pdo->query("SELECT id, name, rarity FROM mw_avatars ORDER BY id ASC LIMIT 30");
                            foreach ($sa->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                $url = mw_resolve_avatar_model_url((int)$row['id'], (string)$row['name'], (string)$row['rarity']);
                                if ($url) { $player_data['hero_model_url'] = $url; break; }
                            }
                        } catch (Throwable $_a) {}
                    }
                }
            } catch (Throwable $_) { /* non-fatal — hero will use fallback procedural model */ }
            $player_data['rarity'] = $player_data['avatar_rarity'] ?? 'common';
            unset($player_data['mw_avatar_id'], $player_data['avatar_name'], $player_data['avatar_rarity']);
        }

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

    // 7. Admin flag (world builder)
    $is_admin = nexus_user_can_world_builder($pdo, $uid);

    json_success([
        'districts'          => $districts,
        'echoes_by_district' => $echoes_by_district,
        'top_players'        => $top_players,
        'last_battle'        => $last_battle ?: null,
        'online_count'       => (int)$online_count,
        'player'             => $player_data,
        'is_admin'           => $is_admin,
    ]);

} catch (PDOException $e) {
    error_log('nexus/world.php error: ' . $e->getMessage());
    json_error('DB_ERROR', 'Failed to load world state', 500);
}
