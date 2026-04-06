<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

$arenaInfoCss = __DIR__ . '/assets/css/arena-info-pages.css';
$extraHead = '<link rel="stylesheet" href="/assets/css/arena-info-pages.css?v=' . (file_exists($arenaInfoCss) ? filemtime($arenaInfoCss) : 0) . '">';
echo generateHeader('How KND Arena Works | KND Arena', 'Learn how KND Arena works: XP, levels, seasons, KND Points, and our progression-based entertainment ecosystem.', $extraHead, true);
?>

<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<section class="arena-info-hero">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <p class="arena-info-eyebrow">Ecosystem</p>
                <h1 class="arena-info-title">How KND Arena Works</h1>
                <p class="arena-info-subtitle">A progression-based digital ecosystem where you earn experience, level up, and compete on leaderboards — all for entertainment within the KND platform.</p>
            </div>
        </div>
    </div>
</section>

<section class="arena-info-section py-4">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto arena-info-content">

                <div class="arena-info-card">
                    <h2><i class="fas fa-gamepad"></i>What Is KND Arena?</h2>
                    <p class="arena-info-text mb-3">
                        KND Arena is our digital entertainment space where you play skill-based and chance-based games — such as LastRoll 1v1, Above/Under (Insight), and Drop Chamber — to earn experience (XP) and KND Points (KP).
                    </p>
                    <p class="arena-info-text mb-0">
                        It is a progression system designed for engagement and fun: you play, you earn, you advance. All within the KND ecosystem.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-star"></i>Experience (XP)</h2>
                    <p class="arena-info-text mb-3">
                        Experience points are earned by participating in Arena games and activities. XP drives your level and your position on leaderboards.
                    </p>
                    <p class="arena-info-text mb-3">
                        XP progression uses a <strong>quadratic scaling</strong> model: to reach level <em>L</em>, you need <code>100 × L²</code> total XP. For example, level 1 requires 0–99 XP, level 2 requires 100–399 XP, and so on. This design rewards sustained engagement while keeping early progression accessible.
                    </p>
                    <p class="arena-info-text mb-0">
                        XP is tracked per account and cannot be transferred between users.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-chart-line"></i>Levels & Progression</h2>
                    <p class="arena-info-text mb-3">
                        Your total XP determines your level, from 1 up to a maximum of <strong>level 30</strong>. Once you reach level 30, you remain at that level; XP continues to accumulate for leaderboard and ranking purposes.
                    </p>
                    <p class="arena-info-text mb-0">
                        Each level represents a milestone in your Arena journey and reflects your participation and engagement over time.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-calendar-alt"></i>Seasons</h2>
                    <p class="arena-info-text mb-3">
                        KND Arena operates with seasons: time-bound periods during which rankings and activity are tracked. Seasonal leaderboards reset or are structured so that each season offers a fresh competitive frame.
                    </p>
                    <p class="arena-info-text mb-0">
                        Your progress, XP, and KP remain tied to your account across seasons; leaderboard standings may reset or cycle per season to give everyone a fair chance to climb.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-coins"></i>KND Points</h2>
                    <p class="arena-info-text mb-3">
                        KND Points (KP) are internal credits earned through Arena activities. They can be used within the platform — for example, to redeem store credits, rewards, or to participate in Arena features.
                    </p>
                    <p class="arena-info-text mb-3">
                        KP have <strong>no cash value</strong>. They are non-transferable between accounts and cannot be exchanged for real money or external currencies. There is <strong>no cashout option</strong>. KP are for in-platform use only.
                    </p>
                    <p class="arena-info-text mb-0">
                        Think of KP as entertainment credits that unlock experiences within KND — not as currency with monetary value.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-theater-masks"></i>Entertainment-Only Ecosystem</h2>
                    <p class="arena-info-text mb-3">
                        KND Arena is designed for entertainment only. All games, rewards, XP, and KP exist within a closed, non-monetizable ecosystem. You play for fun, progression, and community — not for financial gain.
                    </p>
                    <p class="arena-info-text mb-0">
                        We are committed to maintaining a clear line: everything you earn stays inside KND, has no cash value, and cannot be cashed out.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-shield-alt"></i>Fairness & Integrity</h2>
                    <p class="arena-info-text mb-3">
                        KND Arena is built on fair play and transparency. Random outcomes are generated server-side using cryptographically secure methods. We enforce integrity protections to ensure the same rules for everyone.
                    </p>
                    <p class="arena-info-text mb-0">
                        For full details on how we ensure fairness, see our <a href="/game-fairness" class="arena-info-link">Game Fairness & Transparency</a> page.
                    </p>
                </div>

            </div>
        </div>
    </div>
</section>

<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>
