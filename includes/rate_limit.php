<?php
// KND Store - DB-backed rate limiter (Death Roll 1v1)

/**
 * Check and increment rate limit.
 * @param PDO    $pdo        Database connection
 * @param string $key        Unique key (e.g. "create_room:42")
 * @param int    $maxHits    Max allowed hits in window
 * @param int    $windowSecs Window duration in seconds
 * @return bool  true if allowed, false if rate-limited
 */
function rate_limit_check(PDO $pdo, string $key, int $maxHits, int $windowSecs): bool {
    $keyHash = hash('sha256', $key);
    $now = gmdate('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'SELECT id, hits, window_start FROM app_rate_limits WHERE key_hash = ? LIMIT 1'
    );
    $stmt->execute([$keyHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $stmt = $pdo->prepare(
            'INSERT INTO app_rate_limits (key_hash, hits, window_start, created_at, updated_at)
             VALUES (?, 1, ?, ?, ?)'
        );
        $stmt->execute([$keyHash, $now, $now, $now]);
        return true;
    }

    $windowStart = strtotime($row['window_start'] . ' UTC');
    $nowTs = strtotime($now . ' UTC');

    if (($nowTs - $windowStart) >= $windowSecs) {
        $stmt = $pdo->prepare(
            'UPDATE app_rate_limits SET hits = 1, window_start = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$now, $now, $row['id']]);
        return true;
    }

    if ((int)$row['hits'] >= $maxHits) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE app_rate_limits SET hits = hits + 1, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([$now, $row['id']]);
    return true;
}

/**
 * Get current rate limit status without incrementing.
 * @return array{used: int, remaining: int, resets_at: string|null, max: int}
 */
function rate_limit_status(PDO $pdo, string $key, int $maxHits, int $windowSecs): array {
    $keyHash = hash('sha256', $key);
    $now = gmdate('Y-m-d H:i:s');
    $nowTs = strtotime($now . ' UTC');

    $stmt = $pdo->prepare(
        'SELECT hits, window_start FROM app_rate_limits WHERE key_hash = ? LIMIT 1'
    );
    $stmt->execute([$keyHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'used' => 0,
            'remaining' => $maxHits,
            'resets_at' => null,
            'max' => $maxHits,
        ];
    }

    $windowStart = strtotime($row['window_start'] . ' UTC');
    $elapsed = $nowTs - $windowStart;

    if ($elapsed >= $windowSecs) {
        return [
            'used' => 0,
            'remaining' => $maxHits,
            'resets_at' => null,
            'max' => $maxHits,
        ];
    }

    $used = (int)$row['hits'];
    $remaining = max(0, $maxHits - $used);
    $resetsAt = gmdate('Y-m-d H:i:s', $windowStart + $windowSecs);

    return [
        'used' => $used,
        'remaining' => $remaining,
        'resets_at' => $resetsAt,
        'max' => $maxHits,
    ];
}

function rate_limit_guard(PDO $pdo, string $key, int $maxHits, int $windowSecs): void {
    if (!rate_limit_check($pdo, $key, $maxHits, $windowSecs)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => ['code' => 'RATE_LIMITED', 'message' => 'Too many requests. Please wait.']
        ]);
        exit;
    }
}
