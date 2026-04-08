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

api_require_login();
$pdo    = getDBConnection();
$uid    = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ──────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────

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

// ──────────────────────────────────────────────────────────────────
// GET — Cargar sanctum completo
// ──────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $plot = ensure_plot($pdo, $uid);

        // Muebles colocados (con datos del catálogo)
        $ps = $pdo->prepare('
            SELECT rf.id, rf.furniture_id, rf.room, rf.cell_x, rf.cell_y,
                   rf.rotation, rf.color_override,
                   fc.code, fc.name, fc.category, fc.rarity,
                   fc.width, fc.depth, fc.asset_data
            FROM nexus_room_furniture rf
            JOIN nexus_furniture_catalog fc ON fc.id = rf.furniture_id
            WHERE rf.user_id = ?
            ORDER BY rf.placed_at
        ');
        $ps->execute([$uid]);
        $placed = $ps->fetchAll(PDO::FETCH_ASSOC);
        foreach ($placed as &$p) {
            $p['asset_data'] = $p['asset_data'] ? json_decode($p['asset_data'], true) : [];
        }
        unset($p);

        // Catálogo completo
        $cs = $pdo->query('SELECT id,code,name,category,rarity,width,depth,price_kp,asset_data
                           FROM nexus_furniture_catalog WHERE is_active=1
                           ORDER BY category, price_kp');
        $catalog = $cs->fetchAll(PDO::FETCH_ASSOC);
        foreach ($catalog as &$c) {
            $c['asset_data'] = $c['asset_data'] ? json_decode($c['asset_data'], true) : [];
        }
        unset($c);

        // Username
        $un = $pdo->prepare('SELECT username FROM users WHERE id=?');
        $un->execute([$uid]);
        $username = (string)($un->fetchColumn() ?: 'Player');

        json_success([
            'plot'     => $plot,
            'placed'   => $placed,
            'catalog'  => $catalog,
            'balance'  => kp_balance($pdo, $uid),
            'username' => $username,
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

            $w = (int)$item['width'];
            $d = (int)$item['depth'];

            // Verificar todas las celdas del footprint
            $chk = $pdo->prepare('SELECT id FROM nexus_room_furniture WHERE user_id=? AND room=? AND cell_x=? AND cell_y=?');
            for ($dx = 0; $dx < $w; $dx++) {
                for ($dy = 0; $dy < $d; $dy++) {
                    $cx = $cell_x + $dx;
                    $cy = $cell_y + $dy;
                    if ($cx > 9 || $cy > 9) json_error('OUT_OF_BOUNDS', "Footprint exceeds room at $cx,$cy");
                    $chk->execute([$uid, $room, $cx, $cy]);
                    if ($chk->fetch()) json_error('CELL_OCCUPIED', "Cell $cx,$cy is occupied");
                }
            }

            $pdo->prepare('
                INSERT INTO nexus_room_furniture (user_id,furniture_id,room,cell_x,cell_y,rotation)
                VALUES (?,?,?,?,?,?)
            ')->execute([$uid, $fid, $room, $cell_x, $cell_y, $rot]);

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

            $pdo->prepare("
                INSERT INTO points_ledger
                    (user_id,points,entry_type,source_type,source_id,note,status,created_at)
                VALUES (?,?,'spend','sanctum_buy',?,?,'used',NOW())
            ")->execute([$uid, -$price, $fid, "Buy furniture: {$item['name']}"]);

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
