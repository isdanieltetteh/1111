<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit();
}

$page_title = 'Credits Purchase Successful - ' . SITE_NAME;
$page_description = 'Your credits have been purchased successfully.';
$current_page = 'dashboard';
include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 text-center animate-fade-in" data-aos="fade-up">
                <div class="mx-auto mb-4 rounded-circle bg-success bg-opacity-25 text-success d-flex align-items-center justify-content-center" style="width: 90px; height: 90px;">
                    <i class="fas fa-badge-check fa-2x"></i>
                </div>
                <h1 class="text-white fw-bold mb-2">Credits Locked In</h1>
                <p class="text-muted mb-0">Thanks for fueling your campaigns — your balance updates in real time.</p>
            </div>
            <div class="dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center g-4" data-aos="fade-up" data-aos-delay="100">
                <div class="col-12 col-lg-8">
                    <div class="glass-card p-4 p-lg-5 text-center">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-25 text-success mb-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-check fa-2x"></i>
                        </div>
                        <h2 class="text-white mb-2">Payment Successful!</h2>
                        <p class="text-muted mb-4">Your credits are now available to activate promotions, unlock premium submissions, and boost new discoveries.</p>
                        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                            <a href="dashboard.php" class="btn btn-theme btn-gradient">
                                <i class="fas fa-gauge-high me-2"></i>Back to Dashboard
                            </a>
                            <a href="promote-sites.php" class="btn btn-outline-light">
                                <i class="fas fa-rocket me-2"></i>Launch a Campaign
                            </a>
                        </div>

                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 mt-4 text-start">
                            <h3 class="h6 text-white mb-3">What to do next</h3>
                            <ul class="text-muted small mb-0 d-grid gap-2 ps-3">
                                <li>Promote existing listings with boosted placements.</li>
                                <li>Submit new projects and unlock premium review perks.</li>
                                <li>Skip backlink requirements on elite-tier campaigns.</li>
                                <li>Share referral links to earn bonus credits back.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="sticky-lg-top" style="top: 100px;">
                        <div class="glass-card p-4 mb-4">
                            <h3 class="h6 text-white text-uppercase mb-3">Need help?</h3>
                            <p class="text-muted small mb-3">Our support desk can assist with invoice requests, payment confirmations, or campaign strategy.</p>
                            <a href="support-tickets.php" class="btn btn-theme btn-sm w-100"><i class="fas fa-life-ring me-2"></i>Contact Support</a>
                        </div>
                        <div class="dev-slot1 mb-4">Sidebar Ad 300x600</div>
                        <div class="glass-card p-4">
                            <h4 class="h6 text-white mb-2">Track your balance</h4>
                            <p class="text-muted small mb-3">Visit your wallet to verify credits, redeem coupons, and monitor transactions.</p>
                            <a href="wallet.php" class="btn btn-outline-light btn-sm w-100"><i class="fas fa-wallet me-2"></i>Open Wallet</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dev-slot2 mt-4">Inline Ad 728x90</div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
