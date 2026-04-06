<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

require_login();

$seoTitle = 'Mind Wars Seasons | KND Games';
$seoDesc = 'Mind Wars historical seasons, winners, and top leaderboard players.';
$extraHead = '';
$mwCss = __DIR__ . '/../../assets/css/mind-wars.css';
$extraHead .= '<link rel="stylesheet" href="/assets/css/mind-wars.css?v=' . (file_exists($mwCss) ? filemtime($mwCss) : time()) . '">' . "\n";
echo generateHeader($seoTitle, $seoDesc, $extraHead);
?>

<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="hero-section mw-section" style="min-height:100vh; padding-top:120px; padding-bottom:60px;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h1 class="glow-text mb-1">Mind Wars Seasons</h1>
                <p class="text-white-50 mb-0">Previous seasons, winners, and top players.</p>
            </div>
            <a href="/games/mind-wars.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-arrow-left me-1"></i>Back to Mind Wars
            </a>
        </div>

        <div class="glass-card-neon p-4 mb-4 mw-leaderboard-card">
            <h4 class="mb-2"><i class="fas fa-hourglass-half me-2"></i>Current Season</h4>
            <div class="small text-white-50" id="mw-seasons-current">Loading season...</div>
        </div>

        <div id="mw-seasons-history-list" class="d-grid gap-3"></div>
    </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>
<script>
(function () {
    const currentEl = document.getElementById('mw-seasons-current');
    const listEl = document.getElementById('mw-seasons-history-list');

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatCountdown(seconds) {
        const total = Math.max(0, Number(seconds || 0));
        const d = Math.floor(total / 86400);
        const h = Math.floor((total % 86400) / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        return d + 'd ' + h + 'h ' + m + 'm ' + s + 's';
    }

    async function loadCurrentSeason() {
        const res = await fetch('/api/mind-wars/season_info.php', { credentials: 'same-origin' });
        const json = await res.json();
        if (!res.ok || !json || !json.ok) {
            throw new Error('Could not load season info.');
        }
        const data = json.data || {};
        currentEl.textContent = (data.season_name || 'Mind Wars Season') + ' · Ends in ' + formatCountdown(data.seconds_remaining || 0);
    }

    function renderSeasonCard(entry) {
        const season = entry.season || {};
        const winner = entry.winner || null;
        const top = Array.isArray(entry.top) ? entry.top : [];

        const winnerLine = winner
            ? '<div class="small"><strong>Winner:</strong> #' + Number(winner.position || 1) + ' ' + escapeHtml(winner.username || 'Unknown') + ' · ' + Number(winner.rank_score || 0) + ' RP</div>'
            : '<div class="small text-white-50">No winner data.</div>';

        const topRows = top.length
            ? top.map(function (row) {
                return '<div class="mw-lb-row"><span>#' + Number(row.position || 0) + ' ' + escapeHtml(row.username || 'Unknown') + '</span><strong>' + Number(row.rank_score || 0) + ' RP</strong></div>';
            }).join('')
            : '<div class="small text-white-50">No ranking records for this season.</div>';

        return '<div class="glass-card-neon p-4 mw-season-history-card">'
            + '<h5 class="mb-2">' + escapeHtml(season.name || 'Season') + '</h5>'
            + '<div class="small text-white-50 mb-2">' + escapeHtml(season.starts_at || '-') + ' → ' + escapeHtml(season.ends_at || '-') + '</div>'
            + winnerLine
            + '<div class="mt-3">' + topRows + '</div>'
            + '</div>';
    }

    async function loadHistory() {
        const res = await fetch('/api/mind-wars/seasons_history.php', { credentials: 'same-origin' });
        const json = await res.json();
        if (!res.ok || !json || !json.ok) {
            throw new Error('Could not load seasons history.');
        }
        const seasons = Array.isArray(json.data && json.data.seasons) ? json.data.seasons : [];
        if (!seasons.length) {
            listEl.innerHTML = '<div class="glass-card-neon p-4 small text-white-50">No finished seasons yet.</div>';
            return;
        }
        listEl.innerHTML = seasons.map(renderSeasonCard).join('');
    }

    Promise.all([loadCurrentSeason(), loadHistory()]).catch(function (err) {
        currentEl.textContent = 'Season data unavailable.';
        listEl.innerHTML = '<div class="glass-card-neon p-4 small text-danger">' + escapeHtml(err.message || 'Error loading seasons.') + '</div>';
    });
})();
</script>
<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>

