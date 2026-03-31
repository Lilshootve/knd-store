<?php
/**
 * About KND — concept layout (sections/knd_about.php + knd-about.css/js).
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

$aboutCss = __DIR__ . '/assets/css/knd-about.css';
$aboutJs = __DIR__ . '/assets/js/knd-about.js';
$v = file_exists($aboutCss) ? filemtime($aboutCss) : 0;
$vjs = file_exists($aboutJs) ? filemtime($aboutJs) : 0;

$extraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/knd-about.css?v=' . $v . '">' . "\n";
$extraHead .= '    <script src="/assets/js/knd-about.js?v=' . $vjs . '" defer></script>' . "\n";

$title = t('about.meta.title', 'About Us | KND');
$desc = t('about.meta.description', "Knowledge 'N Development — story, mission, and the KND universe.");

echo generateHeader($title, $desc, $extraHead, true);
echo generateNavigation();
include __DIR__ . '/sections/knd_about.php';
echo generateFooter();
echo generateScripts();
