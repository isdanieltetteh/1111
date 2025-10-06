<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

$auth = new Auth();
$security = new SecurityManager((new Database())->getConnection());
$error_message = '';
$success_message = '';
$registration_success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Verify captcha
    $captcha_valid = false;
    
    if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) {
        $captcha_response = $_POST['h-captcha-response'];
        $secret_key = HCAPTCHA_SECRET_KEY;
        
        $verify_url = 'https://hcaptcha.com/siteverify';
        $data = [
            'secret' => $secret_key,
            'response' => $captcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($verify_url, false, $context);
        $response = json_decode($result, true);
        
        $captcha_valid = $response['success'] ?? false;
    }
    
    if (!$captcha_valid) {
        $error_message = 'Please complete the captcha verification';
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $newsletter_preferences = $_POST['newsletter_preferences'] ?? [];
        
        // Additional security validation
        $ip_check = $security->canRegisterFromIP($ip_address);
        if (!$ip_check['allowed']) {
            $error_message = $ip_check['reason'];
        } elseif (empty($username) || empty($email) || empty($password)) {
            $error_message = 'Please fill in all required fields';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error_message = 'Password must be at least 6 characters long';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address';
        } elseif (empty($_POST['privacy_accepted'])) {
            $error_message = 'You must accept the Privacy Policy and Terms of Service';
        } else {
            $result = $auth->register($username, $email, $password, $newsletter_preferences);
            
            if ($result['success']) {
                $registration_success = true;
                $success_message = $result['message'];
                $_POST = [];
            } else {
                $error_message = $result['message'];
            }
        }
    }
}

// Handle referral code from URL
$referral_code = $_GET['ref'] ?? '';
$referrer_id = null;

if (!empty($referral_code)) {
    $database = new Database();
    $db = $database->getConnection();
    
    $referrer_query = "SELECT id, username FROM users WHERE referral_code = :referral_code AND is_banned = 0";
    $referrer_stmt = $db->prepare($referrer_query);
    $referrer_stmt->bindParam(':referral_code', $referral_code);
    $referrer_stmt->execute();
    $referrer = $referrer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($referrer) {
        $referrer_id = $referrer['id'];
    }
}

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard');
    exit();
}

$page_title = 'Create Account - ' . SITE_NAME;
$page_description = 'Join ' . SITE_NAME . ' to discover trusted crypto earning sites, write reviews, and earn rewards.';

$additional_head = '
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
';

include 'includes/header.php';
?>

<div class="auth-shell">
    <div class="container">
        <div class="row g-5 align-items-start justify-content-center">
            <div class="col-lg-7 col-xl-6">
                <div class="auth-card">
                    <?php if ($registration_success): ?>
                        <div class="text-center">
                            <div class="success-icon"><i class="fas fa-check"></i></div>
                            <h1 class="auth-heading mb-2">Account Created!</h1>
                            <p class="auth-subheading mb-4">Welcome to <?php echo SITE_NAME; ?>. Your account is ready and you can start exploring verified earning opportunities.</p>
                            <div class="countdown-chip mb-4">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Redirecting to login in <span id="countdown">10</span> seconds...</span>
                            </div>
                            <a href="login" class="btn btn-theme btn-gradient">
                                <i class="fas fa-right-to-bracket me-2"></i> Go to Login Now
                            </a>
                        </div>
                        <script>
                            let seconds = 10;
                            const countdownElement = document.getElementById('countdown');
                            const interval = setInterval(() => {
                                seconds--;
                                if (countdownElement) {
                                    countdownElement.textContent = seconds;
                                }
                                if (seconds <= 0) {
                                    clearInterval(interval);
                                    window.location.href = 'login';
                                }
                            }, 1000);
                        </script>
                    <?php else: ?>
                        <div class="text-center mb-4">
                            <div class="auth-logo">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h1 class="auth-heading">Create Your Account</h1>
                            <p class="auth-subheading">Join <?php echo SITE_NAME; ?> to access trusted crypto listings and community rewards.</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert-glass alert-danger mb-4">
                                <span class="icon"><i class="fas fa-exclamation-circle"></i></span>
                                <div><?php echo htmlspecialchars($error_message); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($referrer_id): ?>
                            <input type="hidden" name="referrer_id" value="<?php echo $referrer_id; ?>">
                            <div class="alert-glass alert-success mb-4">
                                <span class="icon"><i class="fas fa-user-friends"></i></span>
                                <div>You were referred by <strong><?php echo htmlspecialchars($referrer['username'] ?? ''); ?></strong></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="d-grid gap-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label fw-semibold">Username *</label>
                                    <input type="text"
                                           id="username"
                                           name="username"
                                           class="form-control"
                                           placeholder="Choose a unique username"
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                           oninput="checkUsernameAvailability()"
                                           pattern="[a-zA-Z0-9_]{3,20}"
                                           title="Username must be 3-20 characters and contain only letters, numbers, and underscores"
                                           required>
                                    <div id="usernameStatus" class="mt-2 small text-secondary"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email *</label>
                                    <input type="email"
                                           id="email"
                                           name="email"
                                           class="form-control"
                                           placeholder="you@example.com"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           required>
                                </div>
                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-semibold">Password *</label>
                                    <input type="password"
                                           id="password"
                                           name="password"
                                           class="form-control"
                                           placeholder="Create a strong password"
                                           required>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label fw-semibold">Confirm Password *</label>
                                    <input type="password"
                                           id="confirm_password"
                                           name="confirm_password"
                                           class="form-control"
                                           placeholder="Re-enter your password"
                                           required>
                                </div>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Newsletter Preferences (Optional)</label>
                                <p class="text-secondary small mb-3">Stay updated with the latest crypto earning opportunities and platform news.</p>
                                <div class="row row-cols-1 row-cols-sm-2 g-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="scam_alerts" name="newsletter_preferences[]" value="scam_alerts" <?php echo in_array('scam_alerts', $_POST['newsletter_preferences'] ?? [], true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="scam_alerts">🚨 Scam Alerts</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="new_sites" name="newsletter_preferences[]" value="new_sites" <?php echo in_array('new_sites', $_POST['newsletter_preferences'] ?? [], true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="new_sites">🆕 New Sites Added</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="weekly_digest" name="newsletter_preferences[]" value="weekly_digest" <?php echo in_array('weekly_digest', $_POST['newsletter_preferences'] ?? [], true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="weekly_digest">📊 Weekly Digest</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="high_paying" name="newsletter_preferences[]" value="high_paying" <?php echo in_array('high_paying', $_POST['newsletter_preferences'] ?? [], true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="high_paying">💰 High Paying Sites</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="platform_updates" name="newsletter_preferences[]" value="platform_updates" <?php echo in_array('platform_updates', $_POST['newsletter_preferences'] ?? [], true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="platform_updates">🔔 Platform Updates</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="earning_tips" name="newsletter_preferences[]" value="earning_tips" <?php echo in_array('earning_tips', $_POST['newsletter_preferences'] ?? [], true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="earning_tips">💡 Earning Tips</label>
                                    </div>
                                </div>
                            </div>

                            <div class="glass-card p-4">
                                <h6 class="text-warning fw-semibold mb-3"><i class="fas fa-shield-halved me-2"></i>Account Security Notice</h6>
                                <ul class="text-secondary small mb-0 ps-3">
                                    <li>Only one account per person is allowed.</li>
                                    <li>Multiple accounts from the same IP may result in suspension.</li>
                                    <li>Temporary or disposable email addresses are not permitted.</li>
                                    <li>Violations may result in permanent account bans.</li>
                                </ul>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="privacy_accepted" id="privacy_accepted" required <?php echo isset($_POST['privacy_accepted']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="privacy_accepted">
                                    I agree to the <a href="privacy" target="_blank">Privacy Policy</a> and <a href="terms" target="_blank">Terms of Service</a>.
                                </label>
                            </div>

                            <div class="d-flex justify-content-center">
                                <div class="h-captcha" data-sitekey="<?php echo HCAPTCHA_SITE_KEY; ?>"></div>
                            </div>

                            <button type="submit" class="btn btn-theme btn-gradient w-100">
                                <i class="fas fa-user-plus me-2"></i> Create Account
                            </button>
                        </form>

                        <div class="auth-links text-center mt-4">
                            <p class="mb-2">Already have an account? <a href="login">Sign in here</a></p>
                            <p class="mb-0 text-secondary small">Need help? Visit our <a href="faq">FAQ</a> or <a href="support-tickets">open a support ticket</a>.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 col-xl-4">
                <div class="auth-aside">
                    <div class="auth-aside-card mb-4">
                        <h3 class="section-heading mb-3">Why creators register</h3>
                        <div class="d-grid gap-3">
                            <div class="auth-bullet"><i class="fas fa-rocket"></i>Boost your site visibility with curated promotions.</div>
                            <div class="auth-bullet"><i class="fas fa-star"></i>Collect community reviews and earn trust badges.</div>
                            <div class="auth-bullet"><i class="fas fa-wallet"></i>Track credits, payouts, and referral performance in one dashboard.</div>
                        </div>
                    </div>
                    <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                    <div class="glass-card p-4">
                        <h4 class="section-heading mb-3">New member perks</h4>
                        <ul class="text-secondary ps-3 mb-0">
                            <li class="mb-2">Automated backlink monitoring with alerts.</li>
                            <li class="mb-2">Priority review options for faster approvals.</li>
                            <li>Access to exclusive monetization tips from top earners.</li>
                        </ul>
                    </div>
                    <div class="dev-slot2 mt-4">Footer Ad 728x90</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    async function checkUsernameAvailability() {
        const usernameInput = document.getElementById('username');
        const statusDiv = document.getElementById('usernameStatus');

        if (!usernameInput || !statusDiv) {
            return;
        }

        const username = usernameInput.value;

        if (username.length < 3) {
            statusDiv.innerHTML = '';
            return;
        }

        if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
            statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i> Username must be 3-20 characters (letters, numbers, underscore only)</span>';
            return;
        }

        statusDiv.innerHTML = '<span class="text-secondary"><i class="fas fa-spinner fa-spin me-1"></i> Checking availability...</span>';

        try {
            const response = await fetch('ajax/check-username.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({username})
            });

            const data = await response.json();

            if (data.available) {
                statusDiv.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i> Username available</span>';
            } else {
                statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i> Username already taken</span>';
            }
        } catch (error) {
            statusDiv.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i> Error checking username</span>';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
