<?php
require_once __DIR__ . '/config/bootstrap.php';
// test-model.php
// Standalone script to fetch all nexus_districts and compute average memory.

header('Content-Type: application/json');
require_once BASE_PATH . '/includes/config.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT * FROM nexus_districts');
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $average = 0;
    if (!empty($districts)) {
        $memories = array_column($districts, 'memory');
        // Ensure numeric values
        $memories = array_filter($memories, 'is_numeric');
        if (!empty($memories)) {
            $average = array_sum($memories) / count($memories);
        }
    }

    echo json_encode([
        'districts' => $districts,
        'average'   => $average
    ]);
} catch (Exception $e) {
    error_log('test-model.php error: ' . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to retrieve districts'
    ]);
}
?>