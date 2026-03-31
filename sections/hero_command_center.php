<?php
// HERO / GALACTIC COMMAND CENTER
?>
<section class="knd-section knd-galactic-hero knd-animate" id="hero-command-center">
    <div class="knd-space-bg" aria-hidden="true">
        <div class="knd-nebula-layer"></div>
        <div class="knd-stars-layer"></div>
        <div class="knd-hud-grid"></div>
    </div>

    <div class="knd-galactic-inner container">
        <div class="knd-galactic-layout">
            <header class="knd-galactic-header">
                <div class="knd-hero-label">
                    <span class="knd-chip knd-chip-live"><?php echo t('home.hero.mode', 'KND Galactic Command'); ?></span>
                    <span class="knd-chip"><?php echo t('home.hero.tagline', 'Create • Play • Collect'); ?></span>
                </div>
                <h1 class="knd-hero-title">
                    <span class="knd-hero-kicker">KND STORE / PLATFORM</span>
                    <span class="knd-hero-main">Knowledge ’N Development</span>
                </h1>
                <p class="knd-hero-subtitle">
                    <?php echo t('home.hero.subtitle', 'A portal to the KND digital ecosystem: Labs, Arena, Drops, Services, and Apparel orbiting in one shared universe.'); ?>
                </p>
            </header>

            <div class="knd-orbit-and-detail">
                <div class="knd-orbit-stage" aria-label="KND Modules">
                    <button type="button" class="knd-orbit-nav knd-orbit-nav-prev" data-orbit-nav="prev" aria-label="<?php echo t('home.hero.prev_module', 'Previous module'); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <ul class="knd-orbit-track" data-active-index="0">
                        <li class="knd-orbit-module" data-module="labs"
                            data-title="KND Labs"
                            data-tagline="Create"
                            data-description="Visual lab to generate images, textures, characters, and 3D content that later lives across the KND ecosystem."
                            data-cta-label="<?php echo t('home.hero.cta_labs', 'Enter KND Labs'); ?>"
                            data-cta-link="/labs">
                            <div class="knd-orbit-module-inner knd-orbit-module-labs">
                                <span class="knd-orbit-icon"><i class="fas fa-microscope"></i></span>
                                <span class="knd-orbit-name">KND Labs</span>
                                <span class="knd-orbit-tag">AI • 3D • Texture</span>
                            </div>
                        </li>
                        <li class="knd-orbit-module" data-module="arena"
                            data-title="KND Arena"
                            data-tagline="Play"
                            data-description="Mini-games, controlled RNG, XP, and internal badges. Play inside a transparent system with no empty promises."
                            data-cta-label="<?php echo t('home.hero.cta_arena', 'Enter KND Arena'); ?>"
                            data-cta-link="/knd-arena.php">
                            <div class="knd-orbit-module-inner knd-orbit-module-arena">
                                <span class="knd-orbit-icon"><i class="fas fa-gamepad"></i></span>
                                <span class="knd-orbit-name">KND Arena</span>
                                <span class="knd-orbit-tag">RNG • Skill • XP</span>
                            </div>
                        </li>
                        <li class="knd-orbit-module" data-module="drops"
                            data-title="KND Drops"
                            data-tagline="Collect"
                            data-description="Digital collections, capsules, and artifacts tied to seasons, challenges, and specific ecosystem moments."
                            data-cta-label="<?php echo t('home.hero.cta_drops', 'View Drops and collections'); ?>"
                            data-cta-link="/games/knd-neural-link/drops.php">
                            <div class="knd-orbit-module-inner knd-orbit-module-drops">
                                <span class="knd-orbit-icon"><i class="fas fa-box-open"></i></span>
                                <span class="knd-orbit-name">KND Drops</span>
                                <span class="knd-orbit-tag">Loot • Collections</span>
                            </div>
                        </li>
                        <li class="knd-orbit-module" data-module="services"
                            data-title="<?php echo t('home.hero.services_title', 'Digital Services'); ?>"
                            data-tagline="Deploy"
                            data-description="Consulting, activations, and technical support designed as missions, not generic support tickets."
                            data-cta-label="<?php echo t('home.hero.services_cta', 'Explore Services'); ?>"
                            data-cta-link="/products.php">
                            <div class="knd-orbit-module-inner knd-orbit-module-services">
                                <span class="knd-orbit-icon"><i class="fas fa-sparkles"></i></span>
                                <span class="knd-orbit-name"><?php echo t('home.hero.services_short', 'Digital Services'); ?></span>
                                <span class="knd-orbit-tag">Performance • Activations</span>
                            </div>
                        </li>
                        <li class="knd-orbit-module" data-module="apparel"
                            data-title="KND Apparel"
                            data-tagline="Extend"
                            data-description="Premium apparel connected to the KND universe: CORE essentials and limited drops for the community."
                            data-cta-label="<?php echo t('home.hero.apparel_cta', 'View KND Apparel'); ?>"
                            data-cta-link="/apparel.php">
                            <div class="knd-orbit-module-inner knd-orbit-module-apparel">
                                <span class="knd-orbit-icon"><i class="fas fa-tshirt"></i></span>
                                <span class="knd-orbit-name">KND Apparel</span>
                                <span class="knd-orbit-tag">Core • Limited</span>
                            </div>
                        </li>
                    </ul>
                    <button type="button" class="knd-orbit-nav knd-orbit-nav-next" data-orbit-nav="next" aria-label="<?php echo t('home.hero.next_module', 'Next module'); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <aside class="knd-module-detail" aria-live="polite">
                    <div class="knd-module-detail-header">
                        <p class="knd-section-eyebrow mb-1" id="knd-module-tagline">Create</p>
                        <h2 class="knd-section-title mb-2" id="knd-module-title">KND Labs</h2>
                    </div>
                    <p class="knd-body-text mb-3" id="knd-module-description">
                        Visual lab to generate images, textures, characters, and 3D content that later lives across the KND ecosystem.
                    </p>
                    <ul class="knd-bullet-list knd-module-bullets" id="knd-module-bullets">
                        <li>Generate and test ideas quickly.</li>
                        <li>Connect what you create with Arena, Drops, and Apparel.</li>
                        <li>Optimized for builders, not static demos.</li>
                    </ul>
                    <div class="knd-module-cta-row">
                        <a href="/labs" class="btn knd-btn-primary knd-cta-main btn-lg" id="knd-module-cta">
                            <i class="fas fa-arrow-right me-2"></i><span id="knd-module-cta-label"><?php echo t('home.hero.cta_labs', 'Enter KND Labs'); ?></span>
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>

<section class="knd-section knd-galactic-secondary knd-animate" id="hero-secondary">
    <div class="container">
        <div class="knd-section-header">
            <div>
                <p class="knd-section-eyebrow"><?php echo t('home.secondary.eyebrow', 'Inside the KND ecosystem'); ?></p>
                <h2 class="knd-section-title"><?php echo t('home.secondary.title', 'A connected universe, not disconnected products'); ?></h2>
            </div>
            <p class="knd-section-desc">
                <?php echo t('home.secondary.desc', 'Labs, Arena, Drops, Services, and Apparel feed each other: what you create, play, and collect belongs to one system.'); ?>
            </p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="knd-panel">
                    <h3 class="knd-section-title" style="font-size:1rem;"><?php echo t('home.secondary.block1_title', 'Seasons and progression'); ?></h3>
                    <p class="knd-body-text mb-0">
                        <?php echo t('home.secondary.block1_body', 'We structure content in seasons: new collections, modes, and rewards that evolve the universe without breaking it.'); ?>
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="knd-panel">
                    <h3 class="knd-section-title" style="font-size:1rem;"><?php echo t('home.secondary.block2_title', 'Controlled internal economy'); ?></h3>
                    <p class="knd-body-text mb-0">
                        <?php echo t('home.secondary.block2_body', 'KND Points, XP, and badges live only inside the ecosystem. No cash-out, no external noise, just internal progress.'); ?>
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="knd-panel">
                    <h3 class="knd-section-title" style="font-size:1rem;"><?php echo t('home.secondary.block3_title', 'Builder community'); ?></h3>
                    <p class="knd-body-text mb-0">
                        <?php echo t('home.secondary.block3_body', 'KND is built for people who build: creators, gamers, technical teams, and long-term projects.'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

