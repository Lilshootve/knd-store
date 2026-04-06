<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

api_require_login();

$pricing = [
    'text2img' => [
        'standard' => 3,
        'high' => 6,
    ],
    'upscale' => [
        '2x' => 5,
        '4x' => 8,
    ],
    'character' => [
        'base' => 15,
    ],
    'consistency' => [
        'base' => 5,
    ],
    'remove_bg' => [
        'base' => 5,
    ],
    '3d_vertex' => [
        'standard' => 20,
        'high' => 30,
    ],
];

json_success($pricing);
