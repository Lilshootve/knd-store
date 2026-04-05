<?php
/**
 * Tool: create_credit_sale
 * REQUIERE CONFIRMACIÓN ADMIN.
 * Crea una venta a crédito. El total pasa al saldo deudor del cliente.
 *
 * Input:
 *   items[]             array {product_id|product_name, qty}
 *   customer_id         int (obligatorio, o customer_document para auto-lookup)
 *   customer_document   string alternativo a customer_id
 *   currency            string opcional
 *   idempotency_key     string opcional
 */

defined('KND_ROOT') or define('KND_ROOT', dirname(__DIR__, 2));
require_once KND_ROOT . '/retail/lib/invoice_number.php';
require_once KND_ROOT . '/retail/lib/product_sale_lock.php';

function retail_create_credit_sale(PDO $pdo, array $input): array
{
    $bizId      = retail_business_id();
    $cashierId  = retail_user_id();
    $business   = retail_business();
    $currency   = strtoupper(trim($input['currency'] ?? $business['base_currency']));
    $idemKey    = isset($input['idempotency_key']) ? trim($input['idempotency_key']) : null;
    $items      = $input['items'] ?? [];

    if (empty($items) || !is_array($items)) {
        return ['error' => 'items[] requerido.'];
    }

    // Resolver customer
    $customerId = null;
    if (!empty($input['customer_id'])) {
        $customerId = (int) $input['customer_id'];
    } elseif (!empty($input['customer_document'])) {
        $findStmt = $pdo->prepare(
            'SELECT id FROM retail_customers WHERE business_id = ? AND document_id = ? LIMIT 1'
        );
        $findStmt->execute([$bizId, trim($input['customer_document'])]);
        $found = $findStmt->fetch();
        if (!$found) {
            return ['error' => "Cliente con documento '{$input['customer_document']}' no registrado. Usa create_customer_if_not_exists primero."];
        }
        $customerId = (int) $found['id'];
    } elseif (!empty($input['customer_name'])) {
        // Búsqueda por nombre (fuzzy permitido solo en fast path)
        $findStmt = $pdo->prepare(
            'SELECT id FROM retail_customers WHERE business_id = ? AND name = ? LIMIT 1'
        );
        $findStmt->execute([$bizId, trim($input['customer_name'])]);
        $found = $findStmt->fetch();
        if ($found) {
            $customerId = (int) $found['id'];
        }
    }

    if (!$customerId) {
        return ['error' => 'Venta a crédito requiere cliente identificado. Proveer customer_id o customer_document.'];
    }

    // Verificar que el cliente pertenece al negocio
    $custCheckStmt = $pdo->prepare('SELECT id FROM retail_customers WHERE id = ? AND business_id = ? LIMIT 1');
    $custCheckStmt->execute([$customerId, $bizId]);
    if (!$custCheckStmt->fetch()) {
        return ['error' => 'Cliente no pertenece a este negocio.'];
    }

    // Idempotency
    if ($idemKey) {
        $dupStmt = $pdo->prepare('SELECT id FROM retail_sales WHERE business_id = ? AND idempotency_key = ? LIMIT 1');
        $dupStmt->execute([$bizId, $idemKey]);
        if ($dup = $dupStmt->fetch()) {
            return ['error' => 'DUPLICATE_REQUEST', 'sale_id' => (int) $dup['id']];
        }
    }

    // Tasa de cambio
    $rate = _retail_get_latest_rate($pdo, $bizId, $currency, $business['base_currency']);
    if ($rate === null) {
        return ['error' => "Sin tasa de cambio para $currency."];
    }

    try {
        $pdo->beginTransaction();

        $totalBase = 0.0;
        $saleItems = [];

        foreach ($items as $item) {
            $qty = max(1, (int) ($item['qty'] ?? 1));

            $resolved = retail_resolve_product_row_for_sale($pdo, $bizId, $item);
            if (isset($resolved['error'])) {
                $pdo->rollBack();
                return ['error' => $resolved['error']];
            }
            $product = $resolved['product'];

            if ((int) $product['stock'] < $qty) {
                $pdo->rollBack();
                return ['error' => "Stock insuficiente para '{$product['name']}'. Disponible: {$product['stock']}."];
            }

            $newStock = (int) $product['stock'] - $qty;
            $pdo->prepare('UPDATE retail_products SET stock = ? WHERE id = ? AND business_id = ?')
                ->execute([$newStock, (int) $product['id'], $bizId]);

            $priceSnapshot = (float) $product['price_base'];
            $totalBase    += $priceSnapshot * $qty;
            $saleItems[]   = [
                'product_id'     => (int) $product['id'],
                'product_name'   => $product['name'],
                'qty'            => $qty,
                'price_snapshot' => $priceSnapshot,
            ];
        }

        $totalLocal    = $totalBase * $rate;
        $invoiceNumber = _retail_next_invoice_number($pdo, $bizId);

        // Insertar venta tipo credit
        $insStmt = $pdo->prepare(
            'INSERT INTO retail_sales
             (business_id, customer_id, cashier_user_id, total_base, total_local,
              currency_used, exchange_rate_snapshot, type, idempotency_key, invoice_number)
             VALUES (?, ?, ?, ?, ?, ?, ?, "credit", ?, ?)'
        );
        $insStmt->execute([
            $bizId, $customerId, $cashierId,
            round($totalBase, 4), round($totalLocal, 4),
            $currency, $rate, $idemKey ?: null, $invoiceNumber,
        ]);
        $saleId = (int) $pdo->lastInsertId();

        // Insertar items
        $itemStmt = $pdo->prepare('INSERT INTO retail_sale_items (sale_id, product_id, qty, price_snapshot) VALUES (?, ?, ?, ?)');
        foreach ($saleItems as $si) {
            $itemStmt->execute([$saleId, $si['product_id'], $si['qty'], $si['price_snapshot']]);
        }

        // Upsert en retail_credits (balance deudor aumenta)
        $upsertCredit = $pdo->prepare(
            'INSERT INTO retail_credits (business_id, customer_id, balance)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE balance = balance + ?'
        );
        $upsertCredit->execute([$bizId, $customerId, $totalBase, $totalBase]);

        // Obtener credit_id
        $creditStmt = $pdo->prepare('SELECT id FROM retail_credits WHERE business_id = ? AND customer_id = ? LIMIT 1');
        $creditStmt->execute([$bizId, $customerId]);
        $creditId = (int) $creditStmt->fetchColumn();

        // Registrar transacción de crédito
        $pdo->prepare('INSERT INTO retail_credit_transactions (credit_id, amount, type, reference_sale_id) VALUES (?, ?, "debit", ?)')
            ->execute([$creditId, round($totalBase, 4), $saleId]);

        $pdo->commit();

        retail_audit_log($pdo, 'create_credit_sale', 'sale', $saleId, null, [
            'sale_id'       => $saleId,
            'customer_id'   => $customerId,
            'total_base'    => $totalBase,
            'currency_used' => $currency,
        ]);

        return [
            'success'        => true,
            'sale_id'        => $saleId,
            'invoice_number' => $invoiceNumber,
            'customer_id'    => $customerId,
            'total_base'     => round($totalBase, 4),
            'total_local'    => round($totalLocal, 4),
            'currency_used'  => $currency,
            'items'          => $saleItems,
            'message'        => "Venta a crédito registrada. Factura: $invoiceNumber.",
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('RETAIL_CREDIT_SALE_ERR: ' . $e->getMessage());
        return ['error' => 'Error interno al registrar venta a crédito.'];
    }
}
