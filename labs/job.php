<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_init.php';

require_once BASE_PATH . '/includes/triposr.php';

$jobId = trim($_GET['job_id'] ?? '');
if ($jobId === '') {
    header('Location: /labs-jobs.php?error=missing');
    exit;
}

$job = null;
if ($pdo && current_user_id()) {
    $job = ai_get_job($pdo, $jobId);
    if (!$job) $job = get_triposr_job($pdo, $jobId);
}

if (!$job || (int) $job['user_id'] !== (int) current_user_id()) {
    header('Location: /labs-jobs.php?error=not_found');
    exit;
}

$jobType = $job['job_type'] ?? 'img23d';
$toolLabel = t('labs.job_type_' . $jobType, $jobType);
$labsCss = __DIR__ . '/../assets/css/knd-labs.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/ai-tools.css?v=' . time() . '">';
$extraCss .= '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
echo generateHeader(t('labs.job_detail', 'Job #{id} | KND Labs', ['id' => substr($jobId, 0, 8)]), '', $extraCss);
?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="labs-tool-section py-5">
  <div class="container">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/"><?php echo t('nav.home', 'Home'); ?></a></li>
      <li class="breadcrumb-item"><a href="/labs"><?php echo t('labs.title', 'KND Labs'); ?></a></li>
      <li class="breadcrumb-item"><a href="/labs-jobs.php"><?php echo t('labs.view_all_jobs', 'Jobs'); ?></a></li>
      <li class="breadcrumb-item active"><?php echo htmlspecialchars(substr($jobId, 0, 8)); ?></li>
    </ol></nav>

    <div class="glass-card-neon p-4 mt-4">
      <h3 class="text-white mb-4"><?php echo htmlspecialchars($toolLabel); ?> — <?php echo htmlspecialchars($job['status']); ?></h3>
      <div class="row">
        <div class="col-md-6">
          <p class="text-white-50"><strong>ID:</strong> <code><?php echo htmlspecialchars($jobId); ?></code></p>
          <p class="text-white-50"><strong><?php echo t('labs.job_cost', 'Cost'); ?>:</strong> <?php echo (int)($job['cost_kp'] ?? 0); ?> KP</p>
          <p class="text-white-50"><strong><?php echo t('labs.job_created', 'Created'); ?>:</strong> <?php echo date('Y-m-d H:i:s', strtotime($job['created_at'])); ?></p>
          <?php if (!empty($job['error_message'])): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($job['error_message']); ?></div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <?php if ($job['status'] === 'completed' && !empty($job['output_path'])): ?>
          <?php
          $ext = strtolower(pathinfo($job['output_path'], PATHINFO_EXTENSION));
          if (in_array($ext, ['png','jpg','jpeg','webp'], true)):
          ?>
          <img src="/api/ai/preview.php?job_id=<?php echo urlencode($jobId); ?>" alt="Result" class="img-fluid rounded mb-3" style="max-height:300px;">
          <?php endif; ?>
          <a href="/api/ai/download.php?job_id=<?php echo urlencode($jobId); ?>" class="btn btn-success" target="_blank"><i class="fas fa-download me-2"></i><?php echo t('ai.download'); ?></a>
          <?php elseif (in_array($job['status'], ['pending','processing'], true)): ?>
          <div class="ai-spinner"><i class="fas fa-cog fa-spin fa-2x"></i></div>
          <p class="text-white-50 mt-2"><?php echo t('ai.status.processing'); ?></p>
          <?php endif; ?>
        </div>
      </div>
      <div class="mt-4">
        <a href="/labs-jobs.php" class="btn btn-outline-primary"><?php echo t('labs.back_to_jobs', 'Back to Jobs'); ?></a>
      </div>
    </div>
  </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>
<?php echo generateFooter(); echo generateScripts(); ?>
