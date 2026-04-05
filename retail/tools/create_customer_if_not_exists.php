<?php
/**
 * Tool: create_customer_if_not_exists
 * Busca cliente por document_id; si no existe, lo crea.
 * Retorna el cliente (nuevo o existente) y si fue creado.
 */

function retail_create_customer_if_not_exists(PDO $pdo, array $input): array
{
    $bizId      = retail_business_id();
    $name       = trim($input['name'] ?? '');
    $documentId = isset($input['document_id']) ? trim($input['document_id']) : null;

    if (strlen($name) < 2) {
        return ['error' => 'name del cliente requerido (mínimo 2 caracteres).'];
    }

    // Si hay document_id, buscar primero
    if ($documentId && strlen($documentId) >= 3) {
        $stmt = $pdo->prepare(
            'SELECT id, name, document_id, created_at FROM retail_customers
             WHERE business_id = ? AND document_id = ? LIMIT 1'
        );
        $stmt->execute([$bizId, $documentId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return ['created' => false, 'customer' => $existing];
        }
    }

    // Crear nuevo cliente
    $stmt = $pdo->prepare(
        'INSERT INTO retail_customers (business_id, name, document_id) VALUES (?, ?, ?)'
    );
    $stmt->execute([$bizId, $name, $documentId ?: null]);
    $customerId = (int) $pdo->lastInsertId();

    $newCustomer = [
        'id'          => $customerId,
        'name'        => $name,
        'document_id' => $documentId,
        'created_at'  => date('Y-m-d H:i:s'),
    ];

    retail_audit_log($pdo, 'create_customer', 'customer', $customerId, null, $newCustomer);

    return ['created' => true, 'customer' => $newCustomer];
}
