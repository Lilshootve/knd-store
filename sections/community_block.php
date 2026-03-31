<?php
// COMMUNITY BLOCK
?>
<section class="knd-section knd-community-section knd-animate" id="knd-community">
    <div class="container">
        <div class="knd-panel knd-community-panel">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <header class="knd-panel-header mb-0">
                        <p class="knd-section-eyebrow mb-1"><?php echo t('home.community.eyebrow', 'Community'); ?></p>
                        <h2 class="knd-section-title mb-2"><?php echo t('home.community.title', 'It is not just a store, it is an ecosystem with people inside.'); ?></h2>
                    </header>
                    <div class="knd-panel-body pt-2">
                        <p class="knd-body-text mb-3">
                            <?php echo t('home.community.desc', 'Discord, events, community drops, and direct feedback on what we build. If this type of ecosystem interests you, we want you in.'); ?>
                        </p>
                        <div class="knd-community-meta">
                            <div class="knd-community-stat">
                                <span class="knd-summary-label"><?php echo t('home.community.stat_members', 'Discord members'); ?></span>
                                <span class="knd-summary-value">1.2K+</span>
                            </div>
                            <div class="knd-community-stat">
                                <span class="knd-summary-label"><?php echo t('home.community.stat_seasons', 'Planned seasons'); ?></span>
                                <span class="knd-summary-value">3</span>
                            </div>
                            <div class="knd-community-stat">
                                <span class="knd-summary-label"><?php echo t('home.community.stat_missions', 'Missions & events'); ?></span>
                                <span class="knd-summary-value">In progress</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="knd-panel-body knd-community-actions">
                        <a href="https://discord.gg/zjP3u5Yztx" target="_blank" rel="noopener" class="btn knd-btn-primary btn-lg w-100 mb-3">
                            <i class="fab fa-discord me-2"></i><?php echo t('home.community.cta_discord', 'Join the KND Network'); ?>
                        </a>
                        <a href="/newsletter.php" class="btn knd-btn-ghost w-100">
                            <i class="fas fa-bell me-2"></i><?php echo t('home.community.cta_newsletter', 'Get updates on drops and seasons'); ?>
                        </a>
                        <p class="knd-community-footnote mb-0 mt-2">
                            <?php echo t('home.community.footnote', 'No generic spam. Only news when something truly important goes live.'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

