<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

admin_require_login();

$pdo = getDBConnection();
if (!$pdo) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Database connection failed.';
    exit;
}

$csrfToken = csrf_token();

$avatars = [];
try {
    $stmt = $pdo->query("
        SELECT s.avatar_id, a.name, s.mind, s.focus, s.speed, s.luck
        FROM avatar_stats s
        JOIN avatars a ON a.id = s.avatar_id
        ORDER BY a.name
    ");
    $avatars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    // Table may not exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avatar Stats | KND Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .avadm { min-height: 100vh; background:#0a0a0f; color:#e8ecf0; padding:2rem 0; --cyan:#00d4ff; }
        .av-card { background:rgba(12,15,22,.9); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1.25rem; margin-bottom:1rem; }
        .av-topbar { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
        .av-title { margin:0; font-size:1.4rem; font-weight:700; }
        .av-muted { color:rgba(255,255,255,.62); font-size:.82rem; }
        .avadm .form-control {
            background-color: rgba(6, 10, 22, 0.95);
            border-color: rgba(255, 255, 255, 0.18);
            color: #e8ecf0;
        }
        .avadm .form-control:focus {
            background-color: rgba(8, 12, 26, 0.98);
            border-color: rgba(0, 212, 255, 0.7);
            color: #ffffff;
            box-shadow: 0 0 0 0.2rem rgba(0, 212, 255, 0.2);
        }
        .stat-row { display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem; }
        .stat-row label { min-width:60px; font-size:.85rem; color:var(--cyan); }
        .stat-row input[type="range"] { flex:1; max-width:180px; accent-color:var(--cyan); }
        .stat-row input[type="number"] { width:60px; text-align:center; }
        .avatar-card-header { font-weight:600; font-size:1.1rem; margin-bottom:1rem; color:#e8ecf0; }
        .avatar-card-stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem; }
        .avatar-card-actions { margin-top:1rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,.08); }
        .stat-total { font-weight:700; color:var(--cyan); font-size:1rem; }
    </style>
</head>
<body class="avadm">
<div class="container">
    <div class="av-topbar">
        <h1 class="av-title"><i class="fas fa-chart-bar me-2" style="color:var(--cyan)"></i>Avatar Stats</h1>
        <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <p class="av-muted mb-4">Edit mind, focus, speed, luck (0–100) per avatar. Changes apply to Mind Wars combat.</p>

    <?php if (empty($avatars)): ?>
        <div class="av-card">
            <p class="av-muted mb-0">No avatar stats found. Ensure avatars and avatar_stats tables exist and are populated.</p>
        </div>
    <?php else: ?>
        <?php foreach ($avatars as $a): ?>
            <?php
            $avatarId = htmlspecialchars($a['avatar_id'] ?? '');
            $name = htmlspecialchars($a['name'] ?? $a['avatar_id'] ?? 'Unknown');
            $mind = (int) ($a['mind'] ?? 10);
            $focus = (int) ($a['focus'] ?? 10);
            $speed = (int) ($a['speed'] ?? 10);
            $luck = (int) ($a['luck'] ?? 10);
            ?>
            <div class="av-card avatar-stats-card" data-avatar-id="<?php echo $avatarId; ?>">
                <div class="avatar-card-header"><?php echo $name; ?></div>
                <form class="avatar-stats-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="avatar_id" value="<?php echo $avatarId; ?>">
                    <div class="avatar-card-stats">
                        <div class="stat-row">
                            <label>Mind</label>
                            <input type="range" name="mind" min="0" max="100" value="<?php echo $mind; ?>">
                            <input type="number" name="mind_num" min="0" max="100" value="<?php echo $mind; ?>">
                        </div>
                        <div class="stat-row">
                            <label>Focus</label>
                            <input type="range" name="focus" min="0" max="100" value="<?php echo $focus; ?>">
                            <input type="number" name="focus_num" min="0" max="100" value="<?php echo $focus; ?>">
                        </div>
                        <div class="stat-row">
                            <label>Speed</label>
                            <input type="range" name="speed" min="0" max="100" value="<?php echo $speed; ?>">
                            <input type="number" name="speed_num" min="0" max="100" value="<?php echo $speed; ?>">
                        </div>
                        <div class="stat-row">
                            <label>Luck</label>
                            <input type="range" name="luck" min="0" max="100" value="<?php echo $luck; ?>">
                            <input type="number" name="luck_num" min="0" max="100" value="<?php echo $luck; ?>">
                        </div>
                        <div class="stat-row mt-2">
                            <label>Total</label>
                            <span class="stat-total"><?php echo $mind + $focus + $speed + $luck; ?></span>
                        </div>
                    </div>
                    <div class="avatar-card-actions">
                        <button type="submit" class="btn btn-outline-info btn-sm">Save</button>
                        <span class="save-feedback ms-2" style="font-size:.85rem;"></span>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    // Sync slider <-> number input
    document.querySelectorAll('.avatar-stats-card').forEach(function (card) {
        const form = card.querySelector('.avatar-stats-form');
        const stats = ['mind', 'focus', 'speed', 'luck'];

        stats.forEach(function (stat) {
            const slider = form.querySelector('input[name="' + stat + '"]');
            const numInput = form.querySelector('input[name="' + stat + '_num"]');
            if (!slider || !numInput) return;

            function updateTotal() {
                const totalEl = form.querySelector('.stat-total');
                if (totalEl) {
                    const m = parseInt(form.querySelector('input[name="mind"]').value, 10) || 0;
                    const f = parseInt(form.querySelector('input[name="focus"]').value, 10) || 0;
                    const s = parseInt(form.querySelector('input[name="speed"]').value, 10) || 0;
                    const l = parseInt(form.querySelector('input[name="luck"]').value, 10) || 0;
                    totalEl.textContent = m + f + s + l;
                }
            }
            slider.addEventListener('input', function () {
                numInput.value = slider.value;
                updateTotal();
            });
            numInput.addEventListener('input', function () {
                let v = parseInt(numInput.value, 10);
                if (isNaN(v)) v = 0;
                v = Math.max(0, Math.min(100, v));
                numInput.value = v;
                slider.value = v;
                updateTotal();
            });
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const feedback = form.querySelector('.save-feedback');
            const oldText = btn.textContent;
            btn.disabled = true;
            feedback.textContent = '';
            feedback.className = 'save-feedback ms-2';
            feedback.style.color = '';

            const formData = new FormData(form);
            formData.set('mind', form.querySelector('input[name="mind"]').value);
            formData.set('focus', form.querySelector('input[name="focus"]').value);
            formData.set('speed', form.querySelector('input[name="speed"]').value);
            formData.set('luck', form.querySelector('input[name="luck"]').value);

            try {
                const res = await fetch('/admin/api/update_avatar_stats.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error(data?.error?.message || 'Could not save stats');
                }
                feedback.textContent = 'Saved';
                feedback.style.color = '#4ade80';
            } catch (err) {
                feedback.textContent = 'Error: ' + (err.message || 'Could not update');
                feedback.style.color = '#f87171';
            } finally {
                btn.textContent = 'Save';
                btn.disabled = false;
                setTimeout(function () { feedback.textContent = ''; }, 2500);
            }
        });
    });
})();
</script>
</body>
</html>
