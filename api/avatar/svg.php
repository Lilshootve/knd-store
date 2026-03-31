<?php
// Returns inline SVG content for avatar assets (safe path restriction)
header('Cache-Control: public, max-age=3600');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/knd_avatar.php';

$path = isset($_GET['path']) ? trim($_GET['path']) : '';
if (empty($path)) {
    http_response_code(400);
    exit;
}

$svg = render_inline_svg($path);
if ($svg === '') {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo '';
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
echo $svg;
