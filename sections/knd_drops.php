<?php
// KND DROPS / COLLECTIONS
?>
<section class="knd-section knd-drops-section knd-animate" id="knd-drops">
    <div class="container">
        <div class="knd-section-header">
            <div>
                <p class="knd-section-eyebrow"><?php echo t('home.drops.eyebrow', 'Drops & Collections'); ?></p>
                <h2 class="knd-section-title"><?php echo t('home.drops.title', 'Collections designed to be unlocked'); ?></h2>
            </div>
            <p class="knd-section-desc">
                <?php echo t('home.drops.desc', 'Premium digital loot, seasonal capsules, and visual progression connected with Arena and Labs.'); ?>
            </p>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <article class="knd-panel knd-drop-featured">
                    <header class="knd-panel-header knd-drop-header">
                        <div>
                            <span class="knd-badge-pill knd-badge-live">LIVE DROP</span>
                            <h3 class="knd-drop-title-main">Nebula Core // Season 01</h3>
                            <p class="knd-drop-kicker">
                                <?php echo t('home.drops.featured_kicker', 'Digital artifacts linked to your activity inside KND.'); ?>
                            </p>
                        </div>
                        <div class="knd-drop-meta-side">
                            <span class="knd-chip"><?php echo t('home.drops.type', 'Loot Capsules'); ?></span>
                            <span class="knd-chip knd-chip-soft"><?php echo t('home.drops.connection', 'Arena • Labs • Apparel'); ?></span>
                        </div>
                    </header>
                    <div class="knd-panel-body knd-drop-featured-body">
                        <div class="knd-drop-artwork">
                            <div class="knd-drop-artwork-inner">
                                <div class="knd-drop-glow"></div>
                                <div class="knd-drop-capsule">
                                    <span class="knd-drop-label-top">KND // DROP</span>
                                    <span class="knd-drop-label-main">NEBULA CORE</span>
                                    <span class="knd-drop-label-bottom">S01 • ARTIFACT PROTOCOL</span>
                                </div>
                            </div>
                        </div>
                        <div class="knd-drop-info-main">
                            <div class="knd-drop-stats">
                                <div class="knd-drop-stat">
                                    <span class="knd-stat-label"><?php echo t('home.drops.progress', 'Collection progress'); ?></span>
                                    <div class="knd-progress">
                                        <div class="knd-progress-bar" style="width: 36%;"></div>
                                    </div>
                                    <span class="knd-stat-value">12 / 34 artifacts</span>
                                </div>
                                <div class="knd-drop-stat">
                                    <span class="knd-stat-label"><?php echo t('home.drops.rarity', 'Rarity distribution'); ?></span>
                                    <div class="knd-rarity-row">
                                        <span class="knd-rarity-pill knd-rarity-core">Core × 18</span>
                                        <span class="knd-rarity-pill knd-rarity-rare">Rare × 10</span>
                                        <span class="knd-rarity-pill knd-rarity-mythic">Mythic × 6</span>
                                    </div>
                                </div>
                            </div>
                            <div class="knd-drop-ctas">
                                <a href="/games/knd-neural-link/drops.php" class="btn knd-btn-primary btn-lg w-100 mb-3">
                                    <i class="fas fa-box-open me-2"></i><?php echo t('home.drops.cta_open', 'Open Drop Chamber'); ?>
                                </a>
                                <a href="/games/knd-neural-link/drops.php" class="btn knd-btn-ghost w-100">
                                    <i class="fas fa-layer-group me-2"></i><?php echo t('home.drops.cta_view_collection', 'View full collection'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="col-lg-5">
                <div class="knd-drop-side-list">
                    <article class="knd-panel knd-drop-card-small">
                        <header class="knd-drop-card-header">
                            <span class="knd-badge-soft"><?php echo t('home.drops.side_seasonal', 'Seasonal'); ?></span>
                            <span class="knd-drop-name">Signal Echo Pack</span>
                        </header>
                        <p class="knd-drop-card-body">
                            <?php echo t('home.drops.side1', 'Pack of icons, banners, and visual signals for your profile inside and outside KND.'); ?>
                        </p>
                        <footer class="knd-drop-card-footer">
                            <span class="knd-chip"><?php echo t('home.drops.side_core', 'Core'); ?></span>
                            <a href="/games/knd-neural-link/drops.php" class="knd-link-ghost">
                                <?php echo t('home.drops.side_view', 'View details'); ?> <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </footer>
                    </article>

                    <article class="knd-panel knd-drop-card-small">
                        <header class="knd-drop-card-header">
                            <span class="knd-badge-soft"><?php echo t('home.drops.side_event', 'Event'); ?></span>
                            <span class="knd-drop-name">Arena Badge Set</span>
                        </header>
                        <p class="knd-drop-card-body">
                            <?php echo t('home.drops.side2', 'Themed badges linked to milestones in KND Arena and community challenges.'); ?>
                        </p>
                        <footer class="knd-drop-card-footer">
                            <span class="knd-chip"><?php echo t('home.drops.side_rare', 'Rare'); ?></span>
                            <a href="/knd-arena.php" class="knd-link-ghost">
                                <?php echo t('home.drops.side_earn', 'Earn in Arena'); ?> <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </footer>
                    </article>

                    <article class="knd-panel knd-drop-card-small">
                        <header class="knd-drop-card-header">
                            <span class="knd-badge-soft"><?php echo t('home.drops.side_collab', 'Collab'); ?></span>
                            <span class="knd-drop-name">Labs // Apparel Sync</span>
                        </header>
                        <p class="knd-drop-card-body">
                            <?php echo t('home.drops.side3', 'Visuals generated in Labs turned into selected apparel pieces.'); ?>
                        </p>
                        <footer class="knd-drop-card-footer">
                            <span class="knd-chip"><?php echo t('home.drops.side_mythic', 'Mythic'); ?></span>
                            <a href="/labs" class="knd-link-ghost">
                                <?php echo t('home.drops.side_create', 'Create in Labs'); ?> <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </footer>
                    </article>
                </div>
            </div>
        </div>
    </div>
</section>

