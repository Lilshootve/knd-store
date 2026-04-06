<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once BASE_PATH . '/includes/config.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        die("NO PDO (getDBConnection returned null)\n");
    }
    $row = $pdo->query("SELECT NOW() AS now_time")->fetch(PDO::FETCH_ASSOC);
    echo "OK DB CONNECTED: " . $row['now_time'] . "\n";
} catch (Throwable $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}