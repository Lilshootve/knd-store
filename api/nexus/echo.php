<?php
// api/nexus/echo.php
// GET  — lista todos los ecos con resonancia, status, distrito y metadatos del avatar
// POST — invoca un eco: gasta KP, restaura resonancia, da recompensa, registra logs
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

$pdo    = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ──────────────────────────────────────────────────────────────
// GET — Echo list (público + datos de invocación propios si logueado)
// ──────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $uid = is_logged_in() ? (int)$_SESSION['user_id'] : null;

    try {
        // Todos los ecos con datos del avatar
        $stmt = $pdo->query("
            SELECT
                e.avatar_id,
                e.district_id,
                e.resonance,
                e.status,
                e.last_invoked,
                a.name,
                a.rarity,
                a.class,
                a.subrole,
                a.image,
                d.name   AS district_name,
                d.color_hex AS district_color
            FROM nexus_echo e
            JOIN mw_avatars a ON a.id = e.avatar_id
            LEFT JOIN nexus_districts d ON d.id = e.district_id
            ORDER BY e.district_id, e.resonance DESC
        ");
        $echoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular status dinámico por resonancia
        foreach ($echoes as &$echo) {
            $echo['resonance']    = (float)$echo['resonance'];
            $echo['invoke_cost']  = _kp_cost((float)$echo['resonance']);
            $echo['status']       = _compute_status((float)$echo['resonance'], $echo['status']);
        }
        unset($echo);

        // Invocaciones del jugador actual (últimas 20)
        $my_invocations = [];
        if ($uid) {
            $inv = $pdo->prepare("
                SELECT avatar_id, invoked_at, kp_spent, reward_type, reward_value
                FROM nexus_echo_invocations
                WHERE user_id = ?
                ORDER BY invoked_at DESC
                LIMIT 20
            ");
            $inv->execute([$uid]);
            $my_invocations = $inv->fetchAll(PDO::FETCH_ASSOC);
        }

        json_success([
            'echoes'         => $echoes,
            'my_invocations' => $my_invocations,
        ]);
    } catch (PDOException $e) {
        error_log('nexus/echo GET error: ' . $e->getMessage());
        json_error('DB_ERROR', 'Failed to fetch echo list', 500);
    }
}

// ──────────────────────────────────────────────────────────────
// POST — Invocar un eco
// ──────────────────────────────────────────────────────────────
elseif ($method === 'POST') {
    api_require_login();
    $uid = (int)$_SESSION['user_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || empty($input['avatar_id'])) {
        json_error('INVALID_INPUT', 'avatar_id required');
    }

    $avatar_id = (int)$input['avatar_id'];

    try {
        $pdo->beginTransaction();

        // 1. Obtener eco + avatar
        $stmt = $pdo->prepare("
            SELECT e.avatar_id, e.resonance, e.status, e.district_id,
                   a.name, a.rarity
            FROM nexus_echo e
            JOIN mw_avatars a ON a.id = e.avatar_id
            WHERE e.avatar_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$avatar_id]);
        $echo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$echo) {
            $pdo->rollBack();
            json_error('NOT_FOUND', 'Echo not found', 404);
        }

        $resonance = (float)$echo['resonance'];
        $cost      = _kp_cost($resonance);

        // 2. Verificar balance KP del jugador
        $earned_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0)
            FROM points_ledger
            WHERE user_id = ? AND status = 'available' AND entry_type = 'earn'
              AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $earned_stmt->execute([$uid]);
        $earned = (int)$earned_stmt->fetchColumn();

        $spent_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(ABS(points)), 0)
            FROM points_ledger
            WHERE user_id = ? AND entry_type = 'spend'
        ");
        $spent_stmt->execute([$uid]);
        $spent   = (int)$spent_stmt->fetchColumn();
        $balance = max(0, $earned - $spent);

        if ($balance < $cost) {
            $pdo->rollBack();
            json_error('INSUFFICIENT_KP', "Need {$cost} KP, you have {$balance}", 402);
        }

        // 3. Determinar recompensa según tier de resonancia ANTES de restaurar
        [$reward_type, $reward_value, $reward_kp] = _compute_reward($resonance, $echo['rarity']);

        // 4. Gastar KP (solo si cost > 0)
        if ($cost > 0) {
            $pdo->prepare("
                INSERT INTO points_ledger
                    (user_id, points, entry_type, source_type, source_id, note, status, created_at)
                VALUES (?, ?, 'spend', 'nexus_invoke', ?, ?, 'used', NOW())
            ")->execute([$uid, -$cost, $avatar_id, "Invoke echo: {$echo['name']}"]);
        }

        // 5. Restaurar resonancia (+30, máx 100)
        $new_resonance = min(100.0, $resonance + 30.0);
        $new_status    = _compute_status($new_resonance, 'active');

        $pdo->prepare("
            UPDATE nexus_echo
            SET resonance = ?, status = ?, last_invoked = NOW()
            WHERE avatar_id = ?
        ")->execute([$new_resonance, $new_status, $avatar_id]);

        // 6. Aplicar recompensa
        if ($reward_type === 'kp' && $reward_kp > 0) {
            $pdo->prepare("
                INSERT INTO points_ledger
                    (user_id, points, entry_type, source_type, source_id, note, status, created_at)
                VALUES (?, ?, 'earn', 'nexus_invoke', ?, ?, 'available', NOW())
            ")->execute([$uid, $reward_kp, $avatar_id, "Echo reward: {$echo['name']}"]);
        } elseif ($reward_type === 'xp') {
            // Upsert XP
            $pdo->prepare("
                INSERT INTO knd_user_xp (user_id, xp, level, updated_at)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    xp = xp + VALUES(xp),
                    level = GREATEST(1, FLOOR(1 + SQRT(xp / 50))),
                    updated_at = NOW()
            ")->execute([$uid, $reward_value]);
        }
        // cosmetic y drop_chance se manejan fuera (cliente muestra animación, drop real = backend task futuro)

        // 7. Registrar invocación
        $pdo->prepare("
            INSERT INTO nexus_echo_invocations
                (user_id, avatar_id, invoked_at, kp_spent, reward_type, reward_value)
            VALUES (?, ?, NOW(), ?, ?, ?)
        ")->execute([$uid, $avatar_id, $cost, $reward_type, $reward_value]);

        $pdo->commit();

        json_success([
            'invoked'        => true,
            'avatar_name'    => $echo['name'],
            'kp_spent'       => $cost,
            'resonance_old'  => $resonance,
            'resonance_new'  => $new_resonance,
            'status_new'     => $new_status,
            'reward_type'    => $reward_type,
            'reward_value'   => $reward_value,
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('nexus/echo POST error: ' . $e->getMessage());
        json_error('DB_ERROR', 'Failed to invoke echo', 500);
    }

} else {
    json_error('METHOD_NOT_ALLOWED', 'Only GET or POST allowed', 405);
}

// ──────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────

/** Costo KP según nivel de resonancia actual */
function _kp_cost(float $resonance): int {
    if ($resonance >= 75) return 0;   // activo: gratis (mantener)
    if ($resonance >= 50) return 50;  // estable: barato
    if ($resonance >= 25) return 150; // debilitado: moderado
    return 400;                        // agonizante: rescate
}

/** Status semántico derivado de resonancia */
function _compute_status(float $resonance, string $current_status): string {
    if ($resonance >= 60) return 'active';
    if ($resonance >= 30) return 'ghost';
    return 'forgotten';
}

/**
 * Recompensa según nivel de rescate.
 * Retorna [reward_type, reward_value, reward_kp]
 *  - reward_kp se acredita a points_ledger si tipo = 'kp'
 *  - reward_value se graba en nexus_echo_invocations
 */
function _compute_reward(float $resonance, string $rarity): array {
    $rarity_mult = match($rarity) {
        'legendary' => 3,
        'epic'      => 2,
        'rare'      => 1.5,
        default     => 1,
    };

    if ($resonance >= 75) {
        // Eco activo: recompensa mínima de xp
        return ['xp', (int)(25 * $rarity_mult), 0];
    }
    if ($resonance >= 50) {
        return ['xp', (int)(60 * $rarity_mult), 0];
    }
    if ($resonance >= 25) {
        // Ecos debilitados: mezcla xp + KP
        return ['kp', (int)(100 * $rarity_mult), (int)(100 * $rarity_mult)];
    }
    // Agonizante / rescate total
    if ($resonance < 10) {
        // Posible drop de cosméticos (marcado para lógica futura)
        return ['cosmetic', 1, 0];
    }
    return ['kp', (int)(200 * $rarity_mult), (int)(200 * $rarity_mult)];
}
