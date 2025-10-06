<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

$auth = new Auth();
$security = new SecurityManager((new Database())->getConnection());
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Check if IP is blocked before processing
    if ($security->isIPBlocked($ip_address)) {
        $error_message = 'Access denied from this IP address';
    } else {
        // Verify captcha
        $captcha_valid = false;
        
        if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) {
            $captcha_response = $_POST['h-captcha-response'];
            $secret_key = HCAPTCHA_SECRET_KEY;
            
            $verify_url = 'https://hcaptcha.com/siteverify';
            $data = [
                'secret'   => $secret_key,
                'response' => $captcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];
            
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data)
                ]
            ];
            
            $context  = stream_context_create($options);
            $result   = file_get_contents($verify_url, false, $context);
            $response = json_decode($result, true);
            
            $captcha_valid = $response['success'] ?? false;
        }
        
        if (!$captcha_valid) {
            $error_message = 'Please complete the captcha verification';
        } else {
            $username    = trim($_POST['username']);
            $password    = $_POST['password'];
            $remember_me = isset($_POST['remember_me']);
            
            if (!empty($username) && !empty($password)) {
                $result = $auth->login($username, $password, $remember_me);
                
                if ($result['success']) {
                    $success_message = $result['message'];
                    // Redirect after successful login
                    $redirect_url = $_GET['redirect'] ?? ($_SESSION['is_admin'] ? 'admin/dashboard.php' : 'dashboard');
                    header("refresh:1;url=" . htmlspecialchars($redirect_url));
                } else {
                    $error_message = $result['message'];
                }
            } else {
                $error_message = 'Please fill in all fields';
            }
        }
    }
}

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard');
    exit();
}

$page_title = 'Login - ' . SITE_NAME;
$page_description = 'Login to your ' . SITE_NAME . ' account to manage your reviews, submissions, and earnings.';

$additional_head = '
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
';

include 'includes/header.php';
?>

<div class="auth-shell">
    <div class="container">
        <div class="row g-5 align-items-center justify-content-center">
            <div class="col-lg-5 col-xl-4">
                <div class="auth-card">
                    <div class="text-center mb-4">
                        <div class="auth-logo">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <h1 class="auth-heading">Welcome Back</h1>
                        <p class="auth-subheading">Sign in to manage your listings, credits, and reviews.</p>
                    </div>
                    <?php if ($error_message): ?>
                        <div class="alert-glass alert-danger mb-4">
                            <span class="icon"><i class="fas fa-exclamation-circle"></i></span>
                            <div><?php echo htmlspecialchars($error_message); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert-glass alert-success mb-4">
                            <span class="icon"><i class="fas fa-check-circle"></i></span>
                            <div>
                                <div><?php echo htmlspecialchars($success_message); ?></div>
                                <small class="d-block mt-1 text-secondary"><i class="fas fa-spinner fa-spin me-2"></i>Redirecting to dashboard...</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="d-grid gap-3">
                        <div>
                            <label for="username" class="form-label fw-semibold">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent text-secondary"><i class="fas fa-user"></i></span>
                                <input type="text"
                                       id="username"
                                       name="username"
                                       class="form-control"
                                       placeholder="Enter your username or email"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                        <div>
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent text-secondary"><i class="fas fa-lock"></i></span>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="form-control"
                                       placeholder="Enter your password"
                                       required>
                            </div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="remember_me">Remember me for 30 days</label>
                            </div>
                            <a href="forgot-password" class="text-info small"><i class="fas fa-key me-2"></i>Forgot password?</a>
                        </div>
                        <div class="d-flex justify-content-center">
                            <div class="h-captcha" data-sitekey="<?php echo HCAPTCHA_SITE_KEY; ?>"></div>
                        </div>
                        <button type="submit" class="btn btn-theme btn-gradient w-100">
                            <i class="fas fa-right-to-bracket me-2"></i> Sign In Securely
                        </button>
                    </form>

                    <div class="auth-links text-center mt-4">
                        <p class="mb-2">Don't have an account? <a href="register">Create one here</a></p>
                        <p class="mb-0 text-secondary small">By signing in you agree to our <a href="terms">Terms</a> and <a href="privacy">Privacy Policy</a>.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-xl-5">
                <div class="auth-aside">
                    <div class="auth-aside-card mb-4">
                        <h3 class="section-heading mb-3">Why members log in daily</h3>
                        <div class="d-grid gap-3">
                            <div class="auth-bullet"><i class="fas fa-chart-line"></i>Track promotion performance and credit spend in real time.</div>
                            <div class="auth-bullet"><i class="fas fa-bell"></i>Receive instant alerts for reviews, backlinks, and approvals.</div>
                            <div class="auth-bullet"><i class="fas fa-gem"></i>Unlock premium placements and community trust boosts.</div>
                        </div>
                    </div>
                    <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                    <div class="glass-card p-4">
                        <h4 class="section-heading mb-3">Security Tips</h4>
                        <ul class="text-secondary ps-3 mb-0">
                            <li class="mb-2">Use a strong password unique to <?php echo SITE_NAME; ?>.</li>
                            <li class="mb-2">Enable device-based approvals from your profile.</li>
                            <li>Always verify official emails before clicking links.</li>
                        </ul>
                    </div>
                    <div class="dev-slot2 mt-4">Footer Ad 728x90</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
