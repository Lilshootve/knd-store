<?php
/**
 * Retail module tool: register_credit_payment
 *
 * Business logic for credit payments (authoritative path: /api/agent/execute.php).
 */

declare(strict_types=1);

require_once BASE_PATH . '/core/retail/audit.php';
require_once BASE_PATH . '/core/retail/tools/create_sale.php';

/**
 * Registra un pago que reduce el saldo deudor de un cliente.
 *
 * Input:
 *   customer_id       int (o customer_document / customer_name para lookup)
 *   customer_document string alternativo
 *   customer_name     string alternativo (match exacto)
 *   amount            float positivo
 *   currency          string opcional (default base_currency)
 */
function retail_register_credit_payment(PDO $pdo, array $input): array
{
    $bizId    = retail_business_id();
    $business = retail_business();
    $amount   = (float) ($input['amount'] ?? 0);
    $currency = strtoupper(trim($input['currency'] ?? $business['base_currency']));

    if ($amount <= 0) {
        return ['error' => 'amount debe ser positivo.'];
    }

    $customerId = null;
    if (!empty($input['customer_id'])) {
        $customerId = (int) $input['customer_id'];
    } elseif (!empty($input['customer_document'])) {
        $stmt = $pdo->prepare('SELECT id FROM retail_customers WHERE business_id = ? AND document_id = ? LIMIT 1');
        $stmt->execute([$bizId, trim((string) $input['customer_document'])]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['error' => "Cliente con documento '{$input['customer_document']}' no encontrado."];
        }
        $customerId = (int) $row['id'];
    } elseif (!empty($input['customer_name'])) {
        $stmt = $pdo->prepare('SELECT id FROM retail_customers WHERE business_id = ? AND name = ? LIMIT 1');
        $stmt->execute([$bizId, trim((string) $input['customer_name'])]);
        $row = $stmt->fetch();
        if ($row) {
            $customerId = (int) $row['id'];
        }
    }

    if (!$customerId) {
        return ['error' => 'Cliente no identificado. Proveer customer_id, customer_document o customer_name.'];
    }

    $amountBase = $amount;
    if ($currency !== $business['base_currency']) {
        $rate = _retail_get_latest_rate($pdo, $bizId, $currency, $business['base_currency']);
        if ($rate === null) {
            return ['error' => "Sin tasa de cambio para $currency."];
        }
        $amountBase = $amount / $rate;
    }

    try {
        $pdo->beginTransaction();

        $creditStmt = $pdo->prepare(
            'SELECT id, balance FROM retail_credits
             WHERE business_id = ? AND customer_id = ?
             FOR UPDATE'
        );
        $creditStmt->execute([$bizId, $customerId]);
        $credit = $creditStmt->fetch(PDO::FETCH_ASSOC);

        if (!$credit) {
            $pdo->rollBack();
            return ['error' => 'Este cliente no tiene deuda registrada.'];
        }

        $previousBalance = (float) $credit['balance'];

        if ($previousBalance <= 0) {
            $pdo->rollBack();
            return ['error' => 'El cliente no tiene saldo deudor pendiente.'];
        }

        $amountToApply = min($amountBase, $previousBalance);
        $newBalance    = $previousBalance - $amountToApply;

        $pdo->prepare('UPDATE retail_credits SET balance = ? WHERE id = ?')
            ->execute([round($newBalance, 4), (int) $credit['id']]);

        $pdo->prepare(
            'INSERT INTO retail_credit_transactions (credit_id, amount, type) VALUES (?, ?, "payment")'
        )->execute([(int) $credit['id'], round($amountToApply, 4)]);

        $pdo->commit();

        $custStmt = $pdo->prepare('SELECT name FROM retail_customers WHERE id = ? LIMIT 1');
        $custStmt->execute([$customerId]);
        $customerName = $custStmt->fetchColumn();

        retail_audit_log($pdo, 'register_credit_payment', 'credit', (int) $credit['id'], [
            'balance' => $previousBalance,
        ], [
            'balance'          => $newBalance,
            'payment_applied'  => $amountToApply,
            'currency_paid'    => $currency,
            'amount_local'     => $amount,
        ]);

        return [
            'success'          => true,
            'customer_id'      => $customerId,
            'customer_name'    => $customerName,
            'amount_paid'      => round($amount, 2),
            'currency_paid'    => $currency,
            'amount_base'      => round($amountToApply, 4),
            'previous_balance' => round($previousBalance, 4),
            'new_balance'      => round($newBalance, 4),
            'fully_paid'       => $newBalance <= 0,
            'message'          => "$customerName pagó " . number_format($amount, 2) . " $currency. " .
                                  'Saldo restante: ' . number_format($newBalance, 2) . " {$business['base_currency']}.",
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('RETAIL_CREDIT_PAYMENT_ERR: ' . $e->getMessage());
        return ['error' => 'Error interno al registrar el pago.'];
    }
}
