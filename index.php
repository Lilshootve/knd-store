<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
        echo "<h1 style='color:red'>FATAL ERROR</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . "</p>";
        echo "<p><strong>Line:</strong> " . $error['line'] . "</p>";
        echo "</body></html>";
    }
});

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

/**
 * Scan avatar frame directory by rarity folder.
 */
function scanAvatarFrameByRarity(string $rarity): array {
    $dir = __DIR__ . '/assets/avatars/frame/' . $rarity;
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $items = [];

    if (!is_dir($dir) || !is_readable($dir)) {
        return $items;
    }

    $files = @scandir($dir);
    if ($files === false) {
        return $items;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            continue;
        }

        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }

        $name = (string) pathinfo($file, PATHINFO_FILENAME);
        $name = trim((string) preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $name)));

        $items[] = [
            'name' => $name !== '' ? ucwords($name) : 'KND Avatar',
            'src' => '/assets/avatars/frame/' . rawurlencode($rarity) . '/' . rawurlencode($file),
        ];
    }

    usort($items, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });

    return $items;
}

$legendaryAvatarFrames = scanAvatarFrameByRarity('legendary');
$epicAvatarFrames = scanAvatarFrameByRarity('epic');
$rareAvatarFrames = scanAvatarFrameByRarity('rare');

/**
 * Normalize mw_avatars.image to a public URL (Mind Wars portrait / thumb, no frame asset).
 */
function knd_home_mw_portrait_url(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '/assets/avatars/_placeholder.svg';
    }
    if (preg_match('#^https?://#i', $raw)) {
        return $raw;
    }
    if (strlen($raw) > 0 && $raw[0] === '/') {
        return $raw;
    }
    if (stripos($raw, 'assets/') === 0) {
        return '/' . ltrim($raw, '/');
    }
    return '/assets/avatars/' . ltrim($raw, '/');
}

/**
 * Legendary + epic rows for the home showcase (mw_avatars.image only).
 *
 * @return array{legendary: list<array{id:int,name:string,src:string,rarity:string}>, epic: list<array{id:int,name:string,src:string,rarity:string}>}
 */
function knd_home_mw_showcase_avatars(PDO $pdo): array {
    $out = ['legendary' => [], 'epic' => []];
    try {
        $sql = "SELECT id, name, rarity, image FROM mw_avatars
                WHERE LOWER(TRIM(rarity)) IN ('legendary', 'epic')
                AND NULLIF(TRIM(image), '') IS NOT NULL
                ORDER BY FIELD(LOWER(TRIM(rarity)), 'legendary', 'epic'), name ASC";
        $stmt = $pdo->query($sql);
        if (!$stmt) {
            return $out;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $r = strtolower(trim((string) ($row['rarity'] ?? '')));
            if ($r !== 'legendary' && $r !== 'epic') {
                continue;
            }
            $name = trim((string) ($row['name'] ?? 'Avatar'));
            if ($name === '') {
                $name = 'Avatar';
            }
            $out[$r][] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $name,
                'src' => knd_home_mw_portrait_url((string) ($row['image'] ?? '')),
                'rarity' => $r,
            ];
        }
    } catch (Throwable $e) {
        error_log('knd_home_mw_showcase_avatars: ' . $e->getMessage());
    }
    return $out;
}

$homeAvatarsLegendary = [];
$homeAvatarsEpic = [];
$pdoHome = getDBConnection();
if ($pdoHome) {
    $mwHome = knd_home_mw_showcase_avatars($pdoHome);
    $homeAvatarsLegendary = $mwHome['legendary'];
    $homeAvatarsEpic = $mwHome['epic'];
}

$portalStatAvatarTotal = count($legendaryAvatarFrames) + count($epicAvatarFrames) + count($rareAvatarFrames);
$portalStatLegendaryCount = count($legendaryAvatarFrames);
$portalStatBattles = 2400;
$portalStatOnline = 847;
$portalStatDrops = '12.8K';
$portalStatLegendaryRate = '0.8%';

$homeCss = __DIR__ . '/assets/css/knd_home.css';
$homeJs  = __DIR__ . '/assets/js/knd_home.js';
$homeAssetV = ($isLocal ?? false) ? time() : (file_exists($homeCss) ? filemtime($homeCss) : time());

$extraHead = '<link rel="stylesheet" href="/assets/css/knd_home.css?v=' . $homeAssetV . '">';
$extraHead .= '<script src="/assets/js/knd_home.js?v=' . $homeAssetV . '" defer></script>';

$startTime = startPerformanceTimer();
setCacheHeaders('html');
?>

<?php echo generateHeader(t('nav.home'), t('meta.default_description'), $extraHead); ?>

<?php echo generateNavigation(); ?>

<?php
include __DIR__ . '/sections/knd_home_concept.php';
?>

<script src="/assets/js/navigation-extend.js" defer></script>
<?php
echo generateFooter();
echo generateScripts();

$executionTime = endPerformanceTimer($startTime);

if (error_reporting() > 0) {
    echo "<!-- Page loaded in {$executionTime}ms -->";
}
?>
