<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';
require_once BASE_PATH . '/includes/mind_wars.php';

require_login();

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    exit('Database connection error.');
}
$catalog = [];
$catalogError = '';
try {
    $catalog = mw_get_avatar_collection_catalog($pdo);
} catch (\Throwable $e) {
    $catalogError = 'Unable to load avatar catalog right now.';
}

$ownedMap = [];
try {
    $uid = (int) (current_user_id() ?? 0);
    if ($uid > 0) {
        $ownStmt = $pdo->prepare("SELECT item_id FROM knd_user_avatar_inventory WHERE user_id = ?");
        $ownStmt->execute([$uid]);
        foreach (($ownStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $ownedMap[(int) ($row['item_id'] ?? 0)] = true;
        }
    }
} catch (\Throwable $e) {
    $ownedMap = [];
}
$seoTitle = 'Mind Wars Collection | KND Games';
$seoDesc = 'Explore all Mind Wars avatars, stats, classes, rarities, and combat kits.';
$mwCss = __DIR__ . '/../assets/css/mind-wars.css';
$extraHead = '<link rel="stylesheet" href="/assets/css/mind-wars.css?v=' . (file_exists($mwCss) ? filemtime($mwCss) : time()) . '">' . "\n";

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    $mwCssV = file_exists($mwCss) ? filemtime($mwCss) : time();
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seoTitle); ?></title>
    <?php echo generateFaviconLinks(); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="/assets/css/levels.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/levels.css') ? filemtime(__DIR__ . '/../assets/css/levels.css') : 0; ?>">
    <link rel="stylesheet" href="/assets/css/knd-ui.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/knd-ui.css') ? filemtime(__DIR__ . '/../assets/css/knd-ui.css') : 0; ?>">
    <link rel="stylesheet" href="/assets/css/mind-wars.css?v=<?php echo $mwCssV; ?>">
    <link rel="stylesheet" href="/assets/css/arena-embed.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/arena-embed.css') ? filemtime(__DIR__ . '/../assets/css/arena-embed.css') : 0; ?>">
</head>
<body class="arena-embed">
<div class="arena-embed-inner">
<?php
}
if (!$embed) {
    echo generateHeader($seoTitle, $seoDesc, $extraHead);
}
?>

<?php if (!$embed): ?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>
<?php endif; ?>

<section class="hero-section mw-section" style="min-height:100vh; padding-top:120px; padding-bottom:60px;">
    <div class="container">
        <?php if (!$embed): ?>
        <div class="mw-collection-hero text-center mb-4">
            <h1 class="glow-text mb-2">Mind Wars Collection</h1>
            <p class="mw-hero-subtitle mb-3">All avatars, combat classes, rarities and base stats in one place.</p>
            <a href="/games/mind-wars.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-arrow-left me-1"></i>Back to Mind Wars
            </a>
        </div>
        <?php endif; ?>

        <?php if ($catalogError !== ''): ?>
            <div class="glass-card-neon p-4 text-center">
                <h3 class="mb-2">Collection unavailable</h3>
                <p class="text-white-50 mb-0"><?php echo htmlspecialchars($catalogError); ?></p>
            </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($catalog as $avatar): ?>
                <?php
                    $mind = (int) ($avatar['stats']['mind'] ?? 0);
                    $focus = (int) ($avatar['stats']['focus'] ?? 0);
                    $speed = (int) ($avatar['stats']['speed'] ?? 0);
                    $luck = (int) ($avatar['stats']['luck'] ?? 0);
                    $isOwned = !empty($ownedMap[(int) ($avatar['item_id'] ?? 0)]);
                    $tooltip = implode("\n", [
                        (string) ($avatar['name'] ?? 'Avatar'),
                        (string) ($avatar['combat_class_label'] ?? 'Fighter') . ' | ' . ucfirst((string) ($avatar['rarity'] ?? 'common')),
                        'Owned: ' . ($isOwned ? 'Yes' : 'No'),
                        'Mind: ' . $mind,
                        'Focus: ' . $focus,
                        'Speed: ' . $speed,
                        'Luck: ' . $luck,
                        'Short Lore: ' . (string) ($avatar['short_lore'] ?? 'Description coming soon.'),
                    ]);
                ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                    <a class="mw-collection-card glass-card-neon p-3 d-block text-decoration-none" href="/games/collection_avatar.php?avatar=<?php echo urlencode((string) $avatar['slug']); ?><?php echo $embed ? '&embed=1' : ''; ?>">
                        <div class="text-center">
                            <div class="kd-avatar-frame kd-avatar-frame--large mx-auto" data-level="1">
                                <img
                                    src="<?php echo htmlspecialchars((string) $avatar['asset_url']); ?>"
                                    alt="<?php echo htmlspecialchars((string) $avatar['name']); ?>"
                                    class="mw-collection-card-avatar"
                                    title="<?php echo htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                            </div>
                            <div class="fw-bold mt-2"><?php echo htmlspecialchars((string) $avatar['name']); ?></div>
                            <div class="small text-white-50"><?php echo htmlspecialchars((string) $avatar['combat_class_label']); ?></div>
                            <span class="mw-rarity-badge mw-rarity-<?php echo htmlspecialchars((string) strtolower((string) $avatar['rarity'])); ?> mt-1">
                                <?php echo htmlspecialchars((string) ucfirst((string) $avatar['rarity'])); ?>
                            </span>
                            <?php if ($isOwned): ?>
                                <span class="badge bg-success mt-1">Owned</span>
                            <?php endif; ?>
                        </div>
                        <div class="mw-collection-stats mt-3">
                            <div><span>Mind</span><strong><?php echo $mind; ?></strong></div>
                            <div><span>Focus</span><strong><?php echo $focus; ?></strong></div>
                            <div><span>Speed</span><strong><?php echo $speed; ?></strong></div>
                            <div><span>Luck</span><strong><?php echo $luck; ?></strong></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($embed): ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</div></body></html>
<?php exit; endif; ?>

<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>

