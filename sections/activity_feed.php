<?php
// LIVE ACTIVITY FEED
?>
<section class="knd-section knd-activity-section knd-animate" id="knd-activity-feed">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="knd-panel knd-activity-panel">
                    <header class="knd-panel-header">
                        <div>
                            <p class="knd-section-eyebrow mb-1"><?php echo t('home.activity.eyebrow', 'Live Activity'); ?></p>
                            <h2 class="knd-section-title mb-0"><?php echo t('home.activity.title', 'The ecosystem never stops'); ?></h2>
                        </div>
                        <div class="knd-activity-status">
                            <span class="knd-status-pill knd-status-online">
                                <span class="knd-status-dot"></span>LIVE
                            </span>
                        </div>
                    </header>
                    <div class="knd-panel-body">
                        <ul class="knd-activity-list" id="knd-activity-list">
                            <li class="knd-activity-item" data-source="labs">
                                <div class="knd-activity-icon knd-activity-labs">
                                    <i class="fas fa-microscope"></i>
                                </div>
                                <div class="knd-activity-content">
                                    <div class="knd-activity-main">
                                        <span class="knd-activity-text">User nova_knd generated a new 3D avatar in KND Labs.</span>
                                    </div>
                                    <div class="knd-activity-meta">
                                        <span class="knd-activity-tag">Labs</span>
                                        <span class="knd-activity-time"><?php echo t('home.activity.time_just_now', '1 min ago'); ?></span>
                                    </div>
                                </div>
                            </li>
                            <li class="knd-activity-item" data-source="drops">
                                <div class="knd-activity-icon knd-activity-drops">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <div class="knd-activity-content">
                                    <div class="knd-activity-main">
                                        <span class="knd-activity-text">New capsule unlocked in Nebula Core Drop.</span>
                                    </div>
                                    <div class="knd-activity-meta">
                                        <span class="knd-activity-tag">Drops</span>
                                        <span class="knd-activity-time"><?php echo t('home.activity.time_2min', '2 min ago'); ?></span>
                                    </div>
                                </div>
                            </li>
                            <li class="knd-activity-item" data-source="arena">
                                <div class="knd-activity-icon knd-activity-arena">
                                    <i class="fas fa-dice-d20"></i>
                                </div>
                                <div class="knd-activity-content">
                                    <div class="knd-activity-main">
                                        <span class="knd-activity-text">LastRoll 1v1 match ended after 03 intense rounds.</span>
                                    </div>
                                    <div class="knd-activity-meta">
                                        <span class="knd-activity-tag">Arena</span>
                                        <span class="knd-activity-time"><?php echo t('home.activity.time_5min', '5 min ago'); ?></span>
                                    </div>
                                </div>
                            </li>
                            <li class="knd-activity-item" data-source="services">
                                <div class="knd-activity-icon knd-activity-services">
                                    <i class="fas fa-sparkles"></i>
                                </div>
                                <div class="knd-activity-content">
                                    <div class="knd-activity-main">
                                        <span class="knd-activity-text">New visual activation project confirmed in Digital Services.</span>
                                    </div>
                                    <div class="knd-activity-meta">
                                        <span class="knd-activity-tag">Services</span>
                                        <span class="knd-activity-time"><?php echo t('home.activity.time_12min', '12 min ago'); ?></span>
                                    </div>
                                </div>
                            </li>
                            <li class="knd-activity-item" data-source="apparel">
                                <div class="knd-activity-icon knd-activity-apparel">
                                    <i class="fas fa-tshirt"></i>
                                </div>
                                <div class="knd-activity-content">
                                    <div class="knd-activity-main">
                                        <span class="knd-activity-text">KND Core Signal Hoodie added to multiple users' wishlists.</span>
                                    </div>
                                    <div class="knd-activity-meta">
                                        <span class="knd-activity-tag">Apparel</span>
                                        <span class="knd-activity-time"><?php echo t('home.activity.time_20min', '20 min ago'); ?></span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <footer class="knd-panel-footer">
                        <span class="knd-activity-footnote">
                            <?php echo t('home.activity.footnote', 'Simulated data for preview. Real activity will connect to the KND ecosystem.'); ?>
                        </span>
                    </footer>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="knd-panel knd-summary-panel">
                    <header class="knd-panel-header">
                        <p class="knd-section-eyebrow mb-1"><?php echo t('home.activity.summary_eyebrow', 'Platform Snapshot'); ?></p>
                        <h3 class="knd-section-title mb-0"><?php echo t('home.activity.summary_title', 'KND ecosystem status'); ?></h3>
                    </header>
                    <div class="knd-panel-body">
                        <div class="knd-summary-grid">
                            <div class="knd-summary-item">
                                <span class="knd-summary-label"><?php echo t('home.activity.summary_users', 'Connected users'); ?></span>
                                <span class="knd-summary-value">312</span>
                                <span class="knd-summary-tag knd-trend-up">+18%</span>
                            </div>
                            <div class="knd-summary-item">
                                <span class="knd-summary-label"><?php echo t('home.activity.summary_jobs', 'Active jobs in Labs'); ?></span>
                                <span class="knd-summary-value">29</span>
                                <span class="knd-summary-tag">AI Queue</span>
                            </div>
                            <div class="knd-summary-item">
                                <span class="knd-summary-label"><?php echo t('home.activity.summary_matches', 'Arena matches'); ?></span>
                                <span class="knd-summary-value">12</span>
                                <span class="knd-summary-tag">Ranked</span>
                            </div>
                            <div class="knd-summary-item">
                                <span class="knd-summary-label"><?php echo t('home.activity.summary_drops', 'Items unlocked today'); ?></span>
                                <span class="knd-summary-value">87</span>
                                <span class="knd-summary-tag knd-trend-neutral">Season 01</span>
                            </div>
                        </div>
                    </div>
                    <footer class="knd-panel-footer knd-summary-footer">
                        <span class="knd-summary-pill"><?php echo t('home.activity.summary_pill', 'Create • Play • Collect • Repeat'); ?></span>
                    </footer>
                </div>
            </div>
        </div>
    </div>
</section>

