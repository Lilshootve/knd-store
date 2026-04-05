<?php
/**
 * Tool: get_customer_by_document
 * Busca un cliente por document_id dentro del negocio activo.
 */

function retail_get_customer_by_document(PDO $pdo, array $input): array
{
    $bizId      = retail_business_id();
    $documentId = trim($input['document_id'] ?? '');

    if (strlen($documentId) < 3) {
        return ['error' => 'document_id inválido.'];
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.name, c.document_id, c.created_at,
                COALESCE(cr.balance, 0) AS credit_balance
         FROM retail_customers c
         LEFT JOIN retail_credits cr ON cr.customer_id = c.id AND cr.business_id = c.business_id
         WHERE c.business_id = ? AND c.document_id = ?
         LIMIT 1'
    );
    $stmt->execute([$bizId, $documentId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        return ['found' => false, 'message' => "Cliente con documento '$documentId' no encontrado."];
    }

    return [
        'found'    => true,
        'customer' => $customer,
    ];
}
