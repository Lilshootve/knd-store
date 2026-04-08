<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/mind_wars_arena_bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }
    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $raw = file_get_contents('php://input');
    $json = json_decode((string) $raw, true);
    $input = is_array($json) ? $json : [];
    $mode = strtolower(trim((string) ($input['mode'] ?? $_POST['mode'] ?? 'pvp')));
    $avatarItemId = (int) ($input['avatar_item_id'] ?? $_POST['avatar_item_id'] ?? 0);
    // Allow CSRF in JSON body when Content-Type is application/json
    if (empty($_POST['csrf_token']) && !empty($input['csrf_token'])) {
        $_POST['csrf_token'] = (string) $input['csrf_token'];
    }

    if ($mode === 'pve') {
        rate_limit_guard($pdo, "mw_start_user:{$userId}", 20, 60);
        rate_limit_guard($pdo, "mw_start_ip:{$ip}", 40, 60);
        $pveFormat = strtolower(trim((string) ($input['format'] ?? $_POST['format'] ?? '1v1')));
        $idsRaw = $input['avatar_item_ids'] ?? ($_POST['avatar_item_ids'] ?? null);
        $post = array_merge($_POST, [
            'mode' => 'pve',
            'difficulty' => (string) ($input['difficulty'] ?? $_POST['difficulty'] ?? 'normal'),
            'format' => $pveFormat === '3v3' ? '3v3' : '1v1',
            'avatar_item_id' => (string) $avatarItemId,
        ]);
        if ($pveFormat === '3v3' && is_array($idsRaw) && $idsRaw !== []) {
            $cleanIds = [];
            foreach ($idsRaw as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $cleanIds[] = $id;
                }
            }
            $cleanIds = array_values(array_unique($cleanIds));
            if (count($cleanIds) >= 3) {
                $post['avatar_item_ids'] = array_slice($cleanIds, 0, 3);
            }
        }
        try {
            $data = mw_start_pve_battle_for_user($pdo, $userId, $post);
            $data['action'] = 'pve_started';
            $data['redirect'] = '/games/mind-wars/mind-wars-arena.php?battle_token=' . rawurlencode((string) $data['battle_token']);
            json_success($data);
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'AVATAR_NOT_OWNED')) {
                json_error('AVATAR_NOT_OWNED', trim(substr($msg, strlen('AVATAR_NOT_OWNED:'))), 403);
            }
            if (str_starts_with($msg, 'INVALID_AVATAR')) {
                json_error('INVALID_AVATAR', trim(substr($msg, strlen('INVALID_AVATAR:'))), 400);
            }
            json_error('INVALID_REQUEST', $msg, 400);
        }
    }

    if ($mode === 'pvp' || $mode === 'ranked') {
        rate_limit_guard($pdo, "mw_queue_enq_user:{$userId}", 30, 60);
        rate_limit_guard($pdo, "mw_queue_enq_ip:{$ip}", 60, 60);
        if ($avatarItemId < 1) {
            json_error('INVALID_AVATAR', 'Select an avatar to queue.');
        }
        $avatar = mw_validate_owned_avatar($pdo, $userId, $avatarItemId);
        if (!$avatar) {
            json_error('AVATAR_NOT_OWNED', 'You do not own this avatar.', 403);
        }
        $payload = mw_perform_ranked_queue_enqueue($pdo, $userId, $avatarItemId, $avatar);
        $payload['action'] = 'pvp_queue';
        json_success($payload);
    }

    json_error('INVALID_MODE', 'Unknown mode. Use pve, pvp, or ranked.', 400);
} catch (\Throwable $e) {
    error_log('mind-wars/start_matchmaking error: ' . $e->getMessage());
    if (stripos($e->getMessage(), 'AGUACATE') !== false) {
        json_error('AGUACATE', $e->getMessage(), 400);
    }
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}
