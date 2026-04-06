<?php
/**
 * Iris Public — Safe assistant interface for registered users.
 * Read-only access: can query data but cannot execute mutations.
 * Mode is enforced server-side by api/iris.php (session-based).
 */
require_once __DIR__ . '/../config/bootstrap.php';
$kndRoot = BASE_PATH;
require_once $kndRoot . '/includes/session.php';
require_once $kndRoot . '/includes/auth.php';
require_once $kndRoot . '/includes/config.php';
require_once $kndRoot . '/includes/header.php';
require_once $kndRoot . '/includes/footer.php';

// Require login — memory and conversation history only work for registered users
require_login();

$pdoIris = getDBConnection();
if ($pdoIris) {
    auth_refresh_session_tenant($pdoIris);
}
$irisStoreUserId = current_user_id();
$irisBusinessId  = current_business_id();

$irisApiUrl  = '/api/iris.php';
$irisConvApi = '/api/iris-conversations.php';
$irisMemApi  = '/api/iris-memory.php';

$irisCss = $kndRoot . '/assets/css/iris.css';
$irisJs  = $kndRoot . '/assets/js/iris.js';
$vCss    = file_exists($irisCss) ? filemtime($irisCss) : 0;
$vJs     = file_exists($irisJs)  ? filemtime($irisJs)  : 0;

$extraHead  = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Rajdhani:wght@400;500;600&display=swap">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/iris.css?v=' . (int) $vCss . '">' . "\n";
$extraHead .= '    <script src="/assets/js/iris.js?v=' . (int) $vJs . '" defer></script>' . "\n";

$title = t('iris.public.meta.title', 'Iris | KND');
$desc  = t('iris.public.meta.description', 'Ask Iris — your KND assistant.');

$apiAttr     = htmlspecialchars($irisApiUrl,  ENT_QUOTES, 'UTF-8');
$convApiAttr = htmlspecialchars($irisConvApi, ENT_QUOTES, 'UTF-8');
$memApiAttr  = htmlspecialchars($irisMemApi,  ENT_QUOTES, 'UTF-8');
$agentUidAttr = $irisStoreUserId !== null && $irisStoreUserId > 0
    ? htmlspecialchars((string) (int) $irisStoreUserId, ENT_QUOTES, 'UTF-8')
    : '';
$bizIdAttr = $irisBusinessId !== null && $irisBusinessId > 0
    ? htmlspecialchars((string) (int) $irisBusinessId, ENT_QUOTES, 'UTF-8')
    : '';

echo generateHeader($title, $desc, $extraHead, true);
echo generateNavigation();
?>
<main class="iris-page" id="iris-page">

    <!-- Sidebar toggle button -->
    <button class="iris-menu-btn" id="iris-menu-btn" aria-label="Conversaciones" title="Conversaciones">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="1" y="3" width="16" height="1.5" rx="0.75" fill="currentColor"/>
            <rect x="1" y="8.25" width="16" height="1.5" rx="0.75" fill="currentColor"/>
            <rect x="1" y="13.5" width="16" height="1.5" rx="0.75" fill="currentColor"/>
        </svg>
    </button>

    <!-- Conversation sidebar -->
    <aside class="iris-sidebar" id="iris-sidebar" aria-hidden="true" aria-label="Conversaciones">
        <div class="iris-sidebar-head">
            <span class="iris-sidebar-title">Conversaciones</span>
            <button class="iris-sidebar-close" id="iris-sidebar-close" aria-label="Cerrar">×</button>
        </div>
        <button class="iris-new-conv-btn" id="iris-new-conv-btn">+ Nueva conversación</button>
        <ul class="iris-conv-list" id="iris-conv-list" role="list" aria-label="Lista de conversaciones"></ul>
    </aside>
    <div class="iris-sidebar-backdrop" id="iris-sidebar-backdrop" aria-hidden="true"></div>

    <!-- Main content -->
    <div class="iris-container" id="iris-container"
        data-iris-api="<?php echo $apiAttr; ?>"
        data-iris-conv-api="<?php echo $convApiAttr; ?>"
        data-iris-mem-api="<?php echo $memApiAttr; ?>"
        data-agent-user-id="<?php echo $agentUidAttr; ?>"
        <?php if ($bizIdAttr !== ''): ?>data-business-id="<?php echo $bizIdAttr; ?>" data-business-type="retail"<?php endif; ?>>

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

        <!-- Scrollable chat log -->
        <div class="iris-chat" id="iris-chat" role="log" aria-live="polite" aria-label="Conversación" aria-relevant="additions"></div>

        <!-- Single response / confirm area -->
        <div class="iris-message" id="iris-message" hidden aria-live="assertive"></div>

        <form class="iris-form" id="iris-form" novalidate>
            <label class="visually-hidden" for="iris-input">Pregunta a Iris</label>
            <input
                type="text"
                class="iris-input"
                id="iris-input"
                name="input"
                autocomplete="off"
                placeholder="Pregunta algo a Iris..."
            />
        </form>

        <!-- Memory toggle button (shown after first fact is saved) -->
        <button class="iris-mem-btn" id="iris-mem-btn" aria-label="Memoria de Iris" title="Memoria" hidden>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26C17.81 13.47 19 11.38 19 9c0-3.87-3.13-7-7-7z"/>
                <path d="M9 21h6"/>
                <path d="M10 17v2"/>
                <path d="M14 17v2"/>
            </svg>
            <span class="iris-mem-count" id="iris-mem-count"></span>
        </button>
    </div>

    <!-- Memory panel -->
    <div class="iris-mem-panel" id="iris-mem-panel" hidden role="dialog" aria-modal="true" aria-label="Memoria de Iris">
        <div class="iris-mem-panel-head">
            <span class="iris-mem-panel-title">Memoria de Iris</span>
            <button class="iris-mem-panel-close" id="iris-mem-panel-close" aria-label="Cerrar panel">×</button>
        </div>
        <ul class="iris-mem-list" id="iris-mem-list" role="list"></ul>
        <p class="iris-mem-empty" id="iris-mem-empty" hidden>Sin datos guardados todavía.</p>
    </div>

</main>
<?php
echo generateFooter();
echo generateScripts();
