<?php
/**
 * @deprecated Use POST /retail-admin/api/agent_execute.php with tool "adjust_stock".
 */
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'ok'    => false,
    'error' => 'This endpoint is retired. Use retail-admin/api/agent_execute.php with tool adjust_stock (via execute.php).',
]);
