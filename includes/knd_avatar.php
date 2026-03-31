<?php
// KND Avatar v1 - Modular avatar system with KP shop

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/support_credits.php';

/** Allowed slot names (DB column suffix) */
define('AVATAR_SLOTS', ['hair', 'top', 'bottom', 'shoes', 'accessory1', 'bg', 'frame']);
define('AVATAR_ITEM_SLOTS', ['hair', 'top', 'bottom', 'shoes', 'accessory', 'bg', 'frame']);
define('AVATAR_ASSET_EXTENSIONS', ['svg', 'png', 'jpg', 'jpeg', 'webp']);

/** Web bases for portrait thumbnails (no full-frame backgrounds). Prefer images/thumbs when file exists on disk. */
if (!defined('KND_AVATAR_THUMB_IMAGES_WEB')) {
    define('KND_AVATAR_THUMB_IMAGES_WEB', '/assets/images/thumbs');
}
if (!defined('KND_AVATAR_THUMB_AVATARS_WEB')) {
    define('KND_AVATAR_THUMB_AVATARS_WEB', '/assets/avatars/thumbs');
}

/**
 * Normalize a stored image path to an absolute web path (mw_avatars.image, etc.).
 */
function avatar_normalize_public_image_url(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '/assets/avatars/_placeholder.svg';
    }
    if (preg_match('#^https?://#i', $raw)) {
        return $raw;
    }
    if (strlen($raw) > 0 && $raw[0] === '/') {
        return $raw;
    }
    if (stripos($raw, 'assets/') === 0) {
        return '/' . ltrim($raw, '/');
    }
    return '/assets/avatars/' . ltrim($raw, '/');
}

/**
 * Resolve thumbnail filename to public URL: prefer /assets/images/thumbs when present, else /assets/avatars/thumbs.
 */
function avatar_public_thumb_url_for_filename(string $filename): string {
    $filename = basename(str_replace('\\', '/', $filename));
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return '/assets/avatars/_placeholder.svg';
    }
    $repoRoot = realpath(__DIR__ . '/..');
    if ($repoRoot !== false) {
        $tryImages = $repoRoot . '/assets/images/thumbs/' . $filename;
        $tryAvatars = $repoRoot . '/assets/avatars/thumbs/' . $filename;
        if (is_readable($tryImages)) {
            return rtrim(KND_AVATAR_THUMB_IMAGES_WEB, '/') . '/' . $filename;
        }
        if (is_readable($tryAvatars)) {
            return rtrim(KND_AVATAR_THUMB_AVATARS_WEB, '/') . '/' . $filename;
        }
    }
    // Default URL when file not yet on disk: prefer avatars tree (repo ships PNGs here); mirror under images/thumbs if you use that path.
    return rtrim(KND_AVATAR_THUMB_AVATARS_WEB, '/') . '/' . $filename;
}

/**
 * Remap mw/legacy thumb URL to profile thumb (prefer images/thumbs on disk when available).
 */
function avatar_mw_thumb_url_for_profile(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '/assets/avatars/_placeholder.svg';
    }
    if (preg_match('#/(?:assets/)?(?:images|avatars)/thumbs/([^/?#]+)$#i', $url, $m)) {
        return avatar_public_thumb_url_for_filename($m[1]);
    }
    if (preg_match('#/([^/]+\.(?:png|jpg|jpeg|webp|gif))$#i', $url, $m)) {
        return avatar_public_thumb_url_for_filename($m[1]);
    }
    return $url;
}

/**
 * Public portrait URL for profile UI: Mind Wars thumb by name when possible, else thumbs dir by code/filename.
 *
 * @param array{asset_path?:string,name?:string,code?:string,slot?:string} $item
 */
function avatar_item_thumb_url(PDO $pdo, array $item): string {
    $assetPath = trim((string) ($item['asset_path'] ?? ''));
    if ($assetPath !== '' && (
        stripos($assetPath, '/avatars/thumbs/') !== false ||
        stripos($assetPath, '/images/thumbs/') !== false
    )) {
        if (preg_match('#/(?:images|avatars)/thumbs/([^/?#]+)$#i', $assetPath, $m)) {
            return avatar_public_thumb_url_for_filename($m[1]);
        }
        return $assetPath[0] === '/' ? $assetPath : '/' . ltrim($assetPath, '/');
    }

    $slot = strtolower((string) ($item['slot'] ?? ''));
    $ext = strtolower((string) pathinfo($assetPath, PATHINFO_EXTENSION));
    if ($ext === 'svg' || in_array($slot, ['hair', 'bg'], true)) {
        return $assetPath !== '' ? $assetPath : '/assets/avatars/_placeholder.svg';
    }

    $name = trim((string) ($item['name'] ?? ''));
    if ($name !== '') {
        try {
            $stmt = $pdo->prepare('SELECT image FROM mw_avatars WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
            $stmt->execute([$name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && trim((string) ($row['image'] ?? '')) !== '') {
                $u = avatar_normalize_public_image_url(trim((string) $row['image']));
                return avatar_mw_thumb_url_for_profile($u);
            }
        } catch (\Throwable $e) {
            // mw_avatars may be absent
        }
    }

    $code = trim((string) ($item['code'] ?? ''));
    $slug = $code;
    if ($slug !== '' && preg_match('/^(frame|hair|top|bottom|shoes|accessory|bg)_/i', $slug, $m)) {
        $slug = (string) substr($slug, strlen($m[0]));
    }
    $slug = str_replace('-', '_', strtolower($slug));
    if ($slug === '') {
        $slug = avatar_slugify((string) pathinfo($assetPath, PATHINFO_FILENAME));
    }
    return avatar_public_thumb_url_for_filename($slug . '.png');
}

/**
 * Detect avatar slot from relative asset path.
 * Supports nested folders, e.g. frame/legendary/set_a/item.png
 */
function avatar_detect_slot_from_relative_path(string $relativePath): ?string {
    $normalized = strtolower(str_replace('\\', '/', $relativePath));
    $segments = array_values(array_filter(explode('/', $normalized), static function ($s) {
        return $s !== '';
    }));
    foreach ($segments as $seg) {
        if (in_array($seg, AVATAR_ITEM_SLOTS, true)) {
            return $seg;
        }
    }
    $filename = basename($normalized);
    if (preg_match('/^(hair|top|bottom|shoes|accessory|bg|frame)[_-]/', $filename, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Detect rarity from folders/file name; fallback to common.
 */
function avatar_detect_rarity_from_relative_path(string $relativePath): string {
    $normalized = strtolower(str_replace(['\\', '/', '-', '_', '.'], ' ', $relativePath));
    if (preg_match('/\b(legendary|epic|rare|special|common)\b/', $normalized, $m)) {
        return $m[1];
    }
    return 'common';
}

function avatar_price_for_rarity(string $rarity): int {
    $map = [
        'common' => 50,
        'special' => 120,
        'rare' => 200,
        'epic' => 350,
        'legendary' => 600,
    ];
    return $map[$rarity] ?? 50;
}

function avatar_slugify(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    $value = trim((string) $value, '_');
    return $value !== '' ? $value : 'item';
}

function avatar_slot_slug_key(string $slot, string $path): string {
    $base = (string) pathinfo($path, PATHINFO_FILENAME);
    return $slot . '|' . avatar_slugify($base);
}

function avatar_display_name_from_asset_path(string $assetPath): string {
    $nameBase = (string) pathinfo($assetPath, PATHINFO_FILENAME);
    $displayName = ucwords(trim((string) preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $nameBase))));
    return $displayName !== '' ? $displayName : 'Avatar Item';
}

function avatar_is_system_generated_name(string $name, string $assetPath = ''): bool {
    $normalized = trim((string) $name);
    if ($normalized === '') {
        return true;
    }

    // Legacy generic names commonly seen in profile/drop cards.
    if (preg_match('/^knd\s+avatar(\s+item)?(\s+(common|rare|epic|legendary|special))?(\s*#?\s*\d+)?$/i', $normalized)) {
        return true;
    }

    // Handle legacy variants such as "KND Avatar Avatar #12" (generic but malformed).
    $tokenized = strtolower((string) preg_replace('/[^\w#]+/u', ' ', $normalized));
    $tokens = array_values(array_filter(explode(' ', $tokenized), static function ($t) {
        return $t !== '';
    }));
    if (count($tokens) >= 2 && ($tokens[0] ?? '') === 'knd' && ($tokens[1] ?? '') === 'avatar') {
        $allowed = ['knd', 'avatar', 'item', 'common', 'rare', 'epic', 'legendary', 'special', '#'];
        $allAllowed = true;
        foreach ($tokens as $t) {
            if (ctype_digit($t)) {
                continue;
            }
            if (!in_array($t, $allowed, true)) {
                $allAllowed = false;
                break;
            }
        }
        if ($allAllowed) {
            return true;
        }
    }

    // If current label still equals filename-derived value, treat as auto-managed.
    if ($assetPath !== '') {
        $derived = avatar_display_name_from_asset_path($assetPath);
        if (strcasecmp($normalized, $derived) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Extract numeric token from filename/path to help relink renames.
 * Example: "KND_AVATAR (12).png" => "12"
 */
function avatar_extract_numeric_token(string $pathOrName): ?string {
    $base = (string) pathinfo($pathOrName, PATHINFO_FILENAME);
    if (preg_match('/(\d+)(?!.*\d)/', $base, $m)) {
        return ltrim($m[1], '0') !== '' ? ltrim($m[1], '0') : '0';
    }
    return null;
}

/**
 * Scan /assets/avatars recursively and build candidate catalog.
 */
function avatar_scan_assets_recursive(): array {
    $baseDir = realpath(__DIR__ . '/../assets/avatars');
    if (!$baseDir || !is_dir($baseDir)) {
        return [];
    }

    $items = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($it as $fileInfo) {
        if (!$fileInfo->isFile()) continue;

        $ext = strtolower((string) $fileInfo->getExtension());
        if (!in_array($ext, AVATAR_ASSET_EXTENSIONS, true)) continue;

        $absPath = $fileInfo->getPathname();
        $relative = str_replace('\\', '/', substr($absPath, strlen($baseDir) + 1));
        if ($relative === '' || strpos($relative, '..') !== false) continue;

        // Ignore non-item assets used by renderer internals.
        if (preg_match('#^(base/|_placeholder\.svg$)#i', $relative)) continue;

        $slot = avatar_detect_slot_from_relative_path($relative);
        if ($slot === null) continue;

        $rarity = avatar_detect_rarity_from_relative_path($relative);
        $webPath = '/assets/avatars/' . $relative;
        $nameBase = pathinfo($relative, PATHINFO_FILENAME);
        $displayName = ucwords(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $nameBase))));
        if ($displayName === '') $displayName = 'Avatar Item';

        $slugSeed = avatar_slugify((string) pathinfo($relative, PATHINFO_FILENAME));
        $code = substr($slot . '_' . $slugSeed, 0, 64);

        $items[] = [
            'code' => $code,
            'slot' => $slot,
            'name' => $displayName,
            'rarity' => $rarity,
            'price_kp' => avatar_price_for_rarity($rarity),
            'asset_path' => $webPath,
        ];
    }

    return $items;
}

/**
 * Sync knd_avatar_items from assets/avatars recursively.
 * Idempotent and runs once per request.
 */
function avatar_sync_items_from_assets(PDO $pdo, array $options = []): array {
    static $cache = [];
    $forceNameRefresh = !empty($options['force_name_refresh']);
    $cacheKey = $forceNameRefresh ? 'force_names' : 'default';
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];

    $scanned = avatar_scan_assets_recursive();
    $lastResult = ['inserted' => 0, 'updated' => 0, 'deactivated' => 0, 'relinked' => 0, 'scanned' => count($scanned), 'names_refreshed' => 0];
    if (empty($scanned)) {
        $cache[$cacheKey] = $lastResult;
        return $lastResult;
    }

    $stmt = $pdo->query('SELECT id, code, slot, rarity, name, asset_path FROM knd_avatar_items');
    $byPath = [];
    $byCode = [];
    $bySlotSlug = [];
    $bySlotRarityNum = [];
    $rowsById = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (int) $row['id'];
        $path = (string) $row['asset_path'];
        $slot = (string) ($row['slot'] ?? '');
        $rarity = (string) ($row['rarity'] ?? '');
        $name = (string) ($row['name'] ?? '');
        $code = (string) $row['code'];
        $rowsById[$id] = ['name' => $name, 'asset_path' => $path];

        $byPath[$path] = $id;
        $byCode[$code] = $id;

        if ($slot !== '' && $path !== '') {
            $bySlotSlug[avatar_slot_slug_key($slot, $path)] = $id;
        }
        if ($slot !== '' && $rarity !== '') {
            $numToken = avatar_extract_numeric_token($path);
            if ($numToken !== null) {
                $numKey = $slot . '|' . $rarity . '|' . $numToken;
                if (!isset($bySlotRarityNum[$numKey])) {
                    $bySlotRarityNum[$numKey] = [];
                }
                $bySlotRarityNum[$numKey][] = $id;
            }
        }
    }

    $insertStmt = $pdo->prepare(
        "INSERT INTO knd_avatar_items (code, slot, name, rarity, price_kp, asset_path, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)"
    );
    $updateStmt = $pdo->prepare(
        "UPDATE knd_avatar_items
         SET slot = ?, name = ?, rarity = ?, price_kp = ?, asset_path = ?, is_active = 1
         WHERE id = ?"
    );
    $deactivateStmt = $pdo->prepare("UPDATE knd_avatar_items SET is_active = 0 WHERE id = ?");

    $seenIds = [];
    $usedCodes = array_fill_keys(array_keys($byCode), true);

    foreach ($scanned as $item) {
        $path = $item['asset_path'];
        $slot = (string) $item['slot'];
        $targetId = null;

        if (isset($byPath[$path])) {
            $targetId = $byPath[$path];
        } elseif (isset($byCode[$item['code']])) {
            $targetId = $byCode[$item['code']];
        } else {
            $slotSlugKey = avatar_slot_slug_key($slot, $path);
            if (isset($bySlotSlug[$slotSlugKey])) {
                $targetId = $bySlotSlug[$slotSlugKey];
            } else {
                // Fallback: preserve item ID across filename renames when numeric token is stable.
                $numToken = avatar_extract_numeric_token($path);
                $rarity = (string) ($item['rarity'] ?? '');
                if ($numToken !== null && $rarity !== '') {
                    $numKey = $slot . '|' . $rarity . '|' . $numToken;
                    $candidateIds = $bySlotRarityNum[$numKey] ?? [];
                    if (count($candidateIds) === 1) {
                        $targetId = (int) $candidateIds[0];
                    }
                }
            }
        }

        if ($targetId !== null) {
            $existing = $rowsById[$targetId] ?? ['name' => '', 'asset_path' => ''];
            $existingName = (string) ($existing['name'] ?? '');
            $existingPath = (string) ($existing['asset_path'] ?? '');
            $newName = (string) $item['name'];

            if (!$forceNameRefresh && !avatar_is_system_generated_name($existingName, $existingPath)) {
                // Respect manual aliases edited by admin in normal sync mode.
                $newName = $existingName;
            } else {
                if (strcasecmp($existingName, $newName) !== 0) {
                    $lastResult['names_refreshed']++;
                }
            }

            $updateStmt->execute([
                $slot,
                $newName,
                $item['rarity'],
                (int) $item['price_kp'],
                $path,
                $targetId,
            ]);
            $lastResult['updated']++;
            $seenIds[$targetId] = true;
            $byPath[$path] = $targetId;
            $rowsById[$targetId] = ['name' => $newName, 'asset_path' => $path];
            continue;
        }

        $code = $item['code'];
        if (isset($usedCodes[$code])) {
            $base = substr($code, 0, 54);
            $n = 1;
            while (isset($usedCodes[$base . '_' . $n])) {
                $n++;
            }
            $code = $base . '_' . $n;
        }
        $usedCodes[$code] = true;

        $insertStmt->execute([
            $code,
            $slot,
            $item['name'],
            $item['rarity'],
            (int) $item['price_kp'],
            $path,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $seenIds[$newId] = true;
        $byPath[$path] = $newId;
        $rowsById[$newId] = ['name' => (string) $item['name'], 'asset_path' => $path];
        $lastResult['inserted']++;
    }

    foreach ($byPath as $existingPath => $id) {
        if (!isset($seenIds[$id])) {
            $deactivateStmt->execute([$id]);
            $lastResult['deactivated']++;
        }
    }

    $lastResult['relinked'] = avatar_relink_stale_claimed_items($pdo);
    $cache[$cacheKey] = $lastResult;
    return $lastResult;
}

/**
 * Relink stale claimed items (inactive/old IDs) to active equivalents.
 * Matching key: slot + normalized display name.
 */
function avatar_relink_stale_claimed_items(PDO $pdo): int {
    $activeStmt = $pdo->query("SELECT id, slot, rarity, name, asset_path FROM knd_avatar_items WHERE is_active = 1");
    $activeByKey = [];
    $activeBySlotRarityNum = [];
    $activeBySlotRarity = [];
    while ($r = $activeStmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $r['slot'] . '|' . avatar_slugify((string) $r['name']);
        // Keep first active item for deterministic mapping.
        if (!isset($activeByKey[$key])) {
            $activeByKey[$key] = (int) $r['id'];
        }

        $slot = (string) ($r['slot'] ?? '');
        $rarity = (string) ($r['rarity'] ?? '');
        $assetPath = (string) ($r['asset_path'] ?? '');
        if ($slot !== '' && $rarity !== '') {
            $slotRarityKey = $slot . '|' . $rarity;
            if (!isset($activeBySlotRarity[$slotRarityKey])) {
                $activeBySlotRarity[$slotRarityKey] = [];
            }
            $activeBySlotRarity[$slotRarityKey][] = (int) $r['id'];

            $numToken = avatar_extract_numeric_token($assetPath);
            if ($numToken !== null) {
                $numKey = $slot . '|' . $rarity . '|' . $numToken;
                if (!isset($activeBySlotRarityNum[$numKey])) {
                    $activeBySlotRarityNum[$numKey] = [];
                }
                $activeBySlotRarityNum[$numKey][] = (int) $r['id'];
            }
        }
    }

    $staleStmt = $pdo->query("SELECT id, slot, rarity, name, asset_path FROM knd_avatar_items WHERE is_active = 0");
    $migrations = [];
    while ($r = $staleStmt->fetch(PDO::FETCH_ASSOC)) {
        $oldId = (int) $r['id'];
        $slot = (string) ($r['slot'] ?? '');
        $rarity = (string) ($r['rarity'] ?? '');
        $assetPath = (string) ($r['asset_path'] ?? '');
        $key = $slot . '|' . avatar_slugify((string) $r['name']);
        $newId = $activeByKey[$key] ?? null;

        // Fallback 1: stable numeric token (e.g. avatar 12) within same slot+rarity.
        if (!$newId && $slot !== '' && $rarity !== '') {
            $numToken = avatar_extract_numeric_token($assetPath);
            if ($numToken !== null) {
                $numKey = $slot . '|' . $rarity . '|' . $numToken;
                $candidates = $activeBySlotRarityNum[$numKey] ?? [];
                if (count($candidates) === 1) {
                    $newId = (int) $candidates[0];
                }
            }
        }

        // Fallback 2: if exactly one active candidate exists for same slot+rarity.
        if (!$newId && $slot !== '' && $rarity !== '') {
            $slotRarityKey = $slot . '|' . $rarity;
            $candidates = $activeBySlotRarity[$slotRarityKey] ?? [];
            if (count($candidates) === 1) {
                $newId = (int) $candidates[0];
            }
        }

        if ($newId && $newId !== $oldId) {
            $migrations[$oldId] = $newId;
        }
    }

    if (empty($migrations)) return 0;

    $insertInvStmt = $pdo->prepare(
        "INSERT IGNORE INTO knd_user_avatar_inventory (user_id, item_id, acquired_at)
         SELECT user_id, ?, acquired_at
         FROM knd_user_avatar_inventory
         WHERE item_id = ?"
    );
    $deleteInvStmt = $pdo->prepare("DELETE FROM knd_user_avatar_inventory WHERE item_id = ?");
    $updateDropRewardsStmt = $pdo->prepare("UPDATE knd_user_drop_rewards SET reward_item_id = ? WHERE reward_item_id = ?");
    $updateUsersFavoriteStmt = $pdo->prepare("UPDATE users SET favorite_avatar_id = ? WHERE favorite_avatar_id = ?");

    $equipCols = ['hair_item_id', 'top_item_id', 'bottom_item_id', 'shoes_item_id', 'accessory1_item_id', 'bg_item_id', 'frame_item_id'];
    $updateEquipStmts = [];
    foreach ($equipCols as $col) {
        $updateEquipStmts[$col] = $pdo->prepare("UPDATE knd_user_avatar SET $col = ? WHERE $col = ?");
    }

    $changed = 0;
    foreach ($migrations as $oldId => $newId) {
        $insertInvStmt->execute([$newId, $oldId]);
        $deleteInvStmt->execute([$oldId]);
        $changed += $deleteInvStmt->rowCount();

        $updateDropRewardsStmt->execute([$newId, $oldId]);
        $changed += $updateDropRewardsStmt->rowCount();

        $updateUsersFavoriteStmt->execute([$newId, $oldId]);
        $changed += $updateUsersFavoriteStmt->rowCount();

        foreach ($updateEquipStmts as $stmt) {
            $stmt->execute([$newId, $oldId]);
            $changed += $stmt->rowCount();
        }
    }

    return $changed;
}

/** Map API slot to DB column */
function avatar_slot_to_column(string $slot): ?string {
    $map = [
        'hair' => 'hair_item_id',
        'top' => 'top_item_id',
        'bottom' => 'bottom_item_id',
        'shoes' => 'shoes_item_id',
        'accessory1' => 'accessory1_item_id',
        'bg' => 'bg_item_id',
        'frame' => 'frame_item_id',
    ];
    return $map[$slot] ?? null;
}

/** Map item slot (from knd_avatar_items) to user_avatar column */
function avatar_item_slot_to_column(string $itemSlot): ?string {
    if ($itemSlot === 'accessory') return 'accessory1_item_id';
    $col = $itemSlot . '_item_id';
    return in_array($col, ['hair_item_id','top_item_id','bottom_item_id','shoes_item_id','accessory1_item_id','bg_item_id','frame_item_id']) ? $col : null;
}

/**
 * Get user's equipped loadout and colors.
 */
function avatar_get_user_loadout(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT * FROM knd_user_avatar WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [
            'hair_item_id' => null, 'top_item_id' => null, 'bottom_item_id' => null,
            'shoes_item_id' => null, 'accessory1_item_id' => null,
            'bg_item_id' => null, 'frame_item_id' => null,
            'colors_json' => null,
        ];
    }
    return $row;
}

/**
 * Get shop items (active only).
 */
function avatar_get_shop_items(PDO $pdo): array {
    avatar_sync_items_from_assets($pdo);
    $stmt = $pdo->prepare("SELECT id, code, slot, name, rarity, price_kp, asset_path FROM knd_avatar_items WHERE is_active = 1 ORDER BY slot, rarity, price_kp");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user's inventory (owned item IDs with item details).
 */
function avatar_get_inventory(PDO $pdo, int $userId): array {
    avatar_sync_items_from_assets($pdo);
    $stmt = $pdo->prepare(
        "SELECT i.id, i.code, i.slot, i.name, i.rarity, i.asset_path, inv.acquired_at
         FROM knd_user_avatar_inventory inv
         JOIN knd_avatar_items i ON i.id = inv.item_id
         WHERE inv.user_id = ? AND i.is_active = 1
         ORDER BY inv.acquired_at DESC, i.id DESC"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dedupe legacy clones after rename/sync migrations.
    // Priority is first row (newest acquired / highest id due ORDER BY).
    $unique = [];
    $seen = [];
    foreach ($rows as $row) {
        $slot = (string) ($row['slot'] ?? '');
        $rarity = (string) ($row['rarity'] ?? '');
        $assetPath = (string) ($row['asset_path'] ?? '');
        $numToken = avatar_extract_numeric_token($assetPath);

        if ($slot !== '' && $rarity !== '' && $numToken !== null) {
            $key = 'srn|' . $slot . '|' . $rarity . '|' . $numToken;
        } else {
            $key = 'path|' . $assetPath;
        }

        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $row;
    }

    usort($unique, static function (array $a, array $b): int {
        $aSlot = (string) ($a['slot'] ?? '');
        $bSlot = (string) ($b['slot'] ?? '');
        $slotCmp = strcmp($aSlot, $bSlot);
        if ($slotCmp !== 0) return $slotCmp;

        $rarityOrder = [
            'common' => 1,
            'special' => 2,
            'rare' => 3,
            'epic' => 4,
            'legendary' => 5,
        ];
        $aR = strtolower((string) ($a['rarity'] ?? 'common'));
        $bR = strtolower((string) ($b['rarity'] ?? 'common'));
        $aVal = $rarityOrder[$aR] ?? 999;
        $bVal = $rarityOrder[$bR] ?? 999;
        if ($aVal !== $bVal) {
            return $aVal <=> $bVal;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return $unique;
}

/**
 * Buy item with KP. Transactional.
 * @return array ['ok' => true, 'available_after' => int] or ['error' => string]
 */
function avatar_buy_item(PDO $pdo, int $userId, int $itemId): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM knd_avatar_items WHERE id = ? AND is_active = 1 FOR UPDATE');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            $pdo->rollBack();
            return ['error' => 'ITEM_NOT_FOUND'];
        }

        $stmt = $pdo->prepare('SELECT 1 FROM knd_user_avatar_inventory WHERE user_id = ? AND item_id = ?');
        $stmt->execute([$userId, $itemId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return ['error' => 'ALREADY_OWNED'];
        }

        release_available_points_if_due($pdo, $userId);
        expire_points_if_due($pdo, $userId);
        $available = get_available_points($pdo, $userId);
        $price = (int) $item['price_kp'];
        if ($available < $price) {
            $pdo->rollBack();
            return ['error' => 'INSUFFICIENT_KP', 'available' => $available, 'required' => $price];
        }

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
             VALUES (?, 'avatar_shop', ?, 'spend', 'spent', ?, ?)"
        );
        $stmt->execute([$userId, $itemId, -$price, $now]);

        $stmt = $pdo->prepare(
            "INSERT INTO knd_user_avatar_inventory (user_id, item_id, acquired_at) VALUES (?, ?, ?)"
        );
        $stmt->execute([$userId, $itemId, $now]);

        $pdo->commit();
        $newBalance = get_available_points($pdo, $userId);
        return ['ok' => true, 'available_after' => $newBalance];
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Equip item. Verifies ownership.
 */
function avatar_equip_item(PDO $pdo, int $userId, string $slot, ?int $itemId): bool {
    $col = avatar_slot_to_column($slot);
    if (!$col) return false;

    if ($itemId !== null) {
        $stmt = $pdo->prepare('SELECT id, slot FROM knd_avatar_items WHERE id = ? AND is_active = 1');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) return false;

        $itemCol = avatar_item_slot_to_column($item['slot']);
        if ($itemCol !== $col) return false;

        $stmt = $pdo->prepare('SELECT 1 FROM knd_user_avatar_inventory WHERE user_id = ? AND item_id = ?');
        $stmt->execute([$userId, $itemId]);
        if (!$stmt->fetch()) return false;
    }

    $stmt = $pdo->prepare("INSERT INTO knd_user_avatar (user_id, $col, updated_at) VALUES (?, ?, NOW())
                          ON DUPLICATE KEY UPDATE $col = ?, updated_at = NOW()");
    $stmt->execute([$userId, $itemId, $itemId]);
    return true;
}

/**
 * Set colors. Validates allowed keys and hex format.
 * Allowed: av-primary, av-secondary, av-accent, av-metal, av-glass
 */
function avatar_set_colors(PDO $pdo, int $userId, array $colors): bool {
    $allowed = ['av-primary', 'av-secondary', 'av-accent', 'av-metal', 'av-glass'];
    $valid = [];
    foreach ($allowed as $k) {
        $v = $colors[$k] ?? null;
        if ($v === null) continue;
        $v = trim((string) $v);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) {
            $valid[$k] = $v;
        }
    }
    $json = empty($valid) ? null : json_encode($valid);

    $stmt = $pdo->prepare("INSERT INTO knd_user_avatar (user_id, colors_json, updated_at) VALUES (?, ?, NOW())
                          ON DUPLICATE KEY UPDATE colors_json = ?, updated_at = NOW()");
    $stmt->execute([$userId, $json, $json]);
    return true;
}

/**
 * Render inline SVG from path. Restricts to /assets/avatars/, strips scripts.
 * @param string $path Web path e.g. /assets/avatars/hair/hair_01.svg
 * @return string HTML-safe SVG markup or empty string
 */
function render_inline_svg(string $path): string {
    if (!preg_match('#^/assets/avatars/[a-z0-9 _\-\./\(\)]+\.svg$#i', $path)) {
        return '';
    }
    $base = $_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/..');
    $absPath = realpath($base . $path);
    $allowedDir = realpath($base . '/assets/avatars');
    if (!$absPath || !$allowedDir || strpos($absPath, $allowedDir) !== 0) {
        return '';
    }
    if (!is_readable($absPath)) {
        return '';
    }
    $content = file_get_contents($absPath);
    if ($content === false) return '';
    $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
    return $content;
}
