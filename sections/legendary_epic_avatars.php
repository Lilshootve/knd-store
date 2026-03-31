<?php
$legendaryFrames = isset($legendaryAvatarFrames) && is_array($legendaryAvatarFrames) ? $legendaryAvatarFrames : [];
$epicFrames = isset($epicAvatarFrames) && is_array($epicAvatarFrames) ? $epicAvatarFrames : [];

$renderAvatarTrack = static function (array $items, string $rarityClass, string $rarityLabel): void {
    if (empty($items)) {
        ?>
        <div class="knd-avatar-empty <?php echo htmlspecialchars($rarityClass); ?>">
            <span class="knd-avatar-empty-badge"><?php echo htmlspecialchars($rarityLabel); ?></span>
            <p class="mb-0">No avatars loaded yet. Drop new frames in this rarity folder to light this lane.</p>
        </div>
        <?php
        return;
    }

    $loopItems = array_merge($items, $items);
    ?>
    <div class="knd-avatar-marquee-track">
        <?php foreach ($loopItems as $item): ?>
            <article class="knd-avatar-card <?php echo htmlspecialchars($rarityClass); ?>">
                <div class="knd-avatar-card-glow" aria-hidden="true"></div>
                <div class="knd-avatar-frame-wrap">
                    <img
                        src="<?php echo htmlspecialchars($item['src']); ?>"
                        alt="<?php echo htmlspecialchars($item['name'] . ' frame'); ?>"
                        class="knd-avatar-frame-img"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
                <div class="knd-avatar-card-meta">
                    <span class="knd-avatar-rarity"><?php echo htmlspecialchars($rarityLabel); ?></span>
                    <h3 class="knd-avatar-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
};
?>

<section class="knd-section knd-avatars-showcase knd-avatars-hero knd-animate" id="knd-avatars-showcase">
    <div class="container">
        <div class="knd-section-header knd-avatar-hero-head">
            <div class="knd-avatar-hero-copy">
                <p class="knd-section-eyebrow knd-avatar-kicker">KND Hero Drop Zone</p>
                <h1 class="knd-section-title knd-avatar-hero-title">Legendary & Epic Avatars Built To Own The Feed</h1>
            </div>
            <p class="knd-section-desc knd-avatar-hero-desc">
                This is your first signal. Slide through elite frames, lock the one that hits hardest, and flex your identity like a commander.
            </p>
        </div>

        <div class="knd-avatar-marquee legendary-lane" aria-label="Legendary avatars lane">
            <div class="knd-avatar-lane-header">
                <span class="knd-badge-soft">Legendary Lane</span>
            </div>
            <div class="knd-avatar-track-shell" data-avatar-track-shell="legendary">
                <div class="knd-avatar-lane-nav knd-avatar-lane-nav-left" role="group" aria-label="Legendary lane controls">
                    <button type="button" class="knd-avatar-nav-btn" data-avatar-lane="legendary" data-avatar-nav="prev" aria-label="Previous legendary avatars" aria-hidden="true" tabindex="-1">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                </div>
                <?php $renderAvatarTrack($legendaryFrames, 'rarity-legendary', 'Legendary'); ?>
                <div class="knd-avatar-lane-nav knd-avatar-lane-nav-right" role="group" aria-label="Legendary lane controls">
                    <button type="button" class="knd-avatar-nav-btn" data-avatar-lane="legendary" data-avatar-nav="next" aria-label="Next legendary avatars" aria-hidden="true" tabindex="-1">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="knd-avatar-marquee epic-lane" aria-label="Epic avatars lane">
            <div class="knd-avatar-lane-header">
                <span class="knd-badge-soft">Epic Lane</span>
            </div>
            <div class="knd-avatar-track-shell" data-avatar-track-shell="epic">
                <div class="knd-avatar-lane-nav knd-avatar-lane-nav-left" role="group" aria-label="Epic lane controls">
                    <button type="button" class="knd-avatar-nav-btn" data-avatar-lane="epic" data-avatar-nav="prev" aria-label="Previous epic avatars" aria-hidden="true" tabindex="-1">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                </div>
                <?php $renderAvatarTrack($epicFrames, 'rarity-epic', 'Epic'); ?>
                <div class="knd-avatar-lane-nav knd-avatar-lane-nav-right" role="group" aria-label="Epic lane controls">
                    <button type="button" class="knd-avatar-nav-btn" data-avatar-lane="epic" data-avatar-nav="next" aria-label="Next epic avatars" aria-hidden="true" tabindex="-1">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="knd-avatar-cta">
            <a href="/games/knd-neural-link/drops.php" class="btn knd-btn-primary btn-lg knd-avatar-cta-btn">
                <i class="fas fa-meteor me-2"></i>Pick Yours // Enter KND Drops
            </a>
        </div>
    </div>
</section>
