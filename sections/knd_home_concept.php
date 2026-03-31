<?php
/**
 * Home layout aligned with knd-home-concept.html
 *
 * @var array<int, array{id:int,name:string,src:string,rarity:string}> $homeAvatarsLegendary
 * @var array<int, array{id:int,name:string,src:string,rarity:string}> $homeAvatarsEpic
 * @var int $portalStatBattles
 * @var int $portalStatOnline
 * @var string $portalStatDrops
 * @var string $portalStatLegendaryRate
 * @var int $portalStatAvatarTotal
 * @var int $portalStatLegendaryCount
 */
if (!isset($homeAvatarsLegendary) || !is_array($homeAvatarsLegendary)) {
    $homeAvatarsLegendary = [];
}
if (!isset($homeAvatarsEpic) || !is_array($homeAvatarsEpic)) {
    $homeAvatarsEpic = [];
}
/** Cards repeated for horizontal marquee loop */
$kndHomeMarqueeRepeat = 3;
$kndHomeLegendaryStrip = [];
$kndHomeEpicStrip = [];
for ($__i = 0; $__i < $kndHomeMarqueeRepeat; $__i++) {
    foreach ($homeAvatarsLegendary as $__av) {
        $kndHomeLegendaryStrip[] = $__av;
    }
    foreach ($homeAvatarsEpic as $__av) {
        $kndHomeEpicStrip[] = $__av;
    }
}
$heroPrimary = '/games/mind-wars/lobby.php';
$heroSecondary = '/ecosystem.php';
$kndCardsCollectionHref = '/tools/cards/index.html';
?>
<main class="knd-home-main" id="knd-home">
    <section class="hero" id="knd-hero">
        <div class="hero-orb o1" aria-hidden="true"></div>
        <div class="hero-orb o2" aria-hidden="true"></div>
        <div class="hero-grid" aria-hidden="true">
            <svg viewBox="0 0 200 200"><polygon points="100,10 190,55 190,145 100,190 10,145 10,55" fill="none" stroke="rgba(0,232,255,.5)" stroke-width=".5"/><polygon points="100,30 170,62 170,138 100,170 30,138 30,62" fill="none" stroke="rgba(0,232,255,.3)" stroke-width=".3"/></svg>
            <svg viewBox="0 0 200 200"><polygon points="100,10 190,55 190,145 100,190 10,145 10,55" fill="none" stroke="rgba(212,79,255,.4)" stroke-width=".5"/></svg>
            <svg viewBox="0 0 200 200"><polygon points="100,10 190,55 190,145 100,190 10,145 10,55" fill="none" stroke="rgba(255,204,0,.3)" stroke-width=".3"/></svg>
        </div>
        <div class="hero-content">
            <div class="hero-badge">⬡ <?php echo t('home.hero.badge', "Knowledge 'N Development"); ?></div>
            <h1 class="hero-title"><?php echo t('home.hero.title_line1', 'WHERE'); ?> <span class="accent"><?php echo t('home.hero.title_accent', 'DIGITAL'); ?></span><br><?php echo t('home.hero.title_line2', 'INNOVATION BEGINS'); ?></h1>
            <p class="hero-sub"><?php echo t('home.hero.sub', 'Battle in Mind Wars. Collect legendary avatars. Play LastRoll & Insight. Build your legacy in the KND ecosystem.'); ?></p>
            <div class="hero-actions">
                <a class="hero-btn primary" href="<?php echo htmlspecialchars($heroPrimary); ?>">⚡ <?php echo t('home.hero.cta_primary', 'Enter the Arena'); ?></a>
                <a class="hero-btn secondary" href="<?php echo htmlspecialchars($heroSecondary); ?>">⬡ <?php echo t('home.hero.cta_secondary', 'Explore ecosystem'); ?></a>
            </div>
        </div>
        <div class="scroll-cue">
            <div class="scroll-line" aria-hidden="true"></div>
            <div class="scroll-text"><?php echo t('home.hero.scroll', 'Scroll to explore'); ?></div>
        </div>
    </section>

    <section class="section" id="portals">
        <div class="section-inner">
            <div class="section-header">
                <div class="section-tag"><span class="sdot"></span> <?php echo t('home.portals.tag', 'Command center'); ?></div>
                <h2 class="section-title"><?php echo t('home.portals.title', 'Choose your arena'); ?></h2>
                <p class="section-sub"><?php echo t('home.portals.sub', 'Three gateways into the KND universe. Battle, collect, ascend.'); ?></p>
            </div>
            <div class="section-div" aria-hidden="true"></div>
            <div class="portals">
                <a class="portal mind-wars" href="/games/mind-wars/lobby.php">
                    <div class="portal-visual"><span class="portal-icon" aria-hidden="true">⚔</span></div>
                    <div class="portal-body">
                        <div class="portal-name"><?php echo t('home.portal.mw.name', 'Mind Wars'); ?></div>
                        <div class="portal-desc"><?php echo t('home.portal.mw.desc', '3v3 turn-based battles with holographic warriors. Build your squad, master synergies, dominate the arena.'); ?></div>
                        <div class="portal-stat">
                            <div class="portal-stat-item"><span class="portal-stat-val"><?php echo number_format($portalStatBattles); ?></span><span class="portal-stat-lbl"><?php echo t('home.portal.mw.stat1', 'Battles today'); ?></span></div>
                            <div class="portal-stat-item"><span class="portal-stat-val"><?php echo number_format($portalStatOnline); ?></span><span class="portal-stat-lbl"><?php echo t('home.portal.mw.stat2', 'Players online'); ?></span></div>
                        </div>
                        <div class="portal-enter">⬡ <?php echo t('home.portal.enter_battle', 'Enter battle'); ?> →</div>
                    </div>
                </a>
                <a class="portal drop-chamber" href="/games/knd-neural-link/drops.php">
                    <div class="portal-visual"><span class="portal-icon" aria-hidden="true">🔮</span></div>
                    <div class="portal-body">
                        <div class="portal-name"><?php echo t('home.portal.drop.name', 'Drop Chamber'); ?></div>
                        <div class="portal-desc"><?php echo t('home.portal.drop.desc', 'Open holographic capsules. Discover rare avatars, fragments, and KND Points. Every drop is a chance at legendary.'); ?></div>
                        <div class="portal-stat">
                            <div class="portal-stat-item"><span class="portal-stat-val"><?php echo htmlspecialchars($portalStatDrops); ?></span><span class="portal-stat-lbl"><?php echo t('home.portal.drop.stat1', 'Drops opened'); ?></span></div>
                            <div class="portal-stat-item"><span class="portal-stat-val"><?php echo htmlspecialchars($portalStatLegendaryRate); ?></span><span class="portal-stat-lbl"><?php echo t('home.portal.drop.stat2', 'Legendary rate'); ?></span></div>
                        </div>
                        <div class="portal-enter">⬡ <?php echo t('home.portal.enter_drops', 'Open drops'); ?> →</div>
                    </div>
                </a>
                <a class="portal avatars" href="/my-profile.php">
                    <div class="portal-visual"><span class="portal-icon" aria-hidden="true">👤</span></div>
                    <div class="portal-body">
                        <div class="portal-name"><?php echo t('home.portal.av.name', 'Avatar vault'); ?></div>
                        <div class="portal-desc"><?php echo t('home.portal.av.desc', 'Collect holographic characters from every era — unique avatars to discover and upgrade.'); ?></div>
                        <div class="portal-stat">
                            <div class="portal-stat-item"><span class="portal-stat-val"><?php echo number_format($portalStatAvatarTotal); ?></span><span class="portal-stat-lbl"><?php echo t('home.portal.av.stat1', 'Avatars'); ?></span></div>
                            <div class="portal-stat-item"><span class="portal-stat-val"><?php echo number_format($portalStatLegendaryCount); ?></span><span class="portal-stat-lbl"><?php echo t('home.portal.av.stat2', 'Legendaries'); ?></span></div>
                        </div>
                        <div class="portal-enter">⬡ <?php echo t('home.portal.enter_vault', 'View collection'); ?> →</div>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="section" id="avatars">
        <div class="section-inner">
            <div class="section-header">
                <div class="section-tag"><span class="sdot"></span> <?php echo t('home.avatars.tag', 'Holographic collection'); ?></div>
                <h2 class="section-title"><?php echo t('home.avatars.title', 'Legendary avatars'); ?></h2>
                <p class="section-sub"><?php echo t('home.avatars.sub', 'Legendary and epic Mind Wars portraits in two scrolling lanes.'); ?></p>
            </div>
            <div class="section-div" aria-hidden="true"></div>

            <div class="knd-home-avatar-showcase">
                <div class="knd-home-avatar-lane knd-home-avatar-lane--legendary" aria-label="<?php echo t('home.avatars.lane_legendary_aria', 'Legendary avatars'); ?>">
                    <div class="knd-home-avatar-lane-head">
                        <span class="knd-home-avatar-lane-badge"><?php echo t('home.avatars.lane_legendary', 'Legendary lane'); ?></span>
                    </div>
                    <div class="knd-home-avatar-track-shell">
                        <button type="button" class="knd-home-avatar-nav" data-knd-home-avatar-nav="prev" data-knd-home-avatar-track="knd-home-avatar-track-legendary" aria-label="<?php echo t('home.avatars.lane_prev', 'Previous'); ?>">‹</button>
                        <div class="knd-home-avatar-track knd-home-auto-scroll" id="knd-home-avatar-track-legendary" tabindex="0">
                            <?php if ($kndHomeLegendaryStrip === []): ?>
                                <p class="knd-home-avatar-empty"><?php echo t('home.avatars.empty', 'No avatars to display yet.'); ?></p>
                            <?php else: ?>
                                <?php foreach ($kndHomeLegendaryStrip as $av): ?>
                                    <a class="knd-home-mw-card knd-home-mw-card--legendary" href="<?php echo htmlspecialchars($kndCardsCollectionHref); ?>" style="--knd-rarity-color:#ffc030;--knd-rarity-glow:rgba(255,192,48,0.45);">
                                        <div class="knd-home-mw-card__glow" aria-hidden="true"></div>
                                        <div class="knd-home-mw-card__thumb">
                                            <span class="knd-home-mw-card__rarity-badge"><?php echo t('home.avatars.rarity_legendary', 'LEGENDARY'); ?></span>
                                            <div class="knd-home-mw-card__model">
                                                <img src="<?php echo htmlspecialchars($av['src']); ?>" alt="<?php echo htmlspecialchars($av['name']); ?>" width="160" height="200" loading="lazy" decoding="async">
                                            </div>
                                            <div class="knd-home-mw-card__ring" aria-hidden="true"></div>
                                        </div>
                                        <div class="knd-home-mw-card__body">
                                            <div class="knd-home-mw-card__name"><?php echo htmlspecialchars($av['name']); ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="knd-home-avatar-nav" data-knd-home-avatar-nav="next" data-knd-home-avatar-track="knd-home-avatar-track-legendary" aria-label="<?php echo t('home.avatars.lane_next', 'Next'); ?>">›</button>
                    </div>
                </div>

                <div class="knd-home-avatar-lane knd-home-avatar-lane--epic" aria-label="<?php echo t('home.avatars.lane_epic_aria', 'Epic avatars'); ?>">
                    <div class="knd-home-avatar-lane-head">
                        <span class="knd-home-avatar-lane-badge knd-home-avatar-lane-badge--epic"><?php echo t('home.avatars.lane_epic', 'Epic lane'); ?></span>
                    </div>
                    <div class="knd-home-avatar-track-shell">
                        <button type="button" class="knd-home-avatar-nav" data-knd-home-avatar-nav="prev" data-knd-home-avatar-track="knd-home-avatar-track-epic" aria-label="<?php echo t('home.avatars.lane_prev', 'Previous'); ?>">‹</button>
                        <div class="knd-home-avatar-track knd-home-auto-scroll" id="knd-home-avatar-track-epic" tabindex="0">
                            <?php if ($kndHomeEpicStrip === []): ?>
                                <p class="knd-home-avatar-empty"><?php echo t('home.avatars.empty', 'No avatars to display yet.'); ?></p>
                            <?php else: ?>
                                <?php foreach ($kndHomeEpicStrip as $av): ?>
                                    <a class="knd-home-mw-card knd-home-mw-card--epic" href="<?php echo htmlspecialchars($kndCardsCollectionHref); ?>" style="--knd-rarity-color:#c040ff;--knd-rarity-glow:rgba(192,64,255,0.45);">
                                        <div class="knd-home-mw-card__glow" aria-hidden="true"></div>
                                        <div class="knd-home-mw-card__thumb">
                                            <span class="knd-home-mw-card__rarity-badge"><?php echo t('home.avatars.rarity_epic', 'EPIC'); ?></span>
                                            <div class="knd-home-mw-card__model">
                                                <img src="<?php echo htmlspecialchars($av['src']); ?>" alt="<?php echo htmlspecialchars($av['name']); ?>" width="160" height="200" loading="lazy" decoding="async">
                                            </div>
                                            <div class="knd-home-mw-card__ring" aria-hidden="true"></div>
                                        </div>
                                        <div class="knd-home-mw-card__body">
                                            <div class="knd-home-mw-card__name"><?php echo htmlspecialchars($av['name']); ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="knd-home-avatar-nav" data-knd-home-avatar-nav="next" data-knd-home-avatar-track="knd-home-avatar-track-epic" aria-label="<?php echo t('home.avatars.lane_next', 'Next'); ?>">›</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="pulse">
        <div class="section-inner">
            <div class="section-header">
                <div class="section-tag"><span class="sdot"></span> <?php echo t('home.pulse.tag', 'Live signal'); ?></div>
                <h2 class="section-title"><?php echo t('home.pulse.title', 'Community pulse'); ?></h2>
                <p class="section-sub"><?php echo t('home.pulse.sub', "What's happening in the KND universe right now."); ?></p>
            </div>
            <div class="section-div" aria-hidden="true"></div>
            <div class="pulse-grid">
                <div class="pulse-card">
                    <div class="pulse-icon" aria-hidden="true">⚔</div>
                    <div class="pulse-text"><?php echo t_html('home.pulse.card1', '<strong>NeuroPrism</strong> defeated <strong>VoidWalker</strong> in Mind Wars — Round 4 comeback with Dracula\'s special.'); ?></div>
                    <div class="pulse-time"><?php echo t('home.pulse.time1', '2 minutes ago'); ?></div>
                </div>
                <div class="pulse-card">
                    <div class="pulse-icon" aria-hidden="true">🔮</div>
                    <div class="pulse-text"><?php echo t_html('home.pulse.card2', '<strong>CryptoPhoenix</strong> pulled a <strong style="color:var(--gold)">LEGENDARY</strong> Nikola Tesla from the Drop Chamber!'); ?></div>
                    <div class="pulse-time"><?php echo t('home.pulse.time2', '8 minutes ago'); ?></div>
                </div>
                <div class="pulse-card">
                    <div class="pulse-icon" aria-hidden="true">🏆</div>
                    <div class="pulse-text"><?php echo t_html('home.pulse.card3', '<strong>QuantumAce</strong> reached <strong style="color:var(--gold)">Level 25 — EPIC TIER</strong> and unlocked the Mind Warrior badge.'); ?></div>
                    <div class="pulse-time"><?php echo t('home.pulse.time3', '14 minutes ago'); ?></div>
                </div>
            </div>
        </div>
    </section>
</main>
