<?php
/**
 * KND Store - Death Roll API Endpoint
 * Endpoint autoritativo para ejecutar Death Rolls
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';

// Verificación robusta de config.php
$configPath = BASE_PATH . '/includes/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    
    // Detectar si estamos en desarrollo o producción
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    $isLocal = in_array($serverName, ['localhost', '127.0.0.1']);
    
    $message = $isLocal 
        ? 'Missing includes/config.php on server.' 
        : 'Configuration error. Please contact the administrator.';
    
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

require_once $configPath;

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

if (empty($_SESSION['dr_user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Sesión no válida. Debes iniciar sesión.'
    ]);
    exit;
}

$userId = (int)$_SESSION['dr_user_id'];
$orderId = isset($_POST['order_id']) ? trim($_POST['order_id']) : '';

// Validar order_id
if (empty($orderId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'order_id es requerido'
    ]);
    exit;
}

// Obtener conexión DB
$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error de conexión a la base de datos'
    ]);
    exit;
}

try {
    // Verificar si ya existe un roll para este order_id (evitar re-roll)
    $stmt = $pdo->prepare("
        SELECT dr.*, r.name as reward_name, r.description as reward_description
        FROM deathroll_rolls dr
        JOIN deathroll_rewards r ON dr.reward_id = r.id
        WHERE dr.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $existingRoll = $stmt->fetch();
    
    if ($existingRoll) {
        // Ya existe, devolver el mismo resultado
        $payload = [
            'order_id' => $existingRoll['order_id'],
            'result_number' => (int)$existingRoll['result_number'],
            'rarity' => $existingRoll['rarity'],
            'reward' => [
                'id' => (int)$existingRoll['reward_id'],
                'name' => $existingRoll['reward_name'],
                'description' => $existingRoll['reward_description']
            ]
        ];
        
        // Generar signature
        $secret = getDeathRollSecret();
        if (!$secret) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error de configuración del servidor'
            ]);
            exit;
        }
        
        $signature = hash_hmac('sha256', json_encode($payload), $secret);
        $payload['signature'] = $signature;
        $payload['claim_instructions'] = getClaimInstructions($existingRoll['rarity'], $existingRoll['reward_name']);
        
        echo json_encode([
            'success' => true,
            'order_id' => $payload['order_id'],
            'result_number' => $payload['result_number'],
            'rarity' => $payload['rarity'],
            'reward' => $payload['reward'],
            'claim_instructions' => $payload['claim_instructions'],
            'signature' => $payload['signature']
        ]);
        exit;
    }
    
    // Validar que el order_id existe y pertenece al usuario
    // Intentar validar desde tabla orders si existe
    $order = null;
    try {
        // Verificar si la tabla orders existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            $stmt = $pdo->prepare("
                SELECT id, user_id, status, total 
                FROM orders 
                WHERE id = ? OR order_number = ? OR CAST(id AS CHAR) = ?
                LIMIT 1
            ");
            $stmt->execute([$orderId, $orderId, $orderId]);
            $order = $stmt->fetch();
            
            if ($order) {
                // Verificar que el pedido pertenece al usuario
                if ((int)$order['user_id'] !== $userId) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'No tienes permiso para acceder a este pedido'
                    ]);
                    exit;
                }
                
                // Verificar que el pedido está pagado/confirmado
                $validStatuses = ['paid', 'confirmed', 'completed', 'pago', 'confirmado', 'completado'];
                $orderStatus = strtolower(trim($order['status'] ?? ''));
                if (!in_array($orderStatus, $validStatuses)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'El pedido debe estar pagado/confirmado para ejecutar el Death Roll'
                    ]);
                    exit;
                }
            }
        }
    } catch (PDOException $e) {
        // Si hay error al consultar orders, continuar sin validación estricta
        // (modo desarrollo/temporal)
        error_log("Warning: No se pudo validar order desde tabla orders: " . $e->getMessage());
    }
    
    // Si no se encontró en orders, verificar que no esté ya consumido en deathroll_rolls
    // Esto previene uso de order_ids inválidos
    if (!$order) {
        // Validación básica: el order_id debe tener formato válido
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $orderId) || strlen($orderId) < 3) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Order ID inválido'
            ]);
            exit;
        }
    }
    
    // Generar RNG server-side
    $seed = time() . $userId . $orderId . random_int(1000, 9999);
    $seedHash = hash('sha256', $seed);
    
    // Simular Death Roll: número aleatorio entre 1 y 1000
    mt_srand(crc32($seedHash));
    $resultNumber = mt_rand(1, 1000);
    
    // Determinar rareza según probabilidades
    // Common: 50%, Rare: 30%, Epic: 15%, Legendary: 5%
    $rarity = determineRarity($resultNumber);
    
    // Obtener recompensa aleatoria de la rareza determinada
    $stmt = $pdo->prepare("
        SELECT id, name, description 
        FROM deathroll_rewards 
        WHERE rarity = ? AND is_active = 1 
        ORDER BY RAND() 
        LIMIT 1
    ");
    $stmt->execute([$rarity]);
    $reward = $stmt->fetch();
    
    if (!$reward) {
        // Fallback a Common si no hay recompensas de esa rareza
        $stmt = $pdo->prepare("
            SELECT id, name, description 
            FROM deathroll_rewards 
            WHERE rarity = 'Common' AND is_active = 1 
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute();
        $reward = $stmt->fetch();
        $rarity = 'Common';
    }
    
    // Preparar payload para signature
    $payload = [
        'order_id' => $orderId,
        'result_number' => $resultNumber,
        'rarity' => $rarity,
        'reward' => [
            'id' => (int)$reward['id'],
            'name' => $reward['name'],
            'description' => $reward['description']
        ]
    ];
    
    // Generar signature HMAC
    $secret = getDeathRollSecret();
    if (!$secret) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error de configuración del servidor'
        ]);
        exit;
    }
    
    $signature = hash_hmac('sha256', json_encode($payload), $secret);
    
    // Guardar en DB
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("
        INSERT INTO deathroll_rolls 
        (user_id, order_id, result_number, rarity, reward_id, payload_sig, seed_hash, ip, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $orderId,
        $resultNumber,
        $rarity,
        $reward['id'],
        $signature,
        $seedHash,
        $ip,
        $userAgent
    ]);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'order_id' => $payload['order_id'],
        'result_number' => $payload['result_number'],
        'rarity' => $payload['rarity'],
        'reward' => $payload['reward'],
        'claim_instructions' => getClaimInstructions($rarity, $reward['name']),
        'signature' => $signature
    ]);
    
} catch (PDOException $e) {
    error_log("Death Roll API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
} catch (Exception $e) {
    error_log("Death Roll API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error inesperado'
    ]);
}

/**
 * Determina la rareza según el número resultante
 */
function determineRarity($number) {
    // Common: 1-500 (50%)
    if ($number <= 500) {
        return 'Common';
    }
    // Rare: 501-800 (30%)
    if ($number <= 800) {
        return 'Rare';
    }
    // Epic: 801-950 (15%)
    if ($number <= 950) {
        return 'Epic';
    }
    // Legendary: 951-1000 (5%)
    return 'Legendary';
}

/**
 * Obtiene el secret para HMAC desde archivo no versionado
 */
function getDeathRollSecret() {
    // Intentar desde archivo local no versionado
    $secretFile = BASE_PATH . '/includes/secrets.local.php';
    if (file_exists($secretFile)) {
        require_once $secretFile;
        if (defined('DEATHROLL_HMAC_SECRET')) {
            return DEATHROLL_HMAC_SECRET;
        }
    }
    
    // Intentar desde variable de entorno
    if (function_exists('getenv') && getenv('DEATHROLL_HMAC_SECRET')) {
        return getenv('DEATHROLL_HMAC_SECRET');
    }
    
    // Fallback: usar un secret por defecto (NO RECOMENDADO EN PRODUCCIÓN)
    // En producción, esto debe fallar
    error_log("WARNING: Death Roll HMAC secret no encontrado. Usando fallback inseguro.");
    return 'CHANGE_THIS_SECRET_IN_PRODUCTION_' . md5(__FILE__);
}

/**
 * Genera instrucciones para reclamar la recompensa
 */
function getClaimInstructions($rarity, $rewardName) {
    $base = "Tu recompensa será entregada por Discord o WhatsApp en el plazo acordado.";
    
    if ($rarity === 'Common') {
        return $base . " Si recibiste un cupón, se aplicará automáticamente en tu próxima compra.";
    }
    
    if ($rarity === 'Rare') {
        return $base . " Contacta por Discord para coordinar el estilo de tu avatar personalizado.";
    }
    
    if ($rarity === 'Epic') {
        return $base . " Te contactaremos para coordinar los detalles de tu Epic Bundle.";
    }
    
    if ($rarity === 'Legendary') {
        return $base . " ¡Felicidades! Te contactaremos para entregar tu Legendary Bundle exclusivo.";
    }
    
    return $base;
}

