<?php
/**
 * Tool: create_sale
 * Crea una venta de contado con múltiples items.
 *
 * REGLAS CRÍTICAS:
 *  - Transacción completa (SELECT FOR UPDATE → UPDATE stock → INSERT sale)
 *  - Stock NUNCA puede ir < 0
 *  - price_snapshot es inmutable desde el momento de la venta
 *  - exchange_rate_snapshot NUNCA recalculado después
 *  - Idempotency key previene doble-POST
 *
 * Input:
 *   items[]            array de {product_id|product_name, qty}
 *   currency           moneda de pago (default: base_currency del negocio)
 *   idempotency_key    string opcional (recomendado para POS)
 *   customer_id        int opcional
 *   customer_document  string opcional (resuelve a customer_id dentro del negocio)
 */

require_once __DIR__ . '/../../../config/bootstrap.php';
defined('KND_ROOT') or define('KND_ROOT', BASE_PATH);
require_once BASE_PATH . '/core/retail/lib/invoice_number.php';
require_once BASE_PATH . '/core/retail/lib/product_sale_lock.php';

function retail_create_sale(PDO $pdo, array $input): array
{
    $bizId      = retail_business_id();
    $cashierId  = retail_user_id();
    $business   = retail_business();
    $currency   = strtoupper(trim($input['currency'] ?? $business['base_currency']));
    $idemKey    = isset($input['idempotency_key']) ? trim($input['idempotency_key']) : null;
    $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
    if (($customerId === null || $customerId < 1) && !empty($input['customer_document']) && is_string($input['customer_document'])) {
        $docStmt = $pdo->prepare(
            'SELECT id FROM retail_customers WHERE business_id = ? AND document_id = ? LIMIT 1'
        );
        $docStmt->execute([$bizId, trim($input['customer_document'])]);
        $docRow = $docStmt->fetch(PDO::FETCH_ASSOC);
        if ($docRow) {
            $customerId = (int) $docRow['id'];
        }
    }
    $items      = $input['items'] ?? [];

    if (empty($items) || !is_array($items)) {
        return ['error' => 'items[] requerido.'];
    }

    // Verificar idempotency antes de abrir transacción
    if ($idemKey) {
        $dupStmt = $pdo->prepare(
            'SELECT id FROM retail_sales WHERE business_id = ? AND idempotency_key = ? LIMIT 1'
        );
        $dupStmt->execute([$bizId, $idemKey]);
        $dup = $dupStmt->fetch();
        if ($dup) {
            return ['error' => 'DUPLICATE_REQUEST', 'sale_id' => (int) $dup['id'], 'message' => 'Esta venta ya fue registrada.'];
        }
    }

    // Obtener tasa de cambio vigente
    $rate = _retail_get_latest_rate($pdo, $bizId, $currency, $business['base_currency']);
    if ($rate === null) {
        return ['error' => "No hay tasa de cambio registrada para $currency."];
    }

    try {
        $pdo->beginTransaction();

        $totalBase  = 0.0;
        $saleItems  = [];

        foreach ($items as $idx => $item) {
            $qty = max(1, (int) ($item['qty'] ?? 1));

            $resolved = retail_resolve_product_row_for_sale($pdo, $bizId, $item);
            if (isset($resolved['error'])) {
                $pdo->rollBack();
                return ['error' => $resolved['error']];
            }
            $product = $resolved['product'];

            // Verificar stock suficiente
            if ((int) $product['stock'] < $qty) {
                $pdo->rollBack();
                return [
                    'error'           => "Stock insuficiente para '{$product['name']}'.",
                    'available_stock' => (int) $product['stock'],
                    'requested_qty'   => $qty,
                ];
            }

            // Descontar stock (nunca < 0 por constraint)
            $newStock = (int) $product['stock'] - $qty;
            $updStmt  = $pdo->prepare(
                'UPDATE retail_products SET stock = ? WHERE id = ? AND business_id = ?'
            );
            $updStmt->execute([$newStock, (int) $product['id'], $bizId]);

            $priceSnapshot = (float) $product['price_base'];
            $totalBase    += $priceSnapshot * $qty;

            $saleItems[] = [
                'product_id'     => (int) $product['id'],
                'product_name'   => $product['name'],
                'qty'            => $qty,
                'price_snapshot' => $priceSnapshot,
            ];
        }

        $totalLocal    = $totalBase * $rate;
        $invoiceNumber = _retail_next_invoice_number($pdo, $bizId);

        // Insertar venta
        $insStmt = $pdo->prepare(
            'INSERT INTO retail_sales
             (business_id, customer_id, cashier_user_id, total_base, total_local,
              currency_used, exchange_rate_snapshot, type, idempotency_key, invoice_number)
             VALUES (?, ?, ?, ?, ?, ?, ?, "cash", ?, ?)'
        );
        $insStmt->execute([
            $bizId, $customerId, $cashierId,
            round($totalBase, 4), round($totalLocal, 4),
            $currency, $rate,
            $idemKey ?: null,
            $invoiceNumber,
        ]);
        $saleId = (int) $pdo->lastInsertId();

        // Insertar items
        $itemStmt = $pdo->prepare(
            'INSERT INTO retail_sale_items (sale_id, product_id, qty, price_snapshot) VALUES (?, ?, ?, ?)'
        );
        foreach ($saleItems as $si) {
            $itemStmt->execute([$saleId, $si['product_id'], $si['qty'], $si['price_snapshot']]);
        }

        $pdo->commit();

        // Audit log (fuera de transacción para no bloquear en caso de fallo)
        retail_audit_log($pdo, 'create_sale', 'sale', $saleId, null, [
            'sale_id'        => $saleId,
            'total_base'     => $totalBase,
            'currency_used'  => $currency,
            'items_count'    => count($saleItems),
        ]);

        return [
            'success'       => true,
            'sale_id'       => $saleId,
            'invoice_number'=> $invoiceNumber,
            'total_base'    => round($totalBase, 4),
            'total_local'   => round($totalLocal, 4),
            'currency_used' => $currency,
            'rate_used'     => $rate,
            'items'         => $saleItems,
            'message'       => "Venta registrada. Factura: $invoiceNumber. Total: " .
                               number_format($totalLocal, 2) . " $currency",
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('RETAIL_CREATE_SALE_ERR: ' . $e->getMessage());
        return ['error' => 'Error interno al registrar la venta.'];
    }
}

// --------------------------------------------------------------------------
// Helpers compartidos (prefijo _retail_ para no colisionar)
// --------------------------------------------------------------------------

/**
 * Obtener la tasa de cambio más reciente para una moneda.
 * Si currency == base_currency → retorna 1.0 (sin conversión).
 */
function _retail_get_latest_rate(PDO $pdo, int $bizId, string $currency, string $baseCurrency): ?float
{
    if ($currency === $baseCurrency) {
        return 1.0;
    }
    $stmt = $pdo->prepare(
        'SELECT rate_to_base FROM retail_exchange_rates
         WHERE business_id = ? AND currency = ?
         ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$bizId, $currency]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (float) $row['rate_to_base'] : null;
}
