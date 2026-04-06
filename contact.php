<?php
/**
 * Contact — concept layout (sections/knd_contact.php + knd-contact.css/js).
 */
require_once __DIR__ . '/config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

$success_message = '';
$error_message = '';
$name = $email = $subject = $message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $error_message = t('contact.form.error.required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = t('contact.form.error.email');
    } else {
        $success_message = t('contact.form.success');
        $name = $email = $subject = $message = '';
    }
}

$contactCss = __DIR__ . '/assets/css/knd-contact.css';
$contactJs = __DIR__ . '/assets/js/knd-contact.js';
$v = file_exists($contactCss) ? filemtime($contactCss) : 0;
$vjs = file_exists($contactJs) ? filemtime($contactJs) : 0;

$extraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/knd-contact.css?v=' . $v . '">' . "\n";
$extraHead .= '    <script src="/assets/js/knd-contact.js?v=' . $vjs . '" defer></script>' . "\n";

$title = t('contact.meta.title', 'Contact | KND Store');
$desc = t('contact.meta.description', 'Connect with KND Store for quotes, support, and fast responses.');

echo generateHeader($title, $desc, $extraHead, true);
echo generateNavigation();
include __DIR__ . '/sections/knd_contact.php';
echo generateFooter();
echo generateScripts();
