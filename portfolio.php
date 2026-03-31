<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

$quote_defaults = [
    'name' => '',
    'email' => '',
    'website' => '',
    'need' => '',
    'goal' => '',
    'budget' => '',
    'deadline' => '',
    'message' => '',
    'company' => '',
];

$quote_data = $quote_defaults;
$quote_errors = [];
$quote_success = false;
$quote_feedback = '';

$need_options = ['Landing page', 'Website', 'Redesign', 'Ecommerce', 'Other'];
$goal_options = ['Get more leads', 'Increase sales', 'Book calls', 'Other'];
$budget_options = ['Under $300', '$300–$700', '$700–$1500', '$1500+'];
$deadline_options = ['ASAP (1–3 days)', '1 week', '2 weeks', 'Flexible'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['portfolio_csrf_token'])) {
    $_SESSION['portfolio_csrf_token'] = bin2hex(random_bytes(32));
}

$portfolio_csrf_token = $_SESSION['portfolio_csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portfolio_quote_submit'])) {
    $quote_data['name'] = trim($_POST['name'] ?? '');
    $quote_data['email'] = trim($_POST['email'] ?? '');
    $quote_data['website'] = trim($_POST['website'] ?? '');
    $quote_data['need'] = trim($_POST['need'] ?? '');
    $quote_data['goal'] = trim($_POST['goal'] ?? '');
    $quote_data['budget'] = trim($_POST['budget'] ?? '');
    $quote_data['deadline'] = trim($_POST['deadline'] ?? '');
    $quote_data['message'] = trim($_POST['message'] ?? '');
    $quote_data['company'] = trim($_POST['company'] ?? '');

    $posted_token = $_POST['csrf_token'] ?? '';
    if (!$posted_token || !hash_equals($portfolio_csrf_token, $posted_token)) {
        $quote_errors['form'] = 'Your session expired. Please refresh and try again.';
    }

    if ($quote_data['company'] !== '') {
        $quote_errors['company'] = 'Spam detected.';
        if (empty($quote_errors['form'])) {
            $quote_errors['form'] = 'Unable to submit the form. Please try again.';
        }
    }

    if ($quote_data['name'] === '') {
        $quote_errors['name'] = 'Please enter your name or business.';
    }

    if ($quote_data['email'] === '' || !filter_var($quote_data['email'], FILTER_VALIDATE_EMAIL)) {
        $quote_errors['email'] = 'Please enter a valid email address.';
    }

    if ($quote_data['website'] !== '' && !filter_var($quote_data['website'], FILTER_VALIDATE_URL)) {
        $quote_errors['website'] = 'Please enter a valid website URL.';
    }

    if ($quote_data['need'] === '' || !in_array($quote_data['need'], $need_options, true)) {
        $quote_errors['need'] = 'Select what you need.';
    }

    if ($quote_data['goal'] === '' || !in_array($quote_data['goal'], $goal_options, true)) {
        $quote_errors['goal'] = 'Select your primary goal.';
    }

    if ($quote_data['budget'] === '' || !in_array($quote_data['budget'], $budget_options, true)) {
        $quote_errors['budget'] = 'Select a budget range.';
    }

    if ($quote_data['deadline'] !== '' && !in_array($quote_data['deadline'], $deadline_options, true)) {
        $quote_errors['deadline'] = 'Select a valid deadline option.';
    }

    if ($quote_data['message'] === '') {
        $quote_errors['message'] = 'Tell us a bit more about the project.';
    }

    if (empty($quote_errors)) {
        $safe_name = str_replace(["\r", "\n"], ' ', $quote_data['name']);
        $safe_email = str_replace(["\r", "\n"], ' ', $quote_data['email']);
        $safe_subject = 'Portfolio quote request - ' . $safe_name;
        $domain = $_SERVER['SERVER_NAME'] ?? 'kndstore.com';
        $from_email = 'no-reply@' . preg_replace('/[^a-zA-Z0-9.\-]/', '', $domain);

        $email_body = "Portfolio quote request\n\n";
        $email_body .= "Name / Business: {$quote_data['name']}\n";
        $email_body .= "Email: {$quote_data['email']}\n";
        $email_body .= "Website: " . ($quote_data['website'] ?: 'N/A') . "\n";
        $email_body .= "Need: {$quote_data['need']}\n";
        $email_body .= "Primary goal: {$quote_data['goal']}\n";
        $email_body .= "Budget: {$quote_data['budget']}\n";
        $email_body .= "Deadline: " . ($quote_data['deadline'] ?: 'Not specified') . "\n";
        $email_body .= "\nMessage:\n{$quote_data['message']}\n";

        $headers = [
            'From: KND Portfolio <' . $from_email . '>',
            'Reply-To: ' . $safe_email,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $mail_sent = @mail('YOUR_INBOX_EMAIL_HERE', $safe_subject, $email_body, implode("\r\n", $headers));

        if ($mail_sent) {
            $log_payload = $quote_data;
            $quote_success = true;
            $quote_feedback = 'Thanks! Your request has been received. We will reply within 24 hours.';
            $quote_data = $quote_defaults;
            $_SESSION['portfolio_csrf_token'] = bin2hex(random_bytes(32));
            $portfolio_csrf_token = $_SESSION['portfolio_csrf_token'];

            $log_dir = __DIR__ . '/logs';
            $log_file = $log_dir . '/portfolio_leads.log';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
            }
            $log_entry = sprintf(
                "[%s] %s | %s | %s | %s | %s | %s | %s | %s\n",
                date('Y-m-d H:i:s'),
                $log_payload['name'] ?: $safe_name,
                $log_payload['email'] ?: $safe_email,
                $log_payload['website'] ?: 'N/A',
                $log_payload['need'] ?: 'N/A',
                $log_payload['goal'] ?: 'N/A',
                $log_payload['budget'] ?: 'N/A',
                $log_payload['deadline'] ?: 'N/A',
                str_replace(["\r", "\n"], ' ', $log_payload['message'])
            );
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        } else {
            $quote_errors['form'] = 'We could not send your request right now. Please try again.';
        }
    }
}

$projects = [
    [
        'title' => 'Enviosand',
        'url' => 'https://enviosand.com',
        'goal' => __('portfolio.project.enviosand.goal', 'Increase shipping service inquiries with a clear booking flow.'),
        'role' => __('portfolio.project.enviosand.role', 'Landing page strategy, visual design, and conversion copy.'),
    ],
    [
        'title' => 'SolutionsEA',
        'url' => 'https://solutionseallc.com',
        'goal' => __('portfolio.project.solutionsea.goal', 'Drive qualified leads for enterprise consulting services.'),
        'role' => __('portfolio.project.solutionsea.role', 'Information architecture, UI direction, and CTA optimization.'),
    ],
    [
        'title' => 'Midwest Clean Solutions',
        'url' => 'https://midwestcleansol.com',
        'goal' => __('portfolio.project.midwest.goal', 'Increase service requests for commercial cleaning.'),
        'role' => __('portfolio.project.midwest.role', 'Conversion-focused layout, copy, and visual hierarchy.'),
    ],
    [
        'title' => 'JProd USA',
        'url' => 'https://jprodusa.com',
        'goal' => __('portfolio.project.jprod.goal', 'Position services with clarity and premium positioning.'),
        'role' => __('portfolio.project.jprod.role', 'Page redesign, messaging polish, and CTA strategy.'),
    ],
    [
        'title' => 'HK Jewelry',
        'url' => 'https://hkjewelrycorp.com',
        'goal' => __('portfolio.project.hk.goal', 'Showcase brand value and convert showroom inquiries.'),
        'role' => __('portfolio.project.hk.role', 'Landing page design, copy alignment, and user flow.'),
    ],
];
?>

<?php echo generateHeader(
    __('portfolio.meta.title', 'Portfolio'),
    __('portfolio.meta.description', 'Landing pages built to help businesses get clients and sales')
); ?>

<link rel="stylesheet" href="/assets/css/portfolio.css">

<!-- Particles Background -->
<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<div class="portfolio-page">
    <!-- Hero Section -->
    <section class="hero-section portfolio-hero">
        <div class="portfolio-hero-layer portfolio-hero-ambient" aria-hidden="true">
            <img src="/assets/images/hero-coctact-background.png" alt="">
        </div>
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-7 order-1 order-lg-1">
                    <div class="portfolio-hero-content">
                        <span class="portfolio-badge badge bg-primary">
                            <?php echo __('portfolio.hero.badge', 'Premium landing pages'); ?>
                        </span>
                        <h1 class="hero-title mt-4">
                            <span class="text-gradient">
                                <?php echo __('portfolio.hero.title', 'Landing pages built to help businesses get clients and sales'); ?>
                            </span>
                        </h1>
                        <p class="hero-subtitle">
                            <?php echo __('portfolio.hero.subtitle', 'I design conversion-focused landing pages that turn traffic into qualified leads and sales.'); ?>
                        </p>
                        <div class="hero-buttons">
                            <a href="#quote" class="btn btn-neon-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i> Request a Quote
                            </a>
                            <a href="#work" class="btn btn-outline-neon btn-lg">
                                <i class="fas fa-layer-group me-2"></i> View projects
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 order-2 order-lg-2 mt-5 mt-lg-0">
                    <figure class="portfolio-mockup-wrap">
                        <div class="portfolio-mockup-card">
                            <div class="mockup-topbar">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            <div class="mockup-screen">
                                <div class="mockup-strip"></div>
                                <div class="mockup-lines">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <div class="mockup-cta"></div>
                            </div>
                        </div>
                    </figure>
                </div>
            </div>
        </div>
    </section>

    <!-- Projects Grid -->
    <section class="portfolio-section py-5 bg-dark-epic" id="work">
        <div class="container">
            <div class="row mb-4 align-items-end">
                <div class="col-lg-8">
                    <h2 class="section-title">
                        <?php echo __('portfolio.projects.title', 'Selected landing pages'); ?>
                    </h2>
                    <p class="portfolio-section-subtitle">
                        <?php echo __('portfolio.projects.subtitle', 'Five focused builds designed to convert traffic into booked calls and sales.'); ?>
                    </p>
                </div>
            </div>
            <div class="row g-4">
                <?php foreach ($projects as $project): ?>
                    <div class="col-lg-4 col-md-6">
                        <article class="portfolio-card glass-card-neon h-100">
                            <div class="portfolio-card-body">
                                <h3 class="portfolio-card-title">
                                    <?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                                <div class="portfolio-card-meta">
                                    <div class="portfolio-meta-item">
                                        <span class="portfolio-meta-label">
                                            <?php echo __('portfolio.label.goal', 'Goal'); ?>
                                        </span>
                                        <span class="portfolio-meta-text">
                                            <?php echo $project['goal']; ?>
                                        </span>
                                    </div>
                                    <div class="portfolio-meta-item">
                                        <span class="portfolio-meta-label">
                                            <?php echo __('portfolio.label.role', 'Role'); ?>
                                        </span>
                                        <span class="portfolio-meta-text">
                                            <?php echo $project['role']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="portfolio-card-footer">
                                <a class="btn btn-outline-neon btn-sm portfolio-link"
                                   href="<?php echo htmlspecialchars($project['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <?php echo __('portfolio.project.cta', 'View live'); ?>
                                    <i class="fas fa-arrow-up-right-from-square ms-2"></i>
                                </a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Quote Form -->
    <section class="portfolio-quote-section py-5" id="quote">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="portfolio-quote-header text-center mb-5">
                        <h2 class="section-title">Request a Quote</h2>
                        <p class="portfolio-section-subtitle">
                            Tell me about your landing page and I will send a tailored plan, timeline, and quote.
                        </p>
                    </div>

                    <div class="portfolio-quote-card glass-card-neon">
                        <?php if (!empty($quote_errors['form'])): ?>
                            <div class="quote-alert quote-alert-error" role="alert">
                                <?php echo htmlspecialchars($quote_errors['form'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($quote_success): ?>
                            <div class="quote-alert quote-alert-success" role="status">
                                <?php echo htmlspecialchars($quote_feedback, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <form class="portfolio-quote-form" method="POST" action="/portfolio.php#quote">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($portfolio_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="text" name="company" class="hp-field" value="<?php echo htmlspecialchars($quote_data['company'], ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1" autocomplete="off">

                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="quote-name">Name / Business name</label>
                                    <input type="text" id="quote-name" name="name" class="form-control" required
                                           value="<?php echo htmlspecialchars($quote_data['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php if (!empty($quote_errors['name'])): ?>
                                        <span class="form-error"><?php echo htmlspecialchars($quote_errors['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-field">
                                    <label for="quote-email">Email</label>
                                    <input type="email" id="quote-email" name="email" class="form-control" required
                                           value="<?php echo htmlspecialchars($quote_data['email'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php if (!empty($quote_errors['email'])): ?>
                                        <span class="form-error"><?php echo htmlspecialchars($quote_errors['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-field">
                                    <label for="quote-website">Website (optional)</label>
                                    <input type="url" id="quote-website" name="website" class="form-control"
                                           value="<?php echo htmlspecialchars($quote_data['website'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php if (!empty($quote_errors['website'])): ?>
                                        <span class="form-error"><?php echo htmlspecialchars($quote_errors['website'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-field">
                                    <label for="quote-need">What do you need?</label>
                                    <select id="quote-need" name="need" class="form-select" required>
                                        <option value="">Select one</option>
                                        <?php foreach ($need_options as $option): ?>
                                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($quote_data['need'] === $option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($quote_errors['need'])): ?>
                                        <span class="form-error"><?php echo htmlspecialchars($quote_errors['need'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-field">
                                    <label for="quote-goal">Primary goal</label>
                                    <select id="quote-goal" name="goal" class="form-select" required>
                                        <option value="">Select one</option>
                                        <?php foreach ($goal_options as $option): ?>
                                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($quote_data['goal'] === $option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($quote_errors['goal'])): ?>
                                        <span class="form-error"><?php echo htmlspecialchars($quote_errors['goal'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-field">
                                    <label for="quote-budget">Budget</label>
                                    <select id="quote-budget" name="budget" class="form-select" required>
                                        <option value="">Select one</option>
                                        <?php foreach ($budget_options as $option): ?>
                                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($quote_data['budget'] === $option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($quote_errors['budget'])): ?>
                                        <span class="form-error"><?php echo htmlspecialchars($quote_errors['budget'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="form-field">
                                    <label for="quote-deadline">Deadline (optional)</label>
                                    <select id="quote-deadline" name="deadline" class="form-select">
                                        <option value="">Select one</option>
                                        <?php foreach ($deadline_options as $option): ?>
                                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($quote_data['deadline'] === $option) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($quote_errors['deadline'])): ?>
                                        <span class="form-error"><?php echo htmlspecialchars($quote_errors['deadline'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-field form-field-full">
                                <label for="quote-message">Message / details</label>
                                <textarea id="quote-message" name="message" class="form-control" rows="6" required><?php echo htmlspecialchars($quote_data['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <?php if (!empty($quote_errors['message'])): ?>
                                    <span class="form-error"><?php echo htmlspecialchars($quote_errors['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="portfolio_quote_submit" class="btn btn-neon-primary btn-lg">
                                    <i class="fas fa-rocket me-2"></i> Submit request
                                </button>
                                <p class="form-note">I reply within 24 hours with next steps and pricing.</p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="cta-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h2 class="section-title mb-4">
                        <?php echo __('portfolio.cta.title', 'Need a landing page for your business?'); ?>
                    </h2>
                    <a href="#quote" class="btn btn-neon-primary btn-lg">
                        <i class="fas fa-rocket me-2"></i> <?php echo __('portfolio.cta.button', 'Request a quote'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="/assets/js/navigation-extend.js"></script>

<?php
echo generateFooter();
echo generateScripts();
?>
