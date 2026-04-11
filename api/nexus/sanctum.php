<?php
// api/nexus/sanctum.php
// GET  — carga el sanctum del jugador (plot + muebles + catálogo + balance)
// POST — acciones: place | remove | rotate | update_plot | buy
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/nexus_world_builder_gate.php';
require_once BASE_PATH . '/includes/nexus_furniture_catalog.php';

api_require_login();
$pdo    = getDBConnection();
$uid    = current_user_id();
if (!$uid) {
    json_error('AUTH_REQUIRED', 'Invalid session (no user id)', 401);
}
$method = $_SERVER['REQUEST_METHOD'];

// ──────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────

/**
 * Returns the default room row for a user, creating one if it doesn't exist.
 * Safe to call multiple times (idempotent).
 */
function ensure_room(PDO $pdo, int $uid): array {
    $s = $pdo->prepare('SELECT * FROM nexus_rooms WHERE owner_user_id = ? LIMIT 1');
    $s->execute([$uid]);
    $room = $s->fetch(PDO::FETCH_ASSOC);
    if ($room) return $room;

    // Derive a friendly name from the plot if available
    $nameRow = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(house_name),''), '') FROM nexus_plots WHERE user_id = ? LIMIT 1");
    $nameRow->execute([$uid]);
    $houseName = (string)($nameRow->fetchColumn() ?: '');
    if ($houseName === '') {
        $unRow = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $unRow->execute([$uid]);
        $houseName = ((string)($unRow->fetchColumn() ?: 'Player')) . "'s Sanctum";
    }

    $pdo->prepare('INSERT INTO nexus_rooms (owner_user_id, name, is_public) VALUES (?, ?, 0)')
        ->execute([$uid, $houseName]);

    $s->execute([$uid]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

/** Auto-crea plot si el jugador no tiene uno todavía */
function ensure_plot(PDO $pdo, int $uid): array {
    $s = $pdo->prepare('SELECT * FROM nexus_plots WHERE user_id=?');
    $s->execute([$uid]);
    $plot = $s->fetch(PDO::FETCH_ASSOC);
    if ($plot) return $plot;

    // Asignar coordenadas de grid: siguiente slot disponible
    $next = (int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM nexus_plots')->fetchColumn();
    $gx   = ($next - 1) % 10;
    $gz   = intdiv($next - 1, 10);

    $pdo->prepare('
        INSERT INTO nexus_plots (user_id, grid_x, grid_z, exterior_theme, exterior_color, is_public)
        VALUES (?, ?, ?, ?, ?, 1)
    ')->execute([$uid, $gx, $gz, 'cyber', '#00e8ff']);

    $s->execute([$uid]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

/** Balance KP disponible del jugador */
function kp_balance(PDO $pdo, int $uid): int {
    $e = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM points_ledger
        WHERE user_id=? AND status='available' AND entry_type='earn'
          AND (expires_at IS NULL OR expires_at > NOW())");
    $e->execute([$uid]);
    $earned = (int)$e->fetchColumn();

    $s = $pdo->prepare("SELECT COALESCE(SUM(ABS(points)),0) FROM points_ledger
        WHERE user_id=? AND entry_type='spend'");
    $s->execute([$uid]);
    $spent = (int)$s->fetchColumn();

    return max(0, $earned - $spent);
}

/** IDs de muebles que el usuario puede colocar: ya colocados + comprados (ledger). Sin "gratis para todos" por price_kp=0. */
function sanctum_owned_furniture_ids(PDO $pdo, int $uid): array {
    $placed = $pdo->prepare('SELECT DISTINCT furniture_id FROM nexus_room_furniture WHERE user_id = ?');
    $placed->execute([$uid]);
    $placedIds = array_map('intval', $placed->fetchAll(PDO::FETCH_COLUMN));
    $purchasedIds = [];
    try {
        $bs = $pdo->prepare("
            SELECT DISTINCT source_id FROM points_ledger
            WHERE user_id = ? AND entry_type = 'spend'
              AND source_type = 'nexus_furniture'
              AND source_id IS NOT NULL AND source_id > 0
        ");
        $bs->execute([$uid]);
        $purchasedIds = array_map('intval', $bs->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $_) {
    }
    return array_values(array_unique(array_merge($placedIds, $purchasedIds)));
}

// ──────────────────────────────────────────────────────────────────
// GET — Cargar sanctum completo
// Query opcional: room_user_id — ver muebles de otro usuario (solo si su plot is_public=1).
// Catálogo siempre nexus_furniture_catalog (Sanctum / tienda). No mezclar con world_builder_catalog.
// ──────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $sessionUid = (int) $uid;
        $reqRoom    = isset($_GET['room_user_id']) ? (int) $_GET['room_user_id'] : 0;
        $reqRoomId  = isset($_GET['room_id'])      ? (int) $_GET['room_id']      : 0;
        $targetUid  = $sessionUid;
        $readOnly   = false;
        $activeRoom = null;

        if ($reqRoom > 0 && $reqRoom !== $sessionUid) {
            // Viewing another player's sanctum — verify it's public
            $tp = $pdo->prepare('SELECT * FROM nexus_plots WHERE user_id = ? LIMIT 1');
            $tp->execute([$reqRoom]);
            $tplot = $tp->fetch(PDO::FETCH_ASSOC);
            if (!$tplot || !(int) ($tplot['is_public'] ?? 0)) {
                json_error('PRIVATE_SANCTUM', 'This sanctum is private or does not exist', 403);
            }
            $targetUid = $reqRoom;
            $readOnly  = true;
            $plot      = $tplot;
            // Load (or create) the target user's room
            $activeRoom = ensure_room($pdo, $targetUid);
        } else {
            $plot       = ensure_plot($pdo, $sessionUid);
            $activeRoom = ensure_room($pdo, $sessionUid);
        }

        // Allow specific room_id override (future multi-room support)
        if ($reqRoomId > 0) {
            $rr = $pdo->prepare('SELECT * FROM nexus_rooms WHERE id = ? LIMIT 1');
            $rr->execute([$reqRoomId]);
            $rr = $rr->fetch(PDO::FETCH_ASSOC);
            if ($rr) {
                // If not the owner, verify the room owner's plot is public
                if ((int)$rr['owner_user_id'] !== $sessionUid) {
                    $tp2 = $pdo->prepare('SELECT is_public FROM nexus_plots WHERE user_id = ? LIMIT 1');
                    $tp2->execute([(int)$rr['owner_user_id']]);
                    $pub = $tp2->fetchColumn();
                    if (!$pub) json_error('PRIVATE_SANCTUM', 'This room is private', 403);
                    $readOnly = true;
                }
                $activeRoom = $rr;
                $targetUid  = (int)$rr['owner_user_id'];
            }
        }

        $roomId = (int)($activeRoom['id'] ?? 0);

        // Load placed furniture: primary by room_id, fallback to user_id for legacy rows
        $ps = $pdo->prepare('
            SELECT rf.id, rf.furniture_id, rf.room, rf.cell_x, rf.cell_y,
                   rf.rotation, rf.color_override,
                   fc.code, fc.name, fc.category, fc.rarity,
                   fc.width, fc.depth, fc.asset_data
            FROM nexus_room_furniture rf
            JOIN nexus_furniture_catalog fc ON fc.id = rf.furniture_id
            WHERE (rf.room_id = ? OR (rf.room_id IS NULL AND rf.user_id = ?))
            ORDER BY rf.placed_at
        ');
        $ps->execute([$roomId, $targetUid]);
        $placed = $ps->fetchAll(PDO::FETCH_ASSOC);
        foreach ($placed as &$p) {
            $p['asset_data'] = $p['asset_data'] ? json_decode($p['asset_data'], true) : [];
        }
        unset($p);

        $catalog = nexus_furniture_catalog_fetch_active($pdo);

        // Inventory / KP always from the session user (visitor)
        $ownedFurnitureIds = sanctum_owned_furniture_ids($pdo, $sessionUid);

        $unOwner = $pdo->prepare('SELECT username FROM users WHERE id=?');
        $unOwner->execute([$targetUid]);
        $roomOwnerUsername = (string) ($unOwner->fetchColumn() ?: 'Player');

        $unViewer = $pdo->prepare('SELECT username FROM users WHERE id=?');
        $unViewer->execute([$sessionUid]);
        $viewerUsername = (string) ($unViewer->fetchColumn() ?: 'Player');

        json_success([
            'plot'                 => $plot,
            'room'                 => $activeRoom,
            'placed'               => $placed,
            'catalog'              => $catalog,
            'balance'              => kp_balance($pdo, $sessionUid),
            'username'             => $roomOwnerUsername,
            'viewer_username'      => $viewerUsername,
            'room_owner_id'        => $targetUid,
            'read_only'            => $readOnly,
            'is_admin'             => nexus_user_can_world_builder($pdo, $sessionUid),
            'owned_furniture_ids'  => $ownedFurnitureIds,
        ]);
    } catch (PDOException $e) {
        error_log('sanctum GET: ' . $e->getMessage());
        json_error('DB_ERROR', 'Failed to load sanctum', 500);
    }
}

// ──────────────────────────────────────────────────────────────────
// POST — Acciones
// ──────────────────────────────────────────────────────────────────
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) json_error('INVALID_INPUT', 'JSON body required');

    $action = (string)($input['action'] ?? '');

    try {

        // ── PLACE ─────────────────────────────────────────────────
        if ($action === 'place') {
            $fid    = (int)($input['furniture_id'] ?? 0);
            $cell_x = (int)($input['cell_x'] ?? 0);
            $cell_y = (int)($input['cell_y'] ?? 0);
            $rot    = (int)($input['rotation'] ?? 0) % 4;
            $room   = in_array($input['room'] ?? '', ['main','exterior'], true)
                      ? $input['room'] : 'main';

            if ($cell_x < 0 || $cell_x > 9 || $cell_y < 0 || $cell_y > 9) {
                json_error('OUT_OF_BOUNDS', 'Cell coordinates out of bounds');
            }

            // Obtener mueble del catálogo
            $fc = $pdo->prepare('SELECT id,width,depth FROM nexus_furniture_catalog WHERE id=? AND is_active=1');
            $fc->execute([$fid]);
            $item = $fc->fetch(PDO::FETCH_ASSOC);
            if (!$item) json_error('NOT_FOUND', 'Furniture not found', 404);

            $ownedIds = sanctum_owned_furniture_ids($pdo, $uid);
            if (!in_array($fid, $ownedIds, true)) {
                json_error('NOT_OWNED', 'Buy this item in the store before placing it', 403);
            }

            $w = (int)$item['width'];
            $d = (int)$item['depth'];

            // Resolve room_id for the session user
            $activeRoom = ensure_room($pdo, (int)$uid);
            $roomId     = (int)$activeRoom['id'];

            // Verify footprint — check both room_id rows AND legacy user_id rows
            $chk = $pdo->prepare('
                SELECT id FROM nexus_room_furniture
                WHERE (room_id = ? OR (room_id IS NULL AND user_id = ?))
                  AND room = ? AND cell_x = ? AND cell_y = ?
            ');
            for ($dx = 0; $dx < $w; $dx++) {
                for ($dy = 0; $dy < $d; $dy++) {
                    $cx = $cell_x + $dx;
                    $cy = $cell_y + $dy;
                    if ($cx > 9 || $cy > 9) json_error('OUT_OF_BOUNDS', "Footprint exceeds room at $cx,$cy");
                    $chk->execute([$roomId, $uid, $room, $cx, $cy]);
                    if ($chk->fetch()) json_error('CELL_OCCUPIED', "Cell $cx,$cy is occupied");
                }
            }

            $pdo->prepare('
                INSERT INTO nexus_room_furniture (user_id, room_id, furniture_id, room, cell_x, cell_y, rotation)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ')->execute([$uid, $roomId, $fid, $room, $cell_x, $cell_y, $rot]);

            json_success(['placed' => true, 'id' => (int)$pdo->lastInsertId()]);
        }

        // ── REMOVE ────────────────────────────────────────────────
        elseif ($action === 'remove') {
            $pid = (int)($input['placed_id'] ?? 0);
            $del = $pdo->prepare('DELETE FROM nexus_room_furniture WHERE id=? AND user_id=?');
            $del->execute([$pid, $uid]);
            if ($del->rowCount() === 0) json_error('NOT_FOUND', 'Item not found', 404);
            json_success(['removed' => true]);
        }

        // ── ROTATE ────────────────────────────────────────────────
        elseif ($action === 'rotate') {
            $pid = (int)($input['placed_id'] ?? 0);
            $pdo->prepare('UPDATE nexus_room_furniture SET rotation=(rotation+1)%4 WHERE id=? AND user_id=?')
                ->execute([$pid, $uid]);
            json_success(['rotated' => true]);
        }

        // ── UPDATE_PLOT ───────────────────────────────────────────
        elseif ($action === 'update_plot') {
            $name  = mb_substr(strip_tags($input['house_name'] ?? ''), 0, 40, 'UTF-8');
            $theme = in_array($input['exterior_theme'] ?? '', ['cyber','neon','dark','hologram','nature'], true)
                     ? $input['exterior_theme'] : 'cyber';
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', $input['exterior_color'] ?? '')
                     ? $input['exterior_color'] : '#00e8ff';
            $pub   = !empty($input['is_public']) ? 1 : 0;

            ensure_plot($pdo, $uid);
            $pdo->prepare('UPDATE nexus_plots SET house_name=?,exterior_theme=?,exterior_color=?,is_public=? WHERE user_id=?')
                ->execute([$name ?: null, $theme, $color, $pub, $uid]);

            json_success(['updated' => true]);
        }

        // ── BUY ───────────────────────────────────────────────────
        elseif ($action === 'buy') {
            $fid = (int)($input['furniture_id'] ?? 0);
            $pdo->beginTransaction();

            $fc = $pdo->prepare('SELECT id,name,price_kp FROM nexus_furniture_catalog WHERE id=? AND is_active=1 FOR UPDATE');
            $fc->execute([$fid]);
            $item = $fc->fetch(PDO::FETCH_ASSOC);
            if (!$item) { $pdo->rollBack(); json_error('NOT_FOUND', 'Item not found', 404); }

            $price   = (int)$item['price_kp'];
            $balance = kp_balance($pdo, $uid);
            if ($balance < $price) {
                $pdo->rollBack();
                json_error('INSUFFICIENT_KP', "Need {$price} KP, you have {$balance}", 402);
            }

            // Sin columna `note`; status de gasto = spent; source_type = nexus_furniture (ENUM migration Nexus)
            $pdo->prepare("
                INSERT INTO points_ledger
                    (user_id, source_type, source_id, entry_type, status, points, created_at)
                VALUES (?, 'nexus_furniture', ?, 'spend', 'spent', ?, NOW())
            ")->execute([$uid, $fid, -$price]);

            $pdo->commit();
            json_success(['bought' => true, 'kp_spent' => $price, 'balance' => $balance - $price]);
        }

        else {
            json_error('UNKNOWN_ACTION', "Unknown action: {$action}");
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('sanctum POST: ' . $e->getMessage());
        json_error('DB_ERROR', 'Sanctum operation failed', 500);
    }
} else {
    json_error('METHOD_NOT_ALLOWED', 'Only GET or POST allowed', 405);
}
