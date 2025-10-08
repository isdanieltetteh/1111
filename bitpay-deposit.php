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
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();
$user_id = $_SESSION['user_id'];
$settings = $wallet_manager->getWalletSettings();
$success_message = '';
$error_message = '';

// Handle BitPay invoice creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_invoice'])) {
    $amount = floatval($_POST['amount']);
    $currency = $_POST['currency'] ?? 'USD';
    
    if ($amount < $settings['min_deposit']) {
        $error_message = 'Minimum deposit is $' . number_format($settings['min_deposit'], 4);
    } else {
        $invoice_data = createBitPayInvoice($amount, $currency, $user_id, $settings);
        
        if ($invoice_data['success']) {
            // Store invoice in database
            $invoice_query = "INSERT INTO deposit_transactions (user_id, amount, currency, bitpay_invoice_id, payment_method, status) 
                             VALUES (:user_id, :amount, :currency, :invoice_id, 'bitpay', 'pending')";
            $invoice_stmt = $db->prepare($invoice_query);
            $invoice_stmt->bindParam(':user_id', $user_id);
            $invoice_stmt->bindParam(':amount', $amount);
            $invoice_stmt->bindParam(':currency', $currency);
            $invoice_stmt->bindParam(':invoice_id', $invoice_data['invoice_id']);
            $invoice_stmt->execute();
            
            // Redirect to BitPay payment page
            header('Location: ' . $invoice_data['payment_url']);
            exit();
        } else {
            $error_message = $invoice_data['message'];
        }
    }
}

// Handle BitPay webhook (IPN)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bitpay_webhook'])) {
    $webhook_data = json_decode(file_get_contents('php://input'), true);
    
    if (verifyBitPayWebhook($webhook_data, $settings['bitpay_webhook_secret'])) {
        $invoice_id = $webhook_data['data']['id'];
        $status = $webhook_data['data']['status'];
        $amount = $webhook_data['data']['price'];
        
        if ($status === 'confirmed' || $status === 'complete') {
            // Get transaction details
            $transaction_query = "SELECT * FROM deposit_transactions WHERE bitpay_invoice_id = :invoice_id AND status = 'pending'";
            $transaction_stmt = $db->prepare($transaction_query);
            $transaction_stmt->bindParam(':invoice_id', $invoice_id);
            $transaction_stmt->execute();
            $transaction = $transaction_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($transaction) {
                // Process the deposit
                if ($wallet_manager->processDeposit($transaction['user_id'], $amount, 'USD', null, $invoice_id)) {
                    // Update transaction status
                    $update_query = "UPDATE deposit_transactions SET status = 'completed', completed_at = NOW() WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':id', $transaction['id']);
                    $update_stmt->execute();
                    
                    echo "OK"; // BitPay expects this response
                    exit();
                }
            }
        } elseif ($status === 'expired' || $status === 'invalid') {
            // Update transaction as failed
            $update_query = "UPDATE deposit_transactions SET status = 'failed' WHERE bitpay_invoice_id = :invoice_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':invoice_id', $invoice_id);
            $update_stmt->execute();
        }
    }
    
    http_response_code(200);
    exit();
}

// BitPay API functions
function createBitPayInvoice($amount, $currency, $user_id, $settings) {
    $api_token = $settings['bitpay_api_token'];
    $environment = $settings['bitpay_environment'] === 'prod' ? 'https://bitpay.com' : 'https://test.bitpay.com';
    
    if (empty($api_token)) {
        return ['success' => false, 'message' => 'BitPay not configured'];
    }
    
    $invoice_data = [
        'price' => $amount,
        'currency' => $currency,
        'orderId' => 'deposit_' . $user_id . '_' . time(),
        'itemDesc' => 'Wallet Deposit - ' . SITE_NAME,
        'notificationEmail' => SITE_EMAIL,
        'redirectURL' => SITE_URL . '/deposit-success.php?method=bitpay',
        'notificationURL' => SITE_URL . '/bitpay-deposit.php',
        'buyer' => [
            'email' => $_SESSION['email']
        ]
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_token,
        'X-Accept-Version: 2.0.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $environment . '/invoices');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoice_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'invoice_id' => $result['data']['id'],
            'payment_url' => $result['data']['url']
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to create BitPay invoice'];
    }
}

function verifyBitPayWebhook($webhook_data, $webhook_secret) {
    if (empty($webhook_secret)) return false;
    
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $payload = file_get_contents('php://input');
    $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
    
    return hash_equals($signature, $expected_signature);
}

$page_title = 'BitPay Deposit - ' . SITE_NAME;
$page_description = 'Deposit cryptocurrency via BitPay to your wallet.';
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
                                    <li class="breadcrumb-item active" aria-current="page">BitPay Deposit</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Deposit via BitPay</h1>
                        <p class="text-muted mb-0">Generate an invoice and fund campaigns using your preferred exchange or wallet.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fab fa-bitcoin me-2"></i>
                            <span>Enterprise-grade checkout</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end mt-3">
                            <a href="transactions.php" class="btn btn-theme btn-outline-glass">
                                <i class="fas fa-list me-2"></i>Transaction Log
                            </a>
                            <a href="wallet.php" class="btn btn-theme btn-gradient">
                                <i class="fas fa-wallet me-2"></i>View Wallet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="ad-slot dev-slot2 mb-4">Inline Ad 728x90</div>
            <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                <div class="col-12 col-xl-7">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-circle bg-warning bg-opacity-25 text-warning d-inline-flex align-items-center justify-content-center fs-4" style="width: 60px; height: 60px;">
                                <i class="fab fa-bitcoin"></i>
                            </div>
                            <div>
                                <h2 class="h4 text-white mb-1">Create a BitPay invoice</h2>
                                <p class="text-muted mb-0">Set your amount, pick a display currency, and BitPay handles the heavy lifting.</p>
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
                                           step="0.01"
                                           placeholder="Enter amount in USD"
                                           required>
                                </div>
                                <div class="form-text">You can pay with any cryptocurrency supported by BitPay.</div>
                            </div>

                            <div class="col-12">
                                <label for="currency" class="form-label">Display Currency</label>
                                <select id="currency" name="currency" class="form-select form-select-lg">
                                    <option value="USD">US Dollar (USD)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                    <option value="GBP">British Pound (GBP)</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <button type="submit" name="create_invoice" class="btn btn-theme btn-gradient btn-lg w-100">
                                    <i class="fab fa-bitcoin me-2"></i>Purchase Credits via BitPay
                                </button>
                            </div>
                        </form>

                        <div class="mt-4">
                            <h3 class="h6 text-uppercase text-muted mb-3">Why teams trust BitPay</h3>
                            <div class="row row-cols-2 g-3">
                                <div class="col">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <span class="rounded-circle bg-success bg-opacity-25 text-success d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="fas fa-shield-halved"></i>
                                        </span>
                                        <div>
                                            <h4 class="h6 text-white mb-1">Enterprise security</h4>
                                            <p class="text-muted small mb-0">Bank-grade compliance and monitoring.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <span class="rounded-circle bg-warning bg-opacity-25 text-warning d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="fas fa-coins"></i>
                                        </span>
                                        <div>
                                            <h4 class="h6 text-white mb-1">Multi-asset support</h4>
                                            <p class="text-muted small mb-0">Bitcoin, ETH, stablecoins, and more.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <span class="rounded-circle bg-primary bg-opacity-25 text-primary d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="fas fa-bolt"></i>
                                        </span>
                                        <div>
                                            <h4 class="h6 text-white mb-1">Fast processing</h4>
                                            <p class="text-muted small mb-0">Invoices confirm in minutes.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="d-flex align-items-start gap-3 p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <span class="rounded-circle bg-info bg-opacity-25 text-info d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="fas fa-building"></i>
                                        </span>
                                        <div>
                                            <h4 class="h6 text-white mb-1">Global trust</h4>
                                            <p class="text-muted small mb-0">Relied on by exchanges and enterprises.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-4 border border-warning border-opacity-25 bg-warning bg-opacity-10 p-4 mt-4">
                            <h3 class="h6 text-white mb-3">Supported cryptocurrencies</h3>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge rounded-pill bg-warning bg-opacity-25 text-warning">Bitcoin (BTC)</span>
                                <span class="badge rounded-pill bg-primary bg-opacity-25 text-primary">Ethereum (ETH)</span>
                                <span class="badge rounded-pill bg-secondary bg-opacity-25 text-secondary">Litecoin (LTC)</span>
                                <span class="badge rounded-pill bg-info bg-opacity-25 text-info">Bitcoin Cash (BCH)</span>
                                <span class="badge rounded-pill bg-secondary text-white">Dogecoin (DOGE)</span>
                                <span class="badge rounded-pill bg-success bg-opacity-25 text-success">USDC</span>
                            </div>
                        </div>

                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 mt-4">
                            <h3 class="h6 text-white mb-3">How it works</h3>
                            <ol class="text-muted small mb-0 ps-3">
                                <li>Enter the credit amount and generate a BitPay invoice.</li>
                                <li>Complete payment with your preferred crypto wallet.</li>
                                <li>BitPay confirms the transaction and notifies our system.</li>
                                <li>Credits appear in your wallet balance after confirmation.</li>
                            </ol>
                        </div>

                        <div class="text-center mt-4">
                            <a href="wallet.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-2"></i>Back to Wallet</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-5">
                    <div class="sticky-lg-top" style="top: 100px;">
                        <div class="glass-card p-4 p-lg-5 mb-4">
                            <h3 class="h6 text-white text-uppercase mb-3">Invoice tips</h3>
                            <ul class="text-muted small mb-0 d-grid gap-2 ps-3">
                                <li>Invoices expire after a set window — pay promptly.</li>
                                <li>Ensure your wallet network matches the invoice currency.</li>
                                <li>Need to retry? Generate a fresh invoice for updated rates.</li>
                            </ul>
                        </div>
                        <div class="ad-slot dev-slot1 mb-4">Sidebar Ad 300x600</div>
                        <div class="glass-card p-4">
                            <h4 class="h6 text-white mb-2">Want instant crediting?</h4>
                            <p class="text-muted small mb-3">Use FaucetPay deposits for immediate wallet balance updates.</p>
                            <a href="faucetpay-deposit.php" class="btn btn-theme btn-sm w-100"><i class="fas fa-droplet me-2"></i>Switch to FaucetPay</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>


<?php include 'includes/footer.php'; ?>
