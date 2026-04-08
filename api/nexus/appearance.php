<?php
// api/nexus/appearance.php
// GET  — devuelve apariencia actual del jugador
// POST — guarda apariencia (color_body, color_visor, color_echo, display_name, cosméticos)
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

api_require_login();
$uid = (int)$_SESSION['user_id'];
$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(npa.display_name, u.username) AS display_name,
                COALESCE(npa.color_body,  '#00e8ff')   AS color_body,
                COALESCE(npa.color_visor, '#00e8ff')   AS color_visor,
                COALESCE(npa.color_echo,  '#ffd600')   AS color_echo,
                npa.cosmetic_head, npa.cosmetic_back,
                npa.cosmetic_trail, npa.cosmetic_echo, npa.cosmetic_nameplate
            FROM users u
            LEFT JOIN nexus_player_appearance npa ON npa.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$uid]);
        $appearance = $stmt->fetch(PDO::FETCH_ASSOC);

        // cosméticos poseídos
        $owned = $pdo->prepare("
            SELECT c.id, c.code, c.name, c.slot, c.rarity, c.preview_data,
                   pc.obtained_via, pc.obtained_at
            FROM nexus_player_cosmetics pc
            JOIN nexus_cosmetics c ON c.id = pc.cosmetic_id
            WHERE pc.user_id = ? AND c.is_active = 1
            ORDER BY c.slot, c.rarity DESC
        ");
        $owned->execute([$uid]);
        $owned_cosmetics = $owned->fetchAll(PDO::FETCH_ASSOC);

        json_success([
            'appearance' => $appearance,
            'owned_cosmetics' => $owned_cosmetics,
        ]);
    } catch (PDOException $e) {
        error_log('nexus/appearance GET error: ' . $e->getMessage());
        json_error('DB_ERROR', 'Failed to fetch appearance', 500);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        json_error('INVALID_INPUT', 'JSON body required');
    }

    // Sanitize color hex
    $color_re = '/^#[0-9a-fA-F]{6}$/';
    $color_body  = (isset($input['color_body'])  && preg_match($color_re, $input['color_body']))  ? $input['color_body']  : null;
    $color_visor = (isset($input['color_visor']) && preg_match($color_re, $input['color_visor'])) ? $input['color_visor'] : null;
    $color_echo  = (isset($input['color_echo'])  && preg_match($color_re, $input['color_echo']))  ? $input['color_echo']  : null;

    $display_name = isset($input['display_name'])
        ? mb_strtoupper(trim(preg_replace('/[^a-zA-Z0-9_\-\. ]/u', '', $input['display_name'])), 'UTF-8')
        : null;
    if ($display_name !== null) $display_name = mb_substr($display_name, 0, 20);

    // Cosmetic slots — validate they exist and user owns them
    $cosmetic_slots = ['cosmetic_head','cosmetic_back','cosmetic_trail','cosmetic_echo','cosmetic_nameplate'];
    $cosmetics = [];
    foreach ($cosmetic_slots as $slot) {
        if (isset($input[$slot]) && is_numeric($input[$slot])) {
            $cid = (int)$input[$slot];
            $check = $pdo->prepare("SELECT 1 FROM nexus_player_cosmetics WHERE user_id = ? AND cosmetic_id = ?");
            $check->execute([$uid, $cid]);
            $cosmetics[$slot] = $check->fetchColumn() ? $cid : null;
        } else {
            $cosmetics[$slot] = array_key_exists($slot, $input) ? null : false; // false = don't update
        }
    }

    try {
        // Upsert
        $existing = $pdo->prepare("SELECT 1 FROM nexus_player_appearance WHERE user_id = ?");
        $existing->execute([$uid]);

        if ($existing->fetchColumn()) {
            // Build dynamic UPDATE only for provided fields
            $sets = [];
            $params = [];
            if ($color_body  !== null) { $sets[] = 'color_body = ?';  $params[] = $color_body; }
            if ($color_visor !== null) { $sets[] = 'color_visor = ?'; $params[] = $color_visor; }
            if ($color_echo  !== null) { $sets[] = 'color_echo = ?';  $params[] = $color_echo; }
            if ($display_name !== null) { $sets[] = 'display_name = ?'; $params[] = $display_name; }
            foreach ($cosmetic_slots as $slot) {
                if ($cosmetics[$slot] !== false) {
                    $sets[] = "$slot = ?";
                    $params[] = $cosmetics[$slot]; // can be int or null
                }
            }
            if (!empty($sets)) {
                $params[] = $uid;
                $pdo->prepare("UPDATE nexus_player_appearance SET " . implode(', ', $sets) . " WHERE user_id = ?")
                    ->execute($params);
            }
        } else {
            $pdo->prepare("
                INSERT INTO nexus_player_appearance
                    (user_id, display_name, color_body, color_visor, color_echo,
                     cosmetic_head, cosmetic_back, cosmetic_trail, cosmetic_echo, cosmetic_nameplate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $uid,
                $display_name,
                $color_body  ?? '#00e8ff',
                $color_visor ?? '#00e8ff',
                $color_echo  ?? '#ffd600',
                $cosmetics['cosmetic_head']      !== false ? $cosmetics['cosmetic_head']      : null,
                $cosmetics['cosmetic_back']      !== false ? $cosmetics['cosmetic_back']      : null,
                $cosmetics['cosmetic_trail']     !== false ? $cosmetics['cosmetic_trail']     : null,
                $cosmetics['cosmetic_echo']      !== false ? $cosmetics['cosmetic_echo']      : null,
                $cosmetics['cosmetic_nameplate'] !== false ? $cosmetics['cosmetic_nameplate'] : null,
            ]);
        }

        json_success(['saved' => true]);
    } catch (PDOException $e) {
        error_log('nexus/appearance POST error: ' . $e->getMessage());
        json_error('DB_ERROR', 'Failed to save appearance', 500);
    }

} else {
    json_error('METHOD_NOT_ALLOWED', 'Only GET or POST allowed', 405);
}
