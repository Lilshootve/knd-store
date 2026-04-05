<?php
/**
 * Tool: get_inventory_low
 * Retorna productos cuyo stock <= min_stock dentro del negocio activo.
 */

function retail_get_inventory_low(PDO $pdo, array $input): array
{
    $bizId = retail_business_id();

    $stmt = $pdo->prepare(
        'SELECT id, sku, name, price_base, stock, min_stock
         FROM retail_products
         WHERE business_id = ? AND active = 1 AND stock <= min_stock
         ORDER BY (stock - min_stock) ASC, name ASC
         LIMIT 100'
    );
    $stmt->execute([$bizId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar total de productos activos para contexto
    $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM retail_products WHERE business_id = ? AND active = 1');
    $totalStmt->execute([$bizId]);
    $total = (int) $totalStmt->fetchColumn();

    return [
        'low_stock_count'   => count($products),
        'total_products'    => $total,
        'products'          => $products,
        'message'           => count($products) === 0
            ? 'Todos los productos están sobre el stock mínimo.'
            : count($products) . ' producto(s) con stock bajo o agotado.',
    ];
}
