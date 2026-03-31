<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

$arenaInfoCss = __DIR__ . '/assets/css/arena-info-pages.css';
$extraHead = '<link rel="stylesheet" href="/assets/css/arena-info-pages.css?v=' . (file_exists($arenaInfoCss) ? filemtime($arenaInfoCss) : 0) . '">';
echo generateHeader('Game Fairness & Transparency | KND Arena', 'How KND Arena ensures fair play: server-side RNG, integrity protections, and transparent rewards policy.', $extraHead, true);
?>

<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<section class="arena-info-hero">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <p class="arena-info-eyebrow">Transparency</p>
                <h1 class="arena-info-title">Game Fairness & Transparency</h1>
                <p class="arena-info-subtitle">How we ensure fair play and transparent outcomes in KND Arena.</p>
            </div>
        </div>
    </div>
</section>

<section class="arena-info-section py-4">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto arena-info-content">

                <div class="arena-info-card">
                    <h2><i class="fas fa-random"></i>How Randomness Works</h2>
                    <p class="arena-info-text mb-3">
                        All random outcomes in KND Arena — including dice rolls, drop results, and game-critical decisions — are generated <strong>on the server</strong> using PHP's <code>random_int()</code>. This is a cryptographically secure random number generator designed for fairness and unpredictability.
                    </p>
                    <p class="arena-info-text mb-0">
                        Results are <strong>never</strong> determined by the client or your browser. When you roll, claim, or open a drop, the server computes the outcome and sends it to you. This design prevents manipulation and ensures every player has the same odds.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-shield-alt"></i>Integrity Protections</h2>
                    <p class="arena-info-text mb-3">
                        We protect game integrity through server-side validation, rate limiting, and anti-abuse measures. Actions such as rolls, claims, and drops are verified and recorded on our systems. We do not rely on client-side data for outcomes.
                    </p>
                    <p class="arena-info-text mb-0">
                        Our commitment is simple: the same rules for everyone, enforced consistently by the platform.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-coins"></i>Points & Rewards Policy</h2>
                    <p class="arena-info-text mb-3">
                        <strong>KND Points (KP)</strong> and rewards earned in KND Arena are internal credits used within our ecosystem. They have <strong>no monetary value</strong> and cannot be exchanged for cash or other external currencies.
                    </p>
                    <p class="arena-info-text mb-3">
                        Points and rewards are <strong>non-transferable</strong> between accounts and are tied to the account that earned them. There is no cashout option — KP is for in-platform use only (e.g. redemption for store credits, rewards, or participation in Arena features).
                    </p>
                    <p class="arena-info-text mb-0">
                        We treat this as a transparent system: you play, you earn, you use within KND. No hidden economics.
                    </p>
                </div>

                <div class="arena-info-card">
                    <h2><i class="fas fa-eye"></i>Transparency Commitment</h2>
                    <p class="arena-info-text mb-3">
                        We believe players deserve to know how their games work. This page exists so you can see our approach: server-side randomness, clear rules, and honest communication about what points mean and how they can be used.
                    </p>
                    <p class="arena-info-text mb-0">
                        Questions? Reach out via <a href="/contact.php" class="arena-info-link">Contact</a> or our <a href="https://discord.gg/zjP3u5Yztx" target="_blank" rel="noopener" class="arena-info-link">Discord</a>. We're here to help.
                    </p>
                </div>

            </div>
        </div>
    </div>
</section>

<?php echo generateFooter(); ?>
<?php echo generateScripts(); ?>
