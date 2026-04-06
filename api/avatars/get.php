<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/mind_wars.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $userId = (int) (current_user_id() ?? 0);
    if ($userId < 1) {
        echo json_encode(["ok" => false, "error" => "NOT_LOGGED"]);
        exit;
    }

    // 🧠 MODO 1: LISTAR AVATARES DEL USUARIO (LOBBY)
    if (!isset($_GET['id'])) {
        $avatars = mw_get_user_avatars($pdo, $userId);

        $out = array_map(function ($a) use ($pdo) {
            $mwId = isset($a['mw_avatar_id']) ? (int) $a['mw_avatar_id'] : 0;
            $mwJoin = isset($a['mw_image']) && $a['mw_image'] !== null && trim((string) $a['mw_image']) !== ''
                ? trim((string) $a['mw_image'])
                : null;
            $img = mw_resolve_avatar_image_for_inventory(
                $pdo,
                $mwId > 0 ? $mwId : null,
                (string) ($a['name'] ?? ''),
                (string) ($a['asset_path'] ?? ''),
                $mwJoin
            );
            $stats = null;
            if ($mwId > 0) {
                try {
                    $profile = mw_get_combat_profile_from_db($pdo, $mwId);
                    $stats = [
                        'mind' => (int) ($profile['mind'] ?? 50),
                        'focus' => (int) ($profile['focus'] ?? 50),
                        'speed' => (int) ($profile['speed'] ?? 50),
                        'luck' => (int) ($profile['luck'] ?? 50),
                    ];
                } catch (\Throwable $e) {
                    $stats = ['mind' => 50, 'focus' => 50, 'speed' => 50, 'luck' => 50];
                }
            }
            return [
                "item_id" => (int) ($a['item_id'] ?? 0),
                "id" => (int) ($a['item_id'] ?? 0),
                "mw_avatar_id" => $mwId > 0 ? $mwId : null,
                "name" => (string) ($a['name'] ?? 'Avatar'),
                "rarity" => (string) ($a['rarity'] ?? 'common'),
                "image" => $img ?: null,
                "avatar_level" => max(1, (int) ($a['avatar_level'] ?? 1)),
                "stats" => $stats,
            ];
        }, $avatars);

        echo json_encode([
            "ok" => true,
            "data" => ["avatars" => $out]
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // 🧠 MODO 2: AVATAR INDIVIDUAL (BATALLA) — id = item_id del inventario del usuario
    $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$itemId || $itemId <= 0) {
        echo json_encode(["ok" => false, "error" => "INVALID_ID"]);
        exit;
    }

    $avatar = mw_validate_owned_avatar($pdo, $userId, $itemId);
    if (!$avatar) {
        echo json_encode(["ok" => false, "error" => "NOT_OWNED"]);
        exit;
    }

    $profile = mw_get_combat_profile($avatar);
    $mwOne = (int) ($avatar['mw_avatar_id'] ?? 0);
    $mwJoinOne = isset($avatar['mw_image']) && $avatar['mw_image'] !== null && trim((string) $avatar['mw_image']) !== ''
        ? trim((string) $avatar['mw_image'])
        : null;
    $image = mw_resolve_avatar_image_for_inventory(
        $pdo,
        $mwOne > 0 ? $mwOne : null,
        (string) ($avatar['name'] ?? ''),
        (string) ($avatar['asset_path'] ?? ''),
        $mwJoinOne
    );

    $classLabel = (string) ($profile['combat_class_label'] ?? 'Fighter');

    $healSkill = null;
    $mwAvatarId = (int) ($avatar['mw_avatar_id'] ?? 0);
    if ($mwAvatarId > 0) {
        try {
            $mwStmt = $pdo->prepare("SELECT heal FROM mw_avatar_skills WHERE avatar_id = ? LIMIT 1");
            $mwStmt->execute([$mwAvatarId]);
            $mwRow = $mwStmt->fetch(PDO::FETCH_ASSOC);
            if ($mwRow && !empty(trim((string) ($mwRow['heal'] ?? '')))) {
                $healSkill = trim((string) $mwRow['heal']);
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    echo json_encode([
        "ok" => true,
        "data" => [
            "id" => (int) ($avatar['item_id'] ?? 0),
            "mw_avatar_id" => $mwAvatarId > 0 ? $mwAvatarId : null,
            "name" => (string) ($avatar['name'] ?? 'Avatar'),
            "rarity" => (string) ($avatar['rarity'] ?? 'common'),
            "class" => $classLabel,
            "stats" => [
                "mind" => (int) ($profile['mind'] ?? 50),
                "focus" => (int) ($profile['focus'] ?? 50),
                "speed" => (int) ($profile['speed'] ?? 50),
                "luck" => (int) ($profile['luck'] ?? 50),
            ],
            "skills" => [
                "passive" => (string) ($profile['passive_code'] ?? ''),
                "ability" => (string) ($profile['ability_code'] ?? ''),
                "special" => (string) ($profile['special_code'] ?? ''),
                "heal" => $healSkill,
            ],
            "image" => $image ?: null,
            "level" => max(1, (int) ($avatar['avatar_level'] ?? 1)),
            "knowledge_energy" => max(0, (int) ($avatar['knowledge_energy'] ?? 0)),
        ]
    ], JSON_UNESCAPED_UNICODE);

    exit;

} catch (\Throwable $e) {
    error_log('api/avatars/get error: ' . $e->getMessage());
    http_response_code(stripos($e->getMessage(), 'AGUACATE') !== false ? 400 : 500);
    echo json_encode([
        "ok" => false,
        "error" => stripos($e->getMessage(), 'AGUACATE') !== false ? "AGUACATE" : "SERVER_ERROR",
        "message" => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}