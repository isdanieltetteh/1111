<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit();
}

$method = $_GET['method'] ?? 'faucetpay';
$transaction_id = $_GET['transaction_id'] ?? '';

// Get recent deposit transaction
$recent_deposit_query = "SELECT * FROM deposit_transactions 
                        WHERE user_id = :user_id AND payment_method = :method 
                        ORDER BY created_at DESC LIMIT 1";
$recent_deposit_stmt = $db->prepare($recent_deposit_query);
$recent_deposit_stmt->bindParam(':user_id', $_SESSION['user_id']);
$recent_deposit_stmt->bindParam(':method', $method);
$recent_deposit_stmt->execute();
$recent_deposit = $recent_deposit_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Deposit Successful - ' . SITE_NAME;
$page_description = 'Your credits have been added successfully.';
$current_page = 'dashboard';
include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                    <div>
                        <h1 class="text-white fw-bold mb-2">Deposit Confirmed</h1>
                        <p class="text-muted mb-0">Funds have been added to your wallet and are ready for campaign activation.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="rounded-pill bg-success bg-opacity-25 text-success px-4 py-2 fw-semibold">
                            <i class="fas fa-receipt me-2"></i><?php echo strtoupper($method); ?> reference
                        </div>
                    </div>
                </div>
            </div>
            <div class="dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
                <div class="col-12 col-xl-8">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-circle bg-success bg-opacity-25 text-success d-inline-flex align-items-center justify-content-center fs-4" style="width: 60px; height: 60px;">
                                <i class="fas fa-circle-check"></i>
                            </div>
                            <div>
                                <h2 class="h4 text-white mb-1">Credits added successfully</h2>
                                <p class="text-muted mb-0">Review your deposit summary below and continue building your presence.</p>
                            </div>
                        </div>

                        <?php if ($recent_deposit): ?>
                        <div class="rounded-4 border border-success border-opacity-25 bg-success bg-opacity-10 p-4 mb-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                                    <div>
                                        <span class="text-muted text-uppercase small">Amount</span>
                                        <div class="fs-4 fw-bold text-success mt-1">$<?php echo number_format($recent_deposit['amount'], 4); ?></div>
                                    </div>
                                    <div>
                                        <span class="text-muted text-uppercase small">Status</span>
                                        <span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-25 ms-md-2">
                                            <?php echo $recent_deposit['status'] === 'completed' ? 'Completed' : 'Processing'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row row-cols-1 row-cols-md-2 g-3">
                                    <div class="col">
                                        <div class="rounded-3 bg-dark bg-opacity-25 p-3 h-100">
                                            <span class="text-muted small text-uppercase">Method</span>
                                            <p class="text-white fw-semibold mb-0 mt-1"><?php echo ucfirst($recent_deposit['payment_method']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="rounded-3 bg-dark bg-opacity-25 p-3 h-100">
                                            <span class="text-muted small text-uppercase">Date</span>
                                            <p class="text-white fw-semibold mb-0 mt-1"><?php echo date('M j, Y g:i A', strtotime($recent_deposit['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($transaction_id)): ?>
                                    <div class="mt-3 text-muted small">
                                        <i class="fas fa-hashtag me-2"></i>Transaction ID: <span class="text-white"><?php echo htmlspecialchars($transaction_id); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <p class="text-muted mb-4">Your wallet reflects the new balance immediately. Use your credits to book premium placements, schedule spotlight campaigns, or accelerate review pipelines.</p>

                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <a href="wallet.php" class="btn btn-theme btn-gradient flex-fill"><i class="fas fa-wallet me-2"></i>View Wallet</a>
                            <a href="promote-sites.php" class="btn btn-outline-light flex-fill"><i class="fas fa-rocket me-2"></i>Promote Sites</a>
                        </div>

                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 mt-4">
                            <h3 class="h6 text-white mb-3">Next steps</h3>
                            <ul class="text-muted small mb-0 d-grid gap-2 ps-3">
                                <li>Allocate credits to boosted homepage or ranking slots.</li>
                                <li>Upgrade submissions with fast-track reviews and trust badges.</li>
                                <li>Redeem coupons or referral bonuses for additional balance.</li>
                                <li>Track detailed credit history inside your wallet dashboard.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="sticky-lg-top" style="top: 100px;">
                        <div class="glass-card p-4 mb-4">
                            <h3 class="h6 text-white mb-3">Need a receipt?</h3>
                            <p class="text-muted small mb-3">If you require invoices or have questions about this transaction, our support team is on standby.</p>
                            <a href="support-tickets.php" class="btn btn-theme btn-sm w-100"><i class="fas fa-headset me-2"></i>Open Support Ticket</a>
                        </div>
                        <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                        <div class="glass-card p-4">
                            <h4 class="h6 text-white mb-2">Explore campaigns</h4>
                            <p class="text-muted small mb-3">Visit the promotions desk to schedule placements that convert.</p>
                            <a href="promote-sites.php" class="btn btn-outline-light btn-sm w-100"><i class="fas fa-bullhorn me-2"></i>Go to Promotions</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dev-slot2 mt-4">Inline Ad 728x90</div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
