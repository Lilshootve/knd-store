<?php
/**
 * iris_user_memory — DDL + migración de columnas legacy.
 *
 * El código Iris en PHP usa fact_key / fact_value / user_id.
 * Algunas instalaciones antiguas o migraciones usaron `key` y `value` (nombre reservado en SQL entre backticks).
 */
declare(strict_types=1);

/**
 * Crea la tabla si no existe y renombra `key`→fact_key, `value`→fact_value si hace falta.
 *
 * @return bool false si falla DDL/migración (logueado)
 */
function knd_iris_ensure_user_memory_table(PDO $pdo): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS iris_user_memory (
                user_id    INT UNSIGNED  NOT NULL,
                fact_key   VARCHAR(100)  NOT NULL,
                fact_value VARCHAR(1000) NOT NULL,
                updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, fact_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($db) || $db === '') {
            $done = true;
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([$db, 'iris_user_memory']);
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($cols) || $cols === []) {
            $done = true;
            return true;
        }

        $have = [];
        foreach ($cols as $c) {
            $have[(string) $c] = true;
        }

        if (isset($have['fact_key'], $have['fact_value'])) {
            $done = true;
            return true;
        }

        if (isset($have['key']) && !isset($have['fact_key'])) {
            $pdo->exec('ALTER TABLE iris_user_memory CHANGE COLUMN `key` fact_key VARCHAR(100) NOT NULL');
            $have['fact_key'] = true;
            unset($have['key']);
        }

        if (isset($have['value']) && !isset($have['fact_value'])) {
            $pdo->exec('ALTER TABLE iris_user_memory CHANGE COLUMN `value` fact_value VARCHAR(1000) NOT NULL');
        }

        $done = true;
        return true;
    } catch (Throwable $e) {
        error_log('[iris_user_memory schema] ' . $e->getMessage());
        return false;
    }
}
