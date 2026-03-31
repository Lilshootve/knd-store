<?php
// KND LABS PREVIEW
?>
<section class="knd-section knd-labs-preview knd-animate" id="knd-labs-preview">
    <div class="container">
        <div class="knd-section-header">
            <div>
                <p class="knd-section-eyebrow"><?php echo t('home.labs.eyebrow', 'KND Labs'); ?></p>
                <h2 class="knd-section-title"><?php echo t('home.labs.title', 'A visual lab inside the platform'); ?></h2>
            </div>
            <p class="knd-section-desc">
                <?php echo t('home.labs.desc', 'Image generation, textures, and visual prototypes ready to integrate with Drops, Arena, and Apparel.'); ?>
            </p>
        </div>

        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <div class="knd-panel knd-labs-gallery">
                    <header class="knd-panel-header">
                        <span class="knd-panel-title"><?php echo t('home.labs.gallery_title', 'Visual Lab Feed'); ?></span>
                        <div class="knd-labs-tags">
                            <span class="knd-chip">Text-to-Image</span>
                            <span class="knd-chip">3D Vertex</span>
                            <span class="knd-chip">Texture Lab</span>
                        </div>
                    </header>
                    <div class="knd-panel-body">
                        <div class="knd-labs-grid">
                            <div class="knd-lab-thumb knd-lab-thumb-main">
                                <div class="knd-lab-thumb-inner">
                                    <span class="knd-lab-label">Vertex Render</span>
                                    <span class="knd-lab-caption">KND // Synthetic Pilot</span>
                                </div>
                            </div>
                            <div class="knd-lab-thumb">
                                <div class="knd-lab-thumb-inner">
                                    <span class="knd-lab-label">Texture</span>
                                    <span class="knd-lab-caption">Iridescent Grid</span>
                                </div>
                            </div>
                            <div class="knd-lab-thumb">
                                <div class="knd-lab-thumb-inner">
                                    <span class="knd-lab-label">Avatar</span>
                                    <span class="knd-lab-caption">Signal Operator</span>
                                </div>
                            </div>
                            <div class="knd-lab-thumb">
                                <div class="knd-lab-thumb-inner">
                                    <span class="knd-lab-label">Wallpaper</span>
                                    <span class="knd-lab-caption">Nebula Corridor</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="knd-panel-footer">
                        <span class="knd-labs-footnote">
                            <?php echo t('home.labs.footnote', 'Stylized preview. The real feed connects to your jobs and collections.'); ?>
                        </span>
                    </footer>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="knd-panel knd-labs-copy-panel">
                    <header class="knd-panel-header">
                        <p class="knd-section-eyebrow mb-1"><?php echo t('home.labs.pillar', 'Create'); ?></p>
                        <h3 class="knd-section-title mb-2"><?php echo t('home.labs.copy_title', 'Build the visual language of your universe.'); ?></h3>
                    </header>
                    <div class="knd-panel-body">
                        <p class="knd-body-text">
                            <?php echo t('home.labs.copy_body', 'KND Labs is your visual lab: generate, refine, and connect assets that later live in Arena, your Drops, or even physical apparel.'); ?>
                        </p>
                        <ul class="knd-bullet-list">
                            <li><?php echo t('home.labs.bullet1', 'Multiple tools: image generation, upscaling, consistency, textures, and 3D.'); ?></li>
                            <li><?php echo t('home.labs.bullet2', 'Pipelines designed for creators, not demos.'); ?></li>
                            <li><?php echo t('home.labs.bullet3', 'Continuous visual story: what you create can return as a reward.'); ?></li>
                        </ul>
                        <a href="/labs" class="btn knd-btn-primary knd-cta-main btn-lg mt-3">
                            <i class="fas fa-microscope me-2"></i><?php echo t('home.labs.cta', 'Enter KND Labs'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

