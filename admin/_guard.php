<?php
/**
 * Admin guard - middleware común para páginas protegidas.
 * Proporciona: session, config, headers no-cache, admin_require_login(), _rbac.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once __DIR__ . '/_rbac.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow');

/**
 * Requiere sesión admin; redirige a login si no está autenticado.
 */
function admin_require_login(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: /admin/index.php');
        exit;
    }
}
