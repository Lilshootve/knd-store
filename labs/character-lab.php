<?php
/**
 * Character Lab - Full pipeline: concept image -> 3D (Hunyuan3D/TripoSR/InstantMesh)
 * Route: /character-lab, /labs/character-lab.php
 */
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/character_lab_helpers.php';
require_once __DIR__ . '/../includes/character_lab_policy.php';

labs_perf_checkpoint('character_after_init');

$toolName = t('ai.character.title', 'Character Lab');
$balance = 0;
if ($pdo) {
    $balance = get_available_points($pdo, current_user_id());
}
$kpCost = character_lab_kp_cost();

$aiCss = __DIR__ . '/../assets/css/ai-tools.css';
$labsCss = __DIR__ . '/../assets/css/knd-labs.css';
$clCss = __DIR__ . '/../assets/css/character-lab.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . (file_exists($aiCss) ? filemtime($aiCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/character-lab.css?v=' . (file_exists($clCss) ? filemtime($clCss) : time()) . '">';
$extraCss .= '<script type="module" src="https://cdn.jsdelivr.net/npm/@google/model-viewer/dist/model-viewer.min.js"></script>';

echo generateHeader(
    t('labs.tool_page_title', '{tool} | KND Labs', ['tool' => $toolName]),
    'Create stylized game-ready character concepts and 3D models.',
    $extraCss
);
?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="labs-tool-section character-lab-section py-5">
    <div class="container">
        <?php labs_breadcrumb($toolName); ?>

        <div class="character-lab-hero glass-card-neon p-4 mt-4 mb-4">
            <h2 class="text-white mb-2"><i class="fas fa-user-astronaut me-2"></i><?php echo htmlspecialchars($toolName); ?></h2>
            <p class="text-white-50 mb-0">Generate a stylized game-ready character concept, then create a 3D model (GLB). Single character, full body, clean silhouette.</p>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <?php require __DIR__ . '/../components/character-lab/form.php'; ?>
            </div>
            <div class="col-lg-8">
                <div class="glass-card-neon p-4">
                    <h5 class="text-white mb-3">Status & Output</h5>
                    <div class="character-lab-status mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span id="cl-status-label" class="text-white-50">Idle</span>
                            <span id="cl-status-badge" class="badge bg-secondary">waiting</span>
                        </div>
                        <div class="progress" role="progressbar">
                            <div id="cl-progress-bar" class="progress-bar" style="width:0%"></div>
                        </div>
                    </div>
                    <div id="cl-concept-preview" class="mb-3 text-center" style="display:none;">
                        <img id="cl-concept-img" src="" alt="Concept" class="img-fluid rounded" style="max-height:280px;">
                    </div>
                    <?php require __DIR__ . '/../components/character-lab/viewer.php'; ?>
                    <div id="cl-error" class="alert alert-danger mt-3 mb-0" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
window.KND_CHARACTER_LAB = {
    cost: <?php echo (int) $kpCost; ?>,
    balance: <?php echo (int) $balance; ?>,
    endpoints: {
        create: '/api/character-lab/create.php',
        status: '/api/character-lab/status.php',
        recentImages: '/api/character-lab/recent-images.php',
        download: '/api/character-lab/download.php'
    }
};
</script>
<script src="/assets/js/navigation-extend.js"></script>
<script src="/assets/js/character-lab.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/character-lab.js') ? filemtime(__DIR__ . '/../assets/js/character-lab.js') : time(); ?>"></script>
<?php echo generateFooter(); echo generateScripts(); echo labs_perf_comment(); labs_perf_log(); ?>
