<?php
require_once __DIR__ . '/config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';
require_once BASE_PATH . '/includes/knd_profile.php';
require_once BASE_PATH . '/includes/knd_avatar.php';
require_once BASE_PATH . '/includes/csrf.php';

require_login();

$csrfToken = csrf_token();

$pdo = getDBConnection();
if (!$pdo) {
    header('Location: /auth.php');
    exit;
}

$userId = (int) $_SESSION['dr_user_id'];
$data = profile_get_data($pdo, $userId);
$inventory = avatar_get_inventory($pdo, $userId);

$seoTitle = t('profile.title', 'My Profile') . ' | KND Arena';
$seoDesc = 'Your KND Arena identity: Level, XP, stats for LastRoll, Insight, Drops, and seasonal progress.';
$ogHead = '    <meta property="og:title" content="' . htmlspecialchars($seoTitle) . '">' . "\n";
$ogHead .= '    <meta property="og:description" content="' . htmlspecialchars($seoDesc) . '">' . "\n";
$ogHead .= '    <meta property="og:type" content="website">' . "\n";
$ogHead .= '    <link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
$ogHead .= '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
$ogHead .= '    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">' . "\n";
$ogHead .= '    <link rel="stylesheet" href="/assets/css/avatar.css?v=' . @filemtime(__DIR__ . '/assets/css/avatar.css') . '">' . "\n";
$ogHead .= '    <link rel="stylesheet" href="/assets/css/knd-profile-neural.css?v=' . @filemtime(__DIR__ . '/assets/css/knd-profile-neural.css') . '">' . "\n";
echo generateHeader($seoTitle, $seoDesc, $ogHead);

$level = (int) $data['level'];
$levelTier = 'bronze';
if ($level >= 26) {
    $levelTier = 'legendary';
} elseif ($level >= 21) {
    $levelTier = 'epic';
} elseif ($level >= 16) {
    $levelTier = 'platinum';
} elseif ($level >= 11) {
    $levelTier = 'gold';
} elseif ($level >= 6) {
    $levelTier = 'silver';
}
$pctClamped = $data['progress']['isMaxLevel'] ? 100.0 : min(100, max(0, (float) $data['progress']['progressPct'] * 100));
$seasonRankDisp = (!empty($data['season']) && isset($data['season']['rank'])) ? '#' . (int) $data['season']['rank'] : '—';
$allTimeDisp = $data['all_time_rank'] !== null ? '#' . (int) $data['all_time_rank'] : '—';
$seasonChipLine = '—';
if (!empty($data['season']) && !empty($data['season']['name']) && !empty($data['season']['ends_at'])) {
    $seasonChipLine = $data['season']['name'] . ' · ' . t('profile.season_ends', 'ENDS') . ' ' . strtoupper(date('M j, Y', strtotime($data['season']['ends_at'])));
}
$netKp = (int) ($data['above_under']['net_kp'] ?? 0);
if ($netKp > 0) {
    $netKpStr = '+' . number_format($netKp);
} elseif ($netKp < 0) {
    $netKpStr = number_format($netKp);
} else {
    $netKpStr = '0';
}
$invCount = count($inventory);
$tierTag = strtoupper($levelTier) . ' ' . t('profile.tier', 'TIER');
?>

<?php echo generateNavigation(); ?>

<section class="hero-section profile-page knd-profile-neural-page" style="min-height:127vh; padding-top:160px; padding-bottom:60px;">
<div class="knd-profile-neural-bg" aria-hidden="true"></div>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">

      <div class="knd-profile-neural page">

      <div class="hero-card">
        <div class="level-hex <?php echo htmlspecialchars($levelTier); ?>">
          <svg viewBox="0 0 120 120" aria-hidden="true"><polygon class="level-hex-bg" points="60,6 112,30 112,90 60,114 8,90 8,30"/><polygon class="level-hex-inner" points="60,18 98,36 98,84 60,102 22,84 22,36"/></svg>
          <span class="level-num"><?php echo $level; ?></span>
          <span class="level-label"><?php echo t('profile.level', 'LEVEL'); ?></span>
        </div>
        <div class="identity">
          <div class="identity-tag"><?php echo t('profile.identity_tag', 'NEURAL OPERATIVE'); ?> · <?php echo htmlspecialchars($tierTag); ?></div>
          <div class="identity-name"><?php echo htmlspecialchars($data['username'] ?? 'Player'); ?></div>
          <div class="identity-xp"><?php echo strtoupper(t('profile.xp_total', 'Total XP')); ?>: <strong><?php echo number_format($data['xp']); ?></strong></div>
          <?php if ($data['progress']['isMaxLevel']): ?>
            <div class="identity-max"><i class="fas fa-crown me-1"></i><?php echo t('profile.max', 'MAX LEVEL'); ?> — <?php echo t('profile.max_hint', 'XP continues for leaderboard'); ?></div>
          <?php else: ?>
            <div class="xp-bar" role="progressbar" aria-valuenow="<?php echo (int) round($pctClamped); ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="xp-fill" id="profile-xp-fill" style="width:0%"></div>
            </div>
            <div class="xp-next"><?php echo strtoupper(t('profile.next', 'XP to next level')); ?>: <?php echo number_format($data['progress']['xpToNext']); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="rank-strip">
        <div class="rank-chip">
          <div>
            <div class="rc-val"><?php echo htmlspecialchars($seasonRankDisp); ?></div>
            <div class="rc-label"><?php echo t('profile.rank_season', 'SEASON RANK'); ?></div>
          </div>
        </div>
        <div class="rank-chip">
          <div>
            <div class="rc-val"><?php echo htmlspecialchars($allTimeDisp); ?></div>
            <div class="rc-label"><?php echo t('profile.rank_alltime', 'ALL-TIME RANK'); ?></div>
          </div>
        </div>
        <div class="rank-chip">
          <div>
            <div class="rc-val" style="font-size:clamp(12px,2vw,18px);line-height:1.25;"><?php echo htmlspecialchars($seasonChipLine); ?></div>
            <div class="rc-label"><?php echo t('profile.season_status', 'SEASON'); ?></div>
          </div>
        </div>
      </div>
      <div class="text-end mb-3" style="font-family:var(--FM,monospace);font-size:9px;">
        <a href="/leaderboard.php" style="color:var(--c,#00e8ff);text-decoration:none;border-bottom:1px solid rgba(0,232,255,.35);letter-spacing:1px;"><?php echo t('profile.view_leaderboard', 'View Leaderboard'); ?></a>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="sc-header">
            <div class="sc-icon"><i class="fas fa-dice-d20" aria-hidden="true"></i></div>
            <div class="sc-title"><?php echo strtoupper(t('profile.lastroll', 'KND LastRoll')); ?></div>
          </div>
          <div class="sc-grid">
            <div><div class="sc-item-label"><?php echo t('profile.lastroll_matches', 'Matches'); ?></div><div class="sc-item-val knd-count"><?php echo (int) $data['lastroll']['matches']; ?></div></div>
            <div><div class="sc-item-label"><?php echo t('profile.lastroll_wins', 'Wins'); ?></div><div class="sc-item-val win knd-count"><?php echo (int) $data['lastroll']['wins']; ?></div></div>
            <div><div class="sc-item-label"><?php echo t('profile.lastroll_losses', 'Losses'); ?></div><div class="sc-item-val lose knd-count"><?php echo (int) $data['lastroll']['losses']; ?></div></div>
            <div><div class="sc-item-label"><?php echo t('lb.winrate', 'Winrate'); ?></div><div class="sc-item-val knd-count"><?php echo $data['lastroll']['winrate'] !== null ? htmlspecialchars((string) $data['lastroll']['winrate']) . '%' : '—'; ?></div></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="sc-header">
            <div class="sc-icon"><i class="fas fa-eye" aria-hidden="true"></i></div>
            <div class="sc-title"><?php echo strtoupper(t('profile.aboveunder', 'KND Insight')); ?></div>
          </div>
          <div class="sc-grid">
            <div><div class="sc-item-label"><?php echo t('profile.aboveunder_rolls', 'Rolls'); ?></div><div class="sc-item-val knd-count"><?php echo (int) $data['above_under']['rolls']; ?></div></div>
            <div><div class="sc-item-label"><?php echo t('profile.lastroll_wins', 'Wins'); ?></div><div class="sc-item-val win knd-count"><?php echo (int) $data['above_under']['wins']; ?></div></div>
            <div><div class="sc-item-label"><?php echo t('lb.winrate', 'Winrate'); ?></div><div class="sc-item-val knd-count"><?php echo $data['above_under']['winrate'] !== null ? htmlspecialchars((string) $data['above_under']['winrate']) . '%' : '—'; ?></div></div>
            <div><div class="sc-item-label"><?php echo t('profile.insight_net_kp', 'Net KP'); ?></div><div class="sc-item-val win knd-count"><?php echo htmlspecialchars($netKpStr); ?></div></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="sc-header">
            <div class="sc-icon"><i class="fas fa-box-open" aria-hidden="true"></i></div>
            <div class="sc-title"><?php echo strtoupper(t('profile.drops', 'DROP CHAMBER')); ?></div>
          </div>
          <div class="sc-grid">
            <div><div class="sc-item-label"><?php echo t('profile.drops_total', 'Drops'); ?></div><div class="sc-item-val knd-count"><?php echo (int) $data['drops']['total']; ?></div></div>
            <div><div class="sc-item-label"><?php echo t('profile.drops_best', 'Best'); ?></div><div class="sc-item-val" style="color:var(--gold,#ffcc00)"><?php echo htmlspecialchars(ucfirst($data['drops']['best_rarity'] ?? '—')); ?></div></div>
            <div><div class="sc-item-label"><?php echo t('profile.drops_avg', 'Avg reward'); ?></div><div class="sc-item-val knd-count"><?php echo $data['drops']['avg_reward'] !== null ? number_format($data['drops']['avg_reward']) . ' KP' : '—'; ?></div></div>
            <div><div class="sc-item-label"><?php echo t('profile.drops_collected', 'Collected'); ?></div><div class="sc-item-val knd-count"><?php echo (int) $invCount; ?></div></div>
          </div>
        </div>
      </div>

      <div class="avatar-section">
        <div class="section-head">
          <div class="section-title"><span class="sdot"></span> <?php echo strtoupper(t('avatar.active_avatar', 'Active Avatar')); ?></div>
          <div class="section-meta">
            <span><i class="fas fa-gem me-1" style="color:#a78bfa;"></i><?php echo t('avatar.fragments', 'Fragments'); ?>: <strong id="profile-fragments">—</strong></span>
            <span class="ms-3"><?php echo t('avatar.kp', 'KP'); ?>: <strong id="avatar-kp-balance" class="kp-strong">—</strong></span>
          </div>
        </div>
        <?php if (!empty($data['favorite_avatar'])):
            $favRarity = strtolower((string) ($data['favorite_avatar']['rarity'] ?? 'common'));
            $rarityClass = in_array($favRarity, ['legendary', 'epic', 'rare', 'special', 'common'], true) ? $favRarity : 'common';
        ?>
        <div class="avatar-stage">
          <div class="avatar-frame">
            <img id="favorite-avatar-image" class="avatar-img" src="<?php echo htmlspecialchars($data['favorite_avatar']['thumb_path'] ?? $data['favorite_avatar']['asset_path']); ?>" alt="<?php echo htmlspecialchars($data['favorite_avatar']['name'] ?? 'KND Avatar'); ?>">
          </div>
          <div class="avatar-meta">
            <div class="avatar-name"><?php echo htmlspecialchars($data['favorite_avatar']['name'] ?? 'Favorite Avatar'); ?></div>
            <div class="avatar-rarity <?php echo htmlspecialchars($rarityClass); ?>">★ <?php echo strtoupper(htmlspecialchars($favRarity)); ?></div>
            <span class="avatar-badge">◆ <?php echo strtoupper(t('avatar.favorite_badge', 'Active favorite')); ?></span>
          </div>
        </div>
        <div id="avatar-preview" class="avatar-stage d-none"></div>
        <?php else: ?>
        <div class="avatar-stage">
          <div class="avatar-frame">
            <div class="empty-hint mb-0" style="padding:8px;"><?php echo t('avatar.no_favorite', 'No favorite avatar set'); ?></div>
          </div>
          <div class="avatar-meta">
            <div class="avatar-name"><?php echo t('avatar.choose_hint', 'Choose one below'); ?></div>
          </div>
        </div>
        <div id="avatar-preview" class="avatar-stage"></div>
        <?php endif; ?>
      </div>

      <div class="avatar-section">
        <div class="section-head">
          <div class="section-title"><span class="sdot"></span> <?php echo strtoupper(t('profile.collected_avatars', 'Collected Avatars')); ?></div>
          <span class="section-meta"><?php echo (int) $invCount; ?> <?php echo t('profile.items', 'ITEMS'); ?></span>
        </div>
        
        <?php if (empty($inventory)): ?>
          <div class="empty-hint mb-0">
            <i class="fas fa-box-open me-2" style="opacity:.5;"></i><?php echo t('profile.no_avatars', 'No avatars collected yet. Play KND Drop Chamber to earn avatars!'); ?>
          </div>
        <?php else:
            $avatarsInitial = 12;
            $avatarsVisible = array_slice($inventory, 0, $avatarsInitial);
            $avatarsHidden = array_slice($inventory, $avatarsInitial);
            $avatarsHasMore = count($avatarsHidden) > 0;
            ?>
          <div class="collection-grid" id="avatars-visible-row">
            <?php foreach ($avatarsVisible as $item):
                $isSelected = !empty($data['favorite_avatar']) && $data['favorite_avatar']['id'] == $item['id'];
                $rColl = 'common';
                if (in_array($item['rarity'], ['special', 'rare', 'epic', 'legendary'], true)) {
                    $rColl = $item['rarity'];
                }
                $itemThumb = avatar_item_thumb_url($pdo, $item);
                ?>
            <div class="coll-item<?php echo $isSelected ? ' selected' : ''; ?>">
              <div class="coll-img-wrap">
                <img class="coll-img" src="<?php echo htmlspecialchars($itemThumb); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
              </div>
              <div class="coll-name" title="<?php echo htmlspecialchars($item['name']); ?>"><?php echo htmlspecialchars($item['name']); ?></div>
              <div class="coll-rarity <?php echo htmlspecialchars($rColl); ?>"><?php echo strtoupper(htmlspecialchars($item['rarity'])); ?></div>
              <?php if ($isSelected): ?>
                <button type="button" class="coll-btn active" disabled><?php echo t('profile.avatar_active', '✓ Active'); ?></button>
              <?php else: ?>
                <button type="button" class="coll-btn btn-set-favorite" data-id="<?php echo (int) $item['id']; ?>"><?php echo t('profile.set_fav', 'Set favorite'); ?></button>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
            <?php if ($avatarsHasMore): ?>
          <div id="avatars-more-wrap" style="display:none;">
            <div class="collection-grid mt-2">
              <?php foreach ($avatarsHidden as $item):
                  $isSelected = !empty($data['favorite_avatar']) && $data['favorite_avatar']['id'] == $item['id'];
                  $rColl = 'common';
                  if (in_array($item['rarity'], ['special', 'rare', 'epic', 'legendary'], true)) {
                      $rColl = $item['rarity'];
                  }
                  $itemThumb = avatar_item_thumb_url($pdo, $item);
                  ?>
              <div class="coll-item<?php echo $isSelected ? ' selected' : ''; ?>">
                <div class="coll-img-wrap">
                  <img class="coll-img" src="<?php echo htmlspecialchars($itemThumb); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                </div>
                <div class="coll-name" title="<?php echo htmlspecialchars($item['name']); ?>"><?php echo htmlspecialchars($item['name']); ?></div>
                <div class="coll-rarity <?php echo htmlspecialchars($rColl); ?>"><?php echo strtoupper(htmlspecialchars($item['rarity'])); ?></div>
                <?php if ($isSelected): ?>
                  <button type="button" class="coll-btn active" disabled><?php echo t('profile.avatar_active', '✓ Active'); ?></button>
                <?php else: ?>
                  <button type="button" class="coll-btn btn-set-favorite" data-id="<?php echo (int) $item['id']; ?>"><?php echo t('profile.set_fav', 'Set favorite'); ?></button>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="avatars-more-toggle">
            <button type="button" id="avatars-toggle-btn" data-expanded="false">
              <i class="fas fa-chevron-down me-1"></i><?php echo t('profile.show_more', 'Show more'); ?> (<?php echo (int) count($avatarsHidden); ?>)
            </button>
          </div>
            <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="avatar-section">
        <div class="section-head">
          <div class="section-title"><span class="sdot"></span> <?php echo strtoupper(t('profile.badges', 'Badges')); ?></div>
          <span id="badge-count" class="section-meta">—</span>
        </div>
        <div id="badges-container">
          <div class="empty-hint mb-0"><i class="fas fa-spinner fa-spin me-2"></i><?php echo t('profile.badges_loading', 'Loading badges...'); ?></div>
        </div>
      </div>

      <!-- Avatar Customize Modal (KND HUD) -->
      <div id="avatar-customize-modal" class="avatar-modal-overlay" tabindex="-1" role="dialog" aria-labelledby="avatar-modal-title">
        <div class="avatar-modal-scroll">
          <div class="avatar-modal-hud">
            <div class="avatar-modal-header">
              <h4 id="avatar-modal-title"><?php echo t('avatar.customize', 'Customize Avatar'); ?></h4>
              <button type="button" id="avatar-customize-close" class="avatar-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="avatar-modal-grid">
              <div class="avatar-modal-preview-panel">
                <div class="avatar-preview-frame">
                  <span class="avatar-kp-pill"><i class="fas fa-coins"></i> <strong id="avatar-kp-balance-modal">—</strong> KP</span>
                  <div id="avatar-customize-preview" class="avatar-stage avatar-stage-modal"></div>
                </div>
              </div>
              <div class="avatar-modal-controls">
                <div id="avatar-slot-tabs" class="avatar-slot-seg" data-active="hair"></div>
                <div class="avatar-owned-shop-seg nav nav-tabs" role="tablist">
                  <a class="nav-link active" data-bs-toggle="tab" href="#avatar-owned-tab"><?php echo t('avatar.owned', 'Owned'); ?></a>
                  <a class="nav-link" data-bs-toggle="tab" href="#avatar-shop-tab"><?php echo t('avatar.shop', 'Shop'); ?></a>
                </div>
                <div class="tab-content avatar-tab-content">
                  <div id="avatar-owned-tab" class="tab-pane show active">
                    <div id="avatar-owned-pane" class="avatar-items-grid"></div>
                  </div>
                  <div id="avatar-shop-tab" class="tab-pane">
                    <div id="avatar-shop-pane" class="avatar-items-grid"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="back-link">
        <a href="/games/mind-wars/lobby.php"><i class="fas fa-arrow-left me-1"></i><?php echo t('profile.back_arena', 'Back to Arena'); ?></a>
      </div>

      </div><!-- /.knd-profile-neural -->

    </div>
  </div>
</div>
</section>

<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<script>var CSRF = '<?php echo addslashes($csrfToken); ?>';</script>
<script src="/assets/js/avatar.js" defer></script>
<script>
<?php if (!$data['progress']['isMaxLevel']): ?>
var __kndXpPct = <?php echo json_encode(round($pctClamped, 1)); ?>;
<?php else: ?>
var __kndXpPct = null;
<?php endif; ?>

function kndEsc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function kndProfileAnimateNumbers(root) {
  if (!root || !root.querySelectorAll) return;
  root.querySelectorAll('.knd-count, .rc-val, .mile-val').forEach(function(el) {
    var text = el.textContent.trim();
    if (text === '' || text === '—') return;
    var num = parseFloat(text.replace(/,/g, '').replace(/[^0-9.\-+]/g, ''));
    if (isNaN(num) || num === 0) return;
    var prefix = text.match(/^[^0-9.\-+]+/) ? text.match(/^[^0-9.\-+]+/)[0] : '';
    if (prefix === undefined) prefix = '';
    var suffix = text.match(/[^0-9.]+$/) ? text.match(/[^0-9.]+$/)[0] : '';
    if (suffix === undefined) suffix = '';
    var isFloat = /\.[0-9]/.test(text);
    el.textContent = prefix + '0' + suffix;
    var t0 = performance.now();
    function tick() {
      var p = Math.min((performance.now() - t0) / 800, 1);
      var ease = 1 - Math.pow(1 - p, 3);
      var v = num * ease;
      var mid = isFloat ? v.toFixed(1) : Math.round(v).toLocaleString();
      el.textContent = prefix + mid + suffix;
      if (p < 1) requestAnimationFrame(tick);
    }
    setTimeout(tick, 200 + Math.random() * 400);
  });
}

document.addEventListener('DOMContentLoaded', function() {
  var neural = document.querySelector('.knd-profile-neural');
  if (typeof __kndXpPct === 'number' && document.getElementById('profile-xp-fill')) {
    var fill = document.getElementById('profile-xp-fill');
    fill.style.width = '0%';
    setTimeout(function() { fill.style.width = __kndXpPct + '%'; }, 300);
  }
  if (neural) kndProfileAnimateNumbers(neural);

  var avatarsToggle = document.getElementById('avatars-toggle-btn');
  var avatarsMore = document.getElementById('avatars-more-wrap');
  if (avatarsToggle && avatarsMore) {
    var moreCount = avatarsMore.querySelectorAll('.coll-item').length;
    avatarsToggle.addEventListener('click', function() {
      var expanded = this.getAttribute('data-expanded') === 'true';
      avatarsMore.style.display = expanded ? 'none' : 'block';
      this.setAttribute('data-expanded', !expanded);
      this.innerHTML = expanded
        ? '<i class="fas fa-chevron-down me-1"></i> Show more (' + moreCount + ')'
        : '<i class="fas fa-chevron-up me-1"></i> Show less';
    });
  }
});

document.querySelectorAll('.btn-set-favorite').forEach(btn => {
  btn.addEventListener('click', async function () {
    const itemId = parseInt(this.dataset.id, 10);
    const originalHtml = this.innerHTML;

    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

    try {
      const body = new URLSearchParams();
      body.append('item_id', itemId);
      body.append('csrf_token', CSRF);

      const res = await fetch('/api/avatar/set_favorite.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      });

      const data = await res.json();

      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Error saving avatar');
      }

      window.location.reload();
    } catch (e) {
      alert(e.message || 'Error saving avatar');
      this.disabled = false;
      this.innerHTML = originalHtml;
    }
  });
});

// Load fragments
fetch('/api/avatar/fragments.php', {credentials: 'same-origin'})
  .then(r => r.json())
  .then(d => {
    if (d.ok && d.data) {
      document.getElementById('profile-fragments').textContent = (d.data.fragments || 0).toLocaleString();
    }
  })
  .catch(() => {});

// Load badges
fetch('/api/badges/user_badges.php', {credentials: 'same-origin'})
  .then(r => r.json())
  .then(d => {
    if (d.ok && d.data) {
      const unlocked = d.data.unlocked_badges || [];
      const progress = d.data.progress || [];
      const milestones = d.data.milestones || {};
      
      const countEl = document.getElementById('badge-count');
      const container = document.getElementById('badges-container');
      
      if (countEl) {
        countEl.textContent = unlocked.length + ' / ' + progress.length + ' unlocked';
      }
      
      if (container) {
        if (progress.length === 0) {
          container.innerHTML = '<div class="empty-hint"><i class="fas fa-award me-2" style="opacity:.5;"></i>No badges available.</div>';
        } else {
          const BADGES_VISIBLE_INITIAL = 12;
          const allBadges = progress;
          const visibleBadges = allBadges.slice(0, BADGES_VISIBLE_INITIAL);
          const hiddenBadges = allBadges.slice(BADGES_VISIBLE_INITIAL);
          const hasMore = hiddenBadges.length > 0;
          const rarityColors = {
            generator: '#60a5fa',
            drop: '#a78bfa',
            collector: '#34d399',
            legendary_pull: '#fbbf24',
            level: '#f472b6',
            mind_wars_wins: '#00d4ff',
            mind_wars_streak: '#f97316',
            mind_wars_special: '#8b5cf6',
            mind_wars_legendary: '#eab308'
          };

          function renderBadgeCard(badge) {
            const isUnlocked = badge.unlocked;
            const color = isUnlocked ? (rarityColors[badge.unlock_type] || '#00e8ff') : 'rgba(255,255,255,0.2)';
            let extra = '';
            if (isUnlocked) {
              const uBadge = unlocked.find(u => u.code === badge.code);
              if (uBadge) {
                extra = '<div class="badge-date">' + kndEsc(new Date(uBadge.unlocked_at).toLocaleDateString()) + '</div>';
              }
            } else {
              const progColor = rarityColors[badge.unlock_type] || '#00e8ff';
              extra = '<div class="badge-prog-meta">' + badge.current + ' / ' + badge.threshold + '</div>';
              extra += '<div class="badge-progress"><div class="badge-prog-fill" style="width:' + badge.progress_percent + '%;background:' + progColor + '"></div></div>';
            }
            let h = '<div class="badge-card' + (!isUnlocked ? ' locked' : '') + '">';
            if (!isUnlocked) h += '<span class="lock-icon"><i class="fas fa-lock"></i></span>';
            h += '<div class="badge-icon" style="color:' + color + '"><i class="fas fa-award"></i></div>';
            h += '<div class="badge-name">' + kndEsc(badge.name) + '</div>';
            h += '<div class="badge-desc">' + kndEsc(badge.description) + '</div>';
            h += extra + '</div>';
            return h;
          }

          let html = '<div class="badges-grid" id="badges-visible-row">';
          visibleBadges.forEach(badge => { html += renderBadgeCard(badge); });
          html += '</div>';

          if (hasMore) {
            html += '<div id="badges-more-wrap" style="display:none;"><div class="badges-grid mt-2">';
            hiddenBadges.forEach(badge => { html += renderBadgeCard(badge); });
            html += '</div></div>';
            html += '<div class="avatars-more-toggle"><button type="button" id="badges-toggle-btn" data-expanded="false">';
            html += '<i class="fas fa-chevron-down me-1"></i> Show more (' + hiddenBadges.length + ')';
            html += '</button></div>';
          }

          const milestoneLabels = {
            generator: 'Images Generated',
            drop: 'Drops Opened',
            collector: 'Items Collected',
            legendary_pull: 'Legendary Pulls',
            level: 'Current Level',
            mind_wars_wins: 'Mind Wars Wins',
            mind_wars_streak: 'Best Win Streak',
            mind_wars_special: 'Special Uses',
            mind_wars_legendary: 'Legendary Defeated'
          };
          html += '<div class="mile-grid">';
          Object.keys(milestones).forEach(key => {
            html += '<div class="mile-item"><div class="mile-val knd-count">' + kndEsc(String(milestones[key])) + '</div>';
            html += '<div class="mile-label">' + kndEsc(milestoneLabels[key] || key) + '</div></div>';
          });
          html += '</div>';

          container.innerHTML = html;
          kndProfileAnimateNumbers(container);

          const toggleBtn = document.getElementById('badges-toggle-btn');
          const moreWrap = document.getElementById('badges-more-wrap');
          if (toggleBtn && moreWrap) {
            toggleBtn.addEventListener('click', function() {
              const expanded = this.getAttribute('data-expanded') === 'true';
              moreWrap.style.display = expanded ? 'none' : 'block';
              this.setAttribute('data-expanded', !expanded);
              this.innerHTML = expanded
                ? '<i class="fas fa-chevron-down me-1"></i> Show more (' + hiddenBadges.length + ')'
                : '<i class="fas fa-chevron-up me-1"></i> Show less';
            });
          }
        }
      }
    }
  })
  .catch(() => {
    const container = document.getElementById('badges-container');
    if (container) {
      container.innerHTML = '<div class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load badges</div>';
    }
  });
</script>
<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>
