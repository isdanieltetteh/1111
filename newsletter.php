<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'subscribe';
    
    if ($action === 'subscribe') {
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
            $preferences = $_POST['preferences'] ?? [];
            
            if (empty($email)) {
                $error_message = 'Please enter your email address';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = 'Please enter a valid email address';
            } else {
                // Check if already subscribed
                $check_query = "SELECT id FROM newsletter_subscriptions WHERE email = :email";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':email', $email);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    $error_message = 'This email is already subscribed to our newsletter';
                } else {
                    // Generate verification token
                    $verification_token = bin2hex(random_bytes(32));
                    
                    // Insert subscription
                    $insert_query = "INSERT INTO newsletter_subscriptions (email, preferences, verification_token) 
                                   VALUES (:email, :preferences, :verification_token)";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':email', $email);
                    $insert_stmt->bindParam(':preferences', json_encode($preferences));
                    $insert_stmt->bindParam(':verification_token', $verification_token);
                    
                    if ($insert_stmt->execute()) {
                        $success_message = 'Successfully subscribed! Please check your email to verify your subscription.';
                        // In production, send verification email here
                    } else {
                        $error_message = 'Error subscribing to newsletter. Please try again.';
                    }
                }
            }
        }
    }
}

$page_title = 'Newsletter Subscription - ' . SITE_NAME;
$page_description = 'Subscribe to our newsletter for crypto earning updates, scam alerts, and new site notifications.';

$additional_head = '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';

include 'includes/header.php';
?>


<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 text-center animate-fade-in" data-aos="fade-up">
                <div class="mx-auto mb-4 rounded-circle bg-primary bg-opacity-25 text-primary d-flex align-items-center justify-content-center" style="width: 90px; height: 90px;">
                    <i class="fas fa-envelope-open-text fa-2x"></i>
                </div>
                <h1 class="text-white fw-bold mb-2">Join the Insider Wire</h1>
                <p class="text-muted mb-0">Weekly highlights on legit faucets, scam alerts, and platform drops.</p>
            </div>
            <div class="dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="py-5">
        <div class="container" data-aos="fade-up" data-aos-delay="100">
            <div class="row g-4 align-items-start">
                <div class="col-12 col-xl-7">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <h2 class="h4 text-white mb-3">Subscribe for curated alpha</h2>
                        <p class="text-muted small mb-4">Hand-picked discoveries, campaign boosts, and trust signals delivered straight to your inbox.</p>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success mb-4"><?php echo htmlspecialchars($success_message); ?></div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($error_message); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="d-grid gap-4">
                            <div>
                                <label for="email" class="form-label">Email address</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="fas fa-at"></i></span>
                                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required>
                                </div>
                            </div>

                            <div>
                                <span class="text-muted text-uppercase small fw-semibold">Pick your digests</span>
                                <div class="row g-3 mt-2 row-cols-1 row-cols-md-2">
                                    <div class="col">
                                        <div class="preference-card rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100 p-4"
                                             onclick="togglePreference(this)">
                                            <input type="checkbox" class="preference-checkbox d-none" name="preferences[]" value="new_sites">
                                            <div class="d-flex align-items-center gap-3 mb-2">
                                                <span class="rounded-circle bg-primary bg-opacity-25 text-primary d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="fas fa-fire"></i>
                                                </span>
                                                <div>
                                                    <h3 class="h6 text-white mb-1">New site drops</h3>
                                                    <p class="text-muted small mb-0">Fresh verified faucets and shorteners.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="preference-card rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100 p-4"
                                             onclick="togglePreference(this)">
                                            <input type="checkbox" class="preference-checkbox d-none" name="preferences[]" value="promotions">
                                            <div class="d-flex align-items-center gap-3 mb-2">
                                                <span class="rounded-circle bg-success bg-opacity-25 text-success d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="fas fa-bullhorn"></i>
                                                </span>
                                                <div>
                                                    <h3 class="h6 text-white mb-1">Promotions & boosts</h3>
                                                    <p class="text-muted small mb-0">Campaign offers, discount codes, spotlight slots.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="preference-card rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100 p-4"
                                             onclick="togglePreference(this)">
                                            <input type="checkbox" class="preference-checkbox d-none" name="preferences[]" value="scam_alerts">
                                            <div class="d-flex align-items-center gap-3 mb-2">
                                                <span class="rounded-circle bg-danger bg-opacity-25 text-danger d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="fas fa-triangle-exclamation"></i>
                                                </span>
                                                <div>
                                                    <h3 class="h6 text-white mb-1">Scam alerts</h3>
                                                    <p class="text-muted small mb-0">Dead sites, blacklists, and trust incidents.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="preference-card rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100 p-4"
                                             onclick="togglePreference(this)">
                                            <input type="checkbox" class="preference-checkbox d-none" name="preferences[]" value="earning_guides">
                                            <div class="d-flex align-items-center gap-3 mb-2">
                                                <span class="rounded-circle bg-info bg-opacity-25 text-info d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="fas fa-graduation-cap"></i>
                                                </span>
                                                <div>
                                                    <h3 class="h6 text-white mb-1">Earning guides</h3>
                                                    <p class="text-muted small mb-0">Advanced tactics to maximize claim yields.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-4 border border-success border-opacity-25 bg-success bg-opacity-10 p-4">
                                <h3 class="h6 text-white mb-3"><i class="fas fa-gift me-2"></i>Subscriber perks</h3>
                                <ul class="text-muted small mb-0 ps-3 d-grid gap-2">
                                    <li>Early access to premium listing opportunities.</li>
                                    <li>Exclusive campaign credits and seasonal bonuses.</li>
                                    <li>Priority alerts on compromised or dead projects.</li>
                                    <li>Weekly sentiment and performance snapshots.</li>
                                </ul>
                            </div>

                            <div class="text-center">
                                <div class="d-inline-flex">
                                    <div class="h-captcha" data-sitekey="YOUR_HCAPTCHA_SITE_KEY"></div>
                                </div>
                            </div>

                            <button type="submit" name="action" value="subscribe" class="btn btn-theme btn-gradient btn-lg">
                                <i class="fas fa-bell me-2"></i>Subscribe to newsletter
                            </button>

                            <p class="text-muted small text-center mb-0"><i class="fas fa-shield-halved me-1"></i>We respect your privacy. Unsubscribe anytime.</p>
                        </form>
                    </div>
                </div>
                <div class="col-12 col-xl-5">
                    <div class="sticky-lg-top" style="top: 100px;">
                        <div class="glass-card p-4 p-lg-5 mb-4">
                            <h3 class="h6 text-white text-uppercase mb-3">What’s inside</h3>
                            <div class="d-grid gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="rounded-circle bg-primary bg-opacity-25 text-primary d-inline-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <i class="fas fa-chart-line"></i>
                                    </span>
                                    <div>
                                        <h4 class="h6 text-white mb-1">Performance digests</h4>
                                        <p class="text-muted small mb-0">Weekly snapshot of top converting campaigns.</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-3">
                                    <span class="rounded-circle bg-warning bg-opacity-25 text-warning d-inline-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <div>
                                        <h4 class="h6 text-white mb-1">Security watch</h4>
                                        <p class="text-muted small mb-0">Immediate alerts on scams, downtime, and trust score swings.</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-3">
                                    <span class="rounded-circle bg-success bg-opacity-25 text-success d-inline-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <i class="fas fa-handshake-angle"></i>
                                    </span>
                                    <div>
                                        <h4 class="h6 text-white mb-1">Partner rewards</h4>
                                        <p class="text-muted small mb-0">Access exclusive coupon codes and referral incentives.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                        <div class="glass-card p-4">
                            <h4 class="h6 text-white mb-2">Already subscribed?</h4>
                            <p class="text-muted small mb-3">Manage your preferences from any newsletter or reach out to support for assistance.</p>
                            <a href="support-tickets.php" class="btn btn-outline-light btn-sm w-100"><i class="fas fa-life-ring me-2"></i>Contact support</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dev-slot2 mt-4">Inline Ad 728x90</div>
        </div>
    </section>
</div>

<script>
function togglePreference(card) {
    const checkbox = card.querySelector('.preference-checkbox');
    const willSelect = !checkbox.checked;

    checkbox.checked = willSelect;

    if (willSelect) {
        card.classList.remove('border-light', 'border-opacity-10', 'bg-dark', 'bg-opacity-25');
        card.classList.add('border-primary', 'border-opacity-50', 'bg-primary', 'bg-opacity-10');
    } else {
        card.classList.add('border-light', 'border-opacity-10', 'bg-dark', 'bg-opacity-25');
        card.classList.remove('border-primary', 'border-opacity-50', 'bg-primary', 'bg-opacity-10');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
