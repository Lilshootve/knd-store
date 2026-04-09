<?php
declare(strict_types=1);

/**
 * KND Neural Link — match mw_avatars to knd_avatar_items for inventory (item_id).
 * Names/slugs rarely match exactly between MW DB and shop rows from assets.
 */
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once BASE_PATH . '/includes/knd_avatar.php';

/**
 * Lowercase + rough ASCII transliteration for slug compare (Aladdín ≈ aladdin).
 */
function knl_ascii_fold_lower(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t === false || $t === '') {
        $t = $s;
    }

    return strtolower($t);
}

function knl_slug_key(string $s): string
{
    return avatar_slugify(knl_ascii_fold_lower($s));
}

/**
 * @return array{exact: array<string,int>, slug: array<string,int>, file_slug: array<string,int>}
 */
function knl_neural_build_item_index(PDO $pdo): array
{
    $exact     = [];
    $slug      = [];
    $fileSlug  = [];

    $stmt = $pdo->query('SELECT id, name, asset_path FROM knd_avatar_items WHERE is_active = 1');
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id   = (int) $r['id'];
        $name = (string) ($r['name'] ?? '');
        $ap   = (string) ($r['asset_path'] ?? '');

        $ek = knl_ascii_fold_lower($name);
        if ($ek !== '') {
            $exact[$ek] = $id;
        }
        $sk = knl_slug_key($name);
        if ($sk !== '' && $sk !== 'item' && !isset($slug[$sk])) {
            $slug[$sk] = $id;
        }

        $path = $ap !== '' ? (parse_url($ap, PHP_URL_PATH) ?: $ap) : '';
        if ($path !== '') {
            $base = (string) pathinfo($path, PATHINFO_FILENAME);
            $fk   = knl_slug_key($base);
            if ($fk !== '' && $fk !== 'item' && !isset($fileSlug[$fk])) {
                $fileSlug[$fk] = $id;
            }
        }
    }

    return ['exact' => $exact, 'slug' => $slug, 'file_slug' => $fileSlug];
}

/**
 * @param array{exact: array<string,int>, slug: array<string,int>, file_slug: array<string,int>} $index
 */
function knl_neural_resolve_item_id(array $mw, array $index): ?int
{
    $name = (string) ($mw['name'] ?? '');
    $ek   = knl_ascii_fold_lower($name);
    if ($ek !== '' && isset($index['exact'][$ek])) {
        return (int) $index['exact'][$ek];
    }

    $sk = knl_slug_key($name);
    if ($sk !== '' && $sk !== 'item' && isset($index['slug'][$sk])) {
        return (int) $index['slug'][$sk];
    }

    $img = (string) ($mw['image'] ?? '');
    if ($img !== '') {
        $path = parse_url($img, PHP_URL_PATH) ?: $img;
        $base = (string) pathinfo($path, PATHINFO_FILENAME);
        $fk   = knl_slug_key($base);
        if ($fk !== '' && $fk !== 'item' && isset($index['file_slug'][$fk])) {
            return (int) $index['file_slug'][$fk];
        }
    }

    return null;
}

/**
 * Pick one mw avatar for rarity + matching shop item_id. Portrait stays mw.image (DB).
 *
 * @return array<string,mixed>|null
 */
function knl_neural_pick_mw_with_item(PDO $pdo, string $rarity): ?array
{
    avatar_sync_items_from_assets($pdo);
    $index = knl_neural_build_item_index($pdo);

    $stmt = $pdo->prepare(
        'SELECT id, name, rarity, class, subrole, image FROM mw_avatars WHERE rarity = ?'
    );
    $stmt->execute([$rarity]);
    $mws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($mws === []) {
        return null;
    }

    shuffle($mws);

    foreach ($mws as $mw) {
        $itemId = knl_neural_resolve_item_id($mw, $index);
        if ($itemId === null) {
            continue;
        }

        $it = $pdo->prepare('SELECT id, name, asset_path FROM knd_avatar_items WHERE id = ? AND is_active = 1 LIMIT 1');
        $it->execute([$itemId]);
        $itemRow = $it->fetch(PDO::FETCH_ASSOC);
        if (!$itemRow) {
            continue;
        }

        return [
            'item_id'    => (int) $itemRow['id'],
            'item_name'  => (string) $itemRow['name'],
            'asset_path' => (string) $itemRow['asset_path'],
            'mw_id'      => (int) $mw['id'],
            'mw_name'    => (string) $mw['name'],
            'mw_rarity'  => (string) $mw['rarity'],
            'class'      => $mw['class'],
            'subrole'    => $mw['subrole'],
            'image'      => $mw['image'],
        ];
    }

    return null;
}

/**
 * Pool sizes: mw rows per rarity that can grant a shop item_id (same rules as open_drop).
 *
 * @return array<string,int>
 */
function knl_neural_pool_counts_by_rarity(PDO $pdo): array
{
    avatar_sync_items_from_assets($pdo);
    $index = knl_neural_build_item_index($pdo);

    $stmt = $pdo->query('SELECT id, name, rarity, image FROM mw_avatars');
    $counts = [];
    while ($mw = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (knl_neural_resolve_item_id($mw, $index) === null) {
            continue;
        }
        $r = (string) ($mw['rarity'] ?? '');
        if ($r === '') {
            continue;
        }
        $counts[$r] = ($counts[$r] ?? 0) + 1;
    }

    return $counts;
}
