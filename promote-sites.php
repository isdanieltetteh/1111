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
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Check for success parameter in URL
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get user's approved sites
$user_sites_query = "SELECT s.*, 
                     CASE 
                         WHEN s.is_sponsored = 1 AND s.sponsored_until > NOW() THEN 'sponsored'
                         WHEN s.is_boosted = 1 AND s.boosted_until > NOW() THEN 'boosted'
                         ELSE 'normal'
                     END as promotion_status,
                     s.sponsored_until,
                     s.boosted_until
                     FROM sites s 
                     WHERE s.submitted_by = :user_id AND s.is_approved = 1
                     ORDER BY s.created_at DESC";
$user_sites_stmt = $db->prepare($user_sites_query);
$user_sites_stmt->bindParam(':user_id', $user_id);
$user_sites_stmt->execute();
$user_sites = $user_sites_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pricing
$promotion_pricing_query = "SELECT * FROM promotion_pricing WHERE is_active = 1 ORDER BY promotion_type, duration_days";
$promotion_pricing_stmt = $db->prepare($promotion_pricing_query);
$promotion_pricing_stmt->execute();
$promotion_pricing = $promotion_pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

$feature_pricing_query = "SELECT * FROM feature_pricing WHERE is_active = 1";
$feature_pricing_stmt = $db->prepare($feature_pricing_query);
$feature_pricing_stmt->execute();
$feature_pricing = $feature_pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative arrays
$promotion_prices = [];
foreach ($promotion_pricing as $price) {
    $promotion_prices[$price['promotion_type']][$price['duration_days']] = $price['price'];
}

$feature_prices = [];
foreach ($feature_pricing as $price) {
    $feature_prices[$price['feature_type']] = $price['price'];
}

// Handle promotion purchase
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'promote_site') {
        // Check for form token to prevent duplicate submissions
        if (!isset($_POST['form_token']) || (isset($_SESSION['last_form_token']) && $_POST['form_token'] === $_SESSION['last_form_token'])) {
            $error_message = 'Duplicate form submission detected. Please try again.';
        } else {
            // Store the token in session
            $_SESSION['last_form_token'] = $_POST['form_token'];
            
            $site_id = intval($_POST['site_id']);
            $promotion_type = $_POST['promotion_type'] ?? '';
            $duration = intval($_POST['duration'] ?? 0);
            
            // Verify site ownership
            $verify_query = "SELECT id FROM sites WHERE id = :site_id AND submitted_by = :user_id AND is_approved = 1";
            $verify_stmt = $db->prepare($verify_query);
            $verify_stmt->bindParam(':site_id', $site_id);
            $verify_stmt->bindParam(':user_id', $user_id);
            $verify_stmt->execute();
            
            if ($verify_stmt->rowCount() === 0) {
                $error_message = 'Site not found or you do not have permission to promote it.';
            } else {
                $cost = $promotion_prices[$promotion_type][$duration] ?? 0;
                
                if ($user['credits'] < $cost) {
                    $error_message = "Insufficient credits. You need $" . number_format($cost, 2) . " but have $" . number_format($user['credits'], 2);
                } else {
                    try {
                        $db->beginTransaction();
                        
                        // Deduct credits
                        $deduct_query = "UPDATE users SET credits = credits - :cost WHERE id = :user_id";
                        $deduct_stmt = $db->prepare($deduct_query);
                        $deduct_stmt->bindParam(':cost', $cost);
                        $deduct_stmt->bindParam(':user_id', $user_id);
                        $deduct_stmt->execute();
                        
                        // Add promotion
                        $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                        $promotion_query = "INSERT INTO site_promotions (site_id, user_id, promotion_type, amount_paid, duration_days, expires_at, payment_status) 
                                          VALUES (:site_id, :user_id, :promotion_type, :amount, :duration, :expires_at, 'completed')";
                        $promotion_stmt = $db->prepare($promotion_query);
                        $promotion_stmt->bindParam(':site_id', $site_id);
                        $promotion_stmt->bindParam(':user_id', $user_id);
                        $promotion_stmt->bindParam(':promotion_type', $promotion_type);
                        $promotion_stmt->bindParam(':amount', $cost);
                        $promotion_stmt->bindParam(':duration', $duration);
                        $promotion_stmt->bindParam(':expires_at', $expires_at);
                        $promotion_stmt->execute();
                        
                        // Update site status
                        $update_site = "UPDATE sites SET is_{$promotion_type} = 1, {$promotion_type}_until = :expires_at WHERE id = :site_id";
                        $update_stmt = $db->prepare($update_site);
                        $update_stmt->bindParam(':expires_at', $expires_at);
                        $update_stmt->bindParam(':site_id', $site_id);
                        $update_stmt->execute();
                        
                        $db->commit();
                        
                        // Store success message in session
                        $_SESSION['success_message'] = "Site promoted successfully! Your {$promotion_type} promotion is now active for {$duration} days.";
                        
                        // Redirect to prevent form resubmission
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                        exit();
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error_message = 'Error processing promotion. Please try again.';
                    }
                }
            }
        }
    }
}

function timeRemaining($datetime) {
    $time = strtotime($datetime) - time();
    if ($time <= 0) return 'Expired';
    
    $days = floor($time / 86400);
    $hours = floor(($time % 86400) / 3600);
    
    if ($days > 0) return $days . 'd ' . $hours . 'h';
    return $hours . 'h';
}

$page_title = 'Promote Sites - ' . SITE_NAME;
$page_description = 'Promote your sites with sponsored and boosted listings to increase visibility and traffic.';
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
                                    <li class="breadcrumb-item active" aria-current="page">Promotions</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Boost Your Visibility</h1>
                        <p class="text-muted mb-0">Upgrade listings to premium placements and stay ahead in the crypto discovery race.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-rocket"></i>
                            <span>Sponsored &amp; Boosted tiers</span>
                        </div>
                        <a href="buy-credits.php" class="btn btn-theme btn-gradient mt-3">
                            <i class="fas fa-credit-card me-2"></i>Buy Credits
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
                    <a href="my-ads.php" class="btn btn-theme btn-outline-glass btn-sm">
                        <i class="fas fa-chart-line me-2"></i>View Campaigns
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

            <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <h2 class="h4 text-white mb-2">Your Credit Balance</h2>
                        <p class="text-muted mb-0">Credits power every upgrade, sponsored slot, and boosted placement across the directory.</p>
                    </div>
                    <div class="col-lg-4">
                        <div class="glass-balance-card text-center p-4">
                            <span class="balance-label text-muted text-uppercase">Available Credits</span>
                            <div class="display-6 fw-bold text-success mt-2">$<?php echo number_format($user['credits'], 2); ?></div>
                            <div class="d-grid gap-2 mt-3">
                                <a href="buy-credits.php" class="btn btn-theme btn-outline-glass">
                                    <i class="fas fa-plus-circle me-2"></i>Buy More Credits
                                </a>
                                <a href="redeem-coupon.php" class="btn btn-theme btn-soft">
                                    <i class="fas fa-ticket-simple me-2"></i>Redeem Coupon
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up" data-aos-delay="100">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h4 text-white mb-1">Promotion Plans</h2>
                        <p class="text-muted mb-0">Choose the tier that matches your growth goals. Sponsorships secure the top, boosts own the spotlight.</p>
                    </div>
                    <div class="option-chip">
                        <i class="fas fa-shield-halved"></i>
                        <span>Fraud-protected billing</span>
                    </div>
                </div>
                <div class="row g-4 row-cols-1 row-cols-lg-2">
                    <div class="col">
                        <div class="promotion-tier-card h-100">
                            <div class="tier-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-crown"></i></div>
                            <h3 class="h4 text-white mb-1">Sponsored</h3>
                            <p class="text-muted mb-4">Command the top 5 with unrivaled visibility and a golden badge.</p>
                            <ul class="list-unstyled text-muted small mb-4">
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-check text-warning"></i><span>Guaranteed top-five placement</span></li>
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-check text-warning"></i><span>Bypasses algorithmic ranking</span></li>
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-check text-warning"></i><span>Premium sponsored badge</span></li>
                                <li class="d-flex gap-2"><i class="fas fa-check text-warning"></i><span>Rotates with other sponsors for fairness</span></li>
                            </ul>
                            <div class="row g-2">
                                <div class="col-12 col-sm-4">
                                    <div class="duration-pill">
                                        <span class="label">7 Days</span>
                                        <span class="value">$<?php echo number_format($promotion_prices['sponsored'][7] ?? 0, 2); ?></span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="duration-pill highlight">
                                        <span class="label">30 Days</span>
                                        <span class="value">$<?php echo number_format($promotion_prices['sponsored'][30] ?? 0, 2); ?></span>
                                        <span class="tag">Most Popular</span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="duration-pill">
                                        <span class="label">90 Days</span>
                                        <span class="value">$<?php echo number_format($promotion_prices['sponsored'][90] ?? 0, 2); ?></span>
                                        <span class="tag">Best Value</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="promotion-tier-card h-100">
                            <div class="tier-icon bg-info bg-opacity-10 text-info"><i class="fas fa-rocket"></i></div>
                            <h3 class="h4 text-white mb-1">Boosted</h3>
                            <p class="text-muted mb-4">Rise above organic listings with priority placement and a vibrant badge.</p>
                            <ul class="list-unstyled text-muted small mb-4">
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-check text-info"></i><span>Priority slot just below sponsors</span></li>
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-check text-info"></i><span>Boosted badge for quick recognition</span></li>
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-check text-info"></i><span>Ideal for campaign bursts</span></li>
                                <li class="d-flex gap-2"><i class="fas fa-check text-info"></i><span>Flexible durations for testing</span></li>
                            </ul>
                            <div class="row g-2">
                                <div class="col-12 col-sm-4">
                                    <div class="duration-pill">
                                        <span class="label">7 Days</span>
                                        <span class="value">$<?php echo number_format($promotion_prices['boosted'][7] ?? 0, 2); ?></span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="duration-pill highlight">
                                        <span class="label">30 Days</span>
                                        <span class="value">$<?php echo number_format($promotion_prices['boosted'][30] ?? 0, 2); ?></span>
                                        <span class="tag">Most Popular</span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="duration-pill">
                                        <span class="label">90 Days</span>
                                        <span class="value">$<?php echo number_format($promotion_prices['boosted'][90] ?? 0, 2); ?></span>
                                        <span class="tag">Best Value</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-xl-8">
                    <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up" data-aos-delay="200">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                            <div>
                                <h2 class="h4 text-white mb-1">Your Approved Sites</h2>
                                <p class="text-muted mb-0">Activate sponsorships or boosts directly from your portfolio.</p>
                            </div>
                            <div class="option-chip">
                                <i class="fas fa-bolt"></i>
                                <span>Real-time promotion status</span>
                            </div>
                        </div>

                        <?php if (!empty($user_sites)): ?>
                            <div class="d-flex flex-column gap-4">
                                <?php foreach ($user_sites as $site): ?>
                                    <?php
                                    $status_class = '';
                                    if ($site['promotion_status'] === 'sponsored') {
                                        $status_class = 'tier-active-sponsor';
                                    } elseif ($site['promotion_status'] === 'boosted') {
                                        $status_class = 'tier-active-boost';
                                    }
                                    ?>
                                    <div class="promotion-site-card <?php echo $status_class; ?>">
                                        <div class="d-flex flex-column flex-lg-row gap-4 align-items-start">
                                            <div class="d-flex gap-3 align-items-start flex-grow-1">
                                                <?php if (!empty($site['logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($site['logo']); ?>"
                                                         alt="<?php echo htmlspecialchars($site['name']); ?> logo"
                                                         class="site-logo rounded-4 border border-info-subtle border-opacity-50">
                                                <?php else: ?>
                                                    <div class="site-logo placeholder-logo">
                                                        <i class="fas fa-globe"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                        <h3 class="h5 text-white mb-0"><?php echo htmlspecialchars($site['name']); ?></h3>
                                                        <?php if ($site['promotion_status'] === 'sponsored'): ?>
                                                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold"><i class="fas fa-crown me-1"></i>Sponsored</span>
                                                        <?php elseif ($site['promotion_status'] === 'boosted'): ?>
                                                            <span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-semibold"><i class="fas fa-rocket me-1"></i>Boosted</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-muted small mb-2">
                                                        <?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?> · <?php echo number_format($site['total_upvotes']); ?> upvotes · <?php echo number_format($site['views']); ?> views
                                                    </p>
                                                    <?php if ($site['promotion_status'] !== 'normal'): ?>
                                                        <p class="text-warning small mb-0"><i class="fas fa-clock me-1"></i>Expires in <?php echo timeRemaining($site['promotion_status'] === 'sponsored' ? $site['sponsored_until'] : $site['boosted_until']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-column align-items-stretch gap-2 w-100 w-lg-auto">
                                                <?php if ($site['promotion_status'] === 'normal'): ?>
                                                    <button type="button"
                                                            class="btn btn-theme btn-gradient"
                                                            onclick="promoteSite(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars(addslashes($site['name'])); ?>', 'sponsored')">
                                                        <i class="fas fa-crown me-2"></i>Sponsor Site
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-theme btn-outline-glass"
                                                            onclick="promoteSite(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars(addslashes($site['name'])); ?>', 'boosted')">
                                                        <i class="fas fa-rocket me-2"></i>Boost Site
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-success-subtle text-success fw-semibold text-center">Promotion Active</span>
                                                    <button type="button"
                                                            class="btn btn-theme btn-outline-glass"
                                                            onclick="extendPromotion(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars(addslashes($site['name'])); ?>', '<?php echo $site['promotion_status']; ?>')">
                                                        <i class="fas fa-redo me-2"></i>Extend Promotion
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state text-center py-5">
                                <div class="empty-icon"><i class="fas fa-folder-open"></i></div>
                                <h3 class="text-white">No Approved Sites Yet</h3>
                                <p class="text-muted mb-4">Submit your first listing to unlock promotion options and earn visibility.</p>
                                <a href="submit-site.php" class="btn btn-theme btn-gradient">
                                    <i class="fas fa-plus-circle me-2"></i>Submit a Site
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="d-flex flex-column gap-4">
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="250">
                            <h3 class="h5 text-white mb-3">Promotion Tips</h3>
                            <ul class="list-unstyled text-muted small mb-0">
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-chart-line text-info"></i><span>Activate boosts during major news cycles to capture traffic spikes.</span></li>
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-bullseye text-info"></i><span>Pair sponsorships with fresh reviews to climb community rankings.</span></li>
                                <li class="d-flex gap-2 mb-2"><i class="fas fa-shield-halved text-info"></i><span>Keep trust scores healthy—sponsored slots highlight compliance checks.</span></li>
                                <li class="d-flex gap-2"><i class="fas fa-ticket-simple text-info"></i><span>Stack coupons with large credit buys for maximum ROI.</span></li>
                            </ul>
                        </div>
                        <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="300">
                            <h3 class="h5 text-white mb-3">Need Campaign Support?</h3>
                            <p class="text-muted small mb-4">Our monetization desk reviews creatives, suggests placements, and helps you optimize conversions.</p>
                            <a href="support-tickets.php" class="btn btn-theme btn-outline-glass w-100">
                                <i class="fas fa-life-ring me-2"></i>Contact Support
                            </a>
                        </div>
                        <div class="dev-slot1">Sidebar Ad 300x600</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="aurora-modal" id="promotionModal" aria-hidden="true">
    <div class="aurora-modal-backdrop" onclick="closeModal()"></div>
    <div class="aurora-modal-dialog" role="dialog" aria-modal="true">
        <div class="aurora-modal-header">
            <h3 class="aurora-modal-title" id="promotionModalTitle">Promote Site</h3>
            <button type="button" class="aurora-modal-close" onclick="closeModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="promotionForm" class="aurora-modal-body">
            <input type="hidden" name="action" value="promote_site">
            <input type="hidden" name="site_id" id="promoteSiteId">
            <input type="hidden" name="promotion_type" id="promoteType">
            <input type="hidden" name="form_token" value="<?php echo bin2hex(random_bytes(16)); ?>">

            <div class="text-center mb-4">
                <h4 class="text-white mb-1" id="promoteSiteName"></h4>
                <p class="text-muted small mb-0" id="promoteDescription"></p>
            </div>

            <div class="mb-4">
                <label class="form-label text-uppercase small text-muted">Select Duration</label>
                <div class="d-flex flex-column gap-2" id="durationOptions">
                    <!-- Populated by JavaScript -->
                </div>
            </div>

            <div class="glass-card p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted text-uppercase small">Total Cost</span>
                    <span id="promotionCost" class="h4 text-info mb-0">$0.00</span>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-3">
                <button type="button" class="btn btn-theme btn-outline-glass w-100" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-theme btn-gradient w-100">Purchase Promotion</button>
            </div>
        </form>
    </div>
</div>

<style>
.promotion-tier-card {
    background: rgba(15, 23, 42, 0.65);
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 1.5rem;
    padding: 2rem;
    height: 100%;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}

.promotion-tier-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(56, 189, 248, 0.08), rgba(59, 130, 246, 0.04));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.promotion-tier-card:hover {
    transform: translateY(-6px);
    border-color: rgba(148, 163, 184, 0.35);
    box-shadow: var(--shadow-soft);
}

.promotion-tier-card:hover::after {
    opacity: 1;
}

.tier-icon {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    font-size: 1.5rem;
    margin-bottom: 1.25rem;
}

.duration-pill {
    background: rgba(15, 23, 42, 0.7);
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 1rem;
    padding: 0.75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    position: relative;
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
}

.duration-pill .label {
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(226, 232, 240, 0.7);
}

.duration-pill .value {
    font-weight: 700;
    font-size: 1.15rem;
    color: #38bdf8;
}

.duration-pill .tag {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #fbbf24;
}

.duration-pill.highlight {
    border-color: rgba(59, 130, 246, 0.45);
    box-shadow: 0 0 25px rgba(59, 130, 246, 0.1);
}

.duration-pill:hover {
    transform: translateY(-4px);
    border-color: rgba(148, 163, 184, 0.35);
    box-shadow: var(--shadow-soft);
}

.glass-balance-card {
    background: rgba(15, 23, 42, 0.7);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1.25rem;
}

.promotion-site-card {
    background: rgba(15, 23, 42, 0.65);
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 1.25rem;
    padding: 1.75rem;
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}

.promotion-site-card:hover {
    transform: translateY(-4px);
    border-color: rgba(56, 189, 248, 0.35);
    box-shadow: var(--shadow-soft);
}

.promotion-site-card .site-logo {
    width: 78px;
    height: 78px;
    object-fit: cover;
}

.promotion-site-card .placeholder-logo {
    width: 78px;
    height: 78px;
    background: rgba(56, 189, 248, 0.12);
    border-radius: 1.25rem;
    display: grid;
    place-items: center;
    color: #38bdf8;
    font-size: 1.5rem;
}

.tier-active-sponsor {
    border-color: rgba(251, 191, 36, 0.45);
    box-shadow: 0 0 25px rgba(251, 191, 36, 0.1);
}

.tier-active-boost {
    border-color: rgba(56, 189, 248, 0.4);
    box-shadow: 0 0 25px rgba(56, 189, 248, 0.12);
}

.empty-state {
    background: rgba(15, 23, 42, 0.65);
    border: 1px dashed rgba(148, 163, 184, 0.25);
    border-radius: 1.5rem;
}

.empty-state .empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 20px;
    background: rgba(56, 189, 248, 0.12);
    display: grid;
    place-items: center;
    color: #38bdf8;
    font-size: 1.5rem;
    margin: 0 auto 1.5rem;
}

.aurora-modal {
    position: fixed;
    inset: 0;
    display: none;
    z-index: 1080;
}

.aurora-modal.active {
    display: block;
}

.aurora-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 6, 23, 0.75);
    backdrop-filter: blur(12px);
}

.aurora-modal-dialog {
    position: relative;
    margin: 5vh auto;
    max-width: 520px;
    background: rgba(15, 23, 42, 0.92);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1.5rem;
    padding: 1.5rem;
    box-shadow: 0 40px 120px rgba(15, 23, 42, 0.45);
}

.aurora-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.aurora-modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #f8fafc;
}

.aurora-modal-close {
    border: none;
    background: transparent;
    color: rgba(226, 232, 240, 0.7);
    font-size: 1.1rem;
    transition: color 0.2s ease, transform 0.2s ease;
}

.aurora-modal-close:hover {
    color: #f87171;
    transform: rotate(90deg);
}

.aurora-modal-body {
    display: flex;
    flex-direction: column;
}

.duration-option-tile {
    position: relative;
}

.duration-option-tile input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.duration-option-visual {
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1rem;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
    background: rgba(15, 23, 42, 0.75);
}

.duration-option-visual .info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.duration-option-visual .info span {
    display: block;
}

.duration-option-visual .duration-label {
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(226, 232, 240, 0.65);
}

.duration-option-visual .duration-value {
    font-size: 1.15rem;
    font-weight: 700;
    color: #38bdf8;
}

.duration-option-visual .duration-tag {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #fbbf24;
    letter-spacing: 0.08em;
}

.duration-option-visual .checkmark {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid rgba(148, 163, 184, 0.35);
    display: grid;
    place-items: center;
    color: transparent;
    transition: border-color 0.25s ease, color 0.25s ease, background 0.25s ease;
}

.duration-option-tile input:checked + .duration-option-visual {
    border-color: rgba(59, 130, 246, 0.65);
    box-shadow: 0 0 35px rgba(59, 130, 246, 0.18);
    transform: translateY(-2px);
}

.duration-option-tile input:checked + .duration-option-visual .checkmark {
    border-color: rgba(59, 130, 246, 0.75);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.9), rgba(37, 99, 235, 0.9));
    color: #fff;
}

@media (max-width: 575.98px) {
    .aurora-modal-dialog {
        margin: 4vh 1rem;
        padding: 1.25rem;
    }
}
</style>

<script>
const promotionPrices = <?php echo json_encode($promotion_prices); ?>;

function promoteSite(siteId, siteName, promotionType) {
    document.getElementById('promoteSiteId').value = siteId;
    document.getElementById('promoteType').value = promotionType;
    document.getElementById('promoteSiteName').textContent = siteName;
    document.getElementById('promotionModalTitle').textContent = promotionType === 'sponsored' ? 'Sponsor Site' : 'Boost Site';

    const descriptions = {
        sponsored: 'Appear in the top 5 results across the directory and dominate discovery feeds.',
        boosted: 'Leapfrog organic listings with priority placement below sponsored slots.'
    };
    document.getElementById('promoteDescription').textContent = descriptions[promotionType];

    const durationContainer = document.getElementById('durationOptions');
    const prices = promotionPrices[promotionType] || {};

    durationContainer.innerHTML = '';
    Object.keys(prices).sort((a, b) => parseInt(a, 10) - parseInt(b, 10)).forEach(duration => {
        const price = prices[duration];
        const isPopular = parseInt(duration, 10) === 30;

        const tile = document.createElement('label');
        tile.className = 'duration-option-tile';

        const input = document.createElement('input');
        input.type = 'radio';
        input.name = 'duration';
        input.value = duration;
        input.onchange = updatePromotionCost;

        const visual = document.createElement('div');
        visual.className = 'duration-option-visual';

        const infoWrapper = document.createElement('div');
        infoWrapper.className = 'info';

        const label = document.createElement('span');
        label.className = 'duration-label';
        label.textContent = `${duration} Day${parseInt(duration, 10) > 1 ? 's' : ''}`;

        const value = document.createElement('span');
        value.className = 'duration-value';
        value.textContent = `$${parseFloat(price).toFixed(2)}`;

        infoWrapper.appendChild(label);
        infoWrapper.appendChild(value);

        if (isPopular) {
            const tag = document.createElement('span');
            tag.className = 'duration-tag';
            tag.textContent = 'Most Popular';
            infoWrapper.appendChild(tag);
        }

        const checkmark = document.createElement('div');
        checkmark.className = 'checkmark';
        checkmark.innerHTML = '<i class="fas fa-check"></i>';

        visual.appendChild(infoWrapper);
        visual.appendChild(checkmark);

        tile.appendChild(input);
        tile.appendChild(visual);
        durationContainer.appendChild(tile);
    });

    document.getElementById('promotionCost').textContent = '$0.00';
    document.getElementById('promotionModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function extendPromotion(siteId, siteName, currentType) {
    promoteSite(siteId, siteName, currentType);
}

function updatePromotionCost() {
    const selectedDuration = document.querySelector('input[name="duration"]:checked');
    const promotionType = document.getElementById('promoteType').value;

    if (selectedDuration) {
        const duration = selectedDuration.value;
        const cost = promotionPrices?.[promotionType]?.[duration] ?? 0;
        document.getElementById('promotionCost').textContent = '$' + parseFloat(cost).toFixed(2);
    }
}

function closeModal() {
    document.getElementById('promotionModal').classList.remove('active');
    document.body.style.overflow = '';
}
</script>

<?php include 'includes/footer.php'; ?>
