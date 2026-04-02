<?php
require_once __DIR__ . '/env.php';
// i18n (necesario para evitar fatal en producción)
$__i18n = __DIR__ . '/functions-i18n.php';
if (file_exists($__i18n)) {
    require_once $__i18n;
} else {
    // fallback mínimo por si alguien borra el archivo
    if (!function_exists('t')) {
        function t(string $key, ?string $fallback = null, array $vars = []): string {
            $text = $fallback ?? $key;
            foreach ($vars as $k => $v) $text = str_replace('{'.$k.'}', (string)$v, $text);
            return $text;
        }
    }
}

// KND Store - Configuración Principal

// ==========================
// Configuración de errores
// ==========================
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$isLocal = in_array($serverName, ['localhost', '127.0.0.1']);

error_reporting(E_ALL);

if ($isLocal) {
    // Entorno local / desarrollo
    ini_set('display_errors', 1);
} else {
    // Producción
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    // Asegúrate de que esta carpeta exista en el servidor
    ini_set('error_log', __DIR__ . '/../logs/php-error.log');
}

// ==========================
// Zona horaria
// ==========================
date_default_timezone_set('America/Mexico_City'); // Ajusta si cambias de región

// ==========================
// Headers de seguridad básicos
// (CSP, HSTS, etc. mejor manejarlos en .htaccess)
// ==========================
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    // X-XSS-Protection está deprecado, lo podemos dejar en 0 para evitar comportamientos raros
    header('X-XSS-Protection: 0');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ==========================
// Configuración de base de datos (desde .env)
// ==========================
if (!defined('DB_HOST')) {
    define('DB_HOST', knd_env_required('DB_HOST'));
    define('DB_PORT', knd_env_required('DB_PORT'));
    define('DB_NAME', knd_env_required('DB_NAME'));
    define('DB_USER', knd_env_required('DB_USER'));
    define('DB_PASS', knd_env_required('DB_PASS'));
    define('DB_CHARSET', knd_env_required('DB_CHARSET'));
}

// ==========================
// Configuración de la aplicación
// ==========================
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'KND Store');
    define('SITE_URL', 'https://kndstore.com');
    define('SITE_EMAIL', 'support@kndstore.com');
}

// ==========================
// KND Support Credits config
// ==========================
if (!defined('SUPPORT_POINTS_PER_USD')) {
    define('SUPPORT_POINTS_PER_USD', 100);
    define('SUPPORT_MIN_AMOUNT_USD', 1.00);
    define('SUPPORT_MAX_AMOUNT_USD', 500.00);
    define('SUPPORT_HOLD_DAYS_NEW', 10);
    define('SUPPORT_HOLD_DAYS_NORMAL', 7);
    define('SUPPORT_NEW_ACCOUNT_DAYS', 30);
    define('SUPPORT_EXPIRY_MONTHS', 12);
    define('SUPPORT_ALLOWED_METHODS', ['paypal', 'binance_pay', 'zinli', 'pago_movil', 'ach', 'other']);
}

if (!defined('WELCOME_BONUS_KP')) {
    define('WELCOME_BONUS_KP', 500);
}

// ==========================
// KND LastRoll 1v1 Economy
// ==========================
if (!defined('LASTROLL_ENTRY_KP')) {
    define('LASTROLL_ENTRY_KP', 100);
    define('LASTROLL_PAYOUT_KP', 150);
    define('LASTROLL_HOUSE_KP', 50);
}

// ==========================
// Conexión a la base de datos (con cache de conexión)
// ==========================
function getDBConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $portDsn = defined('DB_PORT') && DB_PORT !== '' ? ";port=" . DB_PORT : '';
        $dsn = "mysql:host=" . DB_HOST . $portDsn . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Activamos emulación para que LIMIT ? y similares no den problemas
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        return false;
    }
}

// ==========================
// Funciones utilitarias
// ==========================

function cleanInput($data) {
    $data = trim($data);
    // stripslashes es irrelevante salvo que tengas magic_quotes (obsoleto), pero lo dejamos
    $data = stripslashes($data);
    // Escapamos con ENT_QUOTES y UTF-8 explícito
    return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// CSRF
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        // Asumimos que no rompe nada: si ya se inició sesión, no la vuelve a iniciar
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Autenticación — unified on dr_user_id (system B)
function isLoggedIn() {
    return !empty($_SESSION['dr_user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return false;
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['dr_user_id']]);
    return $stmt->fetch();
}

// Helpers varios
function redirect($url) {
    header("Location: $url");
    exit();
}

function showError($message) {
    return "<div class='alert alert-danger'>$message</div>";
}

function showSuccess($message) {
    return "<div class='alert alert-success'>$message</div>";
}

function formatPrice($price) {
    return '$' . number_format($price, 2);
}

function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Za-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ==========================
// Funciones relacionadas con productos y carrito
// ==========================

function getFeaturedProducts($limit = 6) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [];
    }

    $limit = (int) $limit;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE featured = 1 AND active = 1 ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getCategories() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT * FROM categories WHERE active = 1 ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getProductsByCategory($categoryId, $limit = 12) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [];
    }

    $limit = (int) $limit;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND active = 1 ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute([$categoryId]);
    return $stmt->fetchAll();
}

function searchProducts($query, $limit = 12) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [];
    }

    $limit = (int) $limit;
    $searchTerm = "%$query%";
    $stmt = $pdo->prepare("
        SELECT * 
        FROM products 
        WHERE (name LIKE ? OR description LIKE ?) 
          AND active = 1 
        ORDER BY created_at DESC 
        LIMIT $limit
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    return $stmt->fetchAll();
}

function getUserCart($userId) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT c.*, p.name, p.price, p.image 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function addToCart($userId, $productId, $quantity = 1) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $existingItem = $stmt->fetch();

    if ($existingItem) {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
        return $stmt->execute([$quantity, $userId, $productId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $productId, $quantity]);
    }
}

function updateCartQuantity($userId, $productId, $quantity) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    if ($quantity <= 0) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        return $stmt->execute([$userId, $productId]);
    } else {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        return $stmt->execute([$quantity, $userId, $productId]);
    }
}

function clearCart($userId) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

function getCartTotal($userId) {
    $cart = getUserCart($userId);
    $total = 0;

    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    return $total;
}

function createOrder($userId, $shippingAddress, $paymentMethod) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $pdo->beginTransaction();

        $total = getCartTotal($userId);

        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, total, shipping_address, payment_method, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $total, $shippingAddress, $paymentMethod]);
        $orderId = $pdo->lastInsertId();

        $cart = getUserCart($userId);
        foreach ($cart as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
        }

        clearCart($userId);

        $pdo->commit();
        return $orderId;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al crear pedido: " . $e->getMessage());
        return false;
    }
}

function getUserOrders($userId) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getOrderDetails($orderId) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT o.*, oi.*, p.name, p.image 
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN products p ON oi.product_id = p.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

// ==========================
// Utilidades de cache y rendimiento
// ==========================

function setCacheHeaders($type = 'default') {
    if (headers_sent()) {
        return;
    }

    switch ($type) {
        case 'static':
            header('Cache-Control: public, max-age=31536000, immutable');
            break;
        case 'short':
            header('Cache-Control: public, max-age=300');
            break;
        default:
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            break;
    }
}

function startPerformanceTimer() {
    return microtime(true);
}

function endPerformanceTimer($startTime) {
    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;
    return $executionTime;
}
