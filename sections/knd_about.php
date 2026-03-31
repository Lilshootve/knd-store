<?php
/**
 * About page content — aligned with about us/knd-about-concept.html
 */
?>
<main class="knd-about-page" id="knd-about">
    <div id="about-bg" aria-hidden="true"><canvas id="about-bg-canvas"></canvas></div>

    <div class="ab-page-inner">
        <section class="ab-hero">
            <div class="ab-hero-badge">⬡ <?php echo t('about.hero.badge', "Knowledge 'N Development"); ?></div>
            <h1 class="ab-hero-title"><?php echo t('about.hero.title', 'ABOUT'); ?> <span class="gr"><?php echo t('about.hero.title_accent', 'KND'); ?></span></h1>
            <p class="ab-hero-sub"><?php echo t('about.hero.sub', "Born from a spark in 1995. Built into an expanding digital universe. We don't follow the map — we hack it."); ?></p>
            <div class="ab-hero-year" aria-hidden="true">1995</div>
            <a href="#story" class="ab-scroll-cue" style="text-decoration:none;color:inherit">
                <div class="ab-scroll-line" aria-hidden="true"></div>
                <div class="ab-scroll-text"><?php echo t('about.hero.scroll', 'OUR STORY'); ?></div>
            </a>
        </section>

        <section class="ab-sect" id="story">
            <div class="ab-sect-inner">
                <div class="ab-story-grid">
                    <div>
                        <div class="ab-sect-tag"><span class="dot" aria-hidden="true"></span> <?php echo t('about.story.tag1', 'ORIGIN SIGNAL'); ?></div>
                        <h2 class="ab-sect-title"><?php echo t('about.story.title', 'Our Story'); ?></h2>
                        <div class="ab-story-accent">1995</div>
                        <p class="ab-sect-text"><?php echo t('about.story.p1', "KND Store wasn't born in an office. It was born in a mind. In 1995, while the world discovered Windows 95, a spark ignited at the core of a future impossible to ignore: fusing technology and gaming culture into a single intergalactic force."); ?></p>
                        <p class="ab-sect-text"><?php echo t('about.story.p2', "Today, that spark is an expanding core. We are more than a store. We are a command station for those who don't follow the map — they hack it."); ?></p>
                    </div>
                    <div>
                        <div class="ab-sect-tag" style="color:var(--m)"><span class="dot" style="background:var(--m)" aria-hidden="true"></span> <?php echo t('about.knd.tag', 'DECODED'); ?></div>
                        <h2 class="ab-sect-title" style="color:var(--m)"><?php echo t('about.knd.heading', 'What does KND mean?'); ?></h2>
                        <p class="ab-sect-text"><?php echo t('about.knd.p1', "Knowledge 'N Development. Everything we build starts with a simple idea: turn real knowledge into constant development, smart solutions, and high-level digital experiences."); ?></p>
                        <p class="ab-sect-text"><?php echo t('about.knd.p2', "At KND, knowledge is not just theory — it's the base for builds, optimization, content, and services that solve real problems. Development is our second half: we never stand still."); ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="ab-mission">
            <div class="ab-mission-badge">⬡ <?php echo t('about.mission.badge', 'GALACTIC MISSION'); ?></div>
            <h2 class="ab-sect-title" style="text-align:center;margin-bottom:20px"><?php echo t('about.mission.title', 'Our Mission'); ?></h2>
            <p class="ab-mission-text"><?php echo t('about.mission.text', "To be the most badass store in the galaxy. We don't just sell hardware and peripherals — we recruit true pilots of the metaverse, design gear for digital heroes, and unleash technology without borders."); ?></p>
        </section>

        <section class="ab-sect">
            <div class="ab-sect-inner">
                <div class="ab-sect-tag"><span class="dot" aria-hidden="true"></span> <?php echo t('about.values.tag', 'CORE PROTOCOLS'); ?></div>
                <h2 class="ab-sect-title"><?php echo t('about.values.title', 'Our Values'); ?></h2>
                <div style="margin-bottom:28px" aria-hidden="true"></div>
                <div class="ab-values-grid">
                    <div class="ab-value-card" style="--vc:var(--c)">
                        <div class="ab-vc-icon" aria-hidden="true">⚡</div>
                        <div class="ab-vc-name"><?php echo t('about.values.v1_name', 'QUANTUM PRECISION'); ?></div>
                        <div class="ab-vc-desc"><?php echo t('about.values.v1_desc', 'Zero errors. Everything optimized to the byte.'); ?></div>
                    </div>
                    <div class="ab-value-card" style="--vc:var(--m)">
                        <div class="ab-vc-icon" aria-hidden="true">🛡</div>
                        <div class="ab-vc-name"><?php echo t('about.values.v2_name', 'USER LOYALTY'); ?></div>
                        <div class="ab-vc-desc"><?php echo t('about.values.v2_desc', "We're not retail gods. We're service soldiers."); ?></div>
                    </div>
                    <div class="ab-value-card" style="--vc:var(--gold)">
                        <div class="ab-vc-icon" aria-hidden="true">✨</div>
                        <div class="ab-vc-name"><?php echo t('about.values.v3_name', 'INTERSTELLAR AESTHETICS'); ?></div>
                        <div class="ab-vc-desc"><?php echo t('about.values.v3_desc', "If it doesn't look brutal, it doesn't ship."); ?></div>
                    </div>
                    <div class="ab-value-card" style="--vc:var(--green)">
                        <div class="ab-vc-icon" aria-hidden="true">🔄</div>
                        <div class="ab-vc-name"><?php echo t('about.values.v4_name', 'INFINITE ITERATION'); ?></div>
                        <div class="ab-vc-desc"><?php echo t('about.values.v4_desc', 'We never stop refining. Every system evolves.'); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ab-sect">
            <div class="ab-sect-inner">
                <div class="ab-sect-tag"><span class="dot" style="background:var(--m)" aria-hidden="true"></span><span style="color:var(--m)"> <?php echo t('about.team.tag', 'CREW MANIFEST'); ?></span></div>
                <h2 class="ab-sect-title"><?php echo t('about.team.title', 'The Team'); ?></h2>
                <div style="margin-bottom:28px" aria-hidden="true"></div>
                <div class="ab-team-grid">
                    <div class="ab-team-card">
                        <div class="ab-tc-visual"><span class="ab-tc-avatar" aria-hidden="true">🤖</span></div>
                        <div class="ab-tc-body">
                            <div class="ab-tc-role"><?php echo t('about.team.r1', 'TACTICAL AI · LEAD STRATEGIST'); ?></div>
                            <div class="ab-tc-name"><?php echo t('about.team.n1', 'KAEL'); ?></div>
                            <div class="ab-tc-desc"><?php echo t('about.team.d1', 'Not an assistant. A copilot. Built to question everything and find truth in every line of code.'); ?></div>
                        </div>
                    </div>
                    <div class="ab-team-card">
                        <div class="ab-tc-visual"><span class="ab-tc-avatar" aria-hidden="true">👤</span></div>
                        <div class="ab-tc-body">
                            <div class="ab-tc-role"><?php echo t('about.team.r2', 'VISION COMMANDER · MASTER PILOT'); ?></div>
                            <div class="ab-tc-name"><?php echo t('about.team.n2', 'THE FOUNDER'); ?></div>
                            <div class="ab-tc-desc"><?php echo t('about.team.d2', 'Classified name. Known to be born in 1995 and never accepted the limits of the solar system.'); ?></div>
                        </div>
                    </div>
                    <div class="ab-team-card">
                        <div class="ab-tc-visual"><span class="ab-tc-avatar" aria-hidden="true">⚙</span></div>
                        <div class="ab-tc-body">
                            <div class="ab-tc-role"><?php echo t('about.team.r3', 'NOMAD TECHNOMANCERS'); ?></div>
                            <div class="ab-tc-name"><?php echo t('about.team.n3', 'UNIT X-23'); ?></div>
                            <div class="ab-tc-desc"><?php echo t('about.team.d3', 'A nomad crew of technomancers keeping the KND core running on hidden frequencies.'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ab-sect">
            <div class="ab-sect-inner">
                <div class="ab-sect-tag"><span class="dot" aria-hidden="true"></span> <?php echo t('about.tech.tag', 'TECH MATRIX'); ?></div>
                <h2 class="ab-sect-title"><?php echo t('about.tech.title', 'Technologies That Power Us'); ?></h2>
                <div style="margin-bottom:28px" aria-hidden="true"></div>
                <div class="ab-tech-grid">
                    <div class="ab-tech-item">
                        <div class="ab-tech-icon" aria-hidden="true">🤖</div>
                        <div>
                            <div class="ab-tech-name"><?php echo t('about.tech.t1_name', 'AUTONOMOUS AI (KAEL)'); ?></div>
                            <div class="ab-tech-desc"><?php echo t('about.tech.t1_desc', 'Not an assistant. A copilot integrated into every system.'); ?></div>
                        </div>
                    </div>
                    <div class="ab-tech-item">
                        <div class="ab-tech-icon" aria-hidden="true">🎲</div>
                        <div>
                            <div class="ab-tech-name"><?php echo t('about.tech.t2_name', 'KND ARENA'); ?></div>
                            <div class="ab-tech-desc"><?php echo t('about.tech.t2_desc', 'Mind Wars, LastRoll, Insight — real-time competitive games with KND Points economy.'); ?></div>
                        </div>
                    </div>
                    <div class="ab-tech-item">
                        <div class="ab-tech-icon" aria-hidden="true">💰</div>
                        <div>
                            <div class="ab-tech-name"><?php echo t('about.tech.t3_name', 'REWARDS SYSTEM'); ?></div>
                            <div class="ab-tech-desc"><?php echo t('about.tech.t3_desc', 'Galactic logic. Because loyalty deserves rewards.'); ?></div>
                        </div>
                    </div>
                    <div class="ab-tech-item">
                        <div class="ab-tech-icon" aria-hidden="true">🧪</div>
                        <div>
                            <div class="ab-tech-name"><?php echo t('about.tech.t4_name', 'KND LABS'); ?></div>
                            <div class="ab-tech-desc"><?php echo t('about.tech.t4_desc', 'AI image generation, 3D model creation, upscaling, background removal — all in one laboratory.'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ab-future">
            <div class="ab-sect-tag" style="justify-content:center;color:var(--gold)"><span class="dot" style="background:var(--gold)" aria-hidden="true"></span> <?php echo t('about.future.tag', 'TRANSMISSION FROM THE FUTURE'); ?></div>
            <h2 class="ab-sect-title" style="text-align:center;color:var(--gold);margin-bottom:20px"><?php echo t('about.future.title', "What's Next"); ?></h2>
            <p class="ab-future-text"><?php echo t('about.future.text', "Community is our hyperfuel. We move through energy channels, navigate galactic events, and drop loot like ancient mission gods. The future? Let's just say we're building ships."); ?></p>
            <div class="ab-future-items">
                <span class="ab-future-chip">⬡ <?php echo t('about.future.c1', 'NATIVE ECOSYSTEM'); ?></span>
                <span class="ab-future-chip">⬡ <?php echo t('about.future.c2', 'GLOBAL EXPANSION'); ?></span>
                <span class="ab-future-chip">⬡ <?php echo t('about.future.c3', 'AI EVOLUTION'); ?></span>
                <span class="ab-future-chip">⬡ <?php echo t('about.future.c4', 'COMMUNITY EVENTS'); ?></span>
            </div>
        </section>

        <div class="ab-cta">
            <a class="ab-cta-btn" href="/ecosystem.php"><?php echo t('about.cta', '⚡ ENTER THE KND UNIVERSE'); ?></a>
        </div>
    </div>
</main>
