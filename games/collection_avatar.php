<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/mind_wars.php';

require_login();

$slug = strtolower(trim((string) ($_GET['avatar'] ?? '')));
$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    exit('Database connection error.');
}
$avatar = null;
$avatarError = '';
try {
    $avatar = mw_get_avatar_collection_item($pdo, $slug);
} catch (\Throwable $e) {
    $avatarError = stripos($e->getMessage(), 'AGUACATE') !== false ? $e->getMessage() : '';
    $avatar = null;
}

if (!$avatar) {
    http_response_code(404);
}

$name = $avatar ? (string) $avatar['name'] : 'Avatar Not Found';
$seoTitle = $name . ' | Mind Wars Collection';
$seoDesc = $avatar
    ? ('Profile, skills and lore for ' . $name . ' in Mind Wars.')
    : 'Mind Wars avatar profile not found.';
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

<section class="hero-section mw-section" style="min-height:100vh; padding-top:<?php echo $embed ? '1rem' : '120px'; ?>; padding-bottom:60px;">
    <div class="container">
        <div class="mb-3">
            <a href="/games/collection.php<?php echo $embed ? '?embed=1' : ''; ?>" class="btn btn-sm btn-outline-light">
                <i class="fas fa-arrow-left me-1"></i>Back to Collection
            </a>
        </div>

        <?php if (!$avatar): ?>
            <div class="glass-card-neon p-4 text-center">
                <h3 class="mb-2">Avatar not found</h3>
                <p class="text-white-50 mb-0">The requested avatar profile does not exist in the current catalog.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-12 col-lg-4">
                    <div class="glass-card-neon p-4 h-100 text-center">
                        <div class="kd-avatar-frame kd-avatar-frame--large mx-auto" data-level="1">
                            <img src="<?php echo htmlspecialchars((string) $avatar['asset_url']); ?>" alt="<?php echo htmlspecialchars((string) $avatar['name']); ?>" class="mw-collection-detail-avatar">
                        </div>
                        <h2 class="mt-3 mb-1"><?php echo htmlspecialchars((string) $avatar['name']); ?></h2>
                        <div class="small text-white-50 mb-1"><?php echo htmlspecialchars((string) $avatar['combat_class_label']); ?></div>
                        <?php if (!empty($avatar['culture_label'])): ?>
                            <div class="small text-info mb-2"><?php echo htmlspecialchars((string) $avatar['culture_label']); ?></div>
                        <?php else: ?>
                            <div class="mb-2"></div>
                        <?php endif; ?>
                        <span class="mw-rarity-badge mw-rarity-<?php echo htmlspecialchars((string) strtolower((string) $avatar['rarity'])); ?>">
                            <?php echo htmlspecialchars((string) ucfirst((string) $avatar['rarity'])); ?>
                        </span>

                        <div class="mw-collection-stats mt-3">
                            <div><span>Mind</span><strong><?php echo (int) ($avatar['stats']['mind'] ?? 0); ?></strong></div>
                            <div><span>Focus</span><strong><?php echo (int) ($avatar['stats']['focus'] ?? 0); ?></strong></div>
                            <div><span>Speed</span><strong><?php echo (int) ($avatar['stats']['speed'] ?? 0); ?></strong></div>
                            <div><span>Luck</span><strong><?php echo (int) ($avatar['stats']['luck'] ?? 0); ?></strong></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-8">
                    <div class="glass-card-neon p-4 mb-3">
                        <h4 class="kd-section-title mb-3">Combat Kit</h4>
                        <div class="mw-collection-skill-list">
                            <div class="mw-collection-skill-item">
                                <div class="mw-collection-skill-title">Passive: <?php echo htmlspecialchars((string) $avatar['passive_name']); ?></div>
                                <div class="small text-white-50"><?php echo htmlspecialchars((string) $avatar['passive_description']); ?></div>
                            </div>
                            <div class="mw-collection-skill-item">
                                <div class="mw-collection-skill-title">Ability: <?php echo htmlspecialchars((string) $avatar['ability_name']); ?></div>
                                <div class="small text-white-50"><?php echo htmlspecialchars((string) $avatar['ability_description']); ?></div>
                            </div>
                            <div class="mw-collection-skill-item">
                                <div class="mw-collection-skill-title">Special: <?php echo htmlspecialchars((string) $avatar['special_name']); ?></div>
                                <div class="small text-white-50"><?php echo htmlspecialchars((string) $avatar['special_description']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card-neon p-4 mb-3">
                        <h4 class="kd-section-title mb-2">Role Description</h4>
                        <p class="mb-0 text-white-75"><?php echo htmlspecialchars((string) $avatar['role_description']); ?></p>
                    </div>

                    <div class="glass-card-neon p-4">
                        <h4 class="kd-section-title mb-2"><i class="fas fa-book-open me-2"></i>Lore</h4>
                        <?php if (!empty($avatar['short_lore']) && $avatar['short_lore'] !== $avatar['historical_description']): ?>
                            <p class="mb-2 text-info fst-italic"><?php echo htmlspecialchars((string) $avatar['short_lore']); ?></p>
                        <?php endif; ?>
                        <p class="mb-2 text-white-75"><?php echo htmlspecialchars((string) $avatar['cultural_description']); ?></p>
                        <p class="mb-0 text-white-75"><?php echo htmlspecialchars((string) $avatar['historical_description']); ?></p>
                    </div>
                </div>
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

