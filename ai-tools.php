<?php
/**
 * KND AI Asset Creator - Text2Img, Upscale, Character Lab, Image→3D
 */
require_once __DIR__ . '/config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/support_credits.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

require_login();

$pdo = getDBConnection();
$balance = 0;
if ($pdo) {
    release_available_points_if_due($pdo, current_user_id());
    expire_points_if_due($pdo, current_user_id());
    $balance = get_available_points($pdo, current_user_id());
}

$errorMsg = '';
if (isset($_GET['error'])) {
    $map = [
        'missing' => t('ai.error.missing', 'Missing job ID'),
        'not_found' => t('ai.error.not_found', 'Job not found'),
        'forbidden' => t('ai.error.forbidden', 'Access denied'),
        'not_ready' => t('ai.error.not_ready', 'Result not ready yet'),
        'file_missing' => t('ai.error.file_missing', 'File not found'),
        'db' => 'Database error',
        'server' => 'Server error',
    ];
    $errorMsg = $map[$_GET['error']] ?? 'An error occurred';
}

$aiCss = __DIR__ . '/assets/css/ai-tools.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
echo generateHeader(t('ai.meta.title'), t('ai.hero.desc'), $extraCss);
?>

<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="hero-section ai-hero py-5">
    <div class="container text-center">
        <h1 class="hero-title"><span class="text-gradient"><?php echo t('ai.hero.title'); ?></span></h1>
        <p class="hero-subtitle text-white-50"><?php echo t('ai.hero.desc'); ?></p>
        <div class="ai-balance-badge mb-4">
            <i class="fas fa-coins me-2"></i>
            <span id="ai-kp-balance"><?php echo t('ai.balance', 'Balance: {kp} KP', ['kp' => number_format($balance)]); ?></span>
        </div>
    </div>
</section>

<section class="py-5 ai-tools-section">
    <div class="container">
        <?php if ($errorMsg): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4">
            <i class="fas fa-exclamation-triangle me-3"></i>
            <span><?php echo htmlspecialchars($errorMsg); ?></span>
        </div>
        <?php endif; ?>

        <!-- Text → Image -->
        <div class="glass-card-neon ai-tool-card p-4 mb-4">
            <h4 class="text-white mb-3"><i class="fas fa-font me-2" style="color: var(--knd-neon-blue);"></i><?php echo t('ai.text2img.title'); ?></h4>
            <form id="ai-text2img-form" class="ai-form">
                <input type="hidden" name="type" value="text2img">
                <div class="mb-3">
                    <label class="form-label text-white-50"><?php echo t('ai.text2img.prompt'); ?></label>
                    <textarea name="prompt" class="form-control bg-dark text-white" rows="3" maxlength="500" placeholder="Describe the image..."></textarea>
                    <div class="form-text text-white-50"><small>Max 500 chars</small></div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="t2i-standard" value="standard" checked>
                        <label class="form-check-label text-white-50" for="t2i-standard"><?php echo t('ai.text2img.mode_standard'); ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="t2i-high" value="high">
                        <label class="form-check-label text-white-50" for="t2i-high"><?php echo t('ai.text2img.mode_high'); ?></label>
                    </div>
                </div>
                <button type="submit" class="btn btn-neon-primary" id="ai-t2i-submit">
                    <i class="fas fa-magic me-1"></i><?php echo t('ai.text2img.generate'); ?>
                </button>
            </form>
        </div>

        <!-- Upscale -->
        <div class="glass-card-neon ai-tool-card p-4 mb-4">
            <h4 class="text-white mb-3"><i class="fas fa-search-plus me-2" style="color: var(--knd-neon-blue);"></i><?php echo t('ai.upscale.title'); ?></h4>
            <form id="ai-upscale-form" class="ai-form">
                <input type="hidden" name="type" value="upscale">
                <div class="mb-3">
                    <div class="ai-dropzone" id="ai-upscale-dropzone">
                        <input type="file" name="image" id="ai-upscale-file" accept="image/jpeg,image/jpg,image/png,image/webp" hidden>
                        <div id="ai-upscale-content">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-white-50"></i>
                            <p class="mb-0 text-white-50"><?php echo t('ai.upscale.upload'); ?></p>
                            <p class="small text-white-50">JPG, PNG, WebP. Max 10MB, 4096×4096</p>
                        </div>
                        <div id="ai-upscale-preview" style="display:none;">
                            <img id="ai-upscale-preview-img" src="" alt="" style="max-height:120px;">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <select name="scale" class="form-select bg-dark text-white w-auto">
                        <option value="2">2x</option>
                        <option value="4">4x</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-neon-primary" id="ai-upscale-submit" disabled>
                    <i class="fas fa-search-plus me-1"></i>Upscale (5 KP)
                </button>
            </form>
        </div>

        <!-- Character Lab -->
        <div class="glass-card-neon ai-tool-card p-4 mb-4">
            <h4 class="text-white mb-3"><i class="fas fa-user-astronaut me-2" style="color: var(--knd-neon-blue);"></i><?php echo t('ai.character.title'); ?></h4>
            <form id="ai-character-form" class="ai-form">
                <input type="hidden" name="type" value="character_create">
                <div class="mb-3">
                    <label class="form-label text-white-50"><?php echo t('ai.text2img.prompt'); ?></label>
                    <textarea name="prompt" class="form-control bg-dark text-white" rows="2" maxlength="500" placeholder="Describe your character..."></textarea>
                </div>
                <div class="mb-3">
                    <select name="style" class="form-select bg-dark text-white w-auto">
                        <option value="game">Game</option>
                        <option value="anime">Anime</option>
                        <option value="realistic">Realistic</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-neon-primary" id="ai-char-submit">
                    <i class="fas fa-user-plus me-1"></i><?php echo t('ai.character.create'); ?>
                </button>
            </form>
        </div>

        <!-- Texture Lab -->
        <div class="glass-card-neon ai-tool-card p-4 mb-4">
            <h4 class="text-white mb-3"><i class="fas fa-border-all me-2" style="color: var(--knd-neon-blue);"></i><?php echo t('ai.texture.title'); ?></h4>
            <form id="ai-texture-form" class="ai-form">
                <input type="hidden" name="type" value="texture_seamless">
                <div class="mb-3">
                    <label class="form-label text-white-50"><?php echo t('ai.text2img.prompt'); ?></label>
                    <textarea name="prompt" class="form-control bg-dark text-white" rows="2" maxlength="500" placeholder="<?php echo t('ai.texture.prompt_placeholder', 'e.g. brick wall, wood grain, marble...'); ?>"></textarea>
                </div>
                <button type="submit" class="btn btn-neon-primary" id="ai-texture-submit">
                    <i class="fas fa-border-all me-1"></i><?php echo t('ai.texture.generate'); ?>
                </button>
            </form>
        </div>

        <!-- 3D Lab link -->
        <div class="glass-card-neon ai-tool-card p-4 mb-4">
            <h4 class="text-white mb-3"><i class="fas fa-cube me-2" style="color: var(--knd-neon-blue);"></i>3D Lab</h4>
            <a href="/labs-3d-lab.php" class="btn btn-outline-primary">Generate 3D Model</a>
        </div>

        <!-- Status panel -->
        <div class="glass-card-neon ai-status-card p-4 mt-4" id="ai-status-panel" style="display:none;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="ai-spinner me-3" id="ai-spinner"><i class="fas fa-cog fa-spin"></i></div>
                    <div>
                        <strong id="ai-status-text"><?php echo t('ai.status.processing'); ?></strong>
                        <div class="small text-white-50" id="ai-status-detail"></div>
                    </div>
                </div>
                <div id="ai-download-wrap" style="display:none;">
                    <a href="#" class="btn btn-success" id="ai-download-btn">
                        <i class="fas fa-download me-2"></i><?php echo t('ai.download'); ?>
                    </a>
                </div>
            </div>
            <div class="alert alert-danger mt-3" id="ai-error-msg" style="display:none;"></div>
        </div>
    </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>
<script>
(function() {
    const balanceEl = document.getElementById('ai-kp-balance');
    const statusPanel = document.getElementById('ai-status-panel');
    const statusText = document.getElementById('ai-status-text');
    const statusDetail = document.getElementById('ai-status-detail');
    const spinner = document.getElementById('ai-spinner');
    const downloadWrap = document.getElementById('ai-download-wrap');
    const downloadBtn = document.getElementById('ai-download-btn');
    const errorMsg = document.getElementById('ai-error-msg');
    let pollInterval = null;
    let currentJobId = null;

    function updateBalance(kp) {
        if (balanceEl) balanceEl.textContent = 'Balance: ' + parseInt(kp, 10).toLocaleString() + ' KP';
    }

    function showStatus(jobId) {
        currentJobId = jobId;
        statusPanel.style.display = 'block';
        statusText.textContent = 'Processing...';
        statusDetail.textContent = '';
        spinner.style.display = 'inline-block';
        downloadWrap.style.display = 'none';
        errorMsg.style.display = 'none';
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(function() {
            fetch('/api/ai/status.php?job_id=' + encodeURIComponent(jobId))
                .then(r => r.json())
                .then(d => {
                    if (!d.ok) return;
                    const st = d.data.status;
                    statusText.textContent = st.charAt(0).toUpperCase() + st.slice(1);
                    if (st === 'failed') {
                        statusDetail.textContent = d.data.error_message || '';
                        errorMsg.textContent = d.data.error_message || 'Failed';
                        errorMsg.style.display = 'block';
                        spinner.style.display = 'none';
                        clearInterval(pollInterval);
                        pollInterval = null;
                        currentJobId = null;
                    } else if (st === 'completed') {
                        clearInterval(pollInterval);
                        pollInterval = null;
                        currentJobId = null;
                        spinner.style.display = 'none';
                        downloadWrap.style.display = 'block';
                        downloadBtn.href = '/api/ai/download.php?job_id=' + encodeURIComponent(jobId);
                        downloadBtn.target = '_blank';
                    }
                    if (d.data.available_after !== undefined) updateBalance(d.data.available_after);
                })
                .catch(function() {});
        }, 3000);
    }

    function submitForm(formEl, formData) {
        const submitBtn = formEl.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        fetch('/api/ai/submit.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                if (submitBtn) submitBtn.disabled = false;
                if (d.ok && d.data.job_id) {
                    if (d.data.available_after !== undefined) updateBalance(d.data.available_after);
                    formEl.reset();
                    document.getElementById('ai-upscale-preview').style.display = 'none';
                    document.getElementById('ai-upscale-content').style.display = 'block';
                    showStatus(d.data.job_id);
                    statusPanel.scrollIntoView({ behavior: 'smooth' });
                } else {
                    const msg = (d.error && d.error.message) ? d.error.message : 'Error';
                    if (typeof kndToast !== 'undefined') kndToast(msg, 'error');
                    else alert(msg);
                }
            })
            .catch(function() {
                if (submitBtn) submitBtn.disabled = false;
                if (typeof kndToast !== 'undefined') kndToast('Network error', 'error');
                else alert('Network error');
            });
    }

    document.getElementById('ai-text2img-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        if (!fd.get('prompt') || fd.get('prompt').trim().length === 0) {
            if (typeof kndToast !== 'undefined') kndToast('Prompt is required', 'error');
            return;
        }
        submitForm(this, fd);
    });

    const upscaleDz = document.getElementById('ai-upscale-dropzone');
    const upscaleFile = document.getElementById('ai-upscale-file');
    const upscaleContent = document.getElementById('ai-upscale-content');
    const upscalePreview = document.getElementById('ai-upscale-preview');
    const upscalePreviewImg = document.getElementById('ai-upscale-preview-img');
    upscaleDz.addEventListener('click', function() { upscaleFile.click(); });
    upscaleFile.addEventListener('change', function() {
        const f = this.files[0];
        if (f && f.type.startsWith('image/')) {
            upscalePreviewImg.src = URL.createObjectURL(f);
            upscalePreview.style.display = 'block';
            upscaleContent.style.display = 'none';
            document.getElementById('ai-upscale-submit').disabled = false;
        }
    });

    document.getElementById('ai-upscale-form').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!upscaleFile.files.length) return;
        const fd = new FormData(this);
        fd.append('image', upscaleFile.files[0]);
        submitForm(this, fd);
    });

    document.getElementById('ai-character-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        if (!fd.get('prompt') || fd.get('prompt').trim().length === 0) {
            if (typeof kndToast !== 'undefined') kndToast('Prompt is required', 'error');
            return;
        }
        submitForm(this, fd);
    });

    document.getElementById('ai-texture-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        if (!fd.get('prompt') || fd.get('prompt').trim().length === 0) {
            if (typeof kndToast !== 'undefined') kndToast('Prompt is required', 'error');
            return;
        }
        submitForm(this, fd);
    });
})();
</script>

<?php
echo generateFooter();
echo generateScripts();
?>
