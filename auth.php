<?php
require_once __DIR__ . '/config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';

if (is_logged_in()) {
    $pdo = getDBConnection();
    if ($pdo) {
        $uid = current_user_id();
        $evStmt = $pdo->prepare('SELECT email, email_verified FROM users WHERE id = ? LIMIT 1');
        $evStmt->execute([$uid]);
        $evRow = $evStmt->fetch();
        if ($evRow && !empty($evRow['email']) && (int) $evRow['email_verified'] === 0) {
            $showVerify = true;
        } else {
            header('Location: /games/mind-wars/lobby.php');
            exit;
        }
    } else {
        header('Location: /games/mind-wars/lobby.php');
        exit;
    }
}
$showVerify = $showVerify ?? false;

$rawRedirect = $_GET['redirect'] ?? '';
if ($rawRedirect === '' || !str_starts_with($rawRedirect, '/') || str_contains($rawRedirect, '://') || str_contains($rawRedirect, '\\')) {
    $rawRedirect = '/games/mind-wars/lobby.php';
}
$redirect = htmlspecialchars($rawRedirect, ENT_QUOTES);
$csrfToken = csrf_token();
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
?>

<?php
$seoTitle = 'KND Access — Sign In';
$seoDesc  = 'Sign in to your KND ecosystem. Access KND Arena, LastRoll, Support Credits, and more.';
$authCssV = file_exists(__DIR__ . '/assets/css/auth.css') ? filemtime(__DIR__ . '/assets/css/auth.css') : 0;
$arenaEmbedCssV = file_exists(__DIR__ . '/assets/css/arena-embed.css') ? filemtime(__DIR__ . '/assets/css/arena-embed.css') : 0;

if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seoTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/auth.css?v=<?php echo $authCssV; ?>">
    <link rel="stylesheet" href="/assets/css/arena-embed.css?v=<?php echo $arenaEmbedCssV; ?>">
</head>
<body class="arena-embed auth-page knd-access-page">
<div class="arena-embed-inner">
<?php
} else {
    $ogHead   = '    <meta property="og:title" content="' . htmlspecialchars($seoTitle) . '">' . "\n";
    $ogHead  .= '    <meta property="og:description" content="' . htmlspecialchars($seoDesc) . '">' . "\n";
    $ogHead  .= '    <meta property="og:type" content="website">' . "\n";
    $ogHead  .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    $ogHead  .= '    <meta name="twitter:title" content="' . htmlspecialchars($seoTitle) . '">' . "\n";
    $ogHead  .= '    <meta name="twitter:description" content="' . htmlspecialchars($seoDesc) . '">' . "\n";
    $ogHead  .= '    <link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    $ogHead  .= '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    $ogHead  .= '    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">' . "\n";
    $ogHead  .= '    <link rel="stylesheet" href="/assets/css/auth.css?v=' . $authCssV . '">' . "\n";
    echo generateHeader($seoTitle, $seoDesc, $ogHead);
}
?>

<div id="knd-access-bg" aria-hidden="true">
    <div class="knd-access-bg-horizon"></div>
    <div class="knd-access-bg-floor"></div>
</div>
<div id="knd-access-stars" aria-hidden="true"></div>

<?php if (!$embed) echo generateNavigation(); ?>

<section class="knd-access-shell" aria-label="KND Access">
    <div class="knd-access-container">

        <div class="knd-access-brand">
            <div class="knd-access-brand-hex">
                <svg viewBox="0 0 100 100" fill="none" aria-hidden="true">
                    <polygon points="50,3 95,25 95,75 50,97 5,75 5,25" stroke="rgba(0,232,255,.3)" stroke-width="1" fill="rgba(0,232,255,.03)"/>
                    <polygon points="50,12 85,30 85,70 50,88 15,70 15,30" stroke="rgba(0,232,255,.15)" stroke-width="0.5" fill="none"/>
                    <polygon points="50,22 74,35 74,65 50,78 26,65 26,35" stroke="rgba(212,79,255,.12)" stroke-width="0.5" fill="rgba(212,79,255,.02)"/>
                </svg>
                <span class="knd-access-brand-hex-inner" aria-hidden="true">⬡</span>
            </div>
            <div class="knd-access-brand-title">KND</div>
            <div class="knd-access-brand-tagline"><?php echo t('dr.auth.brand_tagline', 'Enter the Ecosystem'); ?></div>
            <div class="knd-access-brand-sub"><?php echo t('dr.auth.brand_sub', "KNOWLEDGE 'N DEVELOPMENT — WHERE DIGITAL INNOVATION BEGINS"); ?></div>
            <div class="knd-access-brand-dots" aria-hidden="true">
                <span class="knd-access-brand-dot"></span>
                <span class="knd-access-brand-dot"></span>
                <span class="knd-access-brand-dot"></span>
            </div>
            <p class="knd-access-brand-foot"><?php echo t('dr.auth.brand_secure', 'SECURE · ENCRYPTED · PRIVATE'); ?></p>
        </div>

        <div class="knd-access-form-panel">

            <div id="auth-main-stack"<?php echo $showVerify ? ' style="display:none"' : ''; ?>>
                <!-- Radios first: CSS sibling ~ rules toggle panels without JS -->
                <input type="radio" name="knd-auth-tab" id="knd-auth-tab-login" class="knd-access-tab-radio" value="login" checked autocomplete="off"<?php echo $showVerify ? ' tabindex="-1"' : ''; ?>>
                <input type="radio" name="knd-auth-tab" id="knd-auth-tab-register" class="knd-access-tab-radio" value="register" autocomplete="off"<?php echo $showVerify ? ' tabindex="-1"' : ''; ?>>
                <div class="knd-access-tab-switch" id="auth-tab-switch" role="tablist"<?php echo $showVerify ? ' style="display:none"' : ''; ?>>
                    <label for="knd-auth-tab-login" class="knd-access-tab-btn" id="knd-label-login" role="tab"><?php echo t('dr.auth.login', 'Login'); ?></label>
                    <label for="knd-auth-tab-register" class="knd-access-tab-btn" id="knd-label-register" role="tab"><?php echo t('dr.auth.register', 'Register'); ?></label>
                </div>

                <div id="panel-login" class="knd-access-auth-panel" role="tabpanel" aria-labelledby="knd-label-login">
                    <div class="knd-access-form-heading">
                        <h2><?php echo t('dr.auth.signal_in', 'SIGNAL IN'); ?></h2>
                        <p><?php echo t('dr.auth.signal_in_sub', 'ACCESS YOUR KND ACCOUNT'); ?></p>
                    </div>
                    <form id="form-login" autocomplete="on">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <div class="knd-access-field">
                            <input type="text" name="username" id="login-username" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.username', 'Username'), ENT_QUOTES); ?>" required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" autocomplete="username">
                            <span class="knd-access-input-icon" aria-hidden="true">◆</span>
                        </div>
                        <div class="knd-access-field knd-access-field--password">
                            <input type="password" name="password" id="login-password" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.password', 'Password'), ENT_QUOTES); ?>" required minlength="8" autocomplete="current-password">
                            <span class="knd-access-input-icon" aria-hidden="true">🔒</span>
                            <button type="button" class="knd-access-pwd-toggle" data-knd-toggle-pwd="login-password" aria-label="<?php echo htmlspecialchars(t('dr.auth.toggle_password', 'Toggle password visibility'), ENT_QUOTES); ?>">👁</button>
                        </div>
                        <label class="knd-access-check-row" for="remember-login">
                            <input type="checkbox" id="remember-login" name="remember_login" class="knd-access-check-sr">
                            <span class="knd-access-check-box" aria-hidden="true"></span>
                            <span class="knd-access-check-label"><?php echo t('dr.auth.remember_login', 'Remember username and password on this device'); ?></span>
                        </label>
                        <button type="submit" class="knd-access-btn knd-access-btn-primary">
                            <span class="auth-btn-text">⚡ <?php echo t('dr.auth.access_platform', 'Access Platform'); ?></span>
                        </button>
                        <div class="knd-access-auth-links">
                            <a href="#" id="link-forgot" class="knd-access-auth-link"><?php echo t('dr.auth.forgot_link', 'Forgot your password or username?'); ?></a>
                            <a href="#" id="link-to-register" class="knd-access-auth-link"><?php echo t('dr.auth.no_account', "Don't have an account? Register"); ?></a>
                        </div>
                    </form>
                </div>

                <div id="panel-register" class="knd-access-auth-panel" role="tabpanel" aria-labelledby="knd-label-register">
                    <div class="knd-access-form-heading">
                        <h2><?php echo t('dr.auth.new_signal', 'NEW SIGNAL'); ?></h2>
                        <p><?php echo t('dr.auth.new_signal_sub', 'CREATE YOUR KND IDENTITY'); ?></p>
                    </div>
                    <form id="form-register" autocomplete="on">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <div class="knd-access-field">
                            <input type="text" name="username" id="reg-username" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.username', 'Username'), ENT_QUOTES); ?>" required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" autocomplete="username">
                            <span class="knd-access-input-icon" aria-hidden="true">◆</span>
                            <div class="knd-access-input-hint"><?php echo t('dr.auth.username_hint', '3-24 chars: letters, numbers, underscore'); ?></div>
                        </div>
                        <div class="knd-access-field">
                            <input type="email" name="email" id="reg-email" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.email', 'Email'), ENT_QUOTES); ?>" required maxlength="255" autocomplete="email">
                            <span class="knd-access-input-icon" aria-hidden="true">✉</span>
                            <div class="knd-access-input-hint"><?php echo t('dr.auth.email_hint', 'We\'ll send a verification code'); ?></div>
                        </div>
                        <div class="knd-access-field knd-access-field--password">
                            <input type="password" name="password" id="reg-password" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.password', 'Password'), ENT_QUOTES); ?>" required minlength="8" autocomplete="new-password">
                            <span class="knd-access-input-icon" aria-hidden="true">🔒</span>
                            <button type="button" class="knd-access-pwd-toggle" data-knd-toggle-pwd="reg-password" aria-label="<?php echo htmlspecialchars(t('dr.auth.toggle_password', 'Toggle password visibility'), ENT_QUOTES); ?>">👁</button>
                            <div class="knd-access-pwd-strength">
                                <div class="knd-access-pwd-str-seg"></div>
                                <div class="knd-access-pwd-str-seg"></div>
                                <div class="knd-access-pwd-str-seg"></div>
                                <div class="knd-access-pwd-str-seg"></div>
                            </div>
                            <div class="knd-access-pwd-str-label" id="reg-pwd-str-label"></div>
                            <div class="knd-access-input-hint knd-access-input-hint--below-strength"><?php echo t('dr.auth.password_hint', 'Minimum 8 characters'); ?></div>
                        </div>
                        <div class="knd-access-field knd-access-field--password">
                            <input type="password" name="password_confirm" id="reg-password-confirm" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.confirm_password', 'Confirm Password'), ENT_QUOTES); ?>" required minlength="8" autocomplete="new-password">
                            <span class="knd-access-input-icon" aria-hidden="true">🔒</span>
                            <button type="button" class="knd-access-pwd-toggle" data-knd-toggle-pwd="reg-password-confirm" aria-label="<?php echo htmlspecialchars(t('dr.auth.toggle_password', 'Toggle password visibility'), ENT_QUOTES); ?>">👁</button>
                        </div>
                        <button type="submit" class="knd-access-btn knd-access-btn-outline">
                            <span class="auth-btn-text">⬡ <?php echo t('dr.auth.register', 'Register'); ?></span>
                        </button>
                        <div class="knd-access-auth-links">
                            <a href="#" id="link-to-login" class="knd-access-auth-link"><?php echo t('dr.auth.have_account', 'Already have an account? Log in'); ?></a>
                        </div>
                    </form>
                </div>

                <div id="auth-alert" class="knd-access-alert" role="alert"></div>
            </div>

            <div id="forgot-panel" class="knd-access-aux-panel" style="display:none;">
                <div class="knd-access-form-heading">
                    <h2><?php echo t('dr.auth.forgot_heading', 'SIGNAL RECOVERY'); ?></h2>
                    <p><?php echo t('dr.auth.forgot_subtitle', 'Enter the email linked to your account'); ?></p>
                </div>

                <div id="forgot-step1">
                    <div class="knd-access-field">
                        <input type="email" id="forgot-email" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.email', 'Email'), ENT_QUOTES); ?>" required maxlength="255" autocomplete="email">
                        <span class="knd-access-input-icon" aria-hidden="true">✉</span>
                    </div>
                    <button type="button" id="btn-forgot-password" class="knd-access-btn knd-access-btn-primary">
                        <span class="auth-btn-text">🔒 <?php echo t('dr.auth.reset_password_btn', 'Reset Password'); ?></span>
                    </button>
                    <button type="button" id="btn-forgot-username" class="knd-access-btn knd-access-btn-outline">
                        <span class="auth-btn-text">◆ <?php echo t('dr.auth.send_username_btn', 'Send me my username'); ?></span>
                    </button>
                </div>

                <div id="forgot-step2" style="display:none;">
                    <form id="form-reset-password" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="email" id="reset-email-hidden">
                        <input type="hidden" name="code" id="reset-code-hidden" value="">
                        <p class="knd-access-code-label"><?php echo t('dr.auth.verify_code', 'Verification Code'); ?></p>
                        <div class="knd-access-code-wrap" id="reset-code-wrap" data-knd-code-hidden="reset-code-hidden">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <input class="knd-access-code-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="Digit <?php echo $i + 1; ?>">
                            <?php endfor; ?>
                        </div>
                        <div class="knd-access-field knd-access-field--password">
                            <input type="password" name="password" id="reset-password" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.new_password', 'New Password'), ENT_QUOTES); ?>" required minlength="8" autocomplete="new-password">
                            <span class="knd-access-input-icon" aria-hidden="true">🔒</span>
                            <button type="button" class="knd-access-pwd-toggle" data-knd-toggle-pwd="reset-password" aria-label="<?php echo htmlspecialchars(t('dr.auth.toggle_password', 'Toggle password visibility'), ENT_QUOTES); ?>">👁</button>
                            <div class="knd-access-pwd-strength">
                                <div class="knd-access-pwd-str-seg"></div>
                                <div class="knd-access-pwd-str-seg"></div>
                                <div class="knd-access-pwd-str-seg"></div>
                                <div class="knd-access-pwd-str-seg"></div>
                            </div>
                            <div class="knd-access-pwd-str-label" id="reset-pwd-str-label"></div>
                            <div class="knd-access-input-hint knd-access-input-hint--below-strength"><?php echo t('dr.auth.password_hint', 'Minimum 8 characters'); ?></div>
                        </div>
                        <div class="knd-access-field knd-access-field--password">
                            <input type="password" name="password_confirm" id="reset-password-confirm" class="knd-access-input" placeholder="<?php echo htmlspecialchars(t('dr.auth.confirm_password', 'Confirm Password'), ENT_QUOTES); ?>" required minlength="8" autocomplete="new-password">
                            <span class="knd-access-input-icon" aria-hidden="true">🔒</span>
                            <button type="button" class="knd-access-pwd-toggle" data-knd-toggle-pwd="reset-password-confirm" aria-label="<?php echo htmlspecialchars(t('dr.auth.toggle_password', 'Toggle password visibility'), ENT_QUOTES); ?>">👁</button>
                        </div>
                        <button type="submit" class="knd-access-btn knd-access-btn-primary">
                            <span class="auth-btn-text">💾 <?php echo t('dr.auth.set_new_password_btn', 'Set New Password'); ?></span>
                        </button>
                    </form>
                </div>

                <div class="knd-access-auth-links">
                    <a href="#" id="link-back-login" class="knd-access-auth-link"><?php echo t('dr.auth.back_to_login', 'Back to login'); ?></a>
                </div>
                <div id="forgot-alert" class="knd-access-alert" role="alert"></div>
            </div>

            <div id="verify-panel" class="knd-access-aux-panel" style="display:<?php echo $showVerify ? 'flex' : 'none'; ?>;">
                <div class="knd-access-form-heading">
                    <h2><?php echo t('dr.auth.verify_heading', 'VERIFY SIGNAL'); ?></h2>
                    <p><?php echo t('dr.auth.verify_subtitle', 'Enter the 6-digit code we sent to your email'); ?></p>
                </div>
                <form id="form-verify" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="code" id="verify-code-hidden" value="">
                    <div class="knd-access-code-wrap" id="verify-code-wrap" data-knd-code-hidden="verify-code-hidden">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                        <input class="knd-access-code-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="Digit <?php echo $i + 1; ?>">
                        <?php endfor; ?>
                    </div>
                    <button type="submit" class="knd-access-btn knd-access-btn-primary">
                        <span class="auth-btn-text">✓ <?php echo t('dr.auth.verify_btn', 'Verify'); ?></span>
                    </button>
                </form>
                <div class="knd-access-auth-links">
                    <button type="button" id="btn-resend" class="knd-access-auth-link knd-access-auth-link--btn"><?php echo t('dr.auth.resend_code', 'Resend code'); ?></button>
                    <div id="resend-cooldown" class="knd-access-resend-cooldown" style="display:none;"></div>
                </div>
                <div id="verify-alert" class="knd-access-alert" role="alert"></div>
            </div>

        </div>
    </div>
</section>

<?php if (!$embed): ?><script src="/assets/js/navigation-extend.js"></script><?php endif; ?>
<script src="/assets/js/auth.js"></script>

<?php if (!$embed) echo generateFooter(); ?>

<script>
const REDIRECT = <?php echo json_encode($redirect); ?>;
const SHOW_VERIFY = <?php echo $showVerify ? 'true' : 'false'; ?>;
const LOGIN_STORAGE_KEY = 'knd_auth_saved_login_v1';
const loginForm = document.getElementById('form-login');
const loginUsernameInput = loginForm ? loginForm.querySelector('input[name="username"]') : null;
const loginPasswordInput = loginForm ? loginForm.querySelector('input[name="password"]') : null;
const loginRememberInput = document.getElementById('remember-login');

function kndAuthAlertClass(type) {
    if (type === 'success') return 'success';
    return 'error';
}

function showAlert(msg, type) {
    const el = document.getElementById('auth-alert');
    if (!el) return;
    el.textContent = msg;
    el.className = 'knd-access-alert ' + kndAuthAlertClass(type) + ' show';
    el.style.display = 'block';
}

function showVerifyAlert(msg, type) {
    const el = document.getElementById('verify-alert');
    if (!el) return;
    el.textContent = msg;
    el.className = 'knd-access-alert ' + kndAuthAlertClass(type) + ' show';
    el.style.display = 'block';
}

function focusFirstCodeDigit(wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    const first = wrap.querySelector('.knd-access-code-digit');
    if (first) first.focus();
}

function switchToVerify() {
    const main = document.getElementById('auth-main-stack');
    const tabSwitch = document.getElementById('auth-tab-switch');
    const authAlert = document.getElementById('auth-alert');
    const verifyPanel = document.getElementById('verify-panel');
    if (main) main.style.display = 'none';
    if (tabSwitch) tabSwitch.style.display = 'none';
    if (authAlert) authAlert.style.display = 'none';
    document.getElementById('forgot-panel').style.display = 'none';
    if (verifyPanel) {
        verifyPanel.style.display = 'flex';
    }
    if (typeof window.kndAuthSyncAllCodeHiddens === 'function') {
        window.kndAuthSyncAllCodeHiddens();
    }
    focusFirstCodeDigit('verify-code-wrap');
}

if (SHOW_VERIFY) {
    switchToVerify();
}

function loadRememberedLogin() {
    if (!loginUsernameInput || !loginPasswordInput || !loginRememberInput) return;
    try {
        const raw = localStorage.getItem(LOGIN_STORAGE_KEY);
        if (!raw) return;
        const saved = JSON.parse(raw);
        if (!saved || typeof saved !== 'object') return;

        const username = typeof saved.username === 'string' ? saved.username : '';
        const password = typeof saved.password === 'string' ? saved.password : '';
        if (!username || !password) return;

        loginUsernameInput.value = username;
        loginPasswordInput.value = password;
        loginRememberInput.checked = true;
    } catch (error) {
        console.warn('Could not load remembered login data:', error);
    }
}

function saveRememberedLogin(username, password) {
    try {
        localStorage.setItem(LOGIN_STORAGE_KEY, JSON.stringify({ username, password }));
    } catch (error) {
        console.warn('Could not save remembered login data:', error);
    }
}

function clearRememberedLogin() {
    try {
        localStorage.removeItem(LOGIN_STORAGE_KEY);
    } catch (error) {
        console.warn('Could not clear remembered login data:', error);
    }
}

if (loginRememberInput) {
    loginRememberInput.addEventListener('change', function () {
        if (!this.checked) {
            clearRememberedLogin();
        }
    });
}

loadRememberedLogin();

/* ---- Login ---- */
if (loginForm) loginForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    if (typeof window.kndAuthSetButtonLoading === 'function') {
        window.kndAuthSetButtonLoading(btn, true);
    } else {
        btn.disabled = true;
    }
    const fd = new FormData(this);
    const username = loginUsernameInput ? loginUsernameInput.value.trim() : '';
    const password = loginPasswordInput ? loginPasswordInput.value : '';
    const shouldRemember = !!(loginRememberInput && loginRememberInput.checked);
    fetch('/api/auth/login.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (typeof window.kndAuthSetButtonLoading === 'function') {
                window.kndAuthSetButtonLoading(btn, false);
            } else {
                btn.disabled = false;
            }
            if (d.ok) {
                if (shouldRemember && username && password) {
                    saveRememberedLogin(username, password);
                } else {
                    clearRememberedLogin();
                }
                if (d.data && d.data.email_pending) {
                    showAlert('<?php echo t("dr.auth.email_not_verified", "Please verify your email to continue."); ?>', 'warning');
                    setTimeout(switchToVerify, 800);
                } else {
                    window.location.href = REDIRECT;
                }
            } else {
                showAlert(d.error.message, 'danger');
            }
        })
        .catch(() => {
            if (typeof window.kndAuthSetButtonLoading === 'function') {
                window.kndAuthSetButtonLoading(btn, false);
            } else {
                btn.disabled = false;
            }
            showAlert('Connection error.', 'danger');
        });
});

/* ---- Register ---- */
const formRegister = document.getElementById('form-register');
if (formRegister) formRegister.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    if (fd.get('password') !== fd.get('password_confirm')) {
        showAlert('<?php echo t("dr.auth.password_mismatch", "Passwords do not match."); ?>', 'warning');
        return;
    }
    const btn = this.querySelector('button[type="submit"]');
    if (typeof window.kndAuthSetButtonLoading === 'function') {
        window.kndAuthSetButtonLoading(btn, true);
    } else {
        btn.disabled = true;
    }
    fetch('/api/auth/register.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (typeof window.kndAuthSetButtonLoading === 'function') {
                window.kndAuthSetButtonLoading(btn, false);
            } else {
                btn.disabled = false;
            }
            if (d.ok) {
                if (d.data && d.data.email_pending) {
                    showAlert('<?php echo t("dr.auth.check_email", "Account created! Check your email for the verification code."); ?>', 'success');
                    setTimeout(switchToVerify, 1200);
                } else {
                    window.location.href = REDIRECT;
                }
            } else {
                showAlert(d.error.message, 'danger');
            }
        })
        .catch(() => {
            if (typeof window.kndAuthSetButtonLoading === 'function') {
                window.kndAuthSetButtonLoading(btn, false);
            } else {
                btn.disabled = false;
            }
            showAlert('Connection error.', 'danger');
        });
});

/* ---- Verify Email ---- */
const formVerify = document.getElementById('form-verify');
if (formVerify) formVerify.addEventListener('submit', function(e) {
    e.preventDefault();
    if (typeof window.kndAuthSyncAllCodeHiddens === 'function') {
        window.kndAuthSyncAllCodeHiddens();
    }
    const vch = document.getElementById('verify-code-hidden');
    if (!vch || String(vch.value || '').length !== 6) {
        showVerifyAlert('<?php echo t("dr.auth.invalid_code", "Please enter the 6-digit code."); ?>', 'warning');
        return;
    }
    const fd = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    if (typeof window.kndAuthSetButtonLoading === 'function') {
        window.kndAuthSetButtonLoading(btn, true);
    } else {
        btn.disabled = true;
    }
    fetch('/api/auth/verify_email.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (typeof window.kndAuthSetButtonLoading === 'function') {
                window.kndAuthSetButtonLoading(btn, false);
            } else {
                btn.disabled = false;
            }
            if (d.ok) {
                showVerifyAlert('<?php echo t("dr.auth.email_verified", "Email verified! Redirecting…"); ?>', 'success');
                setTimeout(() => { window.location.href = REDIRECT; }, 1000);
            } else {
                showVerifyAlert(d.error.message, 'danger');
            }
        })
        .catch(() => {
            if (typeof window.kndAuthSetButtonLoading === 'function') {
                window.kndAuthSetButtonLoading(btn, false);
            } else {
                btn.disabled = false;
            }
            showVerifyAlert('Connection error.', 'danger');
        });
});

/* ---- Resend Code ---- */
let resendCooldown = 0;
let resendTimer = null;

function startResendCooldown(sec) {
    resendCooldown = sec;
    const btn = document.getElementById('btn-resend');
    const cd = document.getElementById('resend-cooldown');
    if (!btn || !cd) return;
    btn.style.display = 'none';
    cd.style.display = 'block';
    function tick() {
        if (resendCooldown <= 0) {
            cd.style.display = 'none';
            btn.style.display = '';
            clearInterval(resendTimer);
            return;
        }
        cd.textContent = '<?php echo t("dr.auth.resend_wait", "Resend available in"); ?> ' + resendCooldown + 's';
        resendCooldown--;
    }
    tick();
    resendTimer = setInterval(tick, 1000);
}

const btnResend = document.getElementById('btn-resend');
if (btnResend) btnResend.addEventListener('click', function() {
    const fd = new FormData();
    fd.append('csrf_token', '<?php echo $csrfToken; ?>');
    this.disabled = true;
    fetch('/api/auth/resend_code.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            document.getElementById('btn-resend').disabled = false;
            if (d.ok) {
                showVerifyAlert('<?php echo t("dr.auth.code_resent", "New code sent! Check your inbox."); ?>', 'success');
                startResendCooldown(60);
            } else {
                showVerifyAlert(d.error.message, 'danger');
            }
        })
        .catch(() => { document.getElementById('btn-resend').disabled = false; showVerifyAlert('Connection error.', 'danger'); });
});

/* ---- Forgot Password/Username ---- */
const forgotPanel = document.getElementById('forgot-panel');
const forgotStep1 = document.getElementById('forgot-step1');
const forgotStep2 = document.getElementById('forgot-step2');
const authMainStack = document.getElementById('auth-main-stack');
const authTabSwitch = document.getElementById('auth-tab-switch');

function showForgotAlert(msg, type) {
    const el = document.getElementById('forgot-alert');
    if (!el) return;
    el.textContent = msg;
    el.className = 'knd-access-alert ' + kndAuthAlertClass(type) + ' show';
    el.style.display = 'block';
}

function switchToForgot() {
    if (authMainStack) authMainStack.style.display = 'none';
    if (authTabSwitch) authTabSwitch.style.display = 'none';
    document.getElementById('verify-panel').style.display = 'none';
    forgotPanel.style.display = 'flex';
    forgotStep1.style.display = 'block';
    forgotStep2.style.display = 'none';
    document.getElementById('forgot-alert').style.display = 'none';
    document.getElementById('forgot-email').focus();
}

function switchToLogin() {
    forgotPanel.style.display = 'none';
    document.getElementById('verify-panel').style.display = 'none';
    if (authMainStack) authMainStack.style.display = 'block';
    if (authTabSwitch) authTabSwitch.style.display = 'flex';
    document.getElementById('auth-alert').style.display = 'none';
    if (typeof window.kndAuthShowTab === 'function') {
        window.kndAuthShowTab('login');
    }
}

const linkForgot = document.getElementById('link-forgot');
if (linkForgot) linkForgot.addEventListener('click', function(e) {
    e.preventDefault();
    switchToForgot();
});

const linkToRegister = document.getElementById('link-to-register');
if (linkToRegister) {
    linkToRegister.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof window.kndAuthShowTab === 'function') {
            window.kndAuthShowTab('register');
        }
    });
}

const linkToLogin = document.getElementById('link-to-login');
if (linkToLogin) {
    linkToLogin.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof window.kndAuthShowTab === 'function') {
            window.kndAuthShowTab('login');
        }
    });
}

const linkBackLogin = document.getElementById('link-back-login');
if (linkBackLogin) linkBackLogin.addEventListener('click', function(e) {
    e.preventDefault();
    switchToLogin();
});

document.getElementById('btn-forgot-username').addEventListener('click', function() {
    const email = document.getElementById('forgot-email').value.trim();
    if (!email) { showForgotAlert('<?php echo t("dr.auth.enter_email", "Please enter your email."); ?>', 'warning'); return; }
    this.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', '<?php echo $csrfToken; ?>');
    fd.append('email', email);
    fetch('/api/auth/forgot_username.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            document.getElementById('btn-forgot-username').disabled = false;
            if (d.ok) {
                showForgotAlert('<?php echo t("dr.auth.username_sent", "If an account exists with that email, the username was sent. Check your inbox."); ?>', 'success');
            } else {
                showForgotAlert(d.error.message, 'danger');
            }
        })
        .catch(() => { document.getElementById('btn-forgot-username').disabled = false; showForgotAlert('Connection error.', 'danger'); });
});

document.getElementById('btn-forgot-password').addEventListener('click', function() {
    const email = document.getElementById('forgot-email').value.trim();
    if (!email) { showForgotAlert('<?php echo t("dr.auth.enter_email", "Please enter your email."); ?>', 'warning'); return; }
    this.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', '<?php echo $csrfToken; ?>');
    fd.append('email', email);
    fetch('/api/auth/forgot_password.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            document.getElementById('btn-forgot-password').disabled = false;
            if (d.ok) {
                showForgotAlert('<?php echo t("dr.auth.reset_code_sent", "If an account exists with that email, a reset code was sent. Check your inbox."); ?>', 'success');
                document.getElementById('reset-email-hidden').value = email;
                setTimeout(function() {
                    forgotStep1.style.display = 'none';
                    forgotStep2.style.display = 'block';
                    document.getElementById('forgot-alert').style.display = 'none';
                    if (typeof window.kndAuthClearCodeWrap === 'function') {
                        window.kndAuthClearCodeWrap('reset-code-wrap');
                    }
                    focusFirstCodeDigit('reset-code-wrap');
                }, 1500);
            } else {
                showForgotAlert(d.error.message, 'danger');
            }
        })
        .catch(() => { document.getElementById('btn-forgot-password').disabled = false; showForgotAlert('Connection error.', 'danger'); });
});

const formResetPassword = document.getElementById('form-reset-password');
if (formResetPassword) formResetPassword.addEventListener('submit', function(e) {
    e.preventDefault();
    if (typeof window.kndAuthSyncAllCodeHiddens === 'function') {
        window.kndAuthSyncAllCodeHiddens();
    }
    const codeEl = document.getElementById('reset-code-hidden');
    if (!codeEl || String(codeEl.value || '').length !== 6) {
        showForgotAlert('<?php echo t("dr.auth.invalid_code", "Please enter the 6-digit code."); ?>', 'warning');
        return;
    }
    const fd = new FormData(this);
    if (fd.get('password') !== fd.get('password_confirm')) {
        showForgotAlert('<?php echo t("dr.auth.password_mismatch", "Passwords do not match."); ?>', 'warning');
        return;
    }
    const btn = this.querySelector('button[type="submit"]');
    if (typeof window.kndAuthSetButtonLoading === 'function') {
        window.kndAuthSetButtonLoading(btn, true);
    } else {
        btn.disabled = true;
    }
    fetch('/api/auth/reset_password.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (typeof window.kndAuthSetButtonLoading === 'function') {
                window.kndAuthSetButtonLoading(btn, false);
            } else {
                btn.disabled = false;
            }
            if (d.ok) {
                showForgotAlert('<?php echo t("dr.auth.password_reset_success", "Password reset! You can now log in with your new password."); ?>', 'success');
                setTimeout(switchToLogin, 2000);
            } else {
                showForgotAlert(d.error.message, 'danger');
            }
        })
        .catch(() => {
            if (typeof window.kndAuthSetButtonLoading === 'function') {
                window.kndAuthSetButtonLoading(btn, false);
            } else {
                btn.disabled = false;
            }
            showForgotAlert('Connection error.', 'danger');
        });
});
</script>

<?php
if ($embed) {
    echo '</div></body></html>';
} else {
    echo generateScripts();
}
