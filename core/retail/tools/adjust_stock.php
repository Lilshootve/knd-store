<?php
/**
 * Tool: adjust_stock
 * SOLO ADMIN. Ajusta el stock de un producto (positivo = entrada, negativo = merma).
 * Nunca deja stock < 0. Requiere reason para audit.
 */

function retail_adjust_stock(PDO $pdo, array $input): array
{
    if (!retail_is_admin()) {
        return ['error' => 'INSUFFICIENT_ROLE: admin role required.'];
    }

    $bizId     = retail_business_id();
    $productId = (int) ($input['product_id'] ?? 0);
    $delta     = (int) ($input['delta'] ?? 0);
    $reason    = trim($input['reason'] ?? '');

    if ($productId < 1) {
        return ['error' => 'product_id inválido.'];
    }
    if ($delta === 0) {
        return ['error' => 'delta no puede ser 0.'];
    }
    if (strlen($reason) < 3) {
        return ['error' => 'reason requerido (mínimo 3 caracteres).'];
    }

    try {
        $pdo->beginTransaction();

        // Bloquear fila para evitar race conditions
        $stmt = $pdo->prepare(
            'SELECT id, name, stock FROM retail_products
             WHERE id = ? AND business_id = ? AND active = 1
             FOR UPDATE'
        );
        $stmt->execute([$productId, $bizId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $pdo->rollBack();
            return ['error' => 'Producto no encontrado o inactivo.'];
        }

        $currentStock = (int) $product['stock'];
        $newStock     = $currentStock + $delta;

        // Stock nunca puede ir a negativo
        if ($newStock < 0) {
            $pdo->rollBack();
            return [
                'error'         => 'Stock insuficiente. No puede quedar en negativo.',
                'current_stock' => $currentStock,
                'delta'         => $delta,
                'would_result'  => $newStock,
            ];
        }

        $updateStmt = $pdo->prepare(
            'UPDATE retail_products SET stock = ? WHERE id = ? AND business_id = ?'
        );
        $updateStmt->execute([$newStock, $productId, $bizId]);

        $pdo->commit();

        $before = ['stock' => $currentStock];
        $after  = ['stock' => $newStock, 'delta' => $delta, 'reason' => $reason];
        retail_audit_log($pdo, 'adjust_stock', 'product', $productId, $before, $after);

        return [
            'success'       => true,
            'adjusted'      => true,
            'product_id'    => $productId,
            'product_name'  => $product['name'],
            'previous_stock'=> $currentStock,
            'delta'         => $delta,
            'new_stock'     => $newStock,
            'reason'        => $reason,
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('RETAIL_ADJUST_STOCK_ERR: ' . $e->getMessage());
        return ['error' => 'Error interno al ajustar stock.'];
    }
}
