<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';
$valid_token = false;
$user_data = null;

// Check token validity
$token = $_GET['token'] ?? '';
if (!empty($token)) {
    $token_query = "SELECT prt.*, u.username, u.email 
                   FROM password_reset_tokens prt
                   JOIN users u ON prt.user_id = u.id
                   WHERE prt.token = :token AND prt.expires_at > NOW() AND prt.used_at IS NULL";
    $token_stmt = $db->prepare($token_query);
    $token_stmt->bindParam(':token', $token);
    $token_stmt->execute();
    $user_data = $token_stmt->fetch(PDO::FETCH_ASSOC);
    
    $valid_token = $user_data !== false;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
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
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all fields';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Password must be at least 6 characters long';
        } else {
            try {
                $db->beginTransaction();
                
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':user_id', $user_data['user_id']);
                $update_stmt->execute();
                
                // Mark token as used
                $mark_used_query = "UPDATE password_reset_tokens SET used_at = NOW() WHERE token = :token";
                $mark_used_stmt = $db->prepare($mark_used_query);
                $mark_used_stmt->bindParam(':token', $token);
                $mark_used_stmt->execute();
                
                $db->commit();
                $success_message = 'Password reset successfully! You can now login with your new password.';
                header('refresh:3;url=login');
                
            } catch (Exception $e) {
                $db->rollback();
                $error_message = 'Error resetting password. Please try again.';
            }
        }
    }
}

$page_title = 'Reset Password - ' . SITE_NAME;
$page_description = 'Reset your ' . SITE_NAME . ' account password.';

$additional_head = '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';

include 'includes/header.php';
?>


<div class="auth-shell">
    <div class="container">
        <div class="row g-5 align-items-center justify-content-center">
            <div class="col-lg-5 col-xl-4">
                <div class="auth-card">
                    <?php if ($valid_token): ?>
                        <div class="text-center mb-4">
                            <div class="auth-logo">
                                <i class="fas fa-lock"></i>
                            </div>
                            <h1 class="auth-heading">Reset your password</h1>
                            <p class="auth-subheading">Create a new password for <strong><?php echo htmlspecialchars($user_data['username'] ?? ''); ?></strong>.</p>
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
                                    <small class="d-block mt-1 text-secondary"><i class="fas fa-spinner fa-spin me-2"></i>Redirecting to login...</small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="d-grid gap-3">
                            <div>
                                <label for="new_password" class="form-label fw-semibold">New password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-secondary"><i class="fas fa-lock"></i></span>
                                    <input type="password"
                                           id="new_password"
                                           name="new_password"
                                           class="form-control"
                                           placeholder="Enter your new password (min 6 characters)"
                                           required>
                                </div>
                            </div>
                            <div>
                                <label for="confirm_password" class="form-label fw-semibold">Confirm password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-secondary"><i class="fas fa-lock"></i></span>
                                    <input type="password"
                                           id="confirm_password"
                                           name="confirm_password"
                                           class="form-control"
                                           placeholder="Confirm your new password"
                                           required>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center">
                                <div class="h-captcha" data-sitekey="YOUR_HCAPTCHA_SITE_KEY"></div>
                            </div>
                            <button type="submit" class="btn btn-theme btn-gradient w-100"><i class="fas fa-key me-2"></i>Update password</button>
                        </form>
                        <div class="auth-links text-center mt-4">
                            <a href="login" class="d-inline-flex align-items-center gap-2"><i class="fas fa-arrow-left"></i><span>Back to login</span></a>
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-4">
                            <div class="auth-logo">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h1 class="auth-heading">Invalid or expired link</h1>
                            <p class="auth-subheading">This reset link is no longer valid. Request a new one to continue.</p>
                        </div>
                        <div class="d-grid gap-3">
                            <a href="forgot-password" class="btn btn-theme btn-gradient"><i class="fas fa-key me-2"></i>Request new reset link</a>
                            <a href="login" class="btn btn-outline-light"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 col-xl-4">
                <div class="auth-side-card">
                    <h2 class="h4 text-white mb-3">Account safety tips</h2>
                    <ul class="text-muted small mb-4 ps-3 d-grid gap-2">
                        <li>Use a unique password for <?php echo SITE_NAME; ?> and update it regularly.</li>
                        <li>Enable two-factor authentication from your account settings.</li>
                        <li>Never share reset links — they provide direct access to your account.</li>
                    </ul>
                    <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3">
                        <h3 class="h6 text-white mb-2">Need help?</h3>
                        <p class="text-muted small mb-3">Our trust & safety team can assist if you can’t access your email.</p>
                        <a href="support-tickets.php" class="btn btn-outline-light btn-sm w-100"><i class="fas fa-life-ring me-2"></i>Contact support</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;
        
        if (newPassword && confirmPassword && newPassword !== confirmPassword) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
