<?php
/**
 * Tool: update_exchange_rate
 * SOLO ADMIN. Requiere confirmación previa.
 * Inserta nueva tasa (append-only — la vigente es siempre la más reciente).
 */

function retail_update_exchange_rate(PDO $pdo, array $input): array
{
    if (!retail_is_admin()) {
        return ['error' => 'INSUFFICIENT_ROLE: admin role required.'];
    }

    $bizId    = retail_business_id();
    $currency = strtoupper(trim($input['currency'] ?? ''));
    $rate     = (float) ($input['rate'] ?? 0);

    if (!preg_match('/^[A-Z]{2,10}$/', $currency)) {
        return ['error' => 'currency inválido.'];
    }
    if ($rate <= 0) {
        return ['error' => 'rate debe ser > 0.'];
    }

    // Obtener tasa anterior para audit
    $prevStmt = $pdo->prepare(
        'SELECT rate_to_base, created_at FROM retail_exchange_rates
         WHERE business_id = ? AND currency = ?
         ORDER BY created_at DESC LIMIT 1'
    );
    $prevStmt->execute([$bizId, $currency]);
    $previous = $prevStmt->fetch(PDO::FETCH_ASSOC);

    // Insertar nueva tasa (append-only)
    $insertStmt = $pdo->prepare(
        'INSERT INTO retail_exchange_rates (business_id, currency, rate_to_base) VALUES (?, ?, ?)'
    );
    $insertStmt->execute([$bizId, $currency, $rate]);
    $newId = (int) $pdo->lastInsertId();

    $newRate = [
        'id'           => $newId,
        'business_id'  => $bizId,
        'currency'     => $currency,
        'rate_to_base' => $rate,
        'created_at'   => date('Y-m-d H:i:s'),
    ];

    retail_audit_log($pdo, 'update_exchange_rate', 'rate', $newId, $previous ?: null, $newRate);

    return [
        'success'  => true,
        'updated'  => true,
        'currency' => $currency,
        'rate'     => $rate,
        'previous' => $previous ? (float) $previous['rate_to_base'] : null,
        'message'  => "Tasa $currency actualizada a $rate.",
    ];
}
