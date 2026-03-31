<?php
// KND Support Credits - PayPal Webhook (stub)
// TODO: Activate when PayPal credentials are available
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/support_credits.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    if (!$rawBody) {
        http_response_code(400);
        exit;
    }

    // TODO: Verify PayPal webhook signature
    // $headers = getallheaders();
    // $webhookId = 'YOUR_WEBHOOK_ID';
    // $signatureVerification = [
    //     'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
    //     'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
    //     'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
    //     'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
    //     'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
    //     'webhook_id'        => $webhookId,
    //     'webhook_event'     => json_decode($rawBody, true),
    // ];
    // If signature invalid => http_response_code(401); exit;

    $event = json_decode($rawBody, true);
    if (!$event || empty($event['event_type'])) {
        http_response_code(400);
        exit;
    }

    $eventType = $event['event_type'];
    error_log("PayPal webhook received: $eventType");

    // TODO: Handle event types:
    // PAYMENT.CAPTURE.COMPLETED => confirm payment
    // PAYMENT.CAPTURE.DENIED => reject
    // PAYMENT.CAPTURE.REFUNDED => refund
    // CUSTOMER.DISPUTE.CREATED => dispute

    // $pdo = getDBConnection();
    // if (!$pdo) { http_response_code(500); exit; }
    // switch ($eventType) {
    //     case 'PAYMENT.CAPTURE.COMPLETED':
    //         $txnId = $event['resource']['id'] ?? '';
    //         // find support_payments by provider_txn_id, call admin_update_payment($pdo, $id, 'confirm')
    //         break;
    // }

    http_response_code(200);
    echo json_encode(['status' => 'received']);
} catch (\Throwable $e) {
    error_log('paypal_webhook error: ' . $e->getMessage());
    http_response_code(500);
}
