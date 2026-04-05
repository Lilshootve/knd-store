<?php
/**
 * KND Retail Admin — Layout helpers
 * retail_header(string $title, string $activeNav): void
 * retail_footer(): void
 */

function retail_header(string $title, string $activeNav = ''): void
{
    global $RETAIL_BIZ, $RETAIL_USER;
    $bizName  = htmlspecialchars($RETAIL_BIZ['name'] ?? 'Mi Negocio');
    $currency = htmlspecialchars($RETAIL_BIZ['base_currency'] ?? 'USD');
    $user     = htmlspecialchars($RETAIL_USER ?? '');
    $title    = htmlspecialchars($title);

    $nav = [
        'dashboard'      => ['url' => '/retail-admin/dashboard.php',      'icon' => '📊', 'label' => 'Dashboard'],
        'sales'          => ['url' => '/retail-admin/sales.php',           'icon' => '🛒', 'label' => 'Ventas'],
        'inventory'      => ['url' => '/retail-admin/inventory.php',       'icon' => '📦', 'label' => 'Inventario'],
        'credits'        => ['url' => '/retail-admin/credits.php',         'icon' => '💳', 'label' => 'Créditos'],
        'exchange-rates' => ['url' => '/retail-admin/exchange-rates.php',  'icon' => '💱', 'label' => 'Tasas'],
        'audit-logs'     => ['url' => '/retail-admin/audit-logs.php',      'icon' => '🔍', 'label' => 'Auditoría'],
    ];

    echo '<!DOCTYPE html><html lang="es"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo "<title>$title — KND Retail Admin</title>";
    echo '<style>
:root{--bg:#0d1117;--sidebar:#161b22;--card:#1c2128;--border:#30363d;--text:#e6edf3;--muted:#7d8590;--accent:#58a6ff;--green:#3fb950;--red:#f85149;--yellow:#d29922;--purple:#a371f7;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:"Segoe UI",Arial,sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}
.sidebar{width:220px;background:var(--sidebar);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;padding:20px 0;}
.sidebar-brand{padding:0 20px 20px;border-bottom:1px solid var(--border);margin-bottom:12px;}
.sidebar-brand .biz-name{font-size:15px;font-weight:700;color:var(--accent);}
.sidebar-brand .currency{font-size:11px;color:var(--muted);margin-top:2px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--muted);text-decoration:none;font-size:14px;transition:all .15s;}
.nav-item:hover,.nav-item.active{background:rgba(88,166,255,.08);color:var(--text);}
.nav-item.active{border-left:2px solid var(--accent);color:var(--accent);}
.sidebar-footer{margin-top:auto;padding:16px 20px;border-top:1px solid var(--border);font-size:12px;color:var(--muted);}
.main{margin-left:220px;flex:1;padding:28px 32px;}
.page-title{font-size:22px;font-weight:700;margin-bottom:24px;color:var(--text);}
.card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:20px;margin-bottom:20px;}
.card-title{font-size:13px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:20px;}
.stat{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px 20px;}
.stat-label{font-size:12px;color:var(--muted);margin-bottom:6px;}
.stat-value{font-size:22px;font-weight:700;color:var(--text);}
.stat-sub{font-size:11px;color:var(--muted);margin-top:4px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th{background:rgba(255,255,255,.04);color:var(--muted);padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);}
td{padding:10px 12px;border-bottom:1px solid rgba(48,54,61,.5);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(255,255,255,.02);}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;}
.badge-green{background:rgba(63,185,80,.15);color:var(--green);}
.badge-red{background:rgba(248,81,73,.15);color:var(--red);}
.badge-yellow{background:rgba(210,153,34,.15);color:var(--yellow);}
.badge-blue{background:rgba(88,166,255,.15);color:var(--accent);}
.badge-purple{background:rgba(163,113,247,.15);color:var(--purple);}
.alert{padding:10px 16px;border-radius:6px;font-size:13px;margin-bottom:16px;}
.alert-warning{background:rgba(210,153,34,.12);border:1px solid rgba(210,153,34,.3);color:var(--yellow);}
.alert-danger{background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);color:var(--red);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;}
.btn-primary{background:var(--accent);color:#0d1117;}
.btn-danger{background:var(--red);color:#fff;}
.btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--border);}
.btn:hover{opacity:.85;}
@media(max-width:768px){.sidebar{display:none;}.main{margin-left:0;padding:16px;}}
</style></head><body>';

    echo '<div class="sidebar">';
    echo "<div class='sidebar-brand'><div class='biz-name'>$bizName</div><div class='currency'>$currency · Admin</div></div>";

    foreach ($nav as $key => $item) {
        $cls = $activeNav === $key ? ' active' : '';
        echo "<a class='nav-item$cls' href='{$item['url']}'>{$item['icon']} {$item['label']}</a>";
    }

    echo "<div class='sidebar-footer'>👤 $user</div></div>";
    echo '<div class="main">';
    echo "<div class='page-title'>$title</div>";
}

function retail_footer(): void
{
    echo '</div></body></html>';
}

/**
 * Helper: formatear monto con moneda.
 */
function retail_fmt(float $amount, string $currency): string
{
    return number_format($amount, 2) . ' ' . htmlspecialchars($currency);
}
