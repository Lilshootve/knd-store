<?php
/**
 * Resolve and lock a product row for sale/credit-sale (exact name, then LIKE, strict multi-match guard).
 */
declare(strict_types=1);

/**
 * @param array{product_id?: int|string, product_name?: string, qty?: int} $item
 * @return array{product: array<string, mixed>}|array{error: string, ambiguous?: list<string>}
 */
function retail_resolve_product_row_for_sale(PDO $pdo, int $bizId, array $item): array
{
    if (!empty($item['product_id']) && is_numeric($item['product_id'])) {
        $lockStmt = $pdo->prepare(
            'SELECT id, name, price_base, stock FROM retail_products
             WHERE id = ? AND business_id = ? AND active = 1 FOR UPDATE'
        );
        $lockStmt->execute([(int) $item['product_id'], $bizId]);
        $product = $lockStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return ['error' => "Producto ID {$item['product_id']} no encontrado."];
        }
        return ['product' => $product];
    }

    if (empty($item['product_name']) || !is_string($item['product_name'])) {
        return ['error' => 'Cada ítem requiere product_id o product_name.'];
    }

    $name = trim($item['product_name']);
    if ($name === '') {
        return ['error' => 'Nombre de producto vacío.'];
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, price_base, stock FROM retail_products
         WHERE business_id = ? AND active = 1 AND LOWER(TRIM(name)) = LOWER(TRIM(?))'
    );
    $stmt->execute([$bizId, $name]);
    $exactRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (count($exactRows) > 1) {
        return [
            'error'     => 'Varios productos con el mismo nombre — usar product_id o renombrar en catálogo.',
            'ambiguous' => array_column($exactRows, 'name'),
        ];
    }

    $pickId = null;
    if (count($exactRows) === 1) {
        $pickId = (int) $exactRows[0]['id'];
    } else {
        $like = '%' . _retail_like_escape($name) . '%';
        $likeStmt = $pdo->prepare(
            'SELECT id, name, price_base, stock FROM retail_products
             WHERE business_id = ? AND active = 1 AND name LIKE ? LIMIT 4'
        );
        $likeStmt->execute([$bizId, $like]);
        $likeRows = $likeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (count($likeRows) === 0) {
            return ['error' => "Producto no encontrado: '{$name}'. Use search_product para ubicar el SKU correcto."];
        }
        if (count($likeRows) > 1) {
            return [
                'error'     => "Nombre ambiguo: '{$name}'. Coincidencias: " . implode(', ', array_column($likeRows, 'name')),
                'ambiguous' => array_column($likeRows, 'name'),
            ];
        }
        $pickId = (int) $likeRows[0]['id'];
    }

    $lockStmt = $pdo->prepare(
        'SELECT id, name, price_base, stock FROM retail_products
         WHERE id = ? AND business_id = ? AND active = 1 FOR UPDATE'
    );
    $lockStmt->execute([$pickId, $bizId]);
    $product = $lockStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return ['error' => 'Producto no disponible tras resolución.'];
    }

    return ['product' => $product];
}

function _retail_like_escape(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}
