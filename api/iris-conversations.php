<?php
/**
 * Iris Conversations API
 *
 * GET  /api/iris-conversations.php          → list conversations (newest first)
 * GET  /api/iris-conversations.php?id=X     → get single conversation with messages
 * DELETE /api/iris-conversations.php?id=X   → delete conversation + all messages
 *
 * Requires user to be logged in.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$userId = (int) current_user_id();

function conv_db(): ?PDO
{
    $pdo = getDBConnection();
    return $pdo ?: null;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = conv_db();

    // ── DB unavailable → return empty responses instead of 500 ───────────────
    if ($pdo === null) {
        error_log('[iris-conv-api] DB unavailable');
        if ($method === 'GET') {
            echo json_encode($id !== null ? ['error' => 'db_unavailable'] : ['conversations' => []]);
            exit;
        }
        if ($method === 'DELETE') { echo json_encode(['deleted' => false, 'error' => 'db_unavailable']); exit; }
    }

    // ── GET ───────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        if ($id !== null) {
            // Get single conversation with messages
            $stmt = $pdo->prepare(
                'SELECT id, title, mode, created_at, updated_at FROM iris_conversations
                 WHERE id = ? AND user_id = ? LIMIT 1'
            );
            $stmt->execute([$id, $userId]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conv) {
                http_response_code(404);
                echo json_encode(['error' => 'Conversation not found']);
                exit;
            }

            $stmt2 = $pdo->prepare(
                'SELECT role, content, created_at FROM iris_messages
                 WHERE conversation_id = ? ORDER BY id ASC'
            );
            $stmt2->execute([$id]);
            $messages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'conversation' => $conv,
                'messages'     => $messages,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // List all conversations for this user
        $stmt = $pdo->prepare(
            'SELECT id, title, mode, created_at, updated_at FROM iris_conversations
             WHERE user_id = ?
             ORDER BY updated_at DESC
             LIMIT 100'
        );
        $stmt->execute([$userId]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['conversations' => $conversations], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        if ($id === null) {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }

        // Validate ownership before deleting
        $stmt = $pdo->prepare(
            'SELECT id FROM iris_conversations WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation not found']);
            exit;
        }

        // Messages cascade-delete via FK
        $pdo->prepare('DELETE FROM iris_conversations WHERE id = ? AND user_id = ?')
            ->execute([$id, $userId]);

        echo json_encode(['deleted' => true, 'id' => $id]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log('[iris-conv-api] ' . $e->getMessage());
}
