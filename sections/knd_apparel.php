<?php
// KND APPAREL
?>
<section class="knd-section knd-apparel-section knd-animate" id="knd-apparel">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <div class="knd-panel knd-apparel-panel">
                    <header class="knd-panel-header">
                        <p class="knd-section-eyebrow mb-1"><?php echo t('home.apparel.eyebrow', 'KND Apparel'); ?></p>
                        <h2 class="knd-section-title mb-0"><?php echo t('home.apparel.title', 'Community merch, not generic merch'); ?></h2>
                    </header>
                    <div class="knd-panel-body">
                        <p class="knd-body-text">
                            <?php echo t('home.apparel.desc', 'Hoodies, tees, and limited pieces that extend the KND universe into the physical world. Designed for builders, gamers, and people who live in digital.'); ?>
                        </p>
                        <ul class="knd-bullet-list">
                            <li><?php echo t('home.apparel.b1', 'CORE lines for daily wear.'); ?></li>
                            <li><?php echo t('home.apparel.b2', 'LIMITED drops tied to seasons and events.'); ?></li>
                            <li><?php echo t('home.apparel.b3', 'Graphics connected to Labs and Arena.'); ?></li>
                        </ul>
                        <a href="/apparel.php" class="btn knd-btn-secondary btn-lg mt-3">
                            <i class="fas fa-tshirt me-2"></i><?php echo t('home.apparel.cta', 'View KND Apparel'); ?>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="knd-panel knd-apparel-cards">
                    <div class="knd-apparel-item knd-apparel-core">
                        <span class="knd-badge-soft">CORE</span>
                        <h3 class="knd-apparel-name">KND Core Signal Hoodie</h3>
                        <p class="knd-apparel-meta"><?php echo t('home.apparel.core_meta', 'Heavy cotton • minimal front print • subtle inner glow'); ?></p>
                    </div>
                    <div class="knd-apparel-item knd-apparel-limited">
                        <span class="knd-badge-soft">LIMITED</span>
                        <h3 class="knd-apparel-name">Nebula Line Tee</h3>
                        <p class="knd-apparel-meta"><?php echo t('home.apparel.limited_meta', 'Graphic tied to Nebula Core Drop • short run'); ?></p>
                    </div>
                    <div class="knd-apparel-item knd-apparel-community">
                        <span class="knd-badge-soft">COMMUNITY</span>
                        <h3 class="knd-apparel-name">Arena Signals Cap</h3>
                        <p class="knd-apparel-meta"><?php echo t('home.apparel.comm_meta', 'Iconography derived from Arena streaks and badges.'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

