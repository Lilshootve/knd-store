<?php
/**
 * Shared progress and leaderboard panel component.
 * Context: 'arena' | 'mind-wars' | 'knowledge-duel'
 *
 * @param string $context One of: arena, mind-wars, knowledge-duel
 * @param array|null $profileData For arena: profile data from profile_get_data()
 * @param int $balance For arena: credits balance
 * @param int $avatarCount For arena: avatars collected count
 * @param bool $isLoggedIn For arena: whether user is logged in
 */
if (!function_exists('render_knd_progress_panel')) {
function render_knd_progress_panel($context, $profileData = null, $balance = 0, $avatarCount = 0, $isLoggedIn = false) {
    $ctx = in_array($context, ['arena', 'mind-wars', 'knowledge-duel']) ? $context : 'arena';
    $pfx = $ctx === 'arena' ? 'arena' : ($ctx === 'mind-wars' ? 'mw' : 'kd');
    $showArenaData = ($ctx === 'arena' && $isLoggedIn && $profileData);
    ?>
    <div class="knd-progress-panel glass-card-neon <?php echo $ctx !== 'arena' ? 'p-4 mb-4 ' : ''; ?><?php echo $pfx; ?>-progress-card <?php echo $ctx === 'arena' ? 'arena-progress-panel' : ''; ?><?php echo $ctx === 'mind-wars' ? ' player-panel' : ''; ?>">
        <h3 class="arena-panel-title knd-panel-title"><i class="fas fa-chart-line me-2"></i><?php echo $ctx === 'arena' ? 'Player Progress' : 'Progress Panel'; ?></h3>
        <?php if ($ctx === 'arena'): ?>
            <?php if ($showArenaData): ?>
                <div class="arena-progress-row knd-progress-row">
                    <span class="arena-progress-label knd-progress-label">Level</span>
                    <span class="arena-progress-value knd-progress-value"><?php echo (int) $profileData['level']; ?></span>
                </div>
                <?php
                $pct = $profileData['progress']['isMaxLevel'] ? 100 : min(100, max(0, (float) $profileData['progress']['progressPct'] * 100));
                ?>
                <div class="arena-xp-bar knd-xp-bar">
                    <div class="arena-xp-bar-fill knd-xp-bar-fill" id="arena-xp-bar-fill" style="--arena-xp-width: <?php echo round($pct, 1); ?>%;"></div>
                </div>
                <div class="arena-progress-row knd-progress-row">
                    <span class="arena-progress-label knd-progress-label">Arena Rank</span>
                    <span class="arena-progress-value knd-progress-value">#<?php echo !empty($profileData['season']['rank']) ? (int) $profileData['season']['rank'] : '—'; ?></span>
                </div>
                <div class="arena-progress-row knd-progress-row">
                    <span class="arena-progress-label knd-progress-label">Credits</span>
                    <span class="arena-progress-value arena-accent knd-progress-value"><?php echo number_format($balance); ?></span>
                </div>
                <div class="arena-progress-row knd-progress-row">
                    <span class="arena-progress-label knd-progress-label">Avatars Collected</span>
                    <span class="arena-progress-value knd-progress-value"><?php echo (int) $avatarCount; ?></span>
                </div>
            <?php else: ?>
                <p class="text-white-50 small mb-0"><?php echo t('arena.login_prompt', 'Log in to see your progress.'); ?></p>
            <?php endif; ?>
        <?php elseif ($ctx === 'mind-wars'): ?>
            <div class="mw-progress-pills mb-3">
                <span class="badge mw-pill" id="mw-level-pill">Lv 1</span>
                <span class="badge mw-pill mw-pill--rank" id="mw-rank-pill">Rank 0</span>
            </div>
            <div class="mb-2">
                <div class="d-flex justify-content-between small text-white-50 mb-1">
                    <span>User XP</span><span id="mw-stat-xp-detail">0 / 0</span>
                </div>
                <div class="progress">
                    <div id="mw-user-panel-progress" class="progress-bar bg-info" role="progressbar" style="width:0%"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between small text-white-50 mb-1">
                    <span>Current Fighter XP</span><span id="mw-stat-ke-detail">0 / 0</span>
                </div>
                <div class="progress">
                    <div id="mw-avatar-panel-progress" class="progress-bar bg-warning" role="progressbar" style="width:0%"></div>
                </div>
            </div>
            <ul class="list-unstyled mb-0 mw-stats-list knd-stats-list">
                <li><span>XP</span><strong id="mw-stat-xp">0</strong></li>
                <li><span>Level</span><strong id="mw-stat-level">1</strong></li>
                <li><span>Selected Avatar</span><strong id="mw-stat-avatar">-</strong></li>
                <li><span>Current Fighter XP</span><strong id="mw-stat-ke">0</strong></li>
                <li><span>Avatar Level</span><strong id="mw-stat-avatar-level">1</strong></li>
                <li><span>Season Rank Score</span><strong id="mw-stat-rank">0</strong></li>
                <li><span>Wins</span><strong id="mw-stat-wins">0</strong></li>
                <li><span>Losses</span><strong id="mw-stat-losses">0</strong></li>
                <li><span>Win Rate</span><strong id="mw-stat-win-rate">0%</strong></li>
                <li><span>Estimated Position</span><strong id="mw-stat-position">-</strong></li>
            </ul>
            <a href="/my-profile.php#badges-container" class="small text-white-50" style="text-decoration:underline;"><i class="fas fa-award me-1"></i>View all badges</a>
        <?php elseif ($ctx === 'knowledge-duel'): ?>
            <div class="kd-progress-pills mb-3">
                <span class="badge kd-pill" id="kd-level-pill">Lv 1</span>
                <span class="badge kd-pill kd-pill--rank" id="kd-rank-pill">Rank 0</span>
            </div>
            <div class="mb-2">
                <div class="d-flex justify-content-between small text-white-50 mb-1">
                    <span>User XP</span><span id="kd-stat-xp-detail">0 / 0</span>
                </div>
                <div class="progress">
                    <div id="kd-user-panel-progress" class="progress-bar bg-info" role="progressbar" style="width:0%"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between small text-white-50 mb-1">
                    <span>Avatar Energy</span><span id="kd-stat-ke-detail">0 / 0</span>
                </div>
                <div class="progress">
                    <div id="kd-avatar-panel-progress" class="progress-bar bg-warning" role="progressbar" style="width:0%"></div>
                </div>
            </div>
            <ul class="list-unstyled mb-0 kd-stats-list knd-stats-list">
                <li><span>XP</span><strong id="kd-stat-xp">0</strong></li>
                <li><span>Level</span><strong id="kd-stat-level">1</strong></li>
                <li><span>Selected Avatar</span><strong id="kd-stat-avatar">-</strong></li>
                <li><span>Knowledge Energy</span><strong id="kd-stat-ke">0</strong></li>
                <li><span>Avatar Level</span><strong id="kd-stat-avatar-level">1</strong></li>
                <li><span>Season Rank Score</span><strong id="kd-stat-rank">0</strong></li>
                <li><span>Estimated Position</span><strong id="kd-stat-position">-</strong></li>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}
}

if (!function_exists('render_knd_leaderboard_panel')) {
function render_knd_leaderboard_panel($context) {
    $ctx = in_array($context, ['arena', 'mind-wars', 'knowledge-duel']) ? $context : 'arena';
    $pfx = $ctx === 'arena' ? 'arena' : ($ctx === 'mind-wars' ? 'mw' : 'kd');
    ?>
    <div class="knd-leaderboard-panel glass-card-neon <?php echo $ctx !== 'arena' ? 'p-4 ' : ''; ?><?php echo $pfx; ?>-leaderboard-card <?php echo $ctx === 'arena' ? 'arena-leaderboard' : ''; ?><?php echo $ctx === 'mind-wars' ? ' leaderboard-panel' : ''; ?>">
        <?php if ($ctx === 'arena'): ?>
            <div class="arena-leaderboard-header">
                <h3 class="arena-panel-title knd-panel-title mb-0"><i class="fas fa-trophy me-2"></i>Leaderboard</h3>
                <a href="/leaderboard.php" class="arena-leaderboard-link">View all</a>
            </div>
            <ul class="arena-leaderboard-list knd-leaderboard-list" id="arena-leaderboard-list">
                <li class="text-white-50 small">Loading…</li>
            </ul>
        <?php elseif ($ctx === 'mind-wars'): ?>
            <h4 class="mb-3 knd-panel-title"><i class="fas fa-trophy me-2"></i>Season Leaderboard</h4>
            <div class="small text-white-50 mb-2" id="mw-season-name">Loading season...</div>
            <div class="small text-white-50 mb-3" id="mw-season-countdown">Time remaining: --</div>
            <div id="mw-leaderboard" class="small"></div>
        <?php elseif ($ctx === 'knowledge-duel'): ?>
            <h4 class="mb-3 knd-panel-title"><i class="fas fa-trophy me-2"></i>Season Leaderboard</h4>
            <div class="small text-white-50 mb-2" id="kd-season-name">Loading season...</div>
            <div id="kd-leaderboard" class="small"></div>
        <?php endif; ?>
    </div>
    <?php
}
}
