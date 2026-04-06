<?php
/**
 * KND Ecosystem — hub for Labs, digital services, custom design, and apparel.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

$ecoCss = __DIR__ . '/assets/css/knd-ecosystem.css';
$ecoJs = __DIR__ . '/assets/js/knd-ecosystem.js';
$ecoV = file_exists($ecoCss) ? filemtime($ecoCss) : 0;
$ecoJsV = file_exists($ecoJs) ? filemtime($ecoJs) : 0;

$extraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/knd-ecosystem.css?v=' . $ecoV . '">' . "\n";
$extraHead .= '    <script src="/assets/js/knd-ecosystem.js?v=' . $ecoJsV . '" defer></script>' . "\n";

$title = t('ecosystem.meta.title', 'KND Ecosystem | KND');
$desc = t('ecosystem.meta.description', 'KND Labs, digital services, custom design, and apparel — one holographic ecosystem.');

echo generateHeader($title, $desc, $extraHead, true);
echo generateNavigation();
include __DIR__ . '/sections/knd_ecosystem.php';
echo generateFooter();
echo generateScripts();
