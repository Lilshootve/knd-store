<?php
/**
 * KND Store — primary signed-in app shell (dashboard + Iris + retail snapshot).
 */
require_once __DIR__ . '/config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/core/retail/auth.php';

require_login();

$pdo = getDBConnection();
if ($pdo) {
    auth_refresh_session_tenant($pdo);
}

$uid = current_user_id();
$bid = current_business_id();
$bizName = null;
$baseCurrency = 'USD';
$retailLegacyAdmin = false;

if ($pdo && $uid && $bid) {
    $st = $pdo->prepare(
        'SELECT b.name, b.base_currency FROM businesses b
         INNER JOIN business_users bu ON bu.business_id = b.id AND bu.user_id = ?
         WHERE b.id = ? AND b.active = 1
         LIMIT 1'
    );
    $st->execute([$uid, $bid]);
    $bizRow = $st->fetch(PDO::FETCH_ASSOC);
    if ($bizRow) {
        $bizName = is_string($bizRow['name'] ?? null) ? $bizRow['name'] : null;
        if (!empty($bizRow['base_currency'])) {
            $baseCurrency = (string) $bizRow['base_currency'];
        }
    }
    if ($bizName !== null && retail_resolve_business_for_gateway($pdo, (int) $uid)) {
        $retailLegacyAdmin = retail_is_admin();
    }
}

$hasWorkspace = $bizName !== null;

$mtdTotal = 0.0;
$mtdCount = 0;
$todayTotal = 0.0;
$todayCount = 0;
$customerCount = 0;
$pendingCreditsSum = 0.0;
$debtorCount = 0;
$recentSales = [];
$recentAudit = [];

if ($hasWorkspace && $pdo && $bid) {
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    try {
        $q = $pdo->prepare(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_base), 0) AS total
             FROM retail_sales WHERE business_id = ? AND DATE(created_at) >= ? AND DATE(created_at) <= ?'
        );
        $q->execute([(int) $bid, $monthStart, $today]);
        $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        $mtdCount = (int) ($row['cnt'] ?? 0);
        $mtdTotal = (float) ($row['total'] ?? 0);

        $q->execute([(int) $bid, $today, $today]);
        $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        $todayCount = (int) ($row['cnt'] ?? 0);
        $todayTotal = (float) ($row['total'] ?? 0);

        $q = $pdo->prepare('SELECT COUNT(*) FROM retail_customers WHERE business_id = ?');
        $q->execute([(int) $bid]);
        $customerCount = (int) $q->fetchColumn();

        $q = $pdo->prepare(
            'SELECT COALESCE(SUM(balance), 0) FROM retail_credits WHERE business_id = ? AND balance > 0'
        );
        $q->execute([(int) $bid]);
        $pendingCreditsSum = (float) $q->fetchColumn();
        $q = $pdo->prepare(
            'SELECT COUNT(*) FROM retail_credits WHERE business_id = ? AND balance > 0'
        );
        $q->execute([(int) $bid]);
        $debtorCount = (int) $q->fetchColumn();

        $q = $pdo->prepare(
            'SELECT s.id, s.type, s.total_base, s.currency_used, s.created_at,
                    c.name AS customer_name
             FROM retail_sales s
             LEFT JOIN retail_customers c ON c.id = s.customer_id AND c.business_id = s.business_id
             WHERE s.business_id = ?
             ORDER BY s.created_at DESC LIMIT 15'
        );
        $q->execute([(int) $bid]);
        $recentSales = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $q = $pdo->prepare(
            'SELECT action, entity_type, created_at FROM retail_audit_logs
             WHERE business_id = ? ORDER BY created_at DESC LIMIT 8'
        );
        $q->execute([(int) $bid]);
        $recentAudit = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        $recentSales = [];
        $recentAudit = [];
    }
}

function knd_dashboard_fmt_money(float $amount, string $currency): string {
    return htmlspecialchars($currency . ' ' . number_format($amount, 2), ENT_QUOTES, 'UTF-8');
}

function knd_dashboard_fmt_dt(string $dt): string {
    $t = strtotime($dt);
    return $t ? htmlspecialchars(date('M j, Y g:i A', $t), ENT_QUOTES, 'UTF-8') : '';
}

require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

$irisCss = BASE_PATH . '/assets/css/iris.css';
$irisJs  = BASE_PATH . '/assets/js/iris.js';
$saasCss = BASE_PATH . '/assets/css/saas.css';
$dashShell = BASE_PATH . '/assets/css/dashboard-shell.css';
$irisDash = BASE_PATH . '/assets/css/iris-dashboard.css';
$vIrisCss = file_exists($irisCss) ? filemtime($irisCss) : 0;
$vIrisJs  = file_exists($irisJs) ? filemtime($irisJs) : 0;
$vSaas    = file_exists($saasCss) ? filemtime($saasCss) : 0;
$vDash    = file_exists($dashShell) ? filemtime($dashShell) : 0;
$vIrisD   = file_exists($irisDash) ? filemtime($irisDash) : 0;

$extraHead  = '<link rel="stylesheet" href="/assets/css/saas.css?v=' . (int) $vSaas . '">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/dashboard-shell.css?v=' . (int) $vDash . '">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/iris.css?v=' . (int) $vIrisCss . '">' . "\n";
$extraHead .= '    <link rel="stylesheet" href="/assets/css/iris-dashboard.css?v=' . (int) $vIrisD . '">' . "\n";
$extraHead .= '    <script src="/assets/js/iris.js?v=' . (int) $vIrisJs . '" defer></script>' . "\n";

$title = 'Dashboard | KND Store';
$desc  = 'Your KND Store workspace.';

$apiAttr     = htmlspecialchars('/api/iris.php', ENT_QUOTES, 'UTF-8');
$convApiAttr = htmlspecialchars('/api/iris-conversations.php', ENT_QUOTES, 'UTF-8');
$memApiAttr  = htmlspecialchars('/api/iris-memory.php', ENT_QUOTES, 'UTF-8');
$agentUidAttr = $uid && $uid > 0 ? htmlspecialchars((string) (int) $uid, ENT_QUOTES, 'UTF-8') : '';
$bizIdAttr    = $hasWorkspace && $bid ? htmlspecialchars((string) (int) $bid, ENT_QUOTES, 'UTF-8') : '';

echo generateHeader($title, $desc, $extraHead, true);

$uWelcome = current_username();
$uWelcomeEsc = $uWelcome ? htmlspecialchars($uWelcome, ENT_QUOTES, 'UTF-8') : '';
$bizNameEsc = $bizName ? htmlspecialchars($bizName, ENT_QUOTES, 'UTF-8') : '';
?>
<div class="knd-dash-app" id="knd-dash-app">
    <div class="knd-dash-sidebar-backdrop" id="knd-dash-sidebar-backdrop" aria-hidden="true" data-knd-dash-sidebar-close></div>
    <aside class="knd-dash-sidebar" id="knd-dash-sidebar" aria-label="Main navigation">
        <div class="knd-dash-sidebar-brand">
            <a href="/dashboard.php">KND</a>
            <span>Store</span>
        </div>
        <nav class="knd-dash-nav" aria-label="App">
            <a class="knd-dash-nav--active" href="/dashboard.php" data-knd-dash-sidebar-close>Dashboard</a>
            <?php if ($hasWorkspace && $retailLegacyAdmin): ?>
            <div class="knd-dash-nav-section-label">Legacy admin <small>(retail)</small></div>
            <a href="/retail-admin/dashboard.php" data-knd-dash-sidebar-close>Retail</a>
            <a href="/retail-admin/credits.php" data-knd-dash-sidebar-close>Customers</a>
            <a href="/retail-admin/inventory.php" data-knd-dash-sidebar-close>Products</a>
            <a href="/retail-admin/sales.php" data-knd-dash-sidebar-close>Sales</a>
            <?php endif; ?>
            <?php if ($hasWorkspace): ?>
            <a href="#knd-iris-section" data-knd-scroll-to="iris" data-knd-dash-sidebar-close>AI Assistant</a>
            <?php endif; ?>
            <a href="/my-profile.php" data-knd-dash-sidebar-close>Settings</a>
        </nav>
        <div class="knd-dash-sidebar-foot">
            <a href="/contact.php" data-knd-dash-sidebar-close>Support</a>
            <a href="/logout.php">Log out</a>
        </div>
    </aside>
    <div class="knd-dash-main-wrap">
        <header class="knd-dash-topbar">
            <button type="button" class="knd-dash-menu-toggle" id="knd-dash-sidebar-open" aria-expanded="false" aria-controls="knd-dash-sidebar" aria-label="Open menu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="knd-dash-topbar-title">KND Store</span>
            <span style="width:40px" aria-hidden="true"></span>
        </header>
        <main class="knd-dash-main" id="knd-dashboard">
            <header class="knd-dash-header-block">
                <h1>Welcome<?php echo $uWelcomeEsc !== '' ? ', ' . $uWelcomeEsc : ''; ?></h1>
                <?php if ($hasWorkspace): ?>
                <p><?php echo $bizNameEsc; ?></p>
                <?php else: ?>
                <p>No active workspace is linked to your account.</p>
                <?php endif; ?>
            </header>

            <?php if ($hasWorkspace): ?>
            <section class="knd-dash-kpis" aria-label="Key metrics">
                <div class="knd-kpi-card">
                    <div class="knd-kpi-label">MTD revenue</div>
                    <div class="knd-kpi-value"><?php echo knd_dashboard_fmt_money($mtdTotal, $baseCurrency); ?></div>
                    <div class="knd-kpi-sub"><?php echo (int) $mtdCount; ?> transactions this month</div>
                </div>
                <div class="knd-kpi-card">
                    <div class="knd-kpi-label">Sales today</div>
                    <div class="knd-kpi-value"><?php echo knd_dashboard_fmt_money($todayTotal, $baseCurrency); ?></div>
                    <div class="knd-kpi-sub"><?php echo (int) $todayCount; ?> transactions</div>
                </div>
                <div class="knd-kpi-card">
                    <div class="knd-kpi-label">Customers</div>
                    <div class="knd-kpi-value"><?php echo number_format((int) $customerCount); ?></div>
                    <div class="knd-kpi-sub">Registered in workspace</div>
                </div>
                <div class="knd-kpi-card">
                    <div class="knd-kpi-label">Pending credits</div>
                    <div class="knd-kpi-value"><?php echo knd_dashboard_fmt_money($pendingCreditsSum, $baseCurrency); ?></div>
                    <div class="knd-kpi-sub"><?php echo (int) $debtorCount; ?> accounts with balance</div>
                </div>
            </section>

            <div class="knd-dash-grid">
                <div class="knd-dash-stack">
                    <section class="knd-dash-panel" id="knd-iris-section" aria-labelledby="knd-iris-heading">
                        <h2 id="knd-iris-heading">AI Assistant</h2>
                        <div class="knd-iris-suggestions">
                            <button type="button" class="knd-iris-chip" data-iris-prompt="Register payment">Register payment</button>
                            <button type="button" class="knd-iris-chip" data-iris-prompt="Create sale">Create sale</button>
                            <button type="button" class="knd-iris-chip" data-iris-prompt="View customers">View customers</button>
                        </div>
                        <div class="iris-page iris-page--embed iris-page--saas" id="iris-page">
                            <button class="iris-menu-btn" id="iris-menu-btn" type="button" aria-label="Conversations" title="Conversations">
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <rect x="1" y="3" width="16" height="1.5" rx="0.75" fill="currentColor"/>
                                    <rect x="1" y="8.25" width="16" height="1.5" rx="0.75" fill="currentColor"/>
                                    <rect x="1" y="13.5" width="16" height="1.5" rx="0.75" fill="currentColor"/>
                                </svg>
                            </button>

                            <aside class="iris-sidebar" id="iris-sidebar" aria-hidden="true" aria-label="Conversations">
                                <div class="iris-sidebar-head">
                                    <span class="iris-sidebar-title">Conversations</span>
                                    <button class="iris-sidebar-close" id="iris-sidebar-close" type="button" aria-label="Close">×</button>
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
                                            fill="var(--knd-accent-08)" stroke="var(--knd-accent-12)" stroke-width="1.2"/>
                                    </svg>
                                </div>
                                <p class="iris-status" id="iris-status">Iris is ready</p>

                                <div class="iris-chat" id="iris-chat" role="log" aria-live="polite" aria-label="Chat" aria-relevant="additions"></div>
                                <div class="iris-message" id="iris-message" hidden aria-live="assertive"></div>

                                <form class="iris-form" id="iris-form" novalidate>
                                    <label class="knd-sr-only" for="iris-input">Message to AI Assistant</label>
                                    <input type="text" class="iris-input" id="iris-input" name="input" autocomplete="off"
                                        placeholder="Ask about sales, customers, or payments…" />
                                    <button type="submit" class="knd-iris-send">Send</button>
                                </form>

                                <button class="iris-mem-btn" id="iris-mem-btn" aria-label="Memory" title="Memory" hidden type="button">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26C17.81 13.47 19 11.38 19 9c0-3.87-3.13-7-7-7z"/>
                                    </svg>
                                    <span class="iris-mem-count" id="iris-mem-count"></span>
                                </button>
                            </div>

                            <div class="iris-mem-panel" id="iris-mem-panel" hidden role="dialog" aria-modal="true" aria-label="Memory">
                                <div class="iris-mem-panel-head">
                                    <span class="iris-mem-panel-title">Memory</span>
                                    <button class="iris-mem-panel-close" id="iris-mem-panel-close" aria-label="Close" type="button">×</button>
                                </div>
                                <ul class="iris-mem-list" id="iris-mem-list" role="list"></ul>
                                <p class="iris-mem-empty" id="iris-mem-empty" hidden>No saved facts yet.</p>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="knd-dash-stack">
                    <section class="knd-dash-panel" aria-labelledby="knd-quick-heading">
                        <h2 id="knd-quick-heading">Quick actions</h2>
                        <div class="knd-quick-actions">
                            <button type="button" class="knd-btn-action knd-iris-chip" data-iris-prompt="Create a new sale">New sale</button>
                            <button type="button" class="knd-btn-action knd-iris-chip" data-iris-prompt="Add a new customer">Add customer</button>
                            <button type="button" class="knd-btn-action knd-iris-chip" data-iris-prompt="Register a payment">Register payment</button>
                        </div>
                    </section>
                    <section class="knd-dash-panel" aria-labelledby="knd-activity-heading">
                        <h2 id="knd-activity-heading">Recent activity</h2>
                        <?php if ($recentAudit !== []): ?>
                        <ul class="knd-activity-list">
                            <?php foreach ($recentAudit as $log): ?>
                            <li>
                                <?php echo htmlspecialchars((string) ($log['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($log['entity_type'])): ?>
                                <span class="meta"><?php echo htmlspecialchars((string) $log['entity_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <span class="meta"><?php echo knd_dashboard_fmt_dt((string) ($log['created_at'] ?? '')); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="knd-kpi-sub" style="margin:0">No audit events yet.</p>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

            <section class="knd-dash-table-wrap" aria-labelledby="knd-sales-heading">
                <h2 id="knd-sales-heading">Recent sales</h2>
                <table class="knd-data-table">
                    <thead>
                        <tr>
                            <th scope="col">Customer</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSales as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($s['customer_name'] ?? 'Walk-in'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo knd_dashboard_fmt_money((float) ($s['total_base'] ?? 0), $baseCurrency); ?>
                                <?php if (!empty($s['currency_used']) && strtoupper((string) $s['currency_used']) !== strtoupper($baseCurrency)): ?>
                                <span class="knd-kpi-sub" style="display:block;margin-top:2px"><?php echo htmlspecialchars((string) $s['currency_used'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php $ty = $s['type'] ?? 'cash'; echo htmlspecialchars($ty === 'credit' ? 'Credit' : 'Cash', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo knd_dashboard_fmt_dt((string) ($s['created_at'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($recentSales === []): ?>
                        <tr><td colspan="4" class="knd-dash-empty" style="border:none">No sales recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
            <?php else: ?>
            <div class="knd-dash-panel" style="margin-top:1.5rem">
                <div class="knd-dash-empty">
                    <strong>Workspace not provisioned</strong>
                    Contact support if you believe this is an error, or register a new account to create a store.
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php
echo generateFooter(true);
echo generateScripts(true);
