<?php
/**
 * Retail module — extended NL patterns (runs before shared retail/parser.php).
 * Heuristics for POS-style phrases; returns null when no match.
 */

declare(strict_types=1);

/**
 * @return array{tool: string, input: array}|null
 */
function retail_module_nl_try(string $message): ?array
{
    $raw = trim($message);
    if ($raw === '') {
        return null;
    }

    $msg = mb_strtolower($raw, 'UTF-8');
    $msg = preg_replace('/\s+/u', ' ', $msg) ?? $msg;

    // ── stock producto <nombre> ─────────────────────────────────────────────
    if (preg_match('/^stock\s+producto\s+(.{2,})$/u', $msg, $m)) {
        return [
            'tool'  => 'get_product',
            'input' => ['name' => trim($m[1]), '_nl_v2' => true],
        ];
    }

    // ── venta <qty> <producto> <cedula> ─────────────────────────────────────
    if (preg_match('/^venta\s+(\d+)\s+(.+)\s+(\d{5,14})$/u', $msg, $m)) {
        $qty   = (int) $m[1];
        $prod  = trim($m[2]);
        $doc   = $m[3];
        if ($qty >= 1 && $qty <= 9999 && $prod !== '' && strlen($prod) >= 1) {
            return [
                'tool'  => 'create_sale',
                'input' => [
                    'items'              => [['product_name' => $prod, 'qty' => $qty]],
                    'customer_document'  => $doc,
                    '_nl_v2'             => true,
                ],
            ];
        }
    }

    // ── crédito multiproducto + documento al final ───────────────────────────
    if (preg_match('/^(?:credito|crédito|venta\s+cr[eé]dito)\s+(.+)\s+(\d{5,14})$/u', $msg, $m)) {
        $body = trim($m[1]);
        $doc  = $m[2];
        $pairs = retail_module_parse_qty_name_chain($body);
        if ($pairs !== null && $pairs !== []) {
            return [
                'tool'  => 'create_credit_sale',
                'input' => [
                    'items'             => $pairs,
                    'customer_document' => $doc,
                    '_nl_v2'            => true,
                ],
            ];
        }
    }

    // ── pago cliente <doc> <monto>$ | pago <doc> <monto> bs ────────────────
    if (preg_match('/^(?:pago|abono|cobro)\s+cliente\s+(\d{5,14})\s+([\d.,]+)\s*(?:\$|usd)?\s*$/u', $msg, $m)) {
        return [
            'tool'  => 'register_credit_payment',
            'input' => [
                'customer_document' => $m[1],
                'amount'            => (float) str_replace(',', '.', $m[2]),
                '_nl_v2'            => true,
            ],
        ];
    }

    if (preg_match('/^(?:pago|abono|cobro)\s+cliente\s+(\d{5,14})\s+([\d.,]+)\s*(?:bs|ves|bol[ií]vares?)?\s*$/u', $msg, $m)) {
        return [
            'tool'  => 'register_credit_payment',
            'input' => [
                'customer_document' => $m[1],
                'amount'            => (float) str_replace(',', '.', $m[2]),
                'currency'          => 'VES',
                '_nl_v2'            => true,
            ],
        ];
    }

    return null;
}

/**
 * Parse "1 arroz 2 cigarros" into [['product_name'=>'arroz','qty'=>1], ...].
 *
 * @return list<array{product_name: string, qty: int}>|null
 */
function retail_module_parse_qty_name_chain(string $text): ?array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '' || !preg_match('/\d/u', $text)) {
        return null;
    }

    $items   = [];
    $remaining = $text;

    while ($remaining !== '') {
        if (!preg_match('/^(\d+)\s+/u', $remaining, $qm)) {
            break;
        }
        $qty = (int) $qm[1];
        if ($qty < 1 || $qty > 99999) {
            return null;
        }
        $remaining = trim(mb_substr($remaining, mb_strlen($qm[0], 'UTF-8'), null, 'UTF-8'));
        if ($remaining === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s+/u', $remaining)) {
            return null;
        }

        if (preg_match('/^(.+?)(?=\s+\d+\s|$)/u', $remaining, $nm)) {
            $name = trim($nm[1]);
            if ($name === '') {
                return null;
            }
            $items[] = ['product_name' => $name, 'qty' => $qty];
            $remaining = trim(mb_substr($remaining, mb_strlen($nm[0], 'UTF-8'), null, 'UTF-8'));
        } else {
            $items[] = ['product_name' => $remaining, 'qty' => $qty];
            break;
        }
    }

    return $items === [] ? null : $items;
}
