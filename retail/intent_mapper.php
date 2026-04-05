<?php
/**
 * KND Retail Module — Intent Mapper (NL → Tool, sin LLM)
 *
 * Segunda capa después del fast path parser.
 * Usa keyword matching para comandos más verbosos o alternativos.
 * Si tampoco hace match, el gateway deja pasar a Iris (LLM).
 *
 * retail_intent_map(string $message): ?array
 * Retorna ['tool' => string, 'input' => array] o null.
 */

function retail_intent_map(string $message): ?array
{
    $msg = mb_strtolower(trim($message), 'UTF-8');

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------
    $has = fn(array $needles) => _retail_has_any($msg, $needles);

    // ------------------------------------------------------------------
    // VENTAS / POS
    // ------------------------------------------------------------------

    // Venta a crédito (palabras clave)
    if ($has(['fiado', 'fiar', 'al fiado', 'cuenta', 'apuntar', 'apunta'])) {
        return [
            'tool'  => 'create_credit_sale',
            'input' => ['_intent_mapped' => true, '_raw' => $message],
        ];
    }

    // Venta contado (palabras clave adicionales al fast path)
    if ($has(['vender', 'cobrar', 'factura', 'facturar', 'registrar venta'])) {
        return [
            'tool'  => 'create_sale',
            'input' => ['_intent_mapped' => true, '_raw' => $message],
        ];
    }

    // ------------------------------------------------------------------
    // PAGOS / CRÉDITO
    // ------------------------------------------------------------------

    if ($has(['pag', 'abon', 'salda', 'cancela', 'liquida', 'cobrar deuda', 'cobrar credito'])) {
        return [
            'tool'  => 'register_credit_payment',
            'input' => ['_intent_mapped' => true, '_raw' => $message],
        ];
    }

    // ------------------------------------------------------------------
    // INVENTARIO / PRODUCTOS
    // ------------------------------------------------------------------

    // Buscar producto
    if ($has(['buscar', 'busca', 'hay ', 'tenemos', 'quedan', 'cuánto hay', 'cuantos', 'existencia'])) {
        return [
            'tool'  => 'search_product',
            'input' => ['query' => $message, '_intent_mapped' => true],
        ];
    }

    // Inventario bajo
    if ($has(['por acabar', 'se acaba', 'reponer', 'reabastecer', 'poco stock', 'stock bajo', 'pocas unidades'])) {
        return [
            'tool'  => 'get_inventory_low',
            'input' => ['_intent_mapped' => true],
        ];
    }

    // Ajuste de stock (requiere admin)
    if ($has(['ajustar stock', 'ajuste', 'ingresar mercancía', 'entraron', 'llegaron', 'merma', 'perdida', 'robo'])) {
        return [
            'tool'  => 'adjust_stock',
            'input' => ['_intent_mapped' => true, '_raw' => $message],
        ];
    }

    // ------------------------------------------------------------------
    // CLIENTES
    // ------------------------------------------------------------------

    if ($has(['cliente', 'buscar cliente', 'datos del cliente', 'quien es'])) {
        return [
            'tool'  => 'get_customer_by_document',
            'input' => ['_intent_mapped' => true, '_raw' => $message],
        ];
    }

    if ($has(['registrar cliente', 'agregar cliente', 'nuevo cliente', 'crear cliente'])) {
        return [
            'tool'  => 'create_customer_if_not_exists',
            'input' => ['_intent_mapped' => true, '_raw' => $message],
        ];
    }

    // ------------------------------------------------------------------
    // REPORTES / MÉTRICAS
    // ------------------------------------------------------------------

    if ($has(['ventas hoy', 'ventas del día', 'ventas de hoy', 'resumen del día', 'qué vendimos'])) {
        return [
            'tool'  => 'get_sales_today',
            'input' => ['_intent_mapped' => true],
        ];
    }

    // ------------------------------------------------------------------
    // TASAS DE CAMBIO
    // ------------------------------------------------------------------

    if ($has(['tasa', 'cambio', 'dólar', 'euro', 'bs ', 'bolivar', 'actualizar tasa', 'actualizar cambio'])) {
        return [
            'tool'  => 'update_exchange_rate',
            'input' => ['_intent_mapped' => true, '_raw' => $message],
        ];
    }

    // Sin match — escalar a Iris
    return null;
}

/**
 * Verifica si el mensaje contiene alguna de las palabras clave dadas.
 */
function _retail_has_any(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (mb_strpos($haystack, $needle, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    return false;
}
