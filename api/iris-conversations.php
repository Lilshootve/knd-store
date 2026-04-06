<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$irisConvPanel = BASE_PATH . '/panel/api/iris-conversations.php';
if (!is_file($irisConvPanel)) {
    error_log('[iris-conversations stub] missing panel file: ' . $irisConvPanel);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server error']);
    exit;
}
require_once $irisConvPanel;
