<?php
// KND Store - Centralized JSON response helpers

if (!function_exists('json_success')) {
    function json_success(array $data = []): void {
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['ok' => true, 'data' => $data];
        $encoded = @json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            http_response_code(500);
            $encoded = '{"ok":false,"error":{"code":"ENCODE_ERROR","message":"Failed to encode response"}}';
        }
        echo $encoded;
        exit;
    }
}

if (!function_exists('json_error')) {
    function json_error(string $code, string $message, int $httpStatus = 400): void {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['ok' => false, 'error' => ['code' => $code, 'message' => $message]];
        $encoded = @json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '{"ok":false,"error":{"code":"' . addslashes(substr($code, 0, 50)) . '","message":"An error occurred"}}';
        }
        echo $encoded;
        exit;
    }
}
