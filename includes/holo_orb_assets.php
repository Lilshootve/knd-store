<?php
/**
 * Holo orb CSS/JS snippets for pages that do not use generateHeader()/generateScripts().
 * Store layout uses these functions from header.php / footer.php.
 */

declare(strict_types=1);

function holo_orb_assets_available(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['dr_user_id'])) {
        return false;
    }
    $css = __DIR__ . '/../assets/css/knd-holo-orbs.css';
    $js = __DIR__ . '/../assets/js/knd-holo-orbs.js';
    return is_file($css) && is_file($js);
}

/** Echo one <link> for knd-holo-orbs.css (no trailing logic). */
function holo_orb_emit_stylesheet_link(): void {
    if (!holo_orb_assets_available()) {
        return;
    }
    $css = __DIR__ . '/../assets/css/knd-holo-orbs.css';
    echo '    <link rel="stylesheet" href="/assets/css/knd-holo-orbs.css?v=' . (int) filemtime($css) . '">' . "\n";
}

/** Echo inline config + deferred knd-holo-orbs.js */
function holo_orb_emit_init_script(): void {
    if (!holo_orb_assets_available()) {
        return;
    }
    require_once __DIR__ . '/csrf.php';
    $js = __DIR__ . '/../assets/js/knd-holo-orbs.js';
    $payload = [
        'csrf'     => csrf_token(),
        'offerUrl' => '/api/orb/offer.php',
        'claimUrl' => '/api/orb/claim.php',
    ];
    echo '<script>window.__KND_HOLO_ORB__=' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
    echo '<script src="/assets/js/knd-holo-orbs.js?v=' . (int) filemtime($js) . '" defer></script>' . "\n";
}
