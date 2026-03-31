<?php
require_once __DIR__ . '/_init.php';

$jobType = trim($_GET['tool'] ?? '');
$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$jobs = [];
$total = 0;
if ($pdo && current_user_id()) {
    $jobs = ai_list_jobs($pdo, current_user_id(), $jobType ?: null, $status ?: null, $dateFrom ?: null, $dateTo ?: null, $perPage, $offset);
    $wc = ['user_id = ?'];
    $params = [current_user_id()];
    if ($jobType) { $wc[] = 'job_type = ?'; $params[] = $jobType; }
    if ($status) { $wc[] = 'status = ?'; $params[] = $status; }
    if ($dateFrom) { $wc[] = 'created_at >= ?'; $params[] = $dateFrom; }
    if ($dateTo) { $wc[] = 'created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM triposr_jobs WHERE ' . implode(' AND ', $wc));
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
}

$labsCss = __DIR__ . '/../assets/css/knd-labs.css';
$extraCss = '<link rel="stylesheet" href="/assets/css/knd-labs.css?v=' . (file_exists($labsCss) ? filemtime($labsCss) : time()) . '">';
echo generateHeader(t('labs.jobs_title', 'My Jobs | KND Labs'), t('labs.jobs_desc', 'View and manage your AI jobs'), $extraCss);
?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="labs-tool-section py-5">
  <div class="container">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/"><?php echo t('nav.home', 'Home'); ?></a></li>
      <li class="breadcrumb-item"><a href="/labs"><?php echo t('labs.title', 'KND Labs'); ?></a></li>
      <li class="breadcrumb-item active"><?php echo t('labs.view_all_jobs', 'View All Jobs'); ?></li>
    </ol></nav>

    <div class="glass-card-neon p-4 mt-4">
      <h3 class="text-white mb-4"><?php echo t('labs.jobs_title', 'My Jobs'); ?></h3>

      <form method="get" class="row g-3 mb-4">
        <div class="col-md-2">
          <label class="form-label text-white-50 small"><?php echo t('labs.job_tool', 'Tool'); ?></label>
          <select name="tool" class="form-select form-select-sm bg-dark text-white">
            <option value=""><?php echo t('labs.all', 'All'); ?></option>
            <option value="text2img" <?php echo $jobType === 'text2img' ? 'selected' : ''; ?>>Text→Image</option>
            <option value="upscale" <?php echo $jobType === 'upscale' ? 'selected' : ''; ?>>Upscale</option>
            <option value="character_create" <?php echo $jobType === 'character_create' ? 'selected' : ''; ?>>Character</option>
            <option value="texture_seamless" <?php echo $jobType === 'texture_seamless' ? 'selected' : ''; ?>>Texture</option>
            <option value="img23d" <?php echo $jobType === 'img23d' ? 'selected' : ''; ?>>Image→3D</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label text-white-50 small"><?php echo t('labs.job_status', 'Status'); ?></label>
          <select name="status" class="form-select form-select-sm bg-dark text-white">
            <option value=""><?php echo t('labs.all', 'All'); ?></option>
            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label text-white-50 small"><?php echo t('labs.date_from', 'From'); ?></label>
          <input type="date" name="date_from" class="form-control form-control-sm bg-dark text-white" value="<?php echo htmlspecialchars($dateFrom); ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label text-white-50 small"><?php echo t('labs.date_to', 'To'); ?></label>
          <input type="date" name="date_to" class="form-control form-control-sm bg-dark text-white" value="<?php echo htmlspecialchars($dateTo); ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-neon-primary btn-sm"><?php echo t('labs.filter', 'Filter'); ?></button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-dark table-sm">
          <thead>
            <tr>
              <th><?php echo t('labs.job_tool', 'Tool'); ?></th>
              <th><?php echo t('labs.job_status', 'Status'); ?></th>
              <th><?php echo t('labs.job_cost', 'Cost'); ?></th>
              <th><?php echo t('labs.job_created', 'Created'); ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($jobs)): ?>
            <tr><td colspan="5" class="text-white-50 text-center py-4"><?php echo t('labs.no_jobs', 'No jobs found'); ?></td></tr>
            <?php else: ?>
            <?php foreach ($jobs as $j):
              $tl = t('labs.job_type_' . ($j['job_type'] ?? 'img23d'), $j['job_type'] ?? 'img23d');
              $sc = $j['status'] === 'completed' ? 'success' : ($j['status'] === 'failed' ? 'danger' : 'warning');
            ?>
            <tr>
              <td><?php echo htmlspecialchars($tl); ?></td>
              <td><span class="badge bg-<?php echo $sc; ?>"><?php echo htmlspecialchars($j['status']); ?></span></td>
              <td><?php echo (int)($j['cost_kp'] ?? 0); ?> KP</td>
              <td class="text-white-50 small"><?php echo date('Y-m-d H:i', strtotime($j['created_at'])); ?></td>
              <td>
                <a href="/labs-job.php?job_id=<?php echo urlencode($j['job_uuid']); ?>" class="btn btn-sm btn-outline-primary me-1"><?php echo t('labs.view'); ?></a>
                <?php if ($j['status'] === 'completed'): ?>
                <a href="/api/ai/download.php?job_id=<?php echo urlencode($j['job_uuid']); ?>" class="btn btn-sm btn-success" target="_blank"><i class="fas fa-download"></i></a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total > $perPage): ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm">
          <?php $totalPages = ceil($total / $perPage);
          $qs = http_build_query(array_filter(['tool' => $jobType, 'status' => $status, 'date_from' => $dateFrom, 'date_to' => $dateTo]));
          for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
          <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $qs; ?>"><?php echo $i; ?></a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
  </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>
<?php echo generateFooter(); echo generateScripts(); ?>
