<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();
$success_message = '';
$error_message = '';

// Credit packages
$credit_packages = [
    ['amount1' => 5,   'price' => 6.00,  'bonus' => 1.00, 'popular' => false],
    ['amount1' => 10,  'price' => 15.00, 'bonus' => 5.00, 'popular' => false],
    ['amount1' => 20,  'price' => 35.00, 'bonus' => 15.00, 'popular' => true],
    ['amount1' => 50,  'price' => 95.00, 'bonus' => 45.00, 'popular' => false], 
    ['amount1' => 100, 'price' => 200.00,'bonus' => 100.00,'popular' => false], 
    ['amount1' => 200, 'price' => 450.00,'bonus' => 250.00,'popular' => false]
   
];

$page_title = 'Buy Credits - ' . SITE_NAME;
$page_description = 'Purchase credits to promote your sites and access premium features.';
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
                                    <li class="breadcrumb-item active" aria-current="page">Buy Credits</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Top Up Your Balance</h1>
                        <p class="text-muted mb-0">Secure premium placements, unlock pro tooling, and scale campaigns with instant credit packs.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-credit-card"></i>
                            <span>Encrypted checkout</span>
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

            <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <h2 class="h4 text-white mb-2">Current Credit Balance</h2>
                        <p class="text-muted mb-0">Keep a healthy reserve so you can activate campaigns, unlock premium reviews, and spotlight new drops instantly.</p>
                    </div>
                    <div class="col-lg-4">
                        <div class="glass-balance-card text-center p-4">
                            <span class="balance-label text-muted text-uppercase">Available Credits</span>
                            <div class="display-6 fw-bold text-success mt-2">$<?php echo number_format($user['credits'], 4); ?></div>
                            <p class="text-muted small mb-0 mt-2">Updated in real-time after each purchase.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                <?php foreach ($credit_packages as $index => $package): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="credit-pack-card <?php echo $package['popular'] ? 'featured' : ''; ?> h-100">
                            <?php if ($package['popular']): ?>
                                <div class="ribbon">Most Popular</div>
                            <?php endif; ?>
                            <div class="d-flex flex-column gap-3 text-center">
                                <div class="pack-value">
                                    <span class="label text-uppercase text-muted">Pay For</span>
                                    <span class="amount display-6 fw-bold">$<?php echo number_format($package['amount1'], 0); ?></span>
                                    <span class="caption text-muted">Credits</span>
                                </div>
                                <?php if ($package['bonus'] > 0): ?>
                                    <div class="bonus-chip">
                                        <i class="fas fa-gift me-2"></i>Bonus +$<?php echo number_format($package['bonus'], 2); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="pricing">
                                    <span class="price h2 fw-bold text-info">$<?php echo number_format($package['price'], 2); ?></span>
                                    <?php if ($package['bonus'] > 0): ?>
                                        <small class="d-block text-success mt-1">You receive $<?php echo number_format($package['amount1'] + $package['bonus'], 2); ?> value</small>
                                    <?php endif; ?>
                                </div>
                                <form action="https://faucetpay.io/merchant/webscr" method="POST" target="_blank" class="d-grid gap-3">
                                    <input type="hidden" name="merchant_username" value="<?php echo htmlspecialchars(FAUCETPAY_MERCHANT_USERNAME); ?>">
                                    <input type="hidden" name="item_description" value="Credits Purchase - <?php echo SITE_NAME; ?>">
                                    <input type="hidden" name="amount1" value="<?php echo $package['amount1']; ?>">
                                    <input type="hidden" name="currency1" value="USD">
                                    <input type="hidden" name="currency2" value="">
                                    <input type="hidden" name="custom" value="credits_<?php echo $user['id']; ?>_<?php echo $package['amount1'] + $package['bonus']; ?>">
                                    <input type="hidden" name="callback_url" value="<?php echo SITE_URL; ?>/credits-ipn.php">
                                    <input type="hidden" name="success_url" value="<?php echo SITE_URL; ?>/deposit-success.php?method=faucetpay">
                                    <input type="hidden" name="cancel_url" value="<?php echo SITE_URL; ?>/buy-credits.php">
                                    <button type="submit" class="btn btn-theme btn-gradient">Purchase Credits</button>
                                </form>
                                <p class="text-muted small mb-0">Processed via FaucetPay. Keep this tab open until payment confirms.</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-4" data-aos="fade-up" data-aos-delay="150">
                <div class="col-xl-8">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                            <div>
                                <h2 class="h4 text-white mb-1">What Credits Unlock</h2>
                                <p class="text-muted mb-0">Fuel growth with monetization tools trusted by top crypto publishers.</p>
                            </div>
                            <div class="option-chip">
                                <i class="fas fa-shield-halved"></i>
                                <span>No expiry on unused credits</span>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="feature-tile">
                                    <div class="icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-bullhorn"></i></div>
                                    <h3 class="h5 text-white">Site Promotions</h3>
                                    <ul class="text-muted small mb-0">
                                        <li>Secure sponsored placements in top slots</li>
                                        <li>Boost visibility during product launches</li>
                                        <li>Bypass algorithm queues with instant exposure</li>
                                        <li>Highlight success badges on every listing</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-tile">
                                    <div class="icon bg-success bg-opacity-10 text-success"><i class="fas fa-sparkles"></i></div>
                                    <h3 class="h5 text-white">Premium Features</h3>
                                    <ul class="text-muted small mb-0">
                                        <li>Activate referral multipliers for passive income</li>
                                        <li>Request fast-track reviews for new launches</li>
                                        <li>Unlock backlink flexibility during audits</li>
                                        <li>Showcase VIP trust indicators in profiles</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="d-flex flex-column gap-4">
                        <div class="glass-card p-4">
                            <h3 class="h5 text-white mb-3">Need Help Purchasing?</h3>
                            <p class="text-muted small mb-4">Reach out if you need a custom invoice, bulk discounting, or manual credit adjustments.</p>
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

<style>
.credit-pack-card {
    position: relative;
    background: rgba(15, 23, 42, 0.68);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1.5rem;
    padding: 2rem 1.5rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}

.credit-pack-card:hover {
    transform: translateY(-6px);
    border-color: rgba(59, 130, 246, 0.45);
    box-shadow: var(--shadow-soft);
}

.credit-pack-card.featured {
    border-color: rgba(251, 191, 36, 0.45);
    box-shadow: 0 0 40px rgba(251, 191, 36, 0.15);
}

.credit-pack-card .ribbon {
    position: absolute;
    top: 18px;
    right: 18px;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #0b1120;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
}

.credit-pack-card .pack-value .label {
    font-size: 0.75rem;
    letter-spacing: 0.08em;
}

.credit-pack-card .pack-value .caption {
    font-size: 0.9rem;
    letter-spacing: 0.05em;
}

.credit-pack-card .bonus-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    font-weight: 600;
    margin: 0 auto;
}

.credit-pack-card .pricing .price {
    display: block;
}

.feature-tile {
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 1.25rem;
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.feature-tile .icon {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    font-size: 1.5rem;
}

.feature-tile ul {
    padding-left: 1.1rem;
    margin-bottom: 0;
}

.feature-tile ul li {
    margin-bottom: 0.35rem;
}
</style>

<?php include 'includes/footer.php'; ?>
