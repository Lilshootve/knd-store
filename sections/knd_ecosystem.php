<?php
/**
 * KND Ecosystem hub — Labs, digital services, custom design, apparel.
 */
$ecoLabsBase = '/labs';
?>
<main class="knd-ecosystem" id="knd-ecosystem">
    <div id="eco-bg" aria-hidden="true"><canvas id="eco-bg-canvas"></canvas></div>

    <section class="hero">
        <div class="hero-badge">⬡ <?php echo t('ecosystem.hero.badge', "BEYOND THE ARENA"); ?></div>
        <h1 class="hero-title"><?php echo t('ecosystem.hero.title', 'THE KND'); ?> <span class="gr"><?php echo t('ecosystem.hero.title_accent', 'ECOSYSTEM'); ?></span></h1>
        <p class="hero-sub"><?php echo t('ecosystem.hero.sub', 'AI-powered creative tools. Professional digital services. Custom design and holographic apparel. Everything you need to create, build, and represent.'); ?></p>
        <div class="eco-nav">
            <a class="eco-pill active" data-s="labs" href="#eco-labs"><?php echo t('ecosystem.pill.labs', '🧪 KND LABS'); ?></a>
            <a class="eco-pill" data-s="services" href="#eco-services"><?php echo t('ecosystem.pill.services', '⚙ DIGITAL SERVICES'); ?></a>
            <a class="eco-pill" data-s="custom" href="#eco-custom"><?php echo t('ecosystem.pill.custom', '✦ CUSTOM DESIGN'); ?></a>
            <a class="eco-pill" data-s="apparel" href="#eco-apparel"><?php echo t('ecosystem.pill.apparel', '👕 APPAREL'); ?></a>
        </div>
    </section>

    <section class="eco-section labs-color" id="eco-labs">
        <div class="eco-inner">
            <div class="sec-header">
                <div class="sec-tag" style="color:var(--c)"><span class="dot" style="background:var(--c)"></span> <?php echo t('ecosystem.labs.tag', 'AI CREATIVE LABORATORY'); ?></div>
                <h2 class="sec-title" style="color:var(--c)"><?php echo t('ecosystem.labs.title', 'KND LABS'); ?></h2>
                <p class="sec-sub"><?php echo t('ecosystem.labs.sub', 'Five AI-powered tools to generate, transform, and visualize. From text to image to 3D — all in one laboratory.'); ?></p>
            </div>
            <div class="sec-div"></div>

            <div class="labs-grid">
                <a class="lab-card" href="<?php echo htmlspecialchars($ecoLabsBase); ?>?tool=text2img">
                    <div class="lab-visual"><span class="lab-icon" aria-hidden="true">🎨</span></div>
                    <div class="lab-body">
                        <div class="lab-name"><?php echo t('ecosystem.lab.img', 'IMAGE GEN'); ?></div>
                        <div class="lab-desc"><?php echo t('ecosystem.lab.img_desc', 'Text-to-image AI. Describe anything, get stunning visuals in seconds.'); ?></div>
                        <div class="lab-tag"><?php echo t('ecosystem.lab.img_tag', 'TEXT → IMAGE · MULTIPLE STYLES'); ?></div>
                    </div>
                </a>
                <a class="lab-card" href="<?php echo htmlspecialchars($ecoLabsBase); ?>?tool=remove-bg">
                    <div class="lab-visual"><span class="lab-icon" aria-hidden="true">✂️</span></div>
                    <div class="lab-body">
                        <div class="lab-name"><?php echo t('ecosystem.lab.bg', 'BG REMOVE'); ?></div>
                        <div class="lab-desc"><?php echo t('ecosystem.lab.bg_desc', 'Instant background removal. Clean cutouts with AI precision.'); ?></div>
                        <div class="lab-tag"><?php echo t('ecosystem.lab.bg_tag', 'ONE-CLICK · HIGH ACCURACY'); ?></div>
                    </div>
                </a>
                <a class="lab-card" href="<?php echo htmlspecialchars($ecoLabsBase); ?>?tool=upscale">
                    <div class="lab-visual"><span class="lab-icon" aria-hidden="true">🔍</span></div>
                    <div class="lab-body">
                        <div class="lab-name"><?php echo t('ecosystem.lab.up', 'UPSCALE'); ?></div>
                        <div class="lab-desc"><?php echo t('ecosystem.lab.up_desc', 'Enhance resolution up to 4x. Sharpen details that weren\'t there before.'); ?></div>
                        <div class="lab-tag"><?php echo t('ecosystem.lab.up_tag', 'AI SUPER-RESOLUTION · 4X'); ?></div>
                    </div>
                </a>
                <a class="lab-card" href="<?php echo htmlspecialchars($ecoLabsBase); ?>?tool=3d_vertex">
                    <div class="lab-visual"><span class="lab-icon" aria-hidden="true">🧊</span></div>
                    <div class="lab-body">
                        <div class="lab-name"><?php echo t('ecosystem.lab.3d', '3D FORGE'); ?></div>
                        <div class="lab-desc"><?php echo t('ecosystem.lab.3d_desc', 'Generate 3D models from text or images. With or without textures — export-ready.'); ?></div>
                        <div class="lab-tag"><?php echo t('ecosystem.lab.3d_tag', 'TEXT/IMG → 3D · GLB EXPORT'); ?></div>
                    </div>
                </a>
                <a class="lab-card" href="<?php echo htmlspecialchars($ecoLabsBase); ?>?tool=model_viewer">
                    <div class="lab-visual"><span class="lab-icon" aria-hidden="true">👁</span></div>
                    <div class="lab-body">
                        <div class="lab-name"><?php echo t('ecosystem.lab.view', '3D VIEWER'); ?></div>
                        <div class="lab-desc"><?php echo t('ecosystem.lab.view_desc', 'Inspect any 3D model in a holographic viewer. Rotate, zoom, analyze.'); ?></div>
                        <div class="lab-tag"><?php echo t('ecosystem.lab.view_tag', 'WEBGL · DRAG & DROP'); ?></div>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="eco-section svc-color" id="eco-services">
        <div class="eco-inner">
            <div class="sec-header">
                <div class="sec-tag" style="color:var(--m)"><span class="dot" style="background:var(--m)"></span> <?php echo t('ecosystem.svc.tag', 'PROFESSIONAL DIGITAL SERVICES'); ?></div>
                <h2 class="sec-title" style="color:var(--m)"><?php echo t('ecosystem.svc.title', 'KND SERVICES'); ?></h2>
                <p class="sec-sub"><?php echo t('ecosystem.svc.sub', 'From brand identity to full-stack development. We build digital systems that last.'); ?> <a href="/products.php"><?php echo t('ecosystem.svc.catalog', 'Browse the full catalog'); ?></a> <?php echo t('ecosystem.svc.or', 'or'); ?> <a href="/contact.php"><?php echo t('ecosystem.svc.contact', 'start a project'); ?></a>.</p>
            </div>
            <div class="sec-div" style="background:linear-gradient(90deg,var(--m),transparent)"></div>

            <div class="svc-grid">
                <div class="svc-card">
                    <div class="svc-icon" aria-hidden="true">💻</div>
                    <div class="svc-name"><?php echo t('ecosystem.svc.web', 'WEB DEV'); ?></div>
                    <div class="svc-desc"><?php echo t('ecosystem.svc.web_desc', 'Custom websites, web apps, and platforms built from scratch with modern tech stacks.'); ?></div>
                    <ul class="svc-features">
                        <li><?php echo t('ecosystem.svc.web_f1', 'Full-stack development'); ?></li>
                        <li><?php echo t('ecosystem.svc.web_f2', 'Custom PHP / JS / React'); ?></li>
                        <li><?php echo t('ecosystem.svc.web_f3', 'Database architecture'); ?></li>
                        <li><?php echo t('ecosystem.svc.web_f4', 'API integrations'); ?></li>
                        <li><?php echo t('ecosystem.svc.web_f5', 'Performance optimization'); ?></li>
                    </ul>
                    <a class="svc-btn" href="/products.php"><?php echo t('ecosystem.svc.btn_catalog', 'VIEW CATALOG →'); ?></a>
                </div>
                <div class="svc-card">
                    <div class="svc-icon" aria-hidden="true">🎯</div>
                    <div class="svc-name"><?php echo t('ecosystem.svc.brand', 'BRANDING'); ?></div>
                    <div class="svc-desc"><?php echo t('ecosystem.svc.brand_desc', 'Complete visual identity systems. Logos, guidelines, and digital presence that define who you are.'); ?></div>
                    <ul class="svc-features">
                        <li><?php echo t('ecosystem.svc.brand_f1', 'Logo design & brand mark'); ?></li>
                        <li><?php echo t('ecosystem.svc.brand_f2', 'Visual identity system'); ?></li>
                        <li><?php echo t('ecosystem.svc.brand_f3', 'Brand guidelines'); ?></li>
                        <li><?php echo t('ecosystem.svc.brand_f4', 'Typography & color systems'); ?></li>
                        <li><?php echo t('ecosystem.svc.brand_f5', 'Social media kits'); ?></li>
                    </ul>
                    <a class="svc-btn" href="/contact.php"><?php echo t('ecosystem.svc.btn_contact', 'GET IN TOUCH →'); ?></a>
                </div>
                <div class="svc-card">
                    <div class="svc-icon" aria-hidden="true">🤖</div>
                    <div class="svc-name"><?php echo t('ecosystem.svc.ai', 'AI INTEGRATION'); ?></div>
                    <div class="svc-desc"><?php echo t('ecosystem.svc.ai_desc', 'Integrate AI into your business. From chatbots to image generation to custom LLM workflows.'); ?></div>
                    <ul class="svc-features">
                        <li><?php echo t('ecosystem.svc.ai_f1', 'AI chatbot deployment'); ?></li>
                        <li><?php echo t('ecosystem.svc.ai_f2', 'Image generation pipelines'); ?></li>
                        <li><?php echo t('ecosystem.svc.ai_f3', 'Custom model fine-tuning'); ?></li>
                        <li><?php echo t('ecosystem.svc.ai_f4', 'Workflow automation'); ?></li>
                        <li><?php echo t('ecosystem.svc.ai_f5', 'Strategy & consulting'); ?></li>
                    </ul>
                    <a class="svc-btn" href="/contact.php"><?php echo t('ecosystem.svc.btn_contact', 'GET IN TOUCH →'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="eco-section custom-color" id="eco-custom">
        <div class="eco-inner">
            <div class="sec-header">
                <div class="sec-tag" style="color:#a78bfa"><span class="dot" style="background:#a78bfa"></span> <?php echo t('ecosystem.custom.tag', 'CUSTOM DESIGN'); ?></div>
                <h2 class="sec-title" style="color:#c4b5fd"><?php echo t('ecosystem.custom.title', 'MAKE IT YOURS'); ?></h2>
                <p class="sec-sub"><?php echo t('ecosystem.custom.sub', 'One-of-a-kind pieces, apparel tweaks, and brand-aligned visuals — built together with the KND team.'); ?></p>
            </div>
            <div class="sec-div" style="background:linear-gradient(90deg,#a78bfa,transparent)"></div>

            <div class="custom-panel">
                <div class="custom-visual" aria-hidden="true">✦</div>
                <div>
                    <p class="sec-sub" style="max-width:none;margin-bottom:0"><?php echo t('ecosystem.custom.body', 'Tell us your idea, reference, or drop. We translate it into production-ready design and coordinate with Labs and apparel when you need the full stack.'); ?></p>
                    <a class="custom-cta" href="/custom-design.php"><?php echo t('ecosystem.custom.cta', 'OPEN CUSTOM DESIGN →'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="eco-section app-color" id="eco-apparel">
        <div class="eco-inner">
            <div class="sec-header">
                <div class="sec-tag" style="color:var(--gold)"><span class="dot" style="background:var(--gold)"></span> <?php echo t('ecosystem.app.tag', 'HOLOGRAPHIC STREETWEAR'); ?></div>
                <h2 class="sec-title" style="color:var(--gold)"><?php echo t('ecosystem.app.title', 'KND APPAREL'); ?></h2>
                <p class="sec-sub"><?php echo t('ecosystem.app.sub', 'Wear the signal. Premium streetwear infused with the KND holographic aesthetic.'); ?></p>
            </div>
            <div class="sec-div" style="background:linear-gradient(90deg,var(--gold),transparent)"></div>

            <div class="apparel-showcase">
                <div class="apparel-visual">
                    <div class="product-stage">
                        <div class="product-icon" id="eco-product-icon">👕</div>
                        <div class="product-label" id="eco-product-label"><?php echo t('ecosystem.app.sample1', 'KND CORE TEE'); ?></div>
                    </div>
                </div>
                <div class="apparel-info">
                    <div class="apparel-title"><?php echo t('ecosystem.app.block_title', 'REPRESENT THE VOID'); ?></div>
                    <div class="apparel-desc"><?php echo t('ecosystem.app.block_desc', 'Each piece is designed with the same holographic DNA as the KND ecosystem. Dark palettes, subtle neon accents, premium fabric. Built for the digital generation.'); ?></div>
                    <div class="apparel-items">
                        <span class="apparel-chip" data-icon="👕" data-name="<?php echo t('ecosystem.app.sample1', 'KND CORE TEE'); ?>">⬡ <?php echo t('ecosystem.app.chip1', 'T-SHIRTS'); ?></span>
                        <span class="apparel-chip" data-icon="🧥" data-name="<?php echo t('ecosystem.app.sample2', 'KND VOID HOODIE'); ?>">⬡ <?php echo t('ecosystem.app.chip2', 'HOODIES'); ?></span>
                        <span class="apparel-chip" data-icon="👕" data-name="<?php echo t('ecosystem.app.sample3', 'KND SIGNAL LONGSLEEVE'); ?>">⬡ <?php echo t('ecosystem.app.chip3', 'LONGSLEEVES'); ?></span>
                    </div>
                    <a class="apparel-cta" href="/apparel.php"><?php echo t('ecosystem.app.cta', '⬡ VIEW COLLECTION →'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <div class="cta-banner">
        <div class="cta-inner">
            <div class="cta-title"><?php echo t('ecosystem.cta.title', 'READY TO CREATE?'); ?></div>
            <div class="cta-sub"><?php echo t('ecosystem.cta.sub', 'Open KND Labs, start a custom project, or browse apparel. The ecosystem is yours.'); ?></div>
            <a class="cta-btn" href="/labs"><?php echo t('ecosystem.cta.btn', '⚡ ENTER KND LABS'); ?></a>
        </div>
    </div>
</main>
