<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/MailService.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
$mailer = MailService::getInstance();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'request';
    
    if ($action === 'request') {
        // Verify captcha
        $captcha_valid = false;
        
        if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) {
            $captcha_response = $_POST['h-captcha-response'];
            $secret_key = defined('HCAPTCHA_SECRET_KEY') ? HCAPTCHA_SECRET_KEY : '';

            if (!empty($secret_key)) {
                $verify_url = 'https://hcaptcha.com/siteverify';
                $payload = [
                    'secret' => $secret_key,
                    'response' => $captcha_response,
                ];

                if (!empty($_SERVER['REMOTE_ADDR'])) {
                    $payload['remoteip'] = $_SERVER['REMOTE_ADDR'];
                }

                $result = false;

                if (function_exists('curl_init')) {
                    $ch = curl_init($verify_url);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query($payload),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                    ]);

                    $result = curl_exec($ch);

                    if ($result === false) {
                        error_log('hCaptcha verification failed: ' . curl_error($ch));
                    }

                    curl_close($ch);
                }

                if ($result === false) {
                    $context = stream_context_create([
                        'http' => [
                            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                            'method' => 'POST',
                            'content' => http_build_query($payload)
                        ]
                    ]);

                    $result = @file_get_contents($verify_url, false, $context);
                }

                if ($result !== false) {
                    $response = json_decode($result, true);
                    $captcha_valid = $response['success'] ?? false;
                }
            }
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
                    
                    $reset_link = SITE_URL . "/reset-password?token=" . $token;

                    $recipient = [$email => $user['username'] ?? $email];
                    $html_body = '<p>Hi ' . htmlspecialchars($user['username'] ?? 'there', ENT_QUOTES, 'UTF-8') . ',</p>' .
                                 '<p>You requested to reset your password for ' . SITE_NAME . '. Click the button below to create a new one.</p>' .
                                 '<p><a href="' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '" style="background:#2563eb;color:#fff;padding:12px 18px;border-radius:6px;text-decoration:none;">Reset Password</a></p>' .
                                 '<p>If you did not request this, please ignore this email.</p>' .
                                 '<p>Thanks,<br>' . SITE_NAME . ' Support</p>';

                    $mailer->send(
                        $recipient,
                        '[' . SITE_NAME . '] Password reset instructions',
                        $html_body,
                        [
                            'text' => "Reset your password using this link: {$reset_link}\nIf you did not request this, you can ignore this email.",
                        ]
                    );

                    $success_message = 'We sent a password reset link to your email address. Please check your inbox and spam folder.';
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
                            <div class="h-captcha" data-sitekey="<?php echo htmlspecialchars(defined('HCAPTCHA_SITE_KEY') ? HCAPTCHA_SITE_KEY : '', ENT_QUOTES, 'UTF-8'); ?>"></div>
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
