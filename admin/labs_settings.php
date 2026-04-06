<?php
/**
 * KND Labs - Provider settings (admin only)
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('system.storage_diag');

require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/settings.php';
require_once BASE_PATH . '/includes/comfyui_provider.php';

$pdo = getDBConnection();
if (!$pdo) {
    header('Content-Type: text/html; charset=utf-8');
    die('Database unavailable.');
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    if ($action === 'save') {
        $mode = trim($_POST['provider_mode'] ?? '');
        if (in_array($mode, [PROVIDER_MODE_LOCAL, PROVIDER_MODE_RUNPOD, PROVIDER_MODE_AUTO], true)) {
            settings_set($pdo, 'labs_provider_mode', $mode);
        }
        settings_set($pdo, 'comfyui_base_url_local', trim($_POST['comfyui_base_url_local'] ?? ''));
        settings_set($pdo, 'comfyui_base_url_runpod', trim($_POST['comfyui_base_url_runpod'] ?? ''));
        settings_set($pdo, 'comfyui_default_ckpt', trim($_POST['comfyui_default_ckpt'] ?? ''));
        $timeout = max(500, min(30000, (int) ($_POST['auto_timeout_ms'] ?? 3000)));
        settings_set($pdo, 'labs_auto_timeout_ms', (string) $timeout);
        $token = trim($_POST['comfyui_token'] ?? '');
        if ($token !== '') {
            settings_set($pdo, 'comfyui_token', $token);
        }
        $msg = 'Settings saved.';
    } elseif ($action === 'regenerate_token') {
        $newToken = bin2hex(random_bytes(24));
        settings_set($pdo, 'comfyui_token', $newToken);
        $msg = 'Token regenerated. Copy it now: ' . htmlspecialchars($newToken);
    }
}

$mode = comfyui_get_provider_mode($pdo);
$localUrl = comfyui_get_base_url_local($pdo);
$runpodUrl = comfyui_get_base_url_runpod($pdo);
$defaultCkpt = settings_get($pdo, 'comfyui_default_ckpt', '');
$timeoutMs = comfyui_get_auto_timeout_ms($pdo);
$tokenVal = comfyui_get_token($pdo);
$tokenMasked = $tokenVal !== '' ? (substr($tokenVal, 0, 8) . '…') : '';

require_once BASE_PATH . '/includes/header.php';
echo generateHeader('Labs Settings | KND Admin', 'ComfyUI Provider configuration');
echo generateAdminBar();
?>
<style>
.labs-admin { --cyan: #00d4ff; min-height: 100vh; background: #0a0a0f; color: #e8ecf0; padding: 100px 1rem 2rem; }
.labs-admin .card { background: rgba(12,15,22,.85); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; }
.labs-admin .form-control, .labs-admin .form-select { background: #1a1a22; border-color: #333; color: #fff; }
.labs-admin .form-control:focus { border-color: var(--cyan); box-shadow: 0 0 0 0.2rem rgba(0,212,255,.25); }
.labs-admin .btn-cyber { background: linear-gradient(135deg, var(--cyan), #259cae); border: none; color: #0a0a0f; }
.labs-admin .test-result { font-family: monospace; font-size: .85rem; }
</style>
<div class="labs-admin">
<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="mb-0" style="color:var(--cyan);"><i class="fas fa-microscope me-2"></i>Labs Settings</h1>
        <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?php echo $msg; ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <div class="card p-4 mb-4">
        <h5 class="mb-3">Provider Mode</h5>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <div class="mb-3">
                <label class="form-label">Mode</label>
                <select name="provider_mode" class="form-select" style="max-width:200px;">
                    <option value="auto" <?php echo $mode === PROVIDER_MODE_AUTO ? 'selected' : ''; ?>>Auto (local first, fallback runpod)</option>
                    <option value="local" <?php echo $mode === PROVIDER_MODE_LOCAL ? 'selected' : ''; ?>>Local (Cloudflare Tunnel)</option>
                    <option value="runpod" <?php echo $mode === PROVIDER_MODE_RUNPOD ? 'selected' : ''; ?>>RunPod</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Local Base URL</label>
                <input type="url" name="comfyui_base_url_local" class="form-control" value="<?php echo htmlspecialchars($localUrl); ?>" placeholder="https://comfy.kndstore.com">
            </div>
            <div class="mb-3">
                <label class="form-label">RunPod Base URL</label>
                <input type="url" name="comfyui_base_url_runpod" class="form-control" value="<?php echo htmlspecialchars($runpodUrl); ?>" placeholder="https://xxx.proxy.runpod.net">
            </div>
            <div class="mb-3">
                <label class="form-label">Default Checkpoint</label>
                <input type="text" name="comfyui_default_ckpt" class="form-control" value="<?php echo htmlspecialchars($defaultCkpt); ?>" placeholder="e.g. v1-5-pruned-emaonly.safetensors" style="max-width:400px;">
                <small class="text-white-50">Override: use this checkpoint for all models. Must exist in ComfyUI models/checkpoints. Leave empty to use built-in SDXL models (DreamShaper, etc.).</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Auto Timeout (ms)</label>
                <input type="number" name="auto_timeout_ms" class="form-control" value="<?php echo (int) $timeoutMs; ?>" min="500" max="30000" step="100" style="max-width:150px;">
            </div>
            <div class="mb-4">
                <label class="form-label">Token (X-KND-TOKEN)</label>
                <input type="password" name="comfyui_token" class="form-control" value="" placeholder="<?php echo $tokenMasked ?: 'Leave blank to keep current'; ?>" autocomplete="new-password" style="max-width:400px;">
                <small class="text-white-50">Only set if changing. Never expose to frontend.</small>
            </div>
            <button type="submit" class="btn btn-cyber">Save</button>
        </form>
    </div>

    <div class="card p-4 mb-4">
        <h5 class="mb-3">Test Providers</h5>
        <button type="button" id="labs-test-btn" class="btn btn-outline-info mb-3"><i class="fas fa-vial me-1"></i>Test Providers</button>
        <div id="labs-test-result" class="test-result text-white-50" style="display:none;"></div>
    </div>

    <div class="card p-4">
        <h5 class="mb-2">Regenerate Token</h5>
        <p class="text-white-50 small">Generate a new token. Update ComfyUI/Cloudflare to accept it.</p>
        <form method="post" onsubmit="return confirm('Regenerate token? You must update ComfyUI/Cloudflare with the new value.');">
            <input type="hidden" name="action" value="regenerate_token">
            <button type="submit" class="btn btn-outline-warning btn-sm">Regenerate Token</button>
        </form>
    </div>
</div>
</div>
<script>
(function() {
    var btn = document.getElementById('labs-test-btn');
    var out = document.getElementById('labs-test-result');
    if (!btn || !out) return;
    btn.addEventListener('click', function() {
        btn.disabled = true;
        out.style.display = 'block';
        out.textContent = 'Testing...';
        fetch('/api/labs/provider_test.php', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                if (d.ok && d.data) {
                    var x = d.data;
                    var s = 'Local: ' + (x.local && x.local.ok ? 'OK' : 'FAIL') + (x.local && x.local.latency_ms != null ? ' (' + x.local.latency_ms + ' ms)' : '') + '\n';
                    s += 'RunPod: ' + (x.runpod && x.runpod.ok ? 'OK' : 'FAIL') + (x.runpod && x.runpod.latency_ms != null ? ' (' + x.runpod.latency_ms + ' ms)' : '') + '\n';
                    s += 'Chosen: ' + (x.chosen_provider || '—');
                    out.textContent = s;
                } else {
                    out.textContent = 'Error: ' + (d.error && d.error.message ? d.error.message : 'Unknown');
                }
            })
            .catch(function(e) {
                btn.disabled = false;
                out.textContent = 'Network error: ' + (e.message || 'Failed');
            });
    });
})();
</script>
<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>
