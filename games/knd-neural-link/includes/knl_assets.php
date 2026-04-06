<?php
/**
 * KND Neural Link — static asset helpers (cache-bust for mw_avatars.image URLs).
 */
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 3) . '/config/bootstrap.php';
}

function knl_project_root(): string
{
    return BASE_PATH;
}

/**
 * Append ?v=mtime for local /assets/... files so browser picks up replaced images (same path).
 * Leaves external URLs and missing files unchanged.
 */
function knl_bust_avatar_image_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '' || ($path[0] ?? '') !== '/') {
        return $url;
    }
    if (stripos($path, '/assets/') !== 0) {
        return $url;
    }
    $root = knl_project_root();
    $fs   = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($fs)) {
        return $url;
    }
    $v = (int) filemtime($fs);
    if ($v <= 0) {
        return $url;
    }
    $q = parse_url($url, PHP_URL_QUERY);
    if (is_string($q) && $q !== '') {
        parse_str($q, $params);
        unset($params['v'], $params['ver']);
        $tail = http_build_query($params);
        return $path . '?v=' . $v . ($tail !== '' ? '&' . $tail : '');
    }

    return $path . '?v=' . $v;
}
