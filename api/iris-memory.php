<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$irisMemPanel = BASE_PATH . '/panel/api/iris-memory.php';
if (!is_file($irisMemPanel)) {
    error_log('[iris-memory stub] missing panel file: ' . $irisMemPanel);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server error']);
    exit;
}
require_once $irisMemPanel;
