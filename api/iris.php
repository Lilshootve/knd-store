<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$irisPanel = BASE_PATH . '/panel/api/iris.php';
if (!is_file($irisPanel)) {
    error_log('[iris stub] missing panel file: ' . $irisPanel);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['type' => 'chat', 'response' => 'System unavailable']);
    exit;
}
require_once $irisPanel;
