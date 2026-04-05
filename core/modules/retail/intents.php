<?php
/**
 * Retail module — NL → tool resolution (fast path, then intent mapper).
 * Used by api/agent/execute.php when business_type is retail.
 */

declare(strict_types=1);

defined('KND_ROOT') or define('KND_ROOT', dirname(__DIR__, 3));

require_once __DIR__ . '/nl_patterns.php';
require_once KND_ROOT . '/core/retail/parser.php';
require_once KND_ROOT . '/core/retail/intent_mapper.php';

/**
 * @return array{tool: string, input: array}|null
 */
function retail_module_resolve_message(string $message): ?array
{
    $v2 = retail_module_nl_try($message);
    if ($v2 !== null) {
        return $v2;
    }

    $parsed = retail_fast_parse($message);
    if ($parsed !== null) {
        return [
            'tool'  => $parsed['tool'],
            'input' => $parsed['input'] ?? [],
        ];
    }

    $mapped = retail_intent_map($message);
    if ($mapped !== null) {
        return [
            'tool'  => $mapped['tool'],
            'input' => $mapped['input'] ?? [],
        ];
    }

    return null;
}
