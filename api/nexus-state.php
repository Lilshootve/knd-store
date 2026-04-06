<?php
require_once __DIR__ . '/../config/bootstrap.php';
// api/nexus-state.php
// Handles GET to retrieve all districts and POST to update a district's memory value.

header('Content-Type: application/json');
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';

// Ensure the user is authenticated for API access
api_require_login();

$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare('SELECT district_id, memory, last_updated FROM nexus_districts');
        $stmt->execute();
        $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $districts]);
    } catch (Exception $e) {
        error_log('GET /api/nexus-state.php error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve districts']);
    }
} elseif ($method === 'POST') {
    // Expect JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
        exit;
    }
    $districtId = $input['district_id'] ?? null;
    $memory = $input['memory'] ?? null;
    if ($districtId === null || $memory === null) {
        echo json_encode(['status' => 'error', 'message' => 'district_id and memory required']);
        exit;
    }
    // Validate memory is numeric between 0 and 100
    if (!is_numeric($memory) || $memory < 0 || $memory > 100) {
        echo json_encode(['status' => 'error', 'message' => 'memory must be a number between 0 and 100']);
        exit;
    }
    try {
        $stmt = $pdo->prepare('UPDATE nexus_districts SET memory = :memory, last_updated = NOW() WHERE district_id = :district_id');
        $stmt->execute([
            ':memory' => $memory,
            ':district_id' => $districtId
        ]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'District not found']);
        } else {
            echo json_encode(['status' => 'success', 'data' => ['district_id' => $districtId, 'memory' => $memory]]);
        }
    } catch (Exception $e) {
        error_log('POST /api/nexus-state.php error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to update district']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>