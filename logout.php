<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';

auth_logout();
header('Location: /auth.php');
exit;
