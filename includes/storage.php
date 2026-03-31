<?php
/**
 * KND Store — Storage helpers
 * All JSON reads use LOCK_SH, all writes use LOCK_EX on the same file handle.
 * No temp files, no rename — prevents partial-read corruption.
 */

function storage_path(string $relative = ''): string {
    static $base = null;
    if ($base === null) {
        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
    }
    if ($relative === '') return $base;
    return $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
}

function ensure_storage_ready(): void {
    $dirs = [storage_path(), storage_path('logs')];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }
    $jsonFiles = ['orders.json', 'bank_transfer_requests.json', 'other_payment_requests.json'];
    foreach ($jsonFiles as $f) {
        $path = storage_path($f);
        if (!file_exists($path)) {
            $fh = @fopen($path, 'c+');
            if ($fh) {
                if (flock($fh, LOCK_EX)) {
                    fwrite($fh, '[]');
                    fflush($fh);
                    flock($fh, LOCK_UN);
                }
                fclose($fh);
            }
            @chmod($path, 0640);
        }
    }
}

/**
 * Read a JSON array file with shared lock to prevent reading mid-write.
 */
function read_json_array(string $path): array {
    if (!file_exists($path)) return [];
    clearstatcache(true, $path);

    $fh = @fopen($path, 'r');
    if (!$fh) {
        storage_log('read_json_array: cannot open', ['path' => $path]);
        return [];
    }
    if (!flock($fh, LOCK_SH)) {
        fclose($fh);
        storage_log('read_json_array: cannot lock_sh', ['path' => $path]);
        return [];
    }

    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }

    storage_log('read_json_array: invalid JSON (read-only, not wiping)', [
        'path' => $path,
        'size' => strlen($raw),
        'json_error' => json_last_error_msg(),
        'first_64' => substr($raw, 0, 64),
    ]);
    return [];
}

/**
 * Overwrite a JSON file with a full array. Uses exclusive lock on the same handle.
 * Returns true on success.
 */
function write_json_array(string $path, array $data): bool {
    $fh = @fopen($path, 'c+');
    if (!$fh) {
        storage_log('write_json_array: cannot open', ['path' => $path]);
        return false;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        storage_log('write_json_array: cannot lock_ex', ['path' => $path]);
        return false;
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        flock($fh, LOCK_UN);
        fclose($fh);
        storage_log('write_json_array: json_encode failed', ['path' => $path, 'error' => json_last_error_msg()]);
        return false;
    }

    rewind($fh);
    ftruncate($fh, 0);
    $written = fwrite($fh, $json);
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    if ($written === false) {
        storage_log('write_json_array: fwrite failed', ['path' => $path]);
        return false;
    }

    @chmod($path, 0640);
    return true;
}

/**
 * Append a record to a JSON array file.
 * Opens once, locks exclusive, reads, appends, writes back, unlocks.
 */
function append_json_record(string $path, array $record): bool {
    ensure_storage_ready();

    $fh = @fopen($path, 'c+');
    if (!$fh) {
        storage_log('append_json_record: cannot open', ['path' => $path]);
        return false;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        storage_log('append_json_record: cannot lock_ex', ['path' => $path]);
        return false;
    }

    $raw = stream_get_contents($fh);
    $trimmed = ($raw !== false) ? trim($raw) : '';

    if ($trimmed === '') {
        $existing = [];
    } else {
        $existing = json_decode($trimmed, true);
        if (!is_array($existing)) {
            rewind($fh);
            $raw2 = stream_get_contents($fh);
            $existing = json_decode(trim($raw2 ?: ''), true);
            if (!is_array($existing)) {
                storage_log('append_json_record: corrupt JSON, backing up', [
                    'path' => $path,
                    'size' => strlen($raw),
                    'json_error' => json_last_error_msg(),
                ]);
                $backup = $path . '.bak.' . date('Ymd_His');
                $backupOk = @file_put_contents($backup, $raw);
                if ($backupOk !== false) {
                    $existing = [];
                } else {
                    flock($fh, LOCK_UN);
                    fclose($fh);
                    storage_log('append_json_record: backup failed, aborting to protect data', ['path' => $path]);
                    return false;
                }
            }
        }
    }

    $existing[] = $record;
    $json = json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    rewind($fh);
    ftruncate($fh, 0);
    $written = fwrite($fh, $json);
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    if ($written === false) {
        storage_log('append_json_record: fwrite failed', ['path' => $path]);
        return false;
    }

    @chmod($path, 0640);
    return true;
}

/**
 * Append a JSONL line to storage/logs/payments.log.
 */
function storage_log(string $message, array $context = []): void {
    $logPath = storage_path('logs/payments.log');
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    $entry = [
        'ts' => date('c'),
        'msg' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '-',
        'rid' => substr(bin2hex(random_bytes(4)), 0, 8),
    ];
    $entry = array_merge($entry, $context);
    @file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}
