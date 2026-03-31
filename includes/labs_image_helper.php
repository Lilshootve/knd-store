<?php
/**
 * KND Labs - Image validation and conversion helpers
 */

/**
 * Normalize image to PNG. Supports PNG, JPG, WebP.
 * @param string $sourcePath Path to source image
 * @param string $destPath Path for output PNG
 * @return bool true on success
 */
function labs_image_to_png(string $sourcePath, string $destPath): bool {
    $img = @getimagesize($sourcePath);
    if (!$img || !isset($img[2])) return false;

    $src = null;
    switch ($img[2]) {
        case IMAGETYPE_PNG:
            $src = @imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_JPEG:
            $src = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($sourcePath);
            }
            break;
        default:
            return false;
    }
    if (!$src) return false;

    $ok = @imagepng($src, $destPath);
    @imagedestroy($src);
    return $ok;
}

/**
 * Validate image: max size (bytes), max dimension, allowed types.
 * @return array ['ok' => bool, 'error' => string|null]
 */
function labs_validate_image(string $path, int $maxBytes = 5242880, int $maxDim = 2048): array {
    if (!is_file($path) || !is_readable($path)) {
        return ['ok' => false, 'error' => 'File not readable'];
    }
    $size = filesize($path);
    if ($size === false || $size > $maxBytes) {
        return ['ok' => false, 'error' => 'Image max ' . round($maxBytes / 1024 / 1024, 1) . 'MB'];
    }
    $img = @getimagesize($path);
    if (!$img || !in_array($img[2] ?? 0, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
        return ['ok' => false, 'error' => 'Image must be PNG, JPG or WebP'];
    }
    $w = $img[0] ?? 0;
    $h = $img[1] ?? 0;
    if ($w > $maxDim || $h > $maxDim) {
        return ['ok' => false, 'error' => 'Image max ' . $maxDim . 'px per side'];
    }
    return ['ok' => true, 'error' => null];
}

/**
 * Marks stale processing jobs (worker crashed / never reported) as failed.
 * Jobs in 'processing' for longer than maxMinutes are considered abandoned.
 * Use before counting active jobs to avoid blocking users with orphaned jobs.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param int $maxMinutes Default 30
 * @return int Number of jobs marked as failed
 */
function labs_cleanup_stale_processing_jobs(\PDO $pdo, int $userId, int $maxMinutes = 30): int {
    $stmt = $pdo->prepare(
        "UPDATE knd_labs_jobs SET
           status = 'failed',
           error_message = COALESCE(error_message, 'Job abandoned (worker timeout)'),
           finished_at = NOW(),
           locked_at = NULL,
           locked_by = NULL,
           updated_at = NOW()
         WHERE user_id = ? AND status = 'processing'
         AND (COALESCE(started_at, locked_at, updated_at) < DATE_SUB(NOW(), INTERVAL ? MINUTE))"
    );
    if (!$stmt || !$stmt->execute([$userId, $maxMinutes])) {
        return 0;
    }
    $n = $stmt->rowCount();
    if ($n > 0) {
        error_log("labs_cleanup_stale_processing_jobs: user_id={$userId} marked {$n} stale job(s) as failed");
    }
    return $n;
}

/**
 * Returns count of active (queued + processing) jobs for user.
 * Call labs_cleanup_stale_processing_jobs first to avoid counting orphaned jobs.
 */
function labs_count_active_jobs(\PDO $pdo, int $userId): int {
    labs_cleanup_stale_processing_jobs($pdo, $userId);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM knd_labs_jobs WHERE user_id = ? AND status IN ('queued','processing')");
    if (!$stmt || !$stmt->execute([$userId])) {
        return 0;
    }
    return (int) $stmt->fetchColumn();
}
