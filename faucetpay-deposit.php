<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/wallet.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
$wallet_manager = new WalletManager($db);

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit();
}

$user = $auth->getCurrentUser();
$user_id = $_SESSION['user_id'];
$settings = $wallet_manager->getWalletSettings();
$success_message = '';
$error_message = '';

// Handle FaucetPay IPN callback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['token'])) {
    // This is an IPN callback from FaucetPay
    $token = $_POST['token'];
    
    // Verify the payment with FaucetPay
    $payment_info = file_get_contents("https://faucetpay.io/merchant/get-payment/" . $token);
    $payment_info = json_decode($payment_info, true);
    
    if (!$payment_info) {
        http_response_code(400);
        echo "Invalid payment verification";
        exit();
    }
    
    $token_status = $payment_info['valid'];
    $merchant_username = $payment_info['merchant_username'];
    $amount1 = floatval($payment_info['amount1']);
    $currency1 = $payment_info['currency1'];
    $amount2 = floatval($payment_info['amount2']);
    $currency2 = $payment_info['currency2'];
    $custom = $payment_info['custom'];
    $transaction_id = $payment_info['transaction_id'];
    
    // Verify merchant username
    if ($merchant_username !== $settings['faucetpay_merchant_username'] || !$token_status) {
        http_response_code(400);
        echo "Invalid merchant or token";
        exit();
    }
    
    // Parse custom field to get user_id and expected amount
    $custom_parts = explode('_', $custom);
    if (count($custom_parts) < 3 || $custom_parts[0] !== 'credits') {
        http_response_code(400);
        echo "Invalid custom data";
        exit();
    }
    
    $payment_user_id = intval($custom_parts[1]);
    $expected_amount = floatval($custom_parts[2]);
    
    // Verify the payment amount matches what was expected
    if ($currency1 !== 'USD' || abs($amount1 - $expected_amount) > 0.01) {
        http_response_code(400);
        echo "Payment amount mismatch";
        exit();
    }
    
    // Check if this transaction was already processed
    $existing_query = "SELECT id FROM credit_transactions WHERE transaction_id = :transaction_id";
    $existing_stmt = $db->prepare($existing_query);
    $existing_stmt->bindParam(':transaction_id', $transaction_id);
    $existing_stmt->execute();
    
    if ($existing_stmt->rowCount() > 0) {
        echo "OK"; // Already processed
        exit();
    }
    
    // Process the deposit
    if ($wallet_manager->processDeposit($payment_user_id, $amount1, $currency2, $transaction_id)) {
        echo "OK"; // FaucetPay expects this response
        exit();
    } else {
        http_response_code(500);
        echo "Deposit processing failed";
        exit();
    }
}

// Handle FaucetPay deposit form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    $amount = floatval($_POST['amount']);
    $currency2 = $_POST['currency2']; // user-selected crypto
    
    if ($amount < $settings['min_deposit']) {
        $error_message = 'Minimum deposit is $' . number_format($settings['min_deposit'], 4);
    } else {
        // Create FaucetPay payment form
        $payment_data = [
            'merchant_username' => $settings['faucetpay_merchant_id'],
            'item_description'  => 'Credits Purchase - ' . SITE_NAME,
            'amount1'     => $amount, // amount in USD
            'currency1'   => 'USD',
            'currency2'   => $currency2, // chosen crypto
            'custom'      => 'credits_' . $user_id . '_' . $amount,
            'callback_url' => SITE_URL . '/credits-ipn',
            'success_url' => SITE_URL . '/credits-success',
            'cancel_url'  => SITE_URL . '/buy-credits'
        ];
        
        // Redirect to FaucetPay
        $form_html = '<form id="faucetpayForm" action="https://faucetpay.io/merchant/webscr" method="POST">';
        foreach ($payment_data as $key => $value) {
            $form_html .= '<input type="hidden" name="' . $key . '" value="' . htmlspecialchars($value) . '">';
        }
        $form_html .= '</form>';
        $form_html .= '<script>document.getElementById("faucetpayForm").submit();</script>';
        
        echo $form_html;
        exit();
    }
}

$page_title = 'FaucetPay Deposit - ' . SITE_NAME;
$page_description = 'Deposit cryptocurrency via FaucetPay to your wallet.';
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
                                    <li class="breadcrumb-item"><a href="wallet.php">Wallet</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">FaucetPay Deposit</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Deposit via FaucetPay</h1>
                        <p class="text-muted mb-0">Top up instantly with microtransaction-friendly crypto support.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-droplet me-2"></i>
                            <span>Minimum $<?php echo number_format($settings['min_deposit'], 4); ?></span>
                        </div>
                        <a href="wallet.php" class="btn btn-theme btn-outline-glass mt-3">
                            <i class="fas fa-wallet me-2"></i>View Wallet
                        </a>
                    </div>
                </div>
            </div>
            <div class="dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="py-4">
        <div class="container">
            <?php
            $dashboard_nav_links = [
                [
                    'href' => 'dashboard.php',
                    'icon' => 'fa-gauge-high',
                    'label' => 'Overview',
                    'description' => 'Insights & rewards summary'
                ],
                [
                    'href' => 'my-submissions.php',
                    'icon' => 'fa-globe',
                    'label' => 'My Submissions',
                    'description' => 'Manage and update your listings'
                ],
                [
                    'href' => 'my-ads.php',
                    'icon' => 'fa-rectangle-ad',
                    'label' => 'My Campaigns',
                    'description' => 'Track ad performance & status'
                ],
                [
                    'href' => 'notifications.php',
                    'icon' => 'fa-bell',
                    'label' => 'Notifications',
                    'description' => 'Review alerts & platform updates'
                ],
                [
                    'href' => 'wallet.php',
                    'icon' => 'fa-wallet',
                    'label' => 'Wallet',
                    'description' => 'Monitor credits & transactions'
                ],
                [
                    'href' => 'support-tickets.php',
                    'icon' => 'fa-life-ring',
                    'label' => 'Support',
                    'description' => 'Submit & follow support tickets'
                ],
                [
                    'href' => 'promote-sites.php',
                    'icon' => 'fa-rocket',
                    'label' => 'Promotions',
                    'description' => 'Boost visibility with premium slots'
                ],
                [
                    'href' => 'buy-credits.php',
                    'icon' => 'fa-credit-card',
                    'label' => 'Buy Credits',
                    'description' => 'Top up instantly for upgrades'
                ],
                [
                    'href' => 'redeem-coupon.php',
                    'icon' => 'fa-ticket',
                    'label' => 'Redeem Coupons',
                    'description' => 'Apply promo codes for bonuses'
                ],
                [
                    'href' => 'profile.php',
                    'icon' => 'fa-user-gear',
                    'label' => 'Account Settings',
                    'description' => 'Update profile & security details'
                ]
            ];
            $dashboard_nav_current = basename($_SERVER['PHP_SELF'] ?? '');
            ?>
            <div class="glass-card p-4 p-lg-5 mb-4" data-aos="fade-up">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h5 text-white mb-1">Navigate Your Toolkit</h2>
                        <p class="text-muted mb-0">Quick links to every dashboard feature.</p>
                    </div>
                    <a href="promote-sites.php" class="btn btn-theme btn-outline-glass btn-sm">
                        <i class="fas fa-bullhorn me-2"></i>Promotions Desk
                    </a>
                </div>
                <div class="row g-3 row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-5 dashboard-nav-grid">
                    <?php foreach ($dashboard_nav_links as $link): ?>
                        <div class="col">
                            <a class="dashboard-nav-tile <?php echo $dashboard_nav_current === basename($link['href']) ? 'active' : ''; ?>"
                               href="<?php echo htmlspecialchars($link['href']); ?>">
                                <span class="tile-icon"><i class="fas <?php echo htmlspecialchars($link['icon']); ?>"></i></span>
                                <span class="tile-label"><?php echo htmlspecialchars($link['label']); ?></span>
                                <span class="tile-desc text-muted"><?php echo htmlspecialchars($link['description']); ?></span>
                                <span class="tile-arrow"><i class="fas fa-arrow-right"></i></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="dev-slot2 mb-4">Inline Ad 728x90</div>
            <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                <div class="col-12 col-xl-7">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-circle bg-primary bg-opacity-25 text-primary d-inline-flex align-items-center justify-content-center fs-4" style="width: 60px; height: 60px;">
                                <i class="fas fa-droplet"></i>
                            </div>
                            <div>
                                <h2 class="h4 text-white mb-1">Complete FaucetPay Checkout</h2>
                                <p class="text-muted mb-0">Secure credit purchases with multi-asset support.</p>
                            </div>
                        </div>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success mb-4">
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger mb-4">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="row g-4">
                            <div class="col-12">
                                <label for="amount" class="form-label">Credit Amount (USD)</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                    <input type="number"
                                           id="amount"
                                           name="amount"
                                           class="form-control"
                                           min="<?php echo $settings['min_deposit']; ?>"
                                           step="0.0001"
                                           placeholder="Enter amount in USD"
                                           onchange="updatePrice()"
                                           required>
                                </div>
                                <div class="form-text">Minimum deposit: $<?php echo number_format($settings['min_deposit'], 4); ?></div>
                            </div>

                            <div class="col-12">
                                <label for="currency2" class="form-label">Payment Currency</label>
                                <select id="currency2" name="currency2" class="form-select form-select-lg" required>
                                    <option value="BTC">Bitcoin (BTC)</option>
                                    <option value="ETH">Ethereum (ETH)</option>
                                    <option value="LTC">Litecoin (LTC)</option>
                                    <option value="DOGE">Dogecoin (DOGE)</option>
                                    <option value="BCH">Bitcoin Cash (BCH)</option>
                                    <option value="DASH">Dash (DASH)</option>
                                    <option value="DGB">DigiByte (DGB)</option>
                                    <option value="TRX">Tron (TRX)</option>
                                    <option value="USDT">Tether (USDT)</option>
                                    <option value="FEY">Feyorra (FEY)</option>
                                    <option value="ZEC">Zcash (ZEC)</option>
                                    <option value="BNB">Binance Coin (BNB)</option>
                                    <option value="SOL">Solana (SOL)</option>
                                    <option value="XRP">Ripple (XRP)</option>
                                    <option value="POL">Polygon (POL)</option>
                                    <option value="ADA">Cardano (ADA)</option>
                                    <option value="TON">Toncoin (TON)</option>
                                    <option value="XLM">Stellar (XLM)</option>
                                    <option value="USDC">USD Coin (USDC)</option>
                                    <option value="XMR">Monero (XMR)</option>
                                    <option value="TARA">Taraxa (TARA)</option>
                                    <option value="TRUMP">TRUMP (TRUMP)</option>
                                    <option value="PEPE">Pepe (PEPE)</option>
                                    <option value="FLT">Fluence (FLT)</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div id="priceDisplay" class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 d-none">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <span class="text-muted text-uppercase small">Credits to receive</span>
                                            <div id="creditsAmount" class="fs-4 fw-bold text-white mt-1">$0.00</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-muted text-uppercase small">Payment amount</span>
                                            <div id="paymentAmount" class="fs-4 fw-bold text-primary mt-1">$0.00</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-theme btn-lg w-100">
                                    <i class="fas fa-credit-card me-2"></i>Purchase Credits via FaucetPay
                                </button>
                            </div>
                        </form>

                        <div class="mt-4">
                            <h3 class="h6 text-uppercase text-muted mb-3">Why creators choose FaucetPay</h3>
                            <div class="row row-cols-2 g-3">
                                <div class="col">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <span class="rounded-circle bg-success bg-opacity-25 text-success d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="fas fa-shield-halved"></i>
                                        </span>
                                        <div>
                                            <h4 class="h6 text-white mb-1">Secure</h4>
                                            <p class="text-muted small mb-0">Industry-grade merchant protection.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <span class="rounded-circle bg-warning bg-opacity-25 text-warning d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="fas fa-bolt"></i>
                                        </span>
                                        <div>
                                            <h4 class="h6 text-white mb-1">Instant</h4>
                                            <p class="text-muted small mb-0">Credits land moments after payment.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <span class="rounded-circle bg-primary bg-opacity-25 text-primary d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="fas fa-coins"></i>
                                        </span>
                                        <div>
                                            <h4 class="h6 text-white mb-1">Multi-currency</h4>
                                            <p class="text-muted small mb-0">Tap 20+ supported assets.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <span class="rounded-circle bg-info bg-opacity-25 text-info d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="fas fa-percent"></i>
                                        </span>
                                        <div>
                                            <h4 class="h6 text-white mb-1">Low fees</h4>
                                            <p class="text-muted small mb-0">Optimized for faucet economics.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4">
                            <h3 class="h6 text-white mb-3">How it works</h3>
                            <ol class="text-muted small mb-0 ps-3">
                                <li>Enter the USD value of credits you need.</li>
                                <li>Pick the crypto you want to spend on FaucetPay.</li>
                                <li>Confirm the payment on FaucetPay’s checkout.</li>
                                <li>Credits appear instantly in your wallet balance.</li>
                            </ol>
                        </div>

                        <div class="text-center mt-4">
                            <a href="wallet.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-arrow-left me-2"></i>Back to Wallet
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-5">
                    <div class="sticky-lg-top" style="top: 100px;">
                        <div class="glass-card p-4 p-lg-5 mb-4">
                            <h3 class="h5 text-white mb-3">FaucetPay perks</h3>
                            <ul class="list-unstyled text-muted small mb-0 d-grid gap-2">
                                <li><i class="fas fa-check-circle text-success me-2"></i>No manual confirmations required.</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Supports micropayments and daily top-ups.</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Merchant verified by FaucetPay.</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Automatic crediting to your dashboard.</li>
                            </ul>
                        </div>
                        <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                        <div class="glass-card p-4">
                            <h4 class="h6 text-white mb-3">Need another payment rail?</h4>
                            <p class="text-muted small mb-3">Prefer paying from an exchange balance? Create a BitPay invoice for bank-grade settlement.</p>
                            <a href="bitpay-deposit.php" class="btn btn-theme btn-sm w-100"><i class="fab fa-bitcoin me-2"></i>Switch to BitPay</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
function updatePrice() {
    const amountField = document.getElementById('amount');
    const amount = parseFloat(amountField.value) || 0;
    const minDeposit = <?php echo $settings['min_deposit']; ?>;
    const priceDisplay = document.getElementById('priceDisplay');
    const creditsAmount = document.getElementById('creditsAmount');
    const paymentAmount = document.getElementById('paymentAmount');

    if (amount >= minDeposit) {
        priceDisplay.classList.remove('d-none');
        creditsAmount.textContent = '$' + amount.toFixed(4);
        paymentAmount.textContent = '$' + amount.toFixed(2);
    } else {
        priceDisplay.classList.add('d-none');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
