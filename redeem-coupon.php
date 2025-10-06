<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/coupon-manager.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
$coupon_manager = new CouponManager($db);

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle coupon redemption
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify captcha
    $captcha_valid = false;
    
    if (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) {
        $captcha_response = $_POST['h-captcha-response'];
        $secret_key = HCAPTCHA_SECRET_KEY;
        
        if (!empty($secret_key)) {
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
        } else {
            $captcha_valid = true; // Skip if not configured
        }
    }
    
    if (!$captcha_valid) {
        $error_message = 'Please complete the captcha verification';
    } else {
        $coupon_code = strtoupper(trim($_POST['coupon_code']));
        $deposit_amount = floatval($_POST['deposit_amount'] ?? 0);
        
        if (empty($coupon_code)) {
            $error_message = 'Please enter a coupon code';
        } else {
            $result = $coupon_manager->redeemCoupon($coupon_code, $user_id, $deposit_amount);
            
            if ($result['success']) {
                $success_message = $result['message'] . ' Value: $' . number_format($result['value'], 4);
                // Refresh user data
                $user = $auth->getCurrentUser();
            } else {
                $error_message = $result['message'];
            }
        }
    }
}

// Get user's redemption history
$user_redemptions = $coupon_manager->getUserRedemptions($user_id);

$page_title = 'Redeem Coupon - ' . SITE_NAME;
$page_description = 'Redeem coupons to add bonus funds to your wallet.';

$additional_head = '
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <style>
        .coupon-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .coupon-type-card {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.25rem;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .coupon-type-card:hover {
            transform: translateY(-4px);
            border-color: rgba(59, 130, 246, 0.45);
            box-shadow: var(--shadow-soft);
        }

        .coupon-type-card .icon {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .redemption-history-card {
            border-left: 4px solid rgba(59, 130, 246, 0.45);
            border-radius: 1.25rem;
            background: rgba(15, 23, 42, 0.65);
            padding: 1.5rem;
            transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .redemption-history-card:hover {
            transform: translateY(-4px);
            border-color: rgba(96, 165, 250, 0.65);
            box-shadow: var(--shadow-soft);
        }
    </style>
';

$current_page = 'dashboard';
include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                    <div class="flex-grow-1">
                        <div class="dashboard-breadcrumb mb-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Redeem Coupons</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Claim Your Bonus</h1>
                        <p class="text-muted mb-0">Redeem exclusive coupon codes to convert community rewards into spendable credits.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-ticket-simple"></i>
                            <span>Multi-tier bonuses</span>
                        </div>
                        <a href="buy-credits.php" class="btn btn-theme btn-gradient mt-3">
                            <i class="fas fa-credit-card me-2"></i>Buy Credits
                        </a>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="ad-slot dev-slot2 mb-4">Inline Ad 728x90</div>

            <?php if ($error_message): ?>
                <div class="alert alert-glass alert-danger mb-4" role="alert">
                    <span class="icon text-danger"><i class="fas fa-exclamation-circle"></i></span>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-glass alert-success mb-4" role="alert">
                    <span class="icon text-success"><i class="fas fa-check-circle"></i></span>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <h2 class="h4 text-white mb-2">Current Credit Balance</h2>
                        <p class="text-muted mb-0">Bonuses post instantly once a coupon is approved and can be used across promotions, campaigns, and withdrawals.</p>
                    </div>
                    <div class="col-lg-4">
                        <div class="glass-balance-card text-center p-4">
                            <span class="balance-label text-muted text-uppercase">Credits Available</span>
                            <div class="display-6 fw-bold text-success mt-2">$<?php echo number_format($user['credits'], 4); ?></div>
                            <p class="text-muted small mb-0 mt-2">Need more? Stack coupons with credit packs for extra value.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-xl-8">
                    <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up" data-aos-delay="100">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                            <div>
                                <h2 class="h4 text-white mb-1">Redeem a Coupon</h2>
                                <p class="text-muted mb-0">Enter the code provided by campaigns, partners, or giveaways to unlock instant wallet boosts.</p>
                            </div>
                            <div class="option-chip">
                                <i class="fas fa-shield-halved"></i>
                                <span>Fraud monitored</span>
                            </div>
                        </div>

                        <form method="POST" class="row g-3">
                            <div class="col-12">
                                <label for="coupon_code" class="form-label text-uppercase small text-muted">Coupon Code</label>
                                <input type="text"
                                       id="coupon_code"
                                       name="coupon_code"
                                       class="form-control form-control-lg text-uppercase text-center"
                                       placeholder="ENTER-COUPON-CODE"
                                       value="<?php echo htmlspecialchars($_POST['coupon_code'] ?? ''); ?>"
                                       required>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="deposit_amount" class="form-label text-uppercase small text-muted">Deposit Amount (for % coupons)</label>
                                <input type="number"
                                       id="deposit_amount"
                                       name="deposit_amount"
                                       class="form-control form-control-lg"
                                       min="0"
                                       step="0.01"
                                       placeholder="0.00"
                                       value="<?php echo htmlspecialchars($_POST['deposit_amount'] ?? ''); ?>">
                                <small class="text-muted">Only needed when applying percentage-based bonuses.</small>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-center py-2">
                                    <div class="h-captcha" data-sitekey="<?php echo HCAPTCHA_SITE_KEY; ?>"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-theme btn-gradient w-100">
                                    <i class="fas fa-gift me-2"></i>Redeem Coupon
                                </button>
                            </div>
                        </form>

                        <div class="glass-card p-3 mt-4">
                            <div class="d-flex align-items-start gap-3">
                                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis flex-shrink-0"><i class="fas fa-shield-halved"></i></span>
                                <div class="text-muted small">
                                    <strong class="d-block text-white-50">Security Notice</strong>
                                    <ul class="ps-3 mb-0">
                                        <li>Each coupon can be redeemed once per account.</li>
                                        <li>Expired or fully claimed codes will return an error.</li>
                                        <li>Fraudulent attempts trigger permanent account flags.</li>
                                        <li>Multiple accounts per household may be restricted.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up" data-aos-delay="150">
                        <h2 class="h4 text-white text-center mb-4">Coupon Types</h2>
                        <div class="coupon-type-grid">
                            <div class="coupon-type-card">
                                <div class="icon bg-success bg-opacity-10 text-success"><i class="fas fa-dollar-sign"></i></div>
                                <h3 class="h5 text-white">Deposit Bonus</h3>
                                <p class="text-muted small mb-0">Fixed value credited directly to your wallet balance.</p>
                            </div>
                            <div class="coupon-type-card">
                                <div class="icon bg-info bg-opacity-10 text-info"><i class="fas fa-percentage"></i></div>
                                <h3 class="h5 text-white">Percentage Bonus</h3>
                                <p class="text-muted small mb-0">Multiply deposits with percentage-based boosters.</p>
                            </div>
                            <div class="coupon-type-card">
                                <div class="icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-coins"></i></div>
                                <h3 class="h5 text-white">Points Bonus</h3>
                                <p class="text-muted small mb-0">Convert reputation points into spendable credits.</p>
                            </div>
                            <div class="coupon-type-card">
                                <div class="icon bg-purple bg-opacity-10 text-purple"><i class="fas fa-gem"></i></div>
                                <h3 class="h5 text-white">Credits Bonus</h3>
                                <p class="text-muted small mb-0">Unlock campaign credits for sponsored listings.</p>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($user_redemptions)): ?>
                        <div class="glass-card p-4 p-lg-5 mt-4 animate-fade-in" data-aos="fade-up" data-aos-delay="200">
                            <h2 class="h5 text-white mb-4">Your Redemption History</h2>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($user_redemptions as $redemption): ?>
                                    <div class="redemption-history-card">
                                        <div class="d-flex flex-wrap justify-content-between gap-3 mb-2">
                                            <div>
                                                <h3 class="h6 text-white mb-1"><?php echo htmlspecialchars($redemption['title']); ?></h3>
                                                <span class="text-muted small">Code: <strong><?php echo htmlspecialchars($redemption['code']); ?></strong></span>
                                            </div>
                                            <span class="badge rounded-pill bg-success-subtle text-success fw-semibold">+$<?php echo number_format($redemption['redemption_value'], 4); ?></span>
                                        </div>
                                        <div class="text-muted small d-flex justify-content-between">
                                            <span class="text-capitalize"><?php echo str_replace('_', ' ', htmlspecialchars($redemption['coupon_type'])); ?></span>
                                            <span><?php echo date('M j, Y g:i A', strtotime($redemption['redeemed_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-xl-4">
                    <div class="d-flex flex-column gap-4">
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="200">
                            <h3 class="h5 text-white mb-3">Bonus Tips</h3>
                            <ul class="list-unstyled text-muted small mb-0">
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-calendar-check text-info"></i><span>Redeem early—some codes have limited supplies and strict expiry windows.</span></li>
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-link text-info"></i><span>Pair coupons with referrals to stack rewards faster.</span></li>
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-globe text-info"></i><span>Regional promos may require matching IP locations.</span></li>
                                <li class="d-flex gap-2"><i class="fas fa-life-ring text-info"></i><span>Questions? Our support team can verify code status for you.</span></li>
                            </ul>
                        </div>
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="250">
                            <h3 class="h5 text-white mb-3">Need Assistance?</h3>
                            <p class="text-muted small mb-4">Having trouble redeeming a code or see an incorrect balance? Submit a ticket and we’ll review it quickly.</p>
                            <a href="support-tickets.php" class="btn btn-theme btn-outline-glass w-100">
                                <i class="fas fa-life-ring me-2"></i>Contact Support
                            </a>
                        </div>
                        <div class="ad-slot dev-slot1">Sidebar Ad 300x600</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
