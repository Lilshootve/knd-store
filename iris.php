<?php
/**
 * Iris — KND Agents interface (vanilla JS + iris.css).
 * API URL: change $irisApiUrl for production (same-origin recommended).
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

/** Dev: Next viewer Iris API. Production: e.g. '/api/iris' or '/api/iris.php' on same host. */
$irisApiUrl = 'http://localhost:3000/api/iris';

$irisCss = __DIR__ . '/assets/css/iris.css';
$irisJs = __DIR__ . '/assets/js/iris.js';
$vCss = file_exists($irisCss) ? filemtime($irisCss) : 0;
$vJs = file_exists($irisJs) ? filemtime($irisJs) : 0;

$extraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Rajdhani:wght@400;500;600&display=swap">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/iris.css?v=' . (int) $vCss . '">' . "\n";
$extraHead .= '    <script src="/assets/js/iris.js?v=' . (int) $vJs . '" defer></script>' . "\n";

$title = t('iris.meta.title', 'Iris | KND Agents');
$desc = t('iris.meta.description', 'KND Agents interface — Iris.');

$apiAttr = htmlspecialchars($irisApiUrl, ENT_QUOTES, 'UTF-8');

echo generateHeader($title, $desc, $extraHead, true);
echo generateNavigation();
?>
<main class="iris-page" id="iris-page">
    <div
        class="iris-container"
        id="iris-container"
        data-iris-api="<?php echo $apiAttr; ?>"
    >
        <div class="iris-core idle" id="iris-core" aria-hidden="true">
            <svg class="iris-hex-svg" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="iris-hex-fill" x1="40" y1="30" x2="170" y2="180" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#7c5cff" stop-opacity="0.25" />
                        <stop offset="0.55" stop-color="#00d4ff" stop-opacity="0.12" />
                        <stop offset="1" stop-color="#7c5cff" stop-opacity="0.08" />
                    </linearGradient>
                    <linearGradient id="iris-hex-stroke" x1="30" y1="100" x2="170" y2="100" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#7c5cff" />
                        <stop offset="1" stop-color="#00d4ff" />
                    </linearGradient>
                </defs>
                <g class="iris-hex-glow">
                    <polygon
                        points="170,100 135,160.62 65,160.62 30,100 65,39.38 135,39.38"
                        fill="url(#iris-hex-fill)"
                        stroke="url(#iris-hex-stroke)"
                        stroke-width="1.5"
                    />
                </g>
            </svg>
        </div>
        <p class="iris-status" id="iris-status">Iris is ready</p>
        <div class="iris-message" id="iris-message" hidden aria-live="polite"></div>
        <form class="iris-form" id="iris-form" novalidate>
            <label class="iris-input-label visually-hidden" for="iris-input">Tell Iris what you want to do</label>
            <input
                type="text"
                class="iris-input"
                id="iris-input"
                name="input"
                autocomplete="off"
                placeholder="Tell Iris what you want to do..."
            />
        </form>
    </div>
</main>
<?php
echo generateFooter();
echo generateScripts();
