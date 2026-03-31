<?php
/**
 * Arena Protocol — avatar catalog for Unity client.
 * GET /api/game/get_avatars.php
 *
 * Resolves avatars.json from several layouts (monorepo vs kndstore-only deploy).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
// WebGL / browser builds: UnityWebRequest is subject to CORS.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$gameDir = __DIR__;
$candidates = [
    $gameDir . '/../../game-data/avatars.json',
    dirname($gameDir, 4) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'game-data' . DIRECTORY_SEPARATOR . 'avatars.json',
    dirname($gameDir, 3) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'game-data' . DIRECTORY_SEPARATOR . 'avatars.json',
];

$resolved = null;
foreach ($candidates as $path) {
    $real = realpath($path);
    if ($real !== false && is_readable($real)) {
        $resolved = $real;
        break;
    }
}

if ($resolved === null) {
    http_response_code(500);
    echo json_encode(['error' => 'avatars_catalog_unavailable', 'detail' => 'avatars.json not found']);
    exit;
}

readfile($resolved);
