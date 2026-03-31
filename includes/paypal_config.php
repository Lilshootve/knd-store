<?php
// PayPal configuration (LIVE).
// Credentials are loaded strictly from .env variables.
require_once __DIR__ . '/env.php';

$clientId = knd_env_required('PAYPAL_CLIENT_ID');
$clientSecret = knd_env_required('PAYPAL_CLIENT_SECRET');
$apiBase = knd_env_required('PAYPAL_API_BASE');

if (!defined('PAYPAL_CLIENT_ID')) {
    define('PAYPAL_CLIENT_ID', $clientId);
}
if (!defined('PAYPAL_CLIENT_SECRET')) {
    define('PAYPAL_CLIENT_SECRET', $clientSecret);
}
if (!defined('PAYPAL_API_BASE')) {
    define('PAYPAL_API_BASE', $apiBase);
}

if (!function_exists('getPayPalAccessToken')) {
    function getPayPalAccessToken(): string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => PAYPAL_API_BASE . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en_US',
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return '';
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            return '';
        }

        $payload = json_decode($response, true);
        return $payload['access_token'] ?? '';
    }
}

if (!function_exists('paypalApiRequest')) {
    function paypalApiRequest(string $method, string $path, string $accessToken, ?array $body = null): array {
        $ch = curl_init();
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => PAYPAL_API_BASE . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response ?: '', true);
        return [
            'status' => $status,
            'body' => $decoded,
            'raw' => $response,
        ];
    }
}
?>
