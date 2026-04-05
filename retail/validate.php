<?php
/**
 * KND Retail Module — Validation Layer
 *
 * validate_retail_tool_call(string $tool, array $input): array
 * Retorna: ['safe' => bool, 'issues' => [], 'warnings' => []]
 *
 * Reglas por tool. Nunca confiar en input del cliente.
 */

function validate_retail_tool_call(string $tool, array $input): array
{
    $issues   = [];
    $warnings = [];

    switch ($tool) {

        case 'create_sale':
            if (empty($input['items']) || !is_array($input['items'])) {
                $issues[] = 'items[] es requerido y debe ser un array.';
            } else {
                foreach ($input['items'] as $i => $item) {
                    $hasPid  = !empty($item['product_id']) && is_numeric($item['product_id']);
                    $hasName = !empty($item['product_name']) && is_string($item['product_name'])
                        && trim($item['product_name']) !== '';
                    if (!$hasPid && !$hasName) {
                        $issues[] = "items[$i]: se requiere product_id o product_name.";
                    }
                    if (!isset($item['qty']) || (int) $item['qty'] < 1) {
                        $issues[] = "items[$i].qty debe ser >= 1.";
                    }
                }
            }
            if (empty($input['currency'])) {
                $warnings[] = 'currency no especificado — se usará base_currency del negocio.';
            }
            if (!empty($input['customer_document']) && empty($input['customer_id'])) {
                $warnings[] = 'customer_document: el cliente debe existir en el negocio o la venta seguirá sin customer_id.';
            }
            break;

        case 'create_credit_sale':
            if (empty($input['items']) || !is_array($input['items'])) {
                $issues[] = 'items[] es requerido para venta a crédito.';
            } else {
                foreach ($input['items'] as $i => $item) {
                    $hasPid  = !empty($item['product_id']) && is_numeric($item['product_id']);
                    $hasName = !empty($item['product_name']) && is_string($item['product_name'])
                        && trim($item['product_name']) !== '';
                    if (!$hasPid && !$hasName) {
                        $issues[] = "items[$i]: se requiere product_id o product_name.";
                    }
                    if (!isset($item['qty']) || (int) $item['qty'] < 1) {
                        $issues[] = "items[$i].qty debe ser >= 1.";
                    }
                }
            }
            if (empty($input['customer_id']) && empty($input['customer_document']) && empty($input['customer_name'])) {
                $issues[] = 'Venta a crédito requiere customer_id, customer_document o customer_name.';
            }
            break;

        case 'register_credit_payment':
            if (empty($input['customer_id']) && empty($input['customer_document'])) {
                $issues[] = 'Se requiere customer_id o customer_document.';
            }
            if (!isset($input['amount']) || !is_numeric($input['amount']) || (float) $input['amount'] <= 0) {
                $issues[] = 'amount debe ser un número positivo.';
            }
            break;

        case 'get_product':
            if (empty($input['product_id']) && empty($input['name']) && empty($input['sku'])) {
                $issues[] = 'Se requiere product_id, name o sku.';
            }
            break;

        case 'search_product':
            if (empty($input['query']) || strlen(trim($input['query'])) < 2) {
                $issues[] = 'query debe tener al menos 2 caracteres.';
            }
            break;

        case 'get_inventory_low':
            // Sin parámetros requeridos
            break;

        case 'get_sales_today':
            // Sin parámetros requeridos; date opcional
            break;

        case 'adjust_stock':
            if (empty($input['product_id']) || !is_numeric($input['product_id'])) {
                $issues[] = 'product_id inválido.';
            }
            if (!isset($input['delta']) || !is_numeric($input['delta']) || (int) $input['delta'] === 0) {
                $issues[] = 'delta debe ser un entero distinto de cero (positivo = agregar, negativo = restar).';
            }
            if (!isset($input['reason']) || strlen(trim($input['reason'])) < 3) {
                $issues[] = 'reason es requerido para ajuste de stock (mínimo 3 caracteres).';
            }
            if (isset($input['delta']) && is_numeric($input['delta']) && abs((int) $input['delta']) > 10000) {
                $warnings[] = 'Delta de stock inusualmente grande (>10000). Confirmar intencional.';
            }
            break;

        case 'get_customer_by_document':
            if (empty($input['document_id']) || strlen(trim($input['document_id'])) < 3) {
                $issues[] = 'document_id inválido (mínimo 3 caracteres).';
            }
            break;

        case 'create_customer_if_not_exists':
            if (empty($input['name']) || strlen(trim($input['name'])) < 2) {
                $issues[] = 'name del cliente es requerido (mínimo 2 caracteres).';
            }
            // document_id opcional pero recomendado
            if (empty($input['document_id'])) {
                $warnings[] = 'document_id no proporcionado — el cliente no tendrá identificación única.';
            }
            break;

        case 'update_exchange_rate':
            if (empty($input['currency']) || !preg_match('/^[A-Z]{2,10}$/', strtoupper($input['currency']))) {
                $issues[] = 'currency inválido (ej: VES, EUR, COP).';
            }
            if (!isset($input['rate']) || !is_numeric($input['rate']) || (float) $input['rate'] <= 0) {
                $issues[] = 'rate debe ser un número positivo.';
            }
            if (isset($input['rate']) && is_numeric($input['rate'])) {
                $rate = (float) $input['rate'];
                if ($rate > 1_000_000) {
                    $warnings[] = "Tasa muy alta ($rate). Verificar que es rate_to_base correcto.";
                }
                if ($rate < 0.000001) {
                    $warnings[] = "Tasa extremadamente pequeña ($rate). Verificar unidades.";
                }
            }
            break;

        case 'get_top_products':
            if (isset($input['limit']) && (!is_numeric($input['limit']) || (int) $input['limit'] < 1 || (int) $input['limit'] > 100)) {
                $issues[] = 'limit debe estar entre 1 y 100.';
            }
            foreach (['date_from', 'date_to'] as $df) {
                if (!empty($input[$df]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input[$df])) {
                    $issues[] = "$df debe ser YYYY-MM-DD.";
                }
            }
            break;

        case 'get_sales_summary':
            foreach (['date_from', 'date_to'] as $df) {
                if (!empty($input[$df]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input[$df])) {
                    $issues[] = "$df debe ser YYYY-MM-DD.";
                }
            }
            break;

        case 'list_customer_balances':
            if (isset($input['limit']) && (!is_numeric($input['limit']) || (int) $input['limit'] < 1 || (int) $input['limit'] > 500)) {
                $issues[] = 'limit debe estar entre 1 y 500.';
            }
            if (isset($input['min_balance']) && (!is_numeric($input['min_balance']) || (float) $input['min_balance'] < 0)) {
                $issues[] = 'min_balance debe ser >= 0.';
            }
            break;

        default:
            $issues[] = "Tool '$tool' no reconocido en el módulo retail.";
            break;
    }

    return [
        'safe'     => empty($issues),
        'tool'     => $tool,
        'issues'   => $issues,
        'warnings' => $warnings,
    ];
}
