<?php
/**
 * Tool: get_product
 * Busca un producto por ID, nombre exacto o SKU dentro del negocio activo.
 */

function retail_get_product(PDO $pdo, array $input): array
{
    $bizId = retail_business_id();

    if (!empty($input['product_id'])) {
        $stmt = $pdo->prepare(
            'SELECT id, sku, name, price_base, stock, min_stock, active, created_at
             FROM retail_products WHERE id = ? AND business_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $input['product_id'], $bizId]);
    } elseif (!empty($input['sku'])) {
        $stmt = $pdo->prepare(
            'SELECT id, sku, name, price_base, stock, min_stock, active, created_at
             FROM retail_products WHERE sku = ? AND business_id = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([trim($input['sku']), $bizId]);
    } elseif (!empty($input['name'])) {
        $stmt = $pdo->prepare(
            'SELECT id, sku, name, price_base, stock, min_stock, active, created_at
             FROM retail_products WHERE name = ? AND business_id = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([trim($input['name']), $bizId]);
    } else {
        return ['error' => 'Se requiere product_id, name o sku.'];
    }

    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return ['found' => false, 'message' => 'Producto no encontrado.'];
    }

    return [
        'success' => true,
        'found'   => true,
        'product' => $product,
    ];
}
