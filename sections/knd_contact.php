<?php
/**
 * Contact page — aligned with contact/knd-contact-concept.html
 *
 * Expects: $name, $email, $subject, $message, $success_message, $error_message
 */
$discordUrl = 'https://discord.gg/zjP3u5Yztx';
$supportEmail = 'support@kndstore.com';

$subjectKeys = [
    'contact.form.subject_opt_web',
    'contact.form.subject_opt_social',
    'contact.form.subject_opt_support',
    'contact.form.subject_opt_branding',
    'contact.form.subject_opt_order',
    'contact.form.subject_opt_partnership',
    'contact.form.subject_opt_other',
];
?>
<main class="knd-contact-page" id="knd-contact">
    <div id="contact-bg" aria-hidden="true"><canvas id="contact-bg-canvas"></canvas></div>

    <div class="kc-page-inner">
        <section class="kc-hero">
            <div class="kc-hero-badge">⬡ <?php echo htmlspecialchars(t('contact.concept.hero_badge')); ?></div>
            <h1 class="kc-hero-title">
                <?php echo htmlspecialchars(t('contact.hero.title_open')); ?>
                <span class="gr"><?php echo htmlspecialchars(t('contact.hero.title_transmission')); ?></span>
            </h1>
            <p class="kc-hero-sub"><?php echo htmlspecialchars(t('contact.hero.subtitle')); ?></p>
        </section>

        <div class="kc-grid">
            <div class="kc-form-card">
                <div class="kc-form-title"><span class="dot" aria-hidden="true"></span> <?php echo htmlspecialchars(t('contact.form.title')); ?></div>
                <div class="kc-form-sub"><?php echo htmlspecialchars(t('contact.concept.form_subline')); ?></div>

                <?php if (!empty($success_message)) : ?>
                    <div class="kc-alert kc-alert--success" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($error_message)) : ?>
                    <div class="kc-alert kc-alert--error" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <form method="POST" action="contact.php" class="kc-contact-form">
                    <div class="kc-f-group">
                        <label class="kc-f-label" for="kc-name"><?php echo htmlspecialchars(t('contact.form.name_label_pilot')); ?></label>
                        <input type="text" class="kc-f-input" id="kc-name" name="name" autocomplete="name"
                            value="<?php echo htmlspecialchars($name ?? ''); ?>"
                            placeholder="<?php echo htmlspecialchars(t('contact.form.name_placeholder')); ?>" required>
                    </div>
                    <div class="kc-f-group">
                        <label class="kc-f-label" for="kc-email"><?php echo htmlspecialchars(t('contact.form.email_label_frequency')); ?></label>
                        <input type="email" class="kc-f-input" id="kc-email" name="email" autocomplete="email"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            placeholder="<?php echo htmlspecialchars(t('contact.form.email_placeholder')); ?>" required>
                    </div>
                    <div class="kc-f-group">
                        <label class="kc-f-label" for="kc-subject"><?php echo htmlspecialchars(t('contact.form.subject_label_galactic')); ?></label>
                        <select class="kc-f-select" id="kc-subject" name="subject" required>
                            <option value="" disabled <?php echo ($subject ?? '') === '' ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('contact.form.subject_select_placeholder')); ?></option>
                            <?php foreach ($subjectKeys as $sk) :
                                $opt = t($sk);
                                $sel = isset($subject) && trim((string) $subject) === trim($opt) ? ' selected' : '';
                                ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kc-f-group">
                        <label class="kc-f-label" for="kc-message"><?php echo htmlspecialchars(t('contact.form.message_label_transmission')); ?></label>
                        <textarea class="kc-f-textarea" id="kc-message" name="message" rows="6"
                            placeholder="<?php echo htmlspecialchars(t('contact.form.message_placeholder')); ?>" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="kc-send-btn"><?php echo htmlspecialchars(t('contact.form.submit')); ?></button>
                </form>
            </div>

            <div class="kc-right-col">
                <div class="kc-channel" style="--chc:var(--c)">
                    <div class="kc-ch-header">
                        <span class="kc-ch-icon" aria-hidden="true">✉</span>
                        <span class="kc-ch-label"><?php echo htmlspecialchars(t('contact.info.label_primary')); ?></span>
                    </div>
                    <div class="kc-ch-title"><?php echo htmlspecialchars(t('contact.info.email_official')); ?></div>
                    <div class="kc-ch-value"><?php echo htmlspecialchars($supportEmail); ?></div>
                    <a class="kc-ch-action" href="mailto:<?php echo htmlspecialchars($supportEmail); ?>">⬡ <?php echo htmlspecialchars(t('contact.info.send_message')); ?> →</a>
                </div>

                <div class="kc-channel" style="--chc:#7289da">
                    <div class="kc-ch-header">
                        <span class="kc-ch-icon" aria-hidden="true">💬</span>
                        <span class="kc-ch-label"><?php echo htmlspecialchars(t('contact.info.label_realtime')); ?></span>
                    </div>
                    <div class="kc-ch-title"><?php echo htmlspecialchars(t('contact.info.mothership')); ?></div>
                    <div class="kc-ch-value"><?php echo htmlspecialchars(t('contact.info.discord_desc')); ?></div>
                    <a class="kc-ch-action" href="<?php echo htmlspecialchars($discordUrl); ?>" target="_blank" rel="noopener noreferrer" style="border-color:#7289da;color:#7289da">⬡ <?php echo htmlspecialchars(t('contact.info.join_discord')); ?> →</a>
                </div>

                <div class="kc-channel" style="--chc:var(--gold)">
                    <div class="kc-ch-header">
                        <span class="kc-ch-icon" aria-hidden="true">📍</span>
                        <span class="kc-ch-label"><?php echo htmlspecialchars(t('contact.info.label_ops')); ?></span>
                    </div>
                    <div class="kc-ch-title"><?php echo htmlspecialchars(t('contact.info.location_title')); ?></div>
                    <div class="kc-ch-value"><?php echo htmlspecialchars(t('contact.info.location_sub')); ?></div>
                </div>

                <div class="kc-hours-card">
                    <div class="kc-hours-title">⏱ <?php echo htmlspecialchars(t('contact.hours.title')); ?></div>
                    <div class="kc-hours-row">
                        <span class="kc-hr-name"><span class="kc-status-dot on" aria-hidden="true"></span><?php echo htmlspecialchars(t('contact.info.technical_support')); ?></span>
                        <span class="kc-hr-time live"><?php echo htmlspecialchars(t('contact.hours.support_247')); ?></span>
                    </div>
                    <div class="kc-hours-row">
                        <span class="kc-hr-name"><span class="kc-status-dot on" id="kc-sales-dot" aria-hidden="true"></span><?php echo htmlspecialchars(t('contact.info.sales')); ?></span>
                        <span class="kc-hr-time sched" id="kc-sales-time"
                            data-online="<?php echo htmlspecialchars(t('contact.hours.sales_status_online')); ?>"
                            data-offline="<?php echo htmlspecialchars(t('contact.hours.sales_status_offline')); ?>"
                            data-sched="<?php echo htmlspecialchars(t('contact.hours.mon_sun') . ' · ' . t('contact.hours.hours_range')); ?>"><?php echo htmlspecialchars(t('contact.hours.mon_sun') . ' · ' . t('contact.hours.hours_range')); ?></span>
                    </div>
                </div>

                <div class="kc-urgent-card">
                    <div class="kc-urgent-title">⚠ <?php echo htmlspecialchars(t('contact.cta.immediate_assistance')); ?></div>
                    <div class="kc-urgent-sub"><?php echo htmlspecialchars(t('contact.cta.urgent_text')); ?></div>
                    <div class="kc-urgent-btns">
                        <a class="kc-urg-btn discord" href="<?php echo htmlspecialchars($discordUrl); ?>" target="_blank" rel="noopener noreferrer">💬 <?php echo htmlspecialchars(t('contact.cta.discord_immediate')); ?></a>
                        <a class="kc-urg-btn email" href="mailto:<?php echo htmlspecialchars($supportEmail); ?>">✉ <?php echo htmlspecialchars(t('contact.cta.emergency_email')); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
