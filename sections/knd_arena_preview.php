<?php
// KND ARENA PREVIEW
?>
<section class="knd-section knd-arena-preview knd-animate" id="knd-arena-preview">
    <div class="container">
        <div class="knd-section-header">
            <div>
                <p class="knd-section-eyebrow"><?php echo t('home.arena.eyebrow', 'KND Arena'); ?></p>
                <h2 class="knd-section-title"><?php echo t('home.arena.title', 'Playground designed for rewards, not noise'); ?></h2>
            </div>
            <p class="knd-section-desc">
                <?php echo t('home.arena.desc', 'Mini-games with transparent RNG, XP, internal points, and seasons designed for players who want something different.'); ?>
            </p>
        </div>

        <div class="knd-panel knd-arena-panel">
            <header class="knd-panel-header knd-arena-header">
                <div>
                    <span class="knd-badge-pill knd-badge-live">ARENA HUB</span>
                    <p class="knd-section-eyebrow mb-1"><?php echo t('home.arena.mode_label', 'Game Modes'); ?></p>
                    <h3 class="knd-section-title mb-0"><?php echo t('home.arena.modes_title', 'Choose how you want to play.'); ?></h3>
                </div>
                <div class="knd-arena-meta">
                    <span class="knd-chip">XP • KP • Badges</span>
                    <span class="knd-chip knd-chip-soft">Internal Economy • No cash-out</span>
                </div>
            </header>
            <div class="knd-panel-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <article class="knd-arena-mode">
                            <header>
                                <span class="knd-arena-icon"><i class="fas fa-dice-d20"></i></span>
                                <h4 class="knd-arena-title">LastRoll 1v1</h4>
                            </header>
                            <p class="knd-arena-desc">
                                <?php echo t('home.arena.lastroll_desc', '1v1 duel with progressive death rolls. Simple to understand, intense to play.'); ?>
                            </p>
                            <ul class="knd-arena-tags">
                                <li>Controlled RNG</li>
                                <li>XP Boost</li>
                                <li>Season badges</li>
                            </ul>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="knd-arena-mode">
                            <header>
                                <span class="knd-arena-icon"><i class="fas fa-eye"></i></span>
                                <h4 class="knd-arena-title">KND Insight</h4>
                            </header>
                            <p class="knd-arena-desc">
                                <?php echo t('home.arena.insight_desc', 'Predict the number, adjust your risk, and turn pattern reading into KP.'); ?>
                            </p>
                            <ul class="knd-arena-tags">
                                <li>Pattern reading</li>
                                <li>Fast rounds</li>
                                <li>Visual streaks</li>
                            </ul>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="knd-arena-mode">
                            <header>
                                <span class="knd-arena-icon"><i class="fas fa-box-open"></i></span>
                                <h4 class="knd-arena-title">Drop Chamber</h4>
                            </header>
                            <p class="knd-arena-desc">
                                <?php echo t('home.arena.drop_desc', 'Open capsules connected to collections, wallpapers, and apparel.'); ?>
                            </p>
                            <ul class="knd-arena-tags">
                                <li>Clear loot tables</li>
                                <li>Integrated with Drops</li>
                                <li>Seasonal events</li>
                            </ul>
                        </article>
                    </div>
                </div>
            </div>
            <footer class="knd-panel-footer knd-arena-footer">
                <div class="knd-arena-footer-main">
                    <p class="knd-body-text mb-0">
                        <?php echo t('home.arena.footer_text', 'KND Arena runs on internal points (KP). No cash-out, no empty promises: just gameplay, XP, and collectibles inside the ecosystem.'); ?>
                    </p>
                </div>
                <div class="knd-arena-footer-cta">
                    <a href="/knd-arena.php" class="btn knd-btn-primary knd-cta-main btn-lg">
                        <i class="fas fa-play me-2"></i><?php echo t('home.arena.cta', 'Enter KND Arena'); ?>
                    </a>
                </div>
            </footer>
        </div>
    </div>
</section>

