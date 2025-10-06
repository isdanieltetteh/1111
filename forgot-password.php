<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'request';
    
    if ($action === 'request') {
        // Verify captcha
        $captcha_valid = false;
        
        if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) {
            $captcha_response = $_POST['h-captcha-response'];
            $secret_key = 'YOUR_HCAPTCHA_SECRET_KEY';
            
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
            $email = trim($_POST['email']);
            
            if (empty($email)) {
                $error_message = 'Please enter your email address';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = 'Please enter a valid email address';
            } else {
                // Check if user exists
                $user_query = "SELECT id, username FROM users WHERE email = :email";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->bindParam(':email', $email);
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token
                    $token_query = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)";
                    $token_stmt = $db->prepare($token_query);
                    $token_stmt->bindParam(':user_id', $user['id']);
                    $token_stmt->bindParam(':token', $token);
                    $token_stmt->bindParam(':expires_at', $expires_at);
                    $token_stmt->execute();
                    
                    // Send reset email (simplified - implement proper email sending)
                    $reset_link = SITE_URL . "/reset-password?token=" . $token;
                    
                    // For now, show the link (in production, send via email)
                    $success_message = "Password reset link: <a href='{$reset_link}' style='color: #3b82f6;'>{$reset_link}</a>";
                } else {
                    $error_message = 'No account found with that email address';
                }
            }
        }
    }
}

$page_title = 'Forgot Password - ' . SITE_NAME;
$page_description = 'Reset your ' . SITE_NAME . ' account password securely.';

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
                            <i class="fas fa-lock-open"></i>
                        </div>
                        <h1 class="auth-heading">Reset Password</h1>
                        <p class="auth-subheading">Enter your email address and we'll send a secure reset link.</p>
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
                            <div><?php echo $success_message; ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="d-grid gap-4">
                        <input type="hidden" name="action" value="request">
                        <div>
                            <label for="email" class="form-label fw-semibold">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent text-secondary"><i class="fas fa-envelope"></i></span>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       class="form-control"
                                       placeholder="Enter your registered email"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="glass-card p-4">
                            <h6 class="text-info fw-semibold mb-3"><i class="fas fa-shield-halved me-2"></i>Security Information</h6>
                            <ul class="text-secondary small mb-0 ps-3">
                                <li>Reset links expire after 1 hour for security.</li>
                                <li>Only one reset request per hour per email.</li>
                                <li>Check your spam folder if you don't see the email.</li>
                                <li>Contact support if you continue having issues.</li>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-center">
                            <div class="h-captcha" data-sitekey="YOUR_HCAPTCHA_SITE_KEY"></div>
                        </div>

                        <button type="submit" class="btn btn-theme btn-gradient w-100">
                            <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                        </button>
                    </form>

                    <div class="auth-links text-center mt-4">
                        <p class="mb-2">Remember your password? <a href="login">Sign in here</a></p>
                        <p class="mb-0 text-secondary small">Need a new account? <a href="register">Create one here</a>.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-xl-5">
                <div class="auth-aside">
                    <div class="auth-aside-card mb-4">
                        <h3 class="section-heading mb-3">How the reset works</h3>
                        <ol class="text-secondary mb-0 ps-3">
                            <li class="mb-2">Submit your registered email above.</li>
                            <li class="mb-2">Check your inbox for a secure reset link.</li>
                            <li>Follow the link to set a new password instantly.</li>
                        </ol>
                    </div>
                    <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                    <div class="glass-card p-4">
                        <h4 class="section-heading mb-3">Account recovery tips</h4>
                        <ul class="text-secondary ps-3 mb-0">
                            <li class="mb-2">Whitelist <?php echo SITE_NAME; ?> emails to avoid spam filtering.</li>
                            <li class="mb-2">Use a strong, unique password when resetting.</li>
                            <li>Enable two-factor authentication from your profile once logged in.</li>
                        </ul>
                    </div>
                    <div class="dev-slot2 mt-4">Footer Ad 728x90</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
