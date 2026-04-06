<?php
/**
 * Mind Wars squad selector: avatars owned by the logged-in user with MW stats/skills.
 * Security: uses mw_get_user_avatars() (inventory scoped to user) then enriches per mw_avatars.id.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/mind_wars.php';
require_once BASE_PATH . '/includes/mw_avatar_models.php';

if (!function_exists('avatar_sync_items_from_assets')) {
    require_once BASE_PATH . '/includes/knd_avatar.php';
}

api_require_login();

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'DB_ERROR', 'message' => 'Database unavailable']]);
    exit;
}

$userId = (int) (current_user_id() ?? ($_SESSION['user_id'] ?? 0));
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Invalid session']]);
    exit;
}

/**
 * @return array<string,mixed>|null
 */
function mw_squad_fetch_mw_row(PDO $pdo, int $mwId): ?array {
    if ($mwId < 1) {
        return null;
    }
    $sql = 'SELECT a.id, a.name, a.rarity, a.class, a.image AS mw_image,
                   s.mind, s.focus, s.speed, s.luck,
                   sk.passive, sk.ability, sk.special, sk.heal,
                   sk.passive_code, sk.ability_code, sk.special_code
            FROM mw_avatars a
            LEFT JOIN mw_avatar_stats s ON s.avatar_id = a.id
            LEFT JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
            WHERE a.id = ?
            LIMIT 1';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mwId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        // Some DBs may lack heal or text columns — retry minimal set
        try {
            $sql2 = 'SELECT a.id, a.name, a.rarity, a.class, a.image AS mw_image,
                            s.mind, s.focus, s.speed, s.luck,
                            sk.passive, sk.ability, sk.special,
                            sk.passive_code, sk.ability_code, sk.special_code
                     FROM mw_avatars a
                     LEFT JOIN mw_avatar_stats s ON s.avatar_id = a.id
                     LEFT JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
                     WHERE a.id = ?
                     LIMIT 1';
            $stmt = $pdo->prepare($sql2);
            $stmt->execute([$mwId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['heal'] = null;
            }
            return $row ?: null;
        } catch (Throwable $e2) {
            error_log('mw_squad_fetch_mw_row: ' . $e2->getMessage());
            return null;
        }
    }
}

function mw_squad_skill_text(array $row, string $textKey, string $codeKey): string {
    $t = isset($row[$textKey]) ? trim((string) $row[$textKey]) : '';
    if ($t !== '') {
        return $t;
    }
    $c = isset($row[$codeKey]) ? trim((string) $row[$codeKey]) : '';

    return $c;
}

try {
    $owned = mw_get_user_avatars($pdo, $userId);
} catch (Throwable $e) {
    error_log('get_user_avatars mw_get_user_avatars: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'QUERY_ERROR', 'message' => 'Could not load avatars']]);
    exit;
}

$out = [];
$seenMw = [];

foreach ($owned as $entry) {
    $mwId = (int) ($entry['mw_avatar_id'] ?? 0);
    if ($mwId < 1 || isset($seenMw[$mwId])) {
        continue;
    }
    $seenMw[$mwId] = true;

    $row = mw_squad_fetch_mw_row($pdo, $mwId);
    if (!$row) {
        continue;
    }

    $name = (string) ($row['name'] ?? '');
    $rarity = strtolower((string) ($row['rarity'] ?? 'common'));
    $class = (string) ($row['class'] ?? '');

    $imageUrl = mw_resolve_avatar_image_for_inventory(
        $pdo,
        $mwId,
        $name,
        '',
        isset($row['mw_image']) ? (string) $row['mw_image'] : null
    );
    if ($imageUrl === '') {
        $imageUrl = '/assets/avatars/_placeholder.svg';
    }

    $modelUrl = mw_resolve_avatar_model_url($mwId, $name, $rarity);
    if ($modelUrl === null || $modelUrl === '') {
        $modelUrl = '/assets/avatars/models/epic/thor.glb';
    }

    $healRaw = $row['heal'] ?? null;
    $healStr = $healRaw !== null ? trim((string) $healRaw) : '';
    $healJson = ($healStr === '' || $healStr === '0') ? null : $healStr;

    $passive = mw_squad_skill_text($row, 'passive', 'passive_code');
    $ability = mw_squad_skill_text($row, 'ability', 'ability_code');
    $special = mw_squad_skill_text($row, 'special', 'special_code');

    $avatarLevel = max(1, (int) ($entry['avatar_level'] ?? 1));

    $out[] = [
        'id' => $mwId,
        'name' => strtoupper($name),
        'rarity' => $rarity,
        'class' => $class,
        'avatar_level' => $avatarLevel,
        'model' => $modelUrl,
        'image' => $imageUrl,
        'mind' => (int) ($row['mind'] ?? 0),
        'focus' => (int) ($row['focus'] ?? 0),
        'speed' => (int) ($row['speed'] ?? 0),
        'luck' => (int) ($row['luck'] ?? 0),
        'passive' => $passive,
        'ability' => $ability,
        'special' => $special,
        'heal' => $healJson,
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
