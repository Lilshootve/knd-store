<?php
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/header.php';
require_login();

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$csrf_token = csrf_token();
$user_id = (int) current_user_id();
$stmt = $pdo->prepare(
    "SELECT
        inv.item_id,
        inv.avatar_level,
        inv.knowledge_energy,
        ai.name AS ai_name,
        ai.rarity AS ai_rarity,
        mw.id AS mw_avatar_id,
        mw.name AS mw_name,
        mw.rarity AS mw_rarity,
        mw.class,
        mw.image,
        s.mind,
        s.focus,
        s.speed,
        s.luck,
        CASE WHEN mw.id IS NULL THEN 0 ELSE 1 END AS is_connected
     FROM knd_user_avatar_inventory inv
     JOIN knd_avatar_items ai ON ai.id = inv.item_id
     LEFT JOIN mw_avatars mw ON LOWER(TRIM(mw.name)) = LOWER(TRIM(ai.name))
     LEFT JOIN mw_avatar_stats s ON s.avatar_id = mw.id
     WHERE inv.user_id = ?
       AND ai.is_active = 1
     ORDER BY FIELD(COALESCE(mw.rarity, ai.rarity),'legendary','epic','rare','special','common'),
              COALESCE(mw.name, ai.name) ASC"
);
$stmt->execute([$user_id]);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$extraHead = '<link rel="stylesheet" href="/games/mind-wars-squad/assets/css/squad-battle.css?v=' . (file_exists(__DIR__ . '/../assets/css/squad-battle.css') ? filemtime(__DIR__ . '/../assets/css/squad-battle.css') : time()) . '">';
echo generateHeader('Mind Wars Squad (3v3) | KND Arena', 'Squad-based Mind Wars PvE 3v3 battle mode.', $extraHead);
echo generateNavigation();
?>
<main class="squad-layout-wrap">

<!-- ════════════════════════════════════════════════════════
     TEAM SELECT SCREEN
════════════════════════════════════════════════════════ -->
<div id="screen-select" class="screen active">
    <div class="screen-inner">
        <h1 class="squad-title">⬡ MIND WARS SQUAD</h1>
        <p class="squad-sub">Select 3 linked avatars. Front / mid / back set formation — back row takes less damage while allies in front still stand.</p>
        <p class="squad-hint">Flow: each round, every living unit acts once (speed + dice set order). Click your active fighter → Attack / Ability / Special / Defend → pick an enemy target if needed.</p>

        <div class="squad-config">
            <!-- Slots -->
            <div class="squad-slots">
                <?php foreach (['front' => 'FRONT', 'mid' => 'MID', 'back' => 'BACK'] as $pos => $label): ?>
                <div class="squad-slot" data-slot-pos="<?= $pos ?>">
                    <div class="slot-label"><?= $label ?></div>
                    <div class="slot-frame" id="slot-frame-<?= $pos ?>">
                        <span class="slot-empty">＋</span>
                    </div>
                    <div class="slot-name" id="slot-name-<?= $pos ?>">Empty</div>
                    <select class="slot-select" id="slot-select-<?= $pos ?>">
                        <option value="">— Select avatar —</option>
                        <?php foreach ($inventory as $av): ?>
                        <?php
                            $isConnected = (int)($av['is_connected'] ?? 0) === 1;
                            $displayName = (string)($av['mw_name'] ?? $av['ai_name'] ?? 'Avatar');
                            $displayRarity = (string)($av['mw_rarity'] ?? $av['ai_rarity'] ?? 'common');
                        ?>
                        <option value="<?= (int)$av['item_id'] ?>"
                                data-name="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
                                data-img="<?= htmlspecialchars((string)($av['image'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-rarity="<?= htmlspecialchars($displayRarity, ENT_QUOTES, 'UTF-8') ?>"
                                data-connected="<?= $isConnected ? '1' : '0' ?>"
                                <?= $isConnected ? '' : 'disabled' ?>>
                            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                            (Lv <?= (int)$av['avatar_level'] ?> · <?= htmlspecialchars(ucfirst($displayRarity), ENT_QUOTES, 'UTF-8') ?><?= $isConnected ? '' : ' · Disconnected' ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Difficulty -->
            <div class="diff-select">
                <label class="diff-label">INTERFERENCE LEVEL</label>
                <div class="diff-btns">
                    <button class="diff-btn" data-diff="easy">EASY</button>
                    <button class="diff-btn active" data-diff="normal">NORMAL</button>
                    <button class="diff-btn" data-diff="hard">HARD</button>
                </div>
            </div>
        </div>

        <button id="btn-start" class="btn-primary btn-launch" disabled>
            ⬡ ENGAGE SQUAD LINK
        </button>
        <div id="select-error" class="error-msg hidden"></div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     BATTLE SCREEN
════════════════════════════════════════════════════════ -->
<div id="screen-battle" class="screen">
    <div class="battle-layout">

        <!-- HEADER STRIP -->
        <div class="battle-header">
            <div class="battle-meta">
                <span class="meta-pill" id="b-turn">Round 1</span>
                <span class="meta-pill meta-diff" id="b-diff">—</span>
            </div>
            <div class="battle-title">SQUAD BATTLE</div>
            <div class="battle-meta">
                <span class="meta-pill" id="b-actor">—</span>
            </div>
        </div>

        <!-- ARENA -->
        <div class="battle-arena">

            <!-- PLAYER TEAM -->
            <div class="team team--player" id="player-team">
                <div class="team-label">YOUR SQUAD</div>
                <div class="unit-column" id="player-units">
                    <!-- Rendered by JS -->
                </div>
            </div>

            <!-- VS -->
            <div class="arena-vs">
                <div class="vs-line"></div>
                <div class="vs-text">VS</div>
                <div class="vs-line"></div>
            </div>

            <!-- ENEMY TEAM -->
            <div class="team team--enemy" id="enemy-team">
                <div class="team-label">ENEMY SQUAD</div>
                <div class="unit-column" id="enemy-units">
                    <!-- Rendered by JS -->
                </div>
            </div>

        </div><!-- end arena -->

        <div class="initiative-strip" id="initiative-strip" aria-live="polite"></div>

        <!-- ACTION BAR -->
        <div class="action-bar" id="action-bar">
            <div class="action-bar__inner">
                <div class="action-bar__context" id="action-context">
                    Select a unit to act
                </div>
                <div class="action-btns" id="action-btns">
                    <button class="action-btn" id="btn-attack"  data-action="attack"  disabled>
                        <span class="ab-icon">⚔</span><span class="ab-label">ATTACK</span>
                    </button>
                    <button class="action-btn" id="btn-ability" data-action="ability" disabled>
                        <span class="ab-icon">🧠</span><span class="ab-label">ABILITY</span>
                        <span class="ab-cost">2⚡</span>
                    </button>
                    <button class="action-btn" id="btn-special" data-action="special" disabled>
                        <span class="ab-icon">🌌</span><span class="ab-label">SPECIAL</span>
                        <span class="ab-cost">5⚡</span>
                    </button>
                    <button class="action-btn" id="btn-defend"  data-action="defend"  disabled>
                        <span class="ab-icon">🛡</span><span class="ab-label">DEFEND</span>
                    </button>
                </div>
                <div class="action-status" id="action-status">
                    Waiting for battle to start...
                </div>
            </div>
        </div>

        <!-- COMBAT LOG -->
        <div class="combat-log-panel">
            <div class="log-header">
                <span class="log-dot"></span> COMBAT LOG
            </div>
            <div class="combat-log-body" id="combat-log"></div>
        </div>

    </div><!-- end battle-layout -->
</div><!-- end screen-battle -->

<!-- ════════════════════════════════════════════════════════
     RESULT SCREEN
════════════════════════════════════════════════════════ -->
<div id="screen-result" class="screen">
    <div class="result-inner">
        <div class="result-glyph" id="result-glyph">⬡</div>
        <div class="result-title" id="result-title">—</div>
        <div class="result-sub"   id="result-sub">Processing...</div>
        <div class="result-rewards" id="result-rewards"></div>
        <div class="result-actions">
            <button class="btn-primary" id="btn-play-again">↺ NEW BATTLE</button>
            <button class="btn-ghost"   onclick="window.location.href='/games/mind-wars/lobby.php'">← BACK</button>
        </div>
    </div>
</div>
</main>

<!-- PHP → JS config bridge -->
<script>
window.SquadConfig = {
    csrfToken: <?= json_encode($csrf_token) ?>,
    userId:    <?= json_encode($user_id) ?>,
    apiBase:   '/games/mind-wars-squad/api',
};
</script>
<script src="/games/mind-wars-squad/assets/js/squad-battle.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/squad-battle.js') ? filemtime(__DIR__ . '/../assets/js/squad-battle.js') : time(); ?>"></script>
