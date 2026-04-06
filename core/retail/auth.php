<?php
/**
 * KND Retail Module — Auth & Business Resolution
 *
 * REGLA CRÍTICA: business_id NUNCA viene del cliente.
 * Siempre se resuelve server-side desde $_SESSION['dr_user_id']
 * consultando la tabla business_users.
 *
 * Inyecta en $GLOBALS:
 *   retail_business_id  — INT
 *   retail_role         — 'admin' | 'cashier'
 *   retail_user_id      — INT (= dr_user_id)
 *   retail_business     — array (registro completo de businesses)
 */

// --------------------------------------------------------------------------
// Resolución de negocio (llamar una vez por request)
// --------------------------------------------------------------------------

/**
 * Resuelve y cachea el contexto de negocio del usuario en sesión.
 * Termina con json_error si el usuario no pertenece a ningún negocio.
 */
function retail_require_business(?PDO $pdo = null): void
{
    if (isset($GLOBALS['retail_business_id'])) {
        return; // Ya resuelto en este request
    }

    // 1. Verificar sesión activa
    $userId = current_user_id();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'error' => 'AUTH_REQUIRED', 'message' => 'Sesión requerida.']);
        exit;
    }

    // 2. Conexión a DB
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'DB_ERROR', 'message' => 'Sin conexión a base de datos.']);
        exit;
    }

    // 3. Lookup en business_users → businesses
    $stmt = $pdo->prepare(
        'SELECT bu.business_id, bu.role, b.name, b.base_currency, b.active, b.settings_json
         FROM business_users bu
         INNER JOIN businesses b ON b.id = bu.business_id
         WHERE bu.user_id = ? AND b.active = 1
         ORDER BY bu.id ASC
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'NO_BUSINESS', 'message' => 'Usuario no asignado a ningún negocio activo.']);
        exit;
    }

    // 4. Inyectar en globals (accesibles en todo el request)
    $GLOBALS['retail_business_id'] = (int) $row['business_id'];
    $GLOBALS['retail_role']        = $row['role'];
    $GLOBALS['retail_user_id']     = (int) $userId;
    $GLOBALS['retail_business']    = [
        'id'            => (int) $row['business_id'],
        'name'          => $row['name'],
        'base_currency' => $row['base_currency'],
        'active'        => (bool) $row['active'],
        'settings'      => $row['settings_json'] ? json_decode($row['settings_json'], true) : [],
    ];
}

// --------------------------------------------------------------------------
// Helpers de acceso
// --------------------------------------------------------------------------

function retail_business_id(): int
{
    return (int) ($GLOBALS['retail_business_id'] ?? 0);
}

function retail_role(): string
{
    return (string) ($GLOBALS['retail_role'] ?? '');
}

function retail_user_id(): int
{
    return (int) ($GLOBALS['retail_user_id'] ?? 0);
}

function retail_business(): array
{
    return $GLOBALS['retail_business'] ?? [];
}

function retail_is_admin(): bool
{
    return retail_role() === 'admin';
}

/**
 * Bloquea si el usuario no es admin.
 * Usar en tools que requieren rol elevado (adjust_stock, update_exchange_rate, etc.)
 */
function retail_require_admin(): void
{
    if (!retail_is_admin()) {
        http_response_code(403);
        echo json_encode([
            'status'  => 'blocked',
            'error'   => 'INSUFFICIENT_ROLE',
            'message' => 'Esta operación requiere rol admin.',
        ]);
        exit;
    }
}

/**
 * Resuelve business_id para api/agent/execute.php cuando usa KND_AGENTS_TOKEN y user_id en el body.
 * En ese caso el user_id puede venir en el body del request.
 * NUNCA acepta business_id del cliente — siempre lo resuelve desde user_id.
 */
function retail_resolve_business_for_gateway(PDO $pdo, ?int $userId): bool
{
    if (!$userId) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT bu.business_id, bu.role, b.name, b.base_currency, b.active, b.settings_json
         FROM business_users bu
         INNER JOIN businesses b ON b.id = bu.business_id
         WHERE bu.user_id = ? AND b.active = 1
         ORDER BY bu.id ASC
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    $GLOBALS['retail_business_id'] = (int) $row['business_id'];
    $GLOBALS['retail_role']        = $row['role'];
    $GLOBALS['retail_user_id']     = (int) $userId;
    $GLOBALS['retail_business']    = [
        'id'            => (int) $row['business_id'],
        'name'          => $row['name'],
        'base_currency' => $row['base_currency'],
        'active'        => (bool) $row['active'],
        'settings'      => $row['settings_json'] ? json_decode($row['settings_json'], true) : [],
    ];

    return true;
}
