<?php
/**
 * KND Settings - Key-value store (knd_settings table)
 * settings_get($key, $default = null)
 * settings_set($key, $value)
 */

function settings_get(PDO $pdo, string $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT `value` FROM knd_settings WHERE `key` = ? LIMIT 1");
        if (!$stmt || !$stmt->execute([$key])) return $default;
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['value'] : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function settings_set(PDO $pdo, string $key, string $value): bool {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO knd_settings (`key`, `value`, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()"
        );
        return $stmt && $stmt->execute([$key, $value]);
    } catch (\Throwable $e) {
        error_log('settings_set: ' . $e->getMessage());
        return false;
    }
}
