<?php
/**
 * @deprecated Use POST /retail-admin/api/agent_execute.php with tool "update_exchange_rate".
 */
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'ok'    => false,
    'error' => 'This endpoint is retired. Use retail-admin/api/agent_execute.php with tool update_exchange_rate (via execute.php).',
]);
