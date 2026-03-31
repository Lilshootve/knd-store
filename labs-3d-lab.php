<?php
try {
    require_once __DIR__ . '/labs/3D-Lab.php';
} catch (\Throwable $e) {
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p><a href="/labs">Back to Labs</a></p>';
}
