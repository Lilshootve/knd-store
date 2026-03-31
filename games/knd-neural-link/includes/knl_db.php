<?php
/**
 * KND Neural Link — optional column detection for knd_drop_log (legacy vs new schema).
 */

declare(strict_types=1);

function knl_drop_log_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    try {
        $col = preg_replace('/[^a-z0-9_]/i', '', $column);
        $stmt = $pdo->query('SHOW COLUMNS FROM `knd_drop_log` LIKE ' . $pdo->quote($col));
        $cache[$column] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$column] = false;
    }

    return $cache[$column];
}
