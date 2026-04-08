<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/games/mindwars1v1/bootstrap.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $season = mw_ensure_season($pdo);
    $seasonEndTs = strtotime((string) ($season['ends_at'] ?? 'now')) ?: time();
    $secondsRemaining = max(0, $seasonEndTs - time());

    json_success([
        'season_name' => (string) ($season['name'] ?? 'Mind Wars Season'),
        'season_start' => (string) ($season['starts_at'] ?? ''),
        'season_end' => (string) ($season['ends_at'] ?? ''),
        'seconds_remaining' => $secondsRemaining,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/season_info error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

