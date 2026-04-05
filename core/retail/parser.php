<?php
/**
 * KND Retail Module — Fast Path Parser
 *
 * ZERO llamadas a LLM. Parser determinista (regex/tokens).
 * Para comandos estructurados frecuentes en POS.
 *
 * retail_fast_parse(string $message): ?array
 * Retorna ['tool' => string, 'input' => array] o null si no hace match.
 * null = pasa al intent_mapper, luego a Iris si también falla.
 *
 * Patrones soportados (español, case-insensitive):
 *   "venta 2 coca cola"               → create_sale
 *   "2 cocas"                         → create_sale (qty implícita)
 *   "venta credito 1 leche maria"     → create_credit_sale
 *   "credito 1 leche a pedro"         → create_credit_sale
 *   "pago pedro 50"                   → register_credit_payment
 *   "pago cedula 123456 50.00"        → register_credit_payment
 *   "ventas hoy"                      → get_sales_today
 *   "ventas de hoy"                   → get_sales_today
 *   "stock azucar"                    → get_product
 *   "inventario azucar"               → get_product
 *   "productos bajos"                 → get_inventory_low
 *   "bajo stock" / "sin stock"        → get_inventory_low
 */

function retail_fast_parse(string $message): ?array
{
    $msg = trim(strtolower($message));
    $msg = preg_replace('/\s+/', ' ', $msg); // Normalizar espacios

    // ------------------------------------------------------------------
    // 1. VENTA A CRÉDITO — debe ir ANTES que create_sale
    //    Patrones: "venta credito 2 leche", "credito 1 leche a pedro"
    //              "vc 1 azucar pedro"
    // ------------------------------------------------------------------
    if (preg_match('/^(?:venta\s+cr[eé]dito|cr[eé]dito|vc)\s+(\d+)\s+(.+?)(?:\s+a\s+|\s+para\s+|\s+)([a-záéíóúñ][a-z\s]{1,40})?$/u', $msg, $m)) {
        $qty         = (int) $m[1];
        $productName = trim($m[2]);
        $customer    = isset($m[3]) ? trim($m[3]) : null;
        if ($qty >= 1 && strlen($productName) >= 2) {
            return [
                'tool'  => 'create_credit_sale',
                'input' => [
                    'items'            => [['product_name' => $productName, 'qty' => $qty]],
                    'customer_name'    => $customer,
                    '_fast_path'       => true,
                ],
            ];
        }
    }

    // ------------------------------------------------------------------
    // 2. VENTA CONTADO — "venta 2 coca cola", "venta coca cola", "2 cocas"
    //    Soporta qty explícita o implícita (default 1)
    // ------------------------------------------------------------------

    // "venta [qty] producto"
    if (preg_match('/^venta\s+(\d+)\s+(.{2,})$/', $msg, $m)) {
        return [
            'tool'  => 'create_sale',
            'input' => [
                'items'      => [['product_name' => trim($m[2]), 'qty' => (int) $m[1]]],
                '_fast_path' => true,
            ],
        ];
    }

    // "venta producto" (sin qty)
    if (preg_match('/^venta\s+(.{2,})$/', $msg, $m)) {
        return [
            'tool'  => 'create_sale',
            'input' => [
                'items'      => [['product_name' => trim($m[1]), 'qty' => 1]],
                '_fast_path' => true,
            ],
        ];
    }

    // "N producto" — qty numérica al inicio sin palabra "venta"
    // Excluir palabras que empiezan por número pero son otra cosa (ej: "2do", "1er")
    if (preg_match('/^(\d+)\s+([a-záéíóúñ][a-z0-9\s]{1,80})$/u', $msg, $m)) {
        $qty         = (int) $m[1];
        $productName = trim($m[2]);
        // Evitar falsos positivos: ignorar si parece un precio o código
        if ($qty >= 1 && $qty <= 9999 && !preg_match('/^\d+$/', $productName)) {
            return [
                'tool'  => 'create_sale',
                'input' => [
                    'items'      => [['product_name' => $productName, 'qty' => $qty]],
                    '_fast_path' => true,
                ],
            ];
        }
    }

    // ------------------------------------------------------------------
    // 3. PAGO DE CRÉDITO
    //    "pago pedro 50", "pago cedula 12345 30.50", "abono maria 100"
    // ------------------------------------------------------------------
    if (preg_match('/^(?:pago|abono|cobro)\s+(?:cedula|ci|doc|documento)?\s*([a-z0-9][a-z0-9\s]{1,50}?)\s+([\d]+(?:[\.,]\d{1,4})?)$/u', $msg, $m)) {
        $customerRef = trim($m[1]);
        $amount      = (float) str_replace(',', '.', $m[2]);
        if ($amount > 0) {
            // ¿Es document_id (solo números) o nombre?
            $isDoc = preg_match('/^\d{4,20}$/', $customerRef);
            return [
                'tool'  => 'register_credit_payment',
                'input' => [
                    $isDoc ? 'customer_document' : 'customer_name' => $customerRef,
                    'amount'     => $amount,
                    '_fast_path' => true,
                ],
            ];
        }
    }

    // ------------------------------------------------------------------
    // 4. VENTAS HOY
    //    "ventas hoy", "ventas de hoy", "resumen hoy", "reporte del dia"
    // ------------------------------------------------------------------
    if (preg_match('/^(?:ventas?\s+(?:de\s+)?hoy|resumen\s+hoy|reporte\s+del?\s+d[ií]a|ventas\s+diarias?)$/', $msg)) {
        return [
            'tool'  => 'get_sales_today',
            'input' => ['_fast_path' => true],
        ];
    }

    // ------------------------------------------------------------------
    // 5. CONSULTA DE STOCK / PRODUCTO
    //    "stock coca cola", "inventario azucar", "precio azucar"
    // ------------------------------------------------------------------
    if (preg_match('/^(?:stock|inventario|precio|existencia)\s+(.{2,})$/', $msg, $m)) {
        return [
            'tool'  => 'get_product',
            'input' => ['name' => trim($m[1]), '_fast_path' => true],
        ];
    }

    // ------------------------------------------------------------------
    // 6. PRODUCTOS BAJOS / SIN STOCK
    //    "productos bajos", "bajo stock", "sin stock", "alertas"
    // ------------------------------------------------------------------
    if (preg_match('/^(?:productos?\s+bajos?|bajo\s+stock|sin\s+stock|alertas?\s+(?:de\s+)?stock|inventario\s+bajo)$/', $msg)) {
        return [
            'tool'  => 'get_inventory_low',
            'input' => ['_fast_path' => true],
        ];
    }

    // No hay match en fast path → retornar null para escalar
    return null;
}
