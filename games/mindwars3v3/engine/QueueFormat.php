<?php
declare(strict_types=1);

/**
 * Detección de formato 3v3 por cola (no confundir con squad simultáneo).
 */
function knd_mw3v3_is_queue_format(array $state): bool
{
    $meta = $state['meta'] ?? [];
    if (!is_array($meta)) {
        return false;
    }
    return isset($meta['format']) && (string) $meta['format'] === '3v3';
}
