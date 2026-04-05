<?php
/**
 * Atomic per-business per-year invoice sequence (concurrency-safe).
 */
declare(strict_types=1);

function _retail_next_invoice_number(PDO $pdo, int $bizId): string
{
    $year = (int) date('Y');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS retail_invoice_counters (
            business_id INT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NOT NULL,
            seq INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (business_id, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->prepare(
        'INSERT IGNORE INTO retail_invoice_counters (business_id, year, seq) VALUES (?, ?, 0)'
    )->execute([$bizId, $year]);

    $pdo->prepare(
        'UPDATE retail_invoice_counters SET seq = LAST_INSERT_ID(seq + 1) WHERE business_id = ? AND year = ?'
    )->execute([$bizId, $year]);

    $seq = (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    if ($seq < 1) {
        $seq = 1;
    }

    $prefix = "INV-{$bizId}-{$year}-";
    return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
}
