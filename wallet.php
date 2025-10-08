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
$success_message = '';
$error_message = '';

// Get wallet data
$wallet = $wallet_manager->getUserWallet($user_id);
$settings = $wallet_manager->getWalletSettings();
$referrals = $wallet_manager->getUserReferrals($user_id);
$transactions = $wallet_manager->getUserTransactions($user_id, 'all', 10);

$currencies_query = "SELECT * FROM withdrawal_currencies WHERE is_active = 1 ORDER BY currency_name";
$currencies_stmt = $db->prepare($currencies_query);
$currencies_stmt->execute();
$currencies = $currencies_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['withdraw'])) {
    $points = intval($_POST['points']);
    $method = $_POST['withdrawal_method'];
    $wallet_address = trim($_POST['wallet_address']);
    $faucetpay_email = trim($_POST['faucetpay_email']);
    $currency = $_POST['currency'];

    $result = $wallet_manager->createWithdrawalRequest($user_id, $points, $method, $wallet_address, $currency, $faucetpay_email);

    if ($result['success']) {
        $success_message = $result['message'];
        $wallet = $wallet_manager->getUserWallet($user_id); // Refresh wallet data
    } else {
        $error_message = $result['message'];
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}

$page_title = 'Wallet - ' . SITE_NAME;
$page_description = 'Manage your wallet, deposits, withdrawals, and referral earnings.';
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
                                    <li class="breadcrumb-item active" aria-current="page">Wallet</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Your Earnings Hub</h1>
                        <p class="text-muted mb-0">Top up credits, withdraw points, and track referral impact with a single command center.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-wallet"></i>
                            <span>$<?php echo number_format($user['credits'], 2); ?> credits</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end mt-3">
                            <a href="transactions.php" class="btn btn-theme btn-outline-glass">
                                <i class="fas fa-list me-2"></i>View Transactions
                            </a>
                            <a href="buy-credits.php" class="btn btn-theme btn-gradient">
                                <i class="fas fa-credit-card me-2"></i>Buy Credits
                            </a>
                        </div>
                    </div>
            </div>
        </div>
            <div class="ad-slot dev-slot mt-4">Wallet Banner 970x250</div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <?php if ($success_message): ?>
                <div class="alert alert-glass alert-success mb-4" role="alert">
                    <span class="icon text-success"><i class="fas fa-check-circle"></i></span>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-glass alert-danger mb-4" role="alert">
                    <span class="icon text-danger"><i class="fas fa-exclamation-triangle"></i></span>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-card p-4 text-center h-100">
                        <span class="text-muted text-uppercase small">Credit Balance</span>
                        <div class="fs-3 fw-bold text-success mt-2">$<?php echo number_format($user['credits'], 4); ?></div>
                        <p class="text-muted small mb-0">Available for sponsored placements</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-card p-4 text-center h-100">
                        <span class="text-muted text-uppercase small">Points Balance</span>
                        <div class="fs-3 fw-bold text-info mt-2"><?php echo number_format($wallet['points_balance']); ?></div>
                        <p class="text-muted small mb-0">≈ $<?php echo number_format($wallet['points_balance'] * $settings['points_to_usd_rate'], 4); ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-card p-4 text-center h-100">
                        <span class="text-muted text-uppercase small">Active Referrals</span>
                        <div class="fs-3 fw-bold text-warning mt-2"><?php echo count($referrals); ?></div>
                        <p class="text-muted small mb-0">Friends invited to the platform</p>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-card p-4 text-center h-100">
                        <span class="text-muted text-uppercase small">Referral Points</span>
                        <div class="fs-3 fw-bold text-primary mt-2"><?php echo number_format(array_sum(array_column($referrals, 'points_earned'))); ?></div>
                        <p class="text-muted small mb-0">Earned from community growth</p>
                    </div>
                </div>
            </div>

            <div class="ad-slot dev-slot2 mb-4">Inline Wallet Ad 728x90</div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h2 class="h5 text-white mb-0"><i class="fas fa-coins text-info me-2"></i>Add Credits</h2>
                            <a href="faucetpay-deposit" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-plus me-2"></i>Deposit</a>
                        </div>
                        <p class="text-muted small mb-4">Add credits via FaucetPay to unlock boosted visibility, featured slots, and instant campaign launches.</p>
                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3 mb-3">
                            <h6 class="text-info text-uppercase small fw-semibold mb-2">FaucetPay Integration</h6>
                            <p class="text-muted small mb-3">Minimum deposit: $<?php echo number_format($settings['min_deposit'], 4); ?> · Processing time: instant</p>
                            <a href="faucetpay-deposit" class="btn btn-theme btn-gradient w-100">
                                <i class="fas fa-plus me-2"></i>Add Credits via FaucetPay
                            </a>
                        </div>
                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3">
                            <h6 class="text-muted text-uppercase small fw-semibold mb-2">Need invoices?</h6>
                            <p class="text-muted small mb-0">Contact support for manual payments, invoices, or enterprise packages.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <h2 class="h5 text-white mb-3"><i class="fas fa-arrow-up-right-dots text-warning me-2"></i>Withdraw Points</h2>
                        <p class="text-muted small mb-4">Convert earned points (not purchased credits) into cryptocurrency rewards using FaucetPay or direct wallet transfers.</p>
                        <div class="alert alert-glass alert-warning mb-4" role="alert">
                            <span class="icon text-warning"><i class="fas fa-info-circle"></i></span>
                            <div>Only activity-based points are withdrawable. Purchased credits remain on the platform for promotions.</div>
                        </div>
                        <form method="POST" id="withdrawForm" class="d-grid gap-3">
                            <div>
                                <label for="points" class="form-label">Points to Withdraw</label>
                                <input type="number"
                                       id="points"
                                       name="points"
                                       class="form-control"
                                       max="<?php echo $wallet['points_balance']; ?>"
                                       placeholder="Enter points to withdraw"
                                       onchange="updateWithdrawalCalculation()"
                                       required>
                                <small class="text-muted d-block mt-2">Available: <?php echo number_format($wallet['points_balance']); ?> points (≈ $<?php echo number_format($wallet['points_balance'] * $settings['points_to_usd_rate'], 4); ?>)</small>
                            </div>
                            <div>
                                <label for="withdrawal_method" class="form-label">Withdrawal Method</label>
                                <select id="withdrawal_method" name="withdrawal_method" class="form-select" onchange="updateWithdrawalMethod()" required>
                                    <option value="">Select method</option>
                                    <option value="faucetpay">FaucetPay</option>
                                    <option value="direct_wallet">Direct Crypto Wallet</option>
                                </select>
                            </div>
                            <div>
                                <label for="currency" class="form-label">Currency</label>
                                <select id="currency" name="currency" class="form-select" onchange="updateWithdrawalCalculation()" required>
                                    <option value="">Select currency</option>
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?php echo $currency['currency_code']; ?>"
                                                data-method="<?php echo $currency['withdrawal_method']; ?>"
                                                data-min="<?php echo $currency['min_amount']; ?>"
                                                data-fee="<?php echo $currency['fee_percentage']; ?>">
                                            <?php echo $currency['currency_name']; ?> (<?php echo $currency['currency_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="faucetpayEmailGroup" class="d-none">
                                <label for="faucetpay_email" class="form-label">FaucetPay Email</label>
                                <input type="email" id="faucetpay_email" name="faucetpay_email" class="form-control" placeholder="Enter your FaucetPay email address">
                                <small class="text-muted d-block mt-2"><i class="fas fa-info-circle me-1"></i>Use the email associated with your FaucetPay account.</small>
                            </div>
                            <div id="walletAddressGroup" class="d-none">
                                <label for="wallet_address" class="form-label">Wallet Address</label>
                                <input type="text" id="wallet_address" name="wallet_address" class="form-control" placeholder="Enter your wallet address">
                                <small class="text-muted d-block mt-2"><i class="fas fa-wallet me-1"></i>Paste the destination crypto wallet for your payout.</small>
                            </div>
                            <div id="withdrawalCalculation" class="withdrawal-breakdown d-none"></div>
                            <div class="d-grid">
                                <button type="submit" name="withdraw" class="btn btn-theme btn-gradient">
                                    <i class="fas fa-arrow-up me-2"></i>Request Withdrawal
                                </button>
                            </div>
                            <small class="text-muted text-center">FaucetPay fee: <?php echo $settings['faucetpay_fee_percentage']; ?>% · Direct wallet fee: <?php echo $settings['withdrawal_fee_percentage']; ?>% · Processing time: 24-48h</small>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-lg-6">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <h2 class="h5 text-white mb-3"><i class="fas fa-users text-success me-2"></i>Referral Program</h2>
                        <p class="text-muted small mb-4">Earn <?php echo $settings['referral_percentage']; ?>% points from your referrals' activity. Share your invite link and watch your balance grow.</p>
                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3 mb-3">
                            <label class="form-label fw-semibold">Your Referral Link</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?php echo SITE_URL; ?>/register?ref=<?php echo $user['referral_code']; ?>" readonly id="referralLink">
                                <button class="btn btn-theme btn-outline-glass" type="button" onclick="copyReferralLink(event)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mb-4">
                            <span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-semibold">Active referrals: <?php echo count($referrals); ?></span>
                            <span class="badge rounded-pill bg-success-subtle text-success fw-semibold">Points earned: <?php echo number_format(array_sum(array_column($referrals, 'points_earned'))); ?></span>
                        </div>
                        <?php if (!empty($referrals)): ?>
                            <div class="d-grid gap-3">
                                <?php foreach (array_slice($referrals, 0, 5) as $referral): ?>
                                    <div class="d-flex justify-content-between align-items-center rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3">
                                        <div>
                                            <strong class="text-white"><?php echo htmlspecialchars($referral['username']); ?></strong>
                                            <small class="text-muted d-block">Joined <?php echo timeAgo($referral['joined_date']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge rounded-pill bg-success-subtle text-success fw-semibold"><?php echo $referral['points_earned']; ?> pts</span>
                                            <small class="text-muted d-block mt-1"><?php echo $referral['activities']; ?> activities</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <h2 class="h5 text-white mb-3"><i class="fas fa-chart-line text-info me-2"></i>Recent Transactions</h2>
                        <?php if (!empty($transactions)): ?>
                            <div class="d-grid gap-3">
                                <?php foreach ($transactions as $transaction): ?>
                                    <?php
                                    $icon = '📊';
                                    if ($transaction['type'] === 'earned') {
                                        $icon = '💰';
                                    } elseif ($transaction['type'] === 'spent') {
                                        $icon = '💸';
                                    } elseif ($transaction['type'] === 'referral_bonus') {
                                        $icon = '👥';
                                    } elseif ($transaction['type'] === 'redeemed') {
                                        $icon = '🔄';
                                    }
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3">
                                        <div>
                                            <div class="fw-semibold text-white d-flex align-items-center gap-2">
                                                <span><?php echo $icon; ?></span>
                                                <span><?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?></span>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($transaction['description']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="fw-semibold <?php echo $transaction['points'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $transaction['points'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['points']); ?>
                                            </span>
                                            <small class="text-muted d-block"><?php echo timeAgo($transaction['created_at']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-4">
                                <a href="transactions.php" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-list me-2"></i>See all</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <p class="mb-1">No transactions yet</p>
                                <small>Write reviews, submit sites, and invite friends to start earning.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="glass-card p-4 p-lg-5 mt-4">
                <h2 class="h5 text-white mb-4"><i class="fas fa-bullseye text-warning me-2"></i>Earn More Points</h2>
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 text-center h-100">
                            <div class="display-6 mb-3">📝</div>
                            <h5 class="text-white">Write Reviews</h5>
                            <p class="text-muted small mb-3">Earn 5-15 points per quality review.</p>
                            <a href="sites" class="btn btn-theme btn-outline-glass btn-sm">Start Reviewing</a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 text-center h-100">
                            <div class="display-6 mb-3">🌟</div>
                            <h5 class="text-white">Submit Sites</h5>
                            <p class="text-muted small mb-3">Earn 25 points per approved submission.</p>
                            <a href="submit-site.php" class="btn btn-theme btn-outline-glass btn-sm">Submit Site</a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 text-center h-100">
                            <div class="display-6 mb-3">👍</div>
                            <h5 class="text-white">Vote & Engage</h5>
                            <p class="text-muted small mb-3">Support legit projects and earn daily bonuses.</p>
                            <a href="rankings.php" class="btn btn-theme btn-outline-glass btn-sm">View Rankings</a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 text-center h-100">
                            <div class="display-6 mb-3">👥</div>
                            <h5 class="text-white">Refer Friends</h5>
                            <p class="text-muted small mb-3">Earn <?php echo $settings['referral_percentage']; ?>% of their points forever.</p>
                            <button class="btn btn-theme btn-outline-glass btn-sm" type="button" onclick="copyReferralLink(event)">Copy Link</button>
                        </div>
                    </div>
                </div>
                <div class="ad-slot dev-slot1 mt-4">Footer Wallet Ad 970x90</div>
            </div>
        </div>
    </section>
</div>

<script>
const walletSettings = {
    pointsToUsdRate: <?php echo $settings['points_to_usd_rate']; ?>,
    minPointsWithdrawal: <?php echo $settings['min_points_withdrawal']; ?>,
    minFaucetpayPointsWithdrawal: <?php echo $settings['min_faucetpay_points_withdrawal'] ?? $settings['min_points_withdrawal']; ?>,
    withdrawalFeePercent: <?php echo $settings['withdrawal_fee_percentage']; ?>,
    faucetpayFeePercent: <?php echo $settings['faucetpay_fee_percentage'] ?? $settings['withdrawal_fee_percentage']; ?>
};

const currencies = <?php echo json_encode($currencies); ?>;

function updateWithdrawalMethod() {
    const method = document.getElementById('withdrawal_method').value;
    const faucetpayGroup = document.getElementById('faucetpayEmailGroup');
    const walletGroup = document.getElementById('walletAddressGroup');
    const currencySelect = document.getElementById('currency');
    const pointsInput = document.getElementById('points');

    currencySelect.value = '';
    const allOptions = Array.from(currencySelect.querySelectorAll('option'));

    if (method === 'faucetpay') {
        faucetpayGroup.classList.remove('d-none');
        walletGroup.classList.add('d-none');
        document.getElementById('faucetpay_email').required = true;
        document.getElementById('wallet_address').required = false;
        pointsInput.min = walletSettings.minFaucetpayPointsWithdrawal;
        pointsInput.placeholder = `Minimum ${walletSettings.minFaucetpayPointsWithdrawal.toLocaleString()} points`;

        allOptions.forEach((option, index) => {
            if (index === 0) return;
            const methodAttr = option.dataset.method;
            const enabled = methodAttr === 'faucetpay' || methodAttr === 'both';
            option.style.display = enabled ? 'block' : 'none';
            option.disabled = !enabled;
        });
    } else if (method === 'direct_wallet') {
        faucetpayGroup.classList.add('d-none');
        walletGroup.classList.remove('d-none');
        document.getElementById('faucetpay_email').required = false;
        document.getElementById('wallet_address').required = true;
        pointsInput.min = walletSettings.minPointsWithdrawal;
        pointsInput.placeholder = `Minimum ${walletSettings.minPointsWithdrawal.toLocaleString()} points`;

        allOptions.forEach((option, index) => {
            if (index === 0) return;
            const methodAttr = option.dataset.method;
            const enabled = methodAttr === 'direct_wallet' || methodAttr === 'both';
            option.style.display = enabled ? 'block' : 'none';
            option.disabled = !enabled;
        });
    } else {
        faucetpayGroup.classList.add('d-none');
        walletGroup.classList.add('d-none');
        document.getElementById('faucetpay_email').required = false;
        document.getElementById('wallet_address').required = false;
        pointsInput.min = walletSettings.minPointsWithdrawal;

        allOptions.forEach((option, index) => {
            if (index === 0) return;
            option.style.display = 'none';
            option.disabled = true;
        });
    }
    updateWithdrawalCalculation();
}

function updateWithdrawalCalculation() {
    const points = parseInt(document.getElementById('points').value) || 0;
    const method = document.getElementById('withdrawal_method').value;
    const currencySelect = document.getElementById('currency');
    const currency = currencySelect.value;
    const calculationDiv = document.getElementById('withdrawalCalculation');

    if (points > 0 && method && currency) {
        const usdValue = points * walletSettings.pointsToUsdRate;
        let feePercent = method === 'faucetpay' ? walletSettings.faucetpayFeePercent : walletSettings.withdrawalFeePercent;
        const selectedOption = currencySelect.options[currencySelect.selectedIndex];
        const currencyFee = parseFloat(selectedOption.dataset.fee) || 0;
        if (currencyFee > 0) {
            feePercent = currencyFee;
        }

        const fee = usdValue * (feePercent / 100);
        const netAmount = usdValue - fee;
        const minAmount = parseFloat(selectedOption.dataset.min) || 0;

        if (netAmount < minAmount) {
            calculationDiv.innerHTML = `
                <div class="alert alert-glass alert-danger mb-0" role="alert">
                    <span class="icon text-danger"><i class="fas fa-circle-exclamation"></i></span>
                    <div>Minimum withdrawal for ${currency} is $${minAmount.toFixed(4)}. You need ${(Math.ceil((minAmount + fee) / walletSettings.pointsToUsdRate)).toLocaleString()} points.</div>
                </div>`;
            calculationDiv.classList.remove('d-none');
            return;
        }

        calculationDiv.innerHTML = `
            <div class="d-grid gap-2">
                <div class="d-flex justify-content-between"><span class="text-muted">Points</span><span class="text-white fw-semibold">${points.toLocaleString()}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">USD Value</span><span class="text-white fw-semibold">$${usdValue.toFixed(4)}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Fee (${feePercent.toFixed(1)}%)</span><span class="text-white fw-semibold">$${fee.toFixed(4)}</span></div>
                <hr class="opacity-25">
                <div class="d-flex justify-content-between"><span class="text-muted fw-semibold">You'll Receive</span><span class="text-success fw-bold">$${netAmount.toFixed(4)}</span></div>
            </div>`;
        calculationDiv.classList.remove('d-none');
    } else {
        calculationDiv.classList.add('d-none');
        calculationDiv.innerHTML = '';
    }
}

const withdrawForm = document.getElementById('withdrawForm');
if (withdrawForm) {
    withdrawForm.addEventListener('submit', function(e) {
        const points = parseInt(document.getElementById('points').value) || 0;
        const method = document.getElementById('withdrawal_method').value;
        const currency = document.getElementById('currency').value;
        const faucetpayEmail = document.getElementById('faucetpay_email').value;
        const walletAddress = document.getElementById('wallet_address').value;

        if (points <= 0) {
            e.preventDefault();
            alert('Please enter a valid number of points to withdraw.');
            return;
        }

        if (!method) {
            e.preventDefault();
            alert('Please select a withdrawal method.');
            return;
        }

        if (!currency) {
            e.preventDefault();
            alert('Please select a currency.');
            return;
        }

        if (method === 'faucetpay') {
            if (!faucetpayEmail) {
                e.preventDefault();
                alert('Please enter your FaucetPay email address.');
                return;
            }
            if (!faucetpayEmail.includes('@')) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
        } else if (method === 'direct_wallet') {
            if (!walletAddress) {
                e.preventDefault();
                alert('Please enter your wallet address.');
                return;
            }
            if (walletAddress.length < 10) {
                e.preventDefault();
                alert('Please enter a valid wallet address.');
                return;
            }
        }

        const usdValue = points * walletSettings.pointsToUsdRate;
        const feePercent = method === 'faucetpay' ? walletSettings.faucetpayFeePercent : walletSettings.withdrawalFeePercent;
        const fee = usdValue * (feePercent / 100);
        const netAmount = usdValue - fee;

        if (!confirm(`Confirm withdrawal of ${points.toLocaleString()} points (≈$${netAmount.toFixed(4)} after fees) to your ${method === 'faucetpay' ? 'FaucetPay account' : 'wallet'}?`)) {
            e.preventDefault();
        }
    });
}

function copyReferralLink(event) {
    const referralLink = document.getElementById('referralLink');
    referralLink.select();
    referralLink.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(referralLink.value);

    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    button.classList.remove('btn-outline-glass');
    button.classList.add('btn-success');

    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.add('btn-outline-glass');
        button.classList.remove('btn-success');
    }, 2000);
}
</script>

<?php include 'includes/footer.php'; ?>
