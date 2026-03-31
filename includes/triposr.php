<?php
/**
 * KND Store - InstantMesh 3D model generation helpers
 * Manages jobs for image-to-3D conversion via external GPU server.
 */

if (!function_exists('triposr_quality_cost')) {
    function triposr_quality_cost(string $quality): int {
        $costs = ['fast' => 8, 'balanced' => 15, 'high' => 30];
        return $costs[$quality] ?? 15;
    }
}

if (!function_exists('triposr_count_active_jobs')) {
    function triposr_count_active_jobs(PDO $pdo, int $userId): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM triposr_jobs WHERE user_id = ? AND status IN ('pending','processing')");
        if (!$stmt || !$stmt->execute([$userId])) return 0;
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('triposr_count_jobs_last_hour')) {
    function triposr_count_jobs_last_hour(PDO $pdo, int $userId): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM triposr_jobs WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR");
        if (!$stmt || !$stmt->execute([$userId])) return 0;
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('triposr_count_jobs_today')) {
    function triposr_count_jobs_today(PDO $pdo, int $userId): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM triposr_jobs WHERE user_id = ? AND created_at >= CURDATE()");
        if (!$stmt || !$stmt->execute([$userId])) return 0;
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('triposr_refund_points')) {
    function triposr_refund_points(PDO $pdo, int $jobId, int $userId, int $amount): bool {
        if ($amount <= 0) return false;
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
             VALUES (?, '3d_generation_refund', ?, 'reversal', 'available', ?, ?)"
        );
        return $stmt && $stmt->execute([$userId, $jobId, $amount, $now]);
    }
}

if (!function_exists('create_triposr_job')) {
    /**
     * @param string|null $jobUuid Optional UUID; if null, one is generated.
     * @param string $quality fast|balanced|high
     */
    function create_triposr_job(PDO $pdo, int $userId, string $inputPath, ?string $jobUuid = null, string $quality = 'balanced'): ?array {
        if ($jobUuid === null) {
            $jobUuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff), random_int(0, 0x4fff) | 0x4000,
            random_int(0, 0xffff) | 0x8000, random_int(0, 0xffff),
            random_int(0, 0xffff), random_int(0, 0xffff)
        );
        }

        $sql = 'INSERT INTO triposr_jobs (user_id, job_uuid, input_path, quality, status) VALUES (?, ?, ?, ?, ?)';
        $stmt = $pdo->prepare($sql);
        $ok = $stmt && $stmt->execute([$userId, $jobUuid, $inputPath, $quality, 'pending']);
        if (!$ok) {
            $err = $pdo->errorInfo()[1] ?? 0;
            if ($err === 1054) {
                $sql = 'INSERT INTO triposr_jobs (user_id, job_uuid, input_path, status) VALUES (?, ?, ?, ?)';
                $stmt = $pdo->prepare($sql);
                $ok = $stmt && $stmt->execute([$userId, $jobUuid, $inputPath, 'pending']);
            }
            if (!$ok) return null;
        }

        $id = (int) $pdo->lastInsertId();
        return ['id' => $id, 'job_uuid' => $jobUuid, 'input_path' => $inputPath, 'quality' => $quality, 'status' => 'pending'];
    }
}

if (!function_exists('get_triposr_job')) {
    function get_triposr_job(PDO $pdo, string $jobId): ?array {
        $stmt = $pdo->prepare('SELECT id, user_id, job_uuid, input_path, output_path, quality, status, error_message, created_at, completed_at FROM triposr_jobs WHERE job_uuid = ? LIMIT 1');
        if (!$stmt || !$stmt->execute([$jobId])) {
            return null;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !isset($row['quality'])) {
            $row['quality'] = 'balanced';
        }
        return $row ?: null;
    }
}

if (!function_exists('get_triposr_job_by_uuid')) {
    function get_triposr_job_by_uuid(PDO $pdo, string $jobUuid): ?array {
        return get_triposr_job($pdo, $jobUuid);
    }
}

if (!function_exists('update_triposr_job')) {
    function update_triposr_job(PDO $pdo, string $jobUuid, array $updates): bool {
        $allowed = ['status', 'output_path', 'error_message', 'completed_at'];
        $set = [];
        $params = [];
        foreach ($updates as $key => $val) {
            if (!in_array($key, $allowed, true)) continue;
            $set[] = "`$key` = ?";
            $params[] = $val;
        }
        if (empty($set)) return false;
        $params[] = $jobUuid;
        $sql = 'UPDATE triposr_jobs SET ' . implode(', ', $set) . ' WHERE job_uuid = ?';
        $stmt = $pdo->prepare($sql);
        return $stmt && $stmt->execute($params);
    }
}
