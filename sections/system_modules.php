<?php
// SYSTEM MODULES GRID
?>
<section class="knd-section knd-system-modules knd-animate" id="knd-system-modules">
    <div class="container">
        <div class="knd-section-header">
            <div>
                <p class="knd-section-eyebrow"><?php echo t('home.modules.eyebrow', 'KND Ecosystem'); ?></p>
                <h2 class="knd-section-title"><?php echo t('home.modules.title', 'Choose your mission module'); ?></h2>
            </div>
            <p class="knd-section-desc">
                <?php echo t('home.modules.desc', 'Labs, Arena, Drops, Marketplace, and Apparel operate as connected modules inside the same system.'); ?>
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <article class="knd-panel knd-module-card knd-module-labs">
                    <header class="knd-module-header">
                        <span class="knd-module-icon"><i class="fas fa-microscope"></i></span>
                        <div>
                            <h3 class="knd-module-title">KND Labs</h3>
                            <p class="knd-module-tagline"><?php echo t('home.modules.labs_tag', 'Create / Iterate / Experiment'); ?></p>
                        </div>
                    </header>
                    <p class="knd-module-body">
                        <?php echo t('home.modules.labs_desc', 'Image generation, 3D, textures, and visual assets powered by AI.'); ?>
                    </p>
                    <footer class="knd-module-footer">
                        <span class="knd-badge-soft">AI • 3D • Texture</span>
                        <a href="/labs" class="knd-module-cta">
                            <span><?php echo t('home.modules.enter', 'Enter module'); ?></span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </footer>
                </article>
            </div>

            <div class="col-md-6 col-lg-3">
                <article class="knd-panel knd-module-card knd-module-arena">
                    <header class="knd-module-header">
                        <span class="knd-module-icon"><i class="fas fa-gamepad"></i></span>
                        <div>
                            <h3 class="knd-module-title">KND Arena</h3>
                            <p class="knd-module-tagline"><?php echo t('home.modules.arena_tag', 'Play / Risk / Reward'); ?></p>
                        </div>
                    </header>
                    <p class="knd-module-body">
                        <?php echo t('home.modules.arena_desc', 'Mini-games, controlled RNG, XP, leaderboards, and internal rewards.'); ?>
                    </p>
                    <footer class="knd-module-footer">
                        <span class="knd-badge-soft">LastRoll • Insight • More</span>
                        <a href="/knd-arena.php" class="knd-module-cta">
                            <span><?php echo t('home.modules.play', 'Enter Arena'); ?></span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </footer>
                </article>
            </div>

            <div class="col-md-6 col-lg-3">
                <article class="knd-panel knd-module-card knd-module-drops" id="knd-drops-section">
                    <header class="knd-module-header">
                        <span class="knd-module-icon"><i class="fas fa-box-open"></i></span>
                        <div>
                            <h3 class="knd-module-title">KND Drops</h3>
                            <p class="knd-module-tagline"><?php echo t('home.modules.drops_tag', 'Collect / Seasons / Lore'); ?></p>
                        </div>
                    </header>
                    <p class="knd-module-body">
                        <?php echo t('home.modules.drops_desc', 'Digital collections, capsules, seasonal artifacts, and visual progression.'); ?>
                    </p>
                    <footer class="knd-module-footer">
                        <span class="knd-badge-soft">Loot • Collections</span>
                        <a href="/games/knd-neural-link/drops.php" class="knd-module-cta">
                            <span><?php echo t('home.modules.open_drop', 'Open Drop Hub'); ?></span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </footer>
                </article>
            </div>

            <div class="col-md-6 col-lg-3">
                <article class="knd-panel knd-module-card knd-module-market">
                    <header class="knd-module-header">
                        <span class="knd-module-icon"><i class="fas fa-layer-group"></i></span>
                        <div>
                            <h3 class="knd-module-title">Marketplace</h3>
                            <p class="knd-module-tagline"><?php echo t('home.modules.market_tag', 'Services / Apparel / Digital'); ?></p>
                        </div>
                    </header>
                    <p class="knd-module-body">
                        <?php echo t('home.modules.market_desc', 'Digital services, creative activations, and apparel connected to the ecosystem.'); ?>
                    </p>
                    <footer class="knd-module-footer">
                        <span class="knd-badge-soft">Services • Apparel</span>
                        <a href="/products.php" class="knd-module-cta">
                            <span><?php echo t('home.modules.view_market', 'Explore Marketplace'); ?></span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </footer>
                </article>
            </div>
        </div>
    </div>
</section>

