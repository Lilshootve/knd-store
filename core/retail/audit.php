<?php
/**
 * KND Retail Module — Audit Logging
 *
 * Escribe en retail_audit_logs con snapshot before/after JSON.
 * CRÍTICO: llamar antes del commit en operaciones de escritura.
 * Los logs son append-only — nunca UPDATE ni DELETE en esta tabla.
 */

/**
 * Registra una acción en el audit log del negocio.
 *
 * @param PDO         $pdo
 * @param string      $action       Nombre del tool/acción (create_sale, adjust_stock, etc.)
 * @param string      $entityType   Tipo de entidad (product, sale, credit, rate, customer)
 * @param int|null    $entityId     ID de la entidad afectada
 * @param array|null  $before       Estado anterior (null para creaciones)
 * @param array|null  $after        Estado posterior (null para lecturas)
 */
function retail_audit_log(
    PDO     $pdo,
    string  $action,
    string  $entityType,
    ?int    $entityId,
    ?array  $before,
    ?array  $after
): void {
    try {
        $businessId = retail_business_id();
        $userId     = retail_user_id() ?: null;
        $ip         = _retail_get_ip();

        $stmt = $pdo->prepare(
            'INSERT INTO retail_audit_logs
             (business_id, user_id, action, entity_type, entity_id, before_json, after_json, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $businessId,
            $userId,
            $action,
            $entityType,
            $entityId,
            $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            $after  !== null ? json_encode($after,  JSON_UNESCAPED_UNICODE) : null,
            $ip,
        ]);
    } catch (Throwable $e) {
        // Audit log nunca debe romper el flujo principal — solo loguear el error
        error_log('RETAIL_AUDIT_FAIL: ' . $e->getMessage());
    }
}

/**
 * Helper para obtener IP real considerando proxies confiables.
 */
function _retail_get_ip(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}
