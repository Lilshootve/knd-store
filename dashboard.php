<?php
/**
 * KND Store — main signed-in workspace (SaaS shell + Iris).
 */
require_once __DIR__ . '/config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/config.php';

require_login();

$pdo = getDBConnection();
if ($pdo) {
    auth_refresh_session_tenant($pdo);
}

$uid = current_user_id();
$bid = current_business_id();
$bizName = null;
if ($pdo && $uid && $bid) {
    $st = $pdo->prepare(
        'SELECT b.name FROM businesses b
         INNER JOIN business_users bu ON bu.business_id = b.id AND bu.user_id = ?
         WHERE b.id = ? AND b.active = 1
         LIMIT 1'
    );
    $st->execute([$uid, $bid]);
    $col = $st->fetchColumn();
    $bizName = is_string($col) ? $col : null;
}

$hasWorkspace = $bizName !== null;

require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

$irisCss = BASE_PATH . '/assets/css/iris.css';
$irisJs  = BASE_PATH . '/assets/js/iris.js';
$saasCss = BASE_PATH . '/assets/css/saas.css';
$vIrisCss = file_exists($irisCss) ? filemtime($irisCss) : 0;
$vIrisJs  = file_exists($irisJs) ? filemtime($irisJs) : 0;
$vSaas    = file_exists($saasCss) ? filemtime($saasCss) : 0;

$extraHead  = '<link rel="stylesheet" href="/assets/css/saas.css?v=' . (int) $vSaas . '">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/iris.css?v=' . (int) $vIrisCss . '">' . "\n";
$extraHead .= '    <script src="/assets/js/iris.js?v=' . (int) $vIrisJs . '" defer></script>' . "\n";

$title = 'Dashboard | KND Store';
$desc  = 'Your KND Store workspace.';

$apiAttr     = htmlspecialchars('/api/iris.php', ENT_QUOTES, 'UTF-8');
$convApiAttr = htmlspecialchars('/api/iris-conversations.php', ENT_QUOTES, 'UTF-8');
$memApiAttr  = htmlspecialchars('/api/iris-memory.php', ENT_QUOTES, 'UTF-8');
$agentUidAttr = $uid && $uid > 0 ? htmlspecialchars((string) (int) $uid, ENT_QUOTES, 'UTF-8') : '';
$bizIdAttr    = $hasWorkspace && $bid ? htmlspecialchars((string) (int) $bid, ENT_QUOTES, 'UTF-8') : '';

echo generateHeader($title, $desc, $extraHead, true);
echo generateNavigation();
?>
<main class="knd-saas-main" id="knd-dashboard">
    <div class="knd-saas-hero">
        <h1>Welcome<?php echo current_username() ? ', ' . htmlspecialchars(current_username(), ENT_QUOTES, 'UTF-8') : ''; ?></h1>
        <?php if ($hasWorkspace): ?>
            <p class="knd-saas-hero-meta"><?php echo htmlspecialchars($bizName, ENT_QUOTES, 'UTF-8'); ?></p>
            <span class="knd-saas-badge">Retail workspace</span>
        <?php else: ?>
            <p class="knd-saas-hero-meta">No active workspace is linked to your account.</p>
        <?php endif; ?>
    </div>

    <?php if ($hasWorkspace): ?>
    <section class="knd-saas-panel knd-saas-panel--iris" aria-labelledby="iris-heading">
        <h2 id="iris-heading">Iris</h2>
        <div class="iris-page iris-page--embed" id="iris-page">

            <button class="iris-menu-btn" id="iris-menu-btn" aria-label="Conversations" title="Conversations">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="1" y="3" width="16" height="1.5" rx="0.75" fill="currentColor"/>
                    <rect x="1" y="8.25" width="16" height="1.5" rx="0.75" fill="currentColor"/>
                    <rect x="1" y="13.5" width="16" height="1.5" rx="0.75" fill="currentColor"/>
                </svg>
            </button>

            <aside class="iris-sidebar" id="iris-sidebar" aria-hidden="true" aria-label="Conversations">
                <div class="iris-sidebar-head">
                    <span class="iris-sidebar-title">Conversations</span>
                    <button class="iris-sidebar-close" id="iris-sidebar-close" aria-label="Close">×</button>
                </div>
                <button class="iris-new-conv-btn" id="iris-new-conv-btn" type="button">+ New conversation</button>
                <ul class="iris-conv-list" id="iris-conv-list" role="list"></ul>
            </aside>
            <div class="iris-sidebar-backdrop" id="iris-sidebar-backdrop" aria-hidden="true"></div>

            <div class="iris-container" id="iris-container"
                data-iris-api="<?php echo $apiAttr; ?>"
                data-iris-conv-api="<?php echo $convApiAttr; ?>"
                data-iris-mem-api="<?php echo $memApiAttr; ?>"
                data-agent-user-id="<?php echo $agentUidAttr; ?>"
                data-business-id="<?php echo $bizIdAttr; ?>"
                data-business-type="retail">

                <div class="iris-core idle" id="iris-core" aria-hidden="true">
                    <svg class="iris-hex-svg" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <polygon points="170,100 135,160.62 65,160.62 30,100 65,39.38 135,39.38"
                            fill="rgba(245,245,220,0.08)" stroke="rgba(245,245,220,0.35)" stroke-width="1.2"/>
                    </svg>
                </div>
                <p class="iris-status" id="iris-status">Iris is ready</p>

                <div class="iris-chat" id="iris-chat" role="log" aria-live="polite" aria-label="Chat" aria-relevant="additions"></div>
                <div class="iris-message" id="iris-message" hidden aria-live="assertive"></div>

                <form class="iris-form" id="iris-form" novalidate>
                    <label class="visually-hidden" for="iris-input">Message Iris</label>
                    <input type="text" class="iris-input" id="iris-input" name="input" autocomplete="off"
                        placeholder="Ask Iris about your store…" />
                </form>

                <button class="iris-mem-btn" id="iris-mem-btn" aria-label="Iris memory" title="Memory" hidden type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26C17.81 13.47 19 11.38 19 9c0-3.87-3.13-7-7-7z"/>
                    </svg>
                    <span class="iris-mem-count" id="iris-mem-count"></span>
                </button>
            </div>

            <div class="iris-mem-panel" id="iris-mem-panel" hidden role="dialog" aria-modal="true" aria-label="Iris memory">
                <div class="iris-mem-panel-head">
                    <span class="iris-mem-panel-title">Memory</span>
                    <button class="iris-mem-panel-close" id="iris-mem-panel-close" aria-label="Close panel" type="button">×</button>
                </div>
                <ul class="iris-mem-list" id="iris-mem-list" role="list"></ul>
                <p class="iris-mem-empty" id="iris-mem-empty" hidden>No saved facts yet.</p>
            </div>
        </div>
    </section>
    <?php else: ?>
    <div class="knd-saas-panel">
        <div class="knd-saas-empty">
            <strong>Workspace not provisioned</strong>
            Contact support if you believe this is an error, or register a new account to create a store.
        </div>
    </div>
    <?php endif; ?>

    <p style="margin-top:2rem;text-align:center;">
        <a class="knd-saas-link" href="/logout.php">Log out</a>
    </p>
</main>
<?php
echo generateFooter();
echo generateScripts();
