<?php
/**
 * KND Retail — Tool Registry
 * GET  /api/retail/tools.php          → todos los tools
 * GET  /api/retail/tools.php?name=X   → tool específico
 * Protegido por KND_WORKER_TOKEN.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');

defined('KND_ROOT') or define('KND_ROOT', dirname(__DIR__, 2));
require_once KND_ROOT . '/includes/env.php';
require_once KND_ROOT . '/includes/config.php';

// Auth: Bearer token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token      = knd_env('KND_WORKER_TOKEN', '');
if (!$token || trim(str_replace('Bearer ', '', $authHeader)) !== $token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tools = [
    [
        'name'        => 'create_sale',
        'description' => 'Registra una venta de contado con descuento de stock y snapshot de precios y tasa.',
        'category'    => 'pos',
        'safe'        => false,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['items'],
            'properties' => [
                'items'           => ['type' => 'array', 'description' => 'Lista de productos. Cada elemento: {product_id|product_name, qty}'],
                'currency'        => ['type' => 'string', 'description' => 'Moneda de cobro (VES, USD, EUR). Default: base_currency del negocio.'],
                'idempotency_key' => ['type' => 'string', 'description' => 'Clave única para prevenir doble registro.'],
                'customer_id'     => ['type' => 'integer', 'description' => 'Cliente opcional para asociar la venta.'],
            ],
        ],
        'safety_rules' => ['stock_never_negative', 'transaction_required', 'idempotency_key_recommended'],
    ],
    [
        'name'        => 'create_credit_sale',
        'description' => 'Venta a crédito. Requiere cliente identificado y confirmación del admin.',
        'category'    => 'pos',
        'safe'        => false,
        'requires_role'         => 'cashier',
        'requires_confirmation' => true,
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['items'],
            'properties' => [
                'items'             => ['type' => 'array'],
                'customer_id'       => ['type' => 'integer'],
                'customer_document' => ['type' => 'string', 'description' => 'Alternativo a customer_id.'],
                'currency'          => ['type' => 'string'],
                'idempotency_key'   => ['type' => 'string'],
            ],
        ],
        'safety_rules' => ['customer_required', 'admin_confirmation', 'stock_never_negative'],
    ],
    [
        'name'        => 'register_credit_payment',
        'description' => 'Registra un pago de deuda de un cliente. Reduce el saldo deudor.',
        'category'    => 'credits',
        'safe'        => false,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['amount'],
            'properties' => [
                'customer_id'       => ['type' => 'integer'],
                'customer_document' => ['type' => 'string'],
                'customer_name'     => ['type' => 'string'],
                'amount'            => ['type' => 'number', 'description' => 'Monto pagado (positivo).'],
                'currency'          => ['type' => 'string'],
            ],
        ],
        'safety_rules' => ['balance_never_negative'],
    ],
    [
        'name'        => 'get_product',
        'description' => 'Busca un producto por ID, nombre exacto o SKU.',
        'category'    => 'inventory',
        'safe'        => true,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer'],
                'name'       => ['type' => 'string'],
                'sku'        => ['type' => 'string'],
            ],
        ],
    ],
    [
        'name'        => 'search_product',
        'description' => 'Búsqueda fuzzy de productos por nombre (Levenshtein). Sugiere restock si no hay matches.',
        'category'    => 'inventory',
        'safe'        => true,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['query'],
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 2, 'description' => 'Término de búsqueda (tolerante a typos).'],
            ],
        ],
    ],
    [
        'name'        => 'get_inventory_low',
        'description' => 'Lista productos con stock <= min_stock.',
        'category'    => 'inventory',
        'safe'        => true,
        'requires_role' => 'cashier',
        'input_schema' => ['type' => 'object', 'properties' => []],
    ],
    [
        'name'        => 'get_sales_today',
        'description' => 'Resumen de ventas del día actual (o fecha específica).',
        'category'    => 'reports',
        'safe'        => true,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD. Default: hoy.'],
            ],
        ],
    ],
    [
        'name'        => 'adjust_stock',
        'description' => 'SOLO ADMIN. Ajusta el stock manualmente. Requiere confirmación.',
        'category'    => 'inventory',
        'safe'        => false,
        'requires_role'         => 'admin',
        'requires_confirmation' => true,
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['product_id', 'delta', 'reason'],
            'properties' => [
                'product_id' => ['type' => 'integer'],
                'delta'      => ['type' => 'integer', 'description' => 'Positivo = entrada, negativo = merma/robo.'],
                'reason'     => ['type' => 'string', 'description' => 'Motivo del ajuste.'],
            ],
        ],
        'safety_rules' => ['admin_only', 'stock_never_negative', 'admin_confirmation'],
    ],
    [
        'name'        => 'get_customer_by_document',
        'description' => 'Busca un cliente por su número de documento.',
        'category'    => 'customers',
        'safe'        => true,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['document_id'],
            'properties' => ['document_id' => ['type' => 'string']],
        ],
    ],
    [
        'name'        => 'create_customer_if_not_exists',
        'description' => 'Crea un cliente si no existe. Busca primero por document_id.',
        'category'    => 'customers',
        'safe'        => false,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['name'],
            'properties' => [
                'name'        => ['type' => 'string'],
                'document_id' => ['type' => 'string', 'description' => 'Cédula, RIF o pasaporte.'],
            ],
        ],
    ],
    [
        'name'        => 'update_exchange_rate',
        'description' => 'SOLO ADMIN. Actualiza la tasa de cambio. Requiere confirmación.',
        'category'    => 'finance',
        'safe'        => false,
        'requires_role'         => 'admin',
        'requires_confirmation' => true,
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['currency', 'rate'],
            'properties' => [
                'currency' => ['type' => 'string', 'description' => 'VES, EUR, COP, etc.'],
                'rate'     => ['type' => 'number', 'description' => 'Unidades de la moneda local equivalentes a 1 base_currency.'],
            ],
        ],
        'safety_rules' => ['admin_only', 'admin_confirmation', 'snapshot_immutable'],
    ],
    [
        'name'        => 'get_top_products',
        'description' => 'Ranking de productos por ingreso (total_base) en un rango de fechas.',
        'category'    => 'reports',
        'safe'        => true,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'date_from' => ['type' => 'string', 'format' => 'date'],
                'date_to'   => ['type' => 'string', 'format' => 'date'],
                'limit'     => ['type' => 'integer', 'description' => '1–100, default 10'],
            ],
        ],
    ],
    [
        'name'        => 'get_sales_summary',
        'description' => 'Totales y desglose por día, tipo de venta y moneda en un rango.',
        'category'    => 'reports',
        'safe'        => true,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'date_from' => ['type' => 'string', 'format' => 'date'],
                'date_to'   => ['type' => 'string', 'format' => 'date'],
            ],
        ],
    ],
    [
        'name'        => 'list_customer_balances',
        'description' => 'Clientes con saldo deudor (crédito) y suma total.',
        'category'    => 'credits',
        'safe'        => true,
        'requires_role' => 'cashier',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'min_balance' => ['type' => 'number', 'description' => 'Mínimo en moneda base (default 0.01)'],
                'limit'       => ['type' => 'integer', 'description' => '1–500, default 100'],
            ],
        ],
    ],
];

// Filtrar por nombre si se solicita
$name = $_GET['name'] ?? '';
if ($name) {
    foreach ($tools as $t) {
        if ($t['name'] === $name) {
            echo json_encode(['tool' => $t], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => "Tool '$name' no encontrado."]);
    exit;
}

echo json_encode(['tools' => $tools, 'count' => count($tools)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
