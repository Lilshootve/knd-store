<?php
/**
 * Mind Wars lobby: resolve public URL for avatar GLB under /assets/avatars/models/{rarity}/ (see avatar-glb-map.json).
 */

require_once __DIR__ . '/mind_wars.php';

/** @var array<string,string>|null */
$GLOBALS['_mw_avatar_glb_map_cache'] = null;

/**
 * @return array<string,string> mw_avatar_id string => relative path under models/ (e.g. epic/foo.glb)
 */
function mw_avatar_glb_map(): array {
    if ($GLOBALS['_mw_avatar_glb_map_cache'] !== null) {
        return $GLOBALS['_mw_avatar_glb_map_cache'];
    }
    $out = [];
    $path = dirname(__DIR__) . '/games/mind-wars/data/avatar-glb-map.json';
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                foreach ($j as $k => $v) {
                    if (!is_string($v) || trim($v) === '') {
                        continue;
                    }
                    $id = (string) (int) $k;
                    if ($id === '0') {
                        continue;
                    }
                    $out[$id] = str_replace('\\', '/', trim($v));
                }
            }
        }
    }
    $GLOBALS['_mw_avatar_glb_map_cache'] = $out;

    return $out;
}

/**
 * Build absolute URL path with each segment rawurlencoded (spaces in filenames).
 */
function mw_avatar_model_url_from_relative(string $relativePath): string {
    $rel = trim(str_replace('\\', '/', $relativePath), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return '';
    }
    $parts = explode('/', $rel);
    $enc = [];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.' || $p === '..') {
            return '';
        }
        $enc[] = rawurlencode($p);
    }

    return '/assets/avatars/models/' . implode('/', $enc);
}

function mw_avatar_model_normalized_rarity(string $rarity): string {
    $r = strtolower(trim($rarity));

    return $r === '' ? 'common' : $r;
}

/**
 * True if relative path is safe to expose as a public URL without verifying is_file() on this host
 * (GLBs may live only on CDN / separate static docroot while map/heuristic path is canonical).
 */
function mw_avatar_map_relative_is_publishable(string $relativePath): bool {
    $rel = trim(str_replace('\\', '/', $relativePath), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return false;
    }
    $parts = explode('/', $rel);
    $allowedFolder = ['common', 'rare', 'special', 'epic', 'legendary'];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.' || strpos($p, '..') !== false) {
            return false;
        }
    }
    $last = (string) end($parts);
    if (strlen($last) < 5 || strcasecmp(substr($last, -4), '.glb') !== 0) {
        return false;
    }
    if (count($parts) === 1) {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_. -]*\.glb$/', $last);
    }
    if (count($parts) !== 2) {
        return false;
    }
    $folder = strtolower($parts[0]);

    return in_array($folder, $allowedFolder, true)
        && (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_. -]*\.glb$/', $last);
}

/**
 * If exact path missing (e.g. Linux case), find same file under the same folder ignoring case.
 *
 * @return string|null canonical relative path under models/
 */
function mw_avatar_model_find_case_insensitive(string $relativePath): ?string {
    $rel = trim(str_replace('\\', '/', $relativePath), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return null;
    }
    if (mw_avatar_model_file_exists($rel)) {
        return $rel;
    }
    $parts = explode('/', $rel);
    if (count($parts) === 1) {
        $file = $parts[0];
        $want = strtolower($file);
        $dir = mw_avatar_models_fs_root();
        if (!is_dir($dir)) {
            return null;
        }
        foreach (scandir($dir) ?: [] as $ent) {
            if ($ent === '.' || $ent === '..') {
                continue;
            }
            if (strtolower($ent) === $want && is_file($dir . DIRECTORY_SEPARATOR . $ent)) {
                return $ent;
            }
        }

        return null;
    }
    if (count($parts) < 2) {
        return null;
    }
    $file = array_pop($parts);
    $sub = implode('/', $parts);
    $dir = mw_avatar_models_fs_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
    if (!is_dir($dir)) {
        return null;
    }
    $want = strtolower($file);
    foreach (scandir($dir) ?: [] as $ent) {
        if ($ent === '.' || $ent === '..') {
            continue;
        }
        if (strtolower($ent) === $want && is_file($dir . DIRECTORY_SEPARATOR . $ent)) {
            return $sub . '/' . $ent;
        }
    }

    return null;
}

/**
 * @return string|null public URL or null if no file
 */
function mw_resolve_avatar_model_url(?int $mwAvatarId, string $name, string $rarity): ?string {
    $rNorm = mw_avatar_model_normalized_rarity($rarity);
    $mwAvatarId = (int) $mwAvatarId;
    if ($mwAvatarId > 0) {
        $map = mw_avatar_glb_map();
        $key = (string) $mwAvatarId;
        if (isset($map[$key])) {
            $mapRel = $map[$key];
            if ($mapRel !== '' && strpos($mapRel, '..') === false) {
                $foundMap = mw_avatar_model_find_case_insensitive($mapRel);
                if ($foundMap !== null) {
                    $url = mw_avatar_model_url_from_relative($foundMap);
                    if ($url !== '') {
                        return $url;
                    }
                }
                $base = basename(str_replace('\\', '/', $mapRel));
                if ($base !== '' && substr(strtolower($base), -4) === '.glb' && strpos($base, '..') === false) {
                    $underRarity = $rNorm . '/' . $base;
                    if ($underRarity !== $mapRel) {
                        $foundUnder = mw_avatar_model_find_case_insensitive($underRarity);
                        if ($foundUnder !== null) {
                            $url = mw_avatar_model_url_from_relative($foundUnder);
                            if ($url !== '') {
                                return $url;
                            }
                        }
                    }
                    $flat = mw_avatar_model_find_case_insensitive($base);
                    if ($flat !== null && strpos($flat, '/') === false) {
                        return mw_avatar_model_url_from_relative($flat);
                    }
                    if (mw_avatar_map_relative_is_publishable($mapRel)) {
                        $pubUrl = mw_avatar_model_url_from_relative($mapRel);
                        if ($pubUrl !== '') {
                            return $pubUrl;
                        }
                    }
                }
            }
        }
    }

    $heuristic = mw_resolve_avatar_model_url_heuristic($name, $rarity);
    if ($heuristic !== null) {
        return $heuristic;
    }

    return null;
}

function mw_avatar_models_fs_root(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR . 'models';
}

function mw_avatar_model_file_exists(string $relativePath): bool {
    $rel = trim(str_replace('\\', '/', $relativePath), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return false;
    }
    $full = mw_avatar_models_fs_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    return is_file($full);
}

/**
 * Try common on-disk layouts when JSON has no entry.
 *
 * @return string|null absolute URL path
 */
function mw_resolve_avatar_model_url_heuristic(string $name, string $rarity): ?string {
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    if (!function_exists('mw_avatar_slug_from_name')) {
        return null;
    }
    $slugHyphen = mw_avatar_slug_from_name($name);
    $slugUnder = str_replace('-', '_', $slugHyphen);
    $r = mw_avatar_model_normalized_rarity($rarity);
    $candidates = [
        $r . '/' . $slugUnder . '.glb',
        $r . '/' . $slugHyphen . '.glb',
    ];
    foreach ($candidates as $rel) {
        $found = mw_avatar_model_find_case_insensitive($rel);
        if ($found !== null) {
            $url = mw_avatar_model_url_from_relative($found);
            if ($url !== '') {
                return $url;
            }
        }
    }
    foreach ($candidates as $rel) {
        if (mw_avatar_map_relative_is_publishable($rel)) {
            $url = mw_avatar_model_url_from_relative($rel);
            if ($url !== '') {
                return $url;
            }
        }
    }

    return null;
}
