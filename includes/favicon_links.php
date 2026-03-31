<?php
/**
 * Favicon + PWA meta (mismas rutas que el sitio principal).
 * Usar: require_once .../favicon_links.php; echo generateFaviconLinks();
 */
function generateFaviconLinks() {
    $favicon  = '    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">' . "\n";
    $favicon .= '    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-96x96.png">' . "\n";
    $favicon .= '    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-96x96.png">' . "\n";
    $favicon .= '    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">' . "\n";
    $favicon .= '    <link rel="shortcut icon" href="/assets/images/favicon.ico">' . "\n";
    $favicon .= '    <meta name="msapplication-TileColor" content="#259cae">' . "\n";
    $favicon .= '    <meta name="msapplication-config" content="/assets/images/browserconfig.xml">' . "\n";
    $favicon .= '    <meta name="theme-color" content="#259cae">' . "\n";

    return $favicon;
}
