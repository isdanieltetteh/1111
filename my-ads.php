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

// Get user's advertisements with dimensions
$ads_query = "SELECT ua.*,
              DATEDIFF(ua.end_date, NOW()) as days_remaining,
              (SELECT COUNT(*) FROM ad_impressions WHERE ad_id = ua.id) as total_impressions,
              (SELECT COUNT(*) FROM ad_clicks WHERE ad_id = ua.id) as total_clicks
              FROM user_advertisements ua
              WHERE ua.user_id = :user_id
              ORDER BY ua.created_at DESC";
$ads_stmt = $db->prepare($ads_query);
$ads_stmt->bindParam(':user_id', $user_id);
$ads_stmt->execute();
$user_ads = $ads_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total_ads,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_ads,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_ads,
    SUM(cost_paid + premium_cost) as total_spent,
    SUM(impression_count) as total_impressions,
    SUM(click_count) as total_clicks
    FROM user_advertisements
    WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$avg_ctr = $stats['total_impressions'] > 0
    ? ($stats['total_clicks'] / $stats['total_impressions']) * 100
    : 0;

function timeRemaining($datetime) {
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime) - time();
    if ($time <= 0) return 'Expired';
    
    $days = floor($time / 86400);
    $hours = floor(($time % 86400) / 3600);
    
    if ($days > 0) return $days . 'd ' . $hours . 'h';
    return $hours . 'h';
}

$page_title = 'My Campaigns - ' . SITE_NAME;
$page_description = 'Monitor every ad campaign you run on ' . SITE_NAME . ' with live performance insights.';
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
                                    <li class="breadcrumb-item active" aria-current="page">My Campaigns</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Advertise with Confidence</h1>
                        <p class="text-muted mb-0">Review pacing, placements, and returns for every sponsored slot you are running across the directory.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-bullhorn"></i>
                            <span><?php echo number_format($stats['total_ads']); ?> total campaigns</span>
                        </div>
                        <a href="buy-ads.php" class="btn btn-theme btn-gradient mt-3">
                            <i class="fas fa-plus me-2"></i>Create Campaign
                        </a>
                    </div>
                </div>
            </div>
            <div class="dev-slot mt-4">Campaign Banner 970x250</div>
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
                    <a href="wallet.php" class="btn btn-theme btn-outline-glass btn-sm">
                        <i class="fas fa-wallet me-2"></i>View Wallet
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
            <div class="row g-4 align-items-stretch mb-4">
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Active Campaigns</span>
                        <span class="glass-stat-value"><?php echo number_format($stats['active_ads']); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Pending Review</span>
                        <span class="glass-stat-value text-warning"><?php echo number_format($stats['pending_ads']); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Expired</span>
                        <span class="glass-stat-value text-muted"><?php echo number_format($stats['expired_ads']); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Total Spend</span>
                        <span class="glass-stat-value text-success">$<?php echo number_format($stats['total_spent'], 2); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Total Views</span>
                        <span class="glass-stat-value"><?php echo number_format($stats['total_impressions']); ?></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="glass-stat-tile h-100 text-center">
                        <span class="glass-stat-label">Avg. CTR</span>
                        <span class="glass-stat-value text-info"><?php echo number_format($avg_ctr, 2); ?>%</span>
                    </div>
                </div>
            </div>

            <div class="dev-slot2 mb-4">Inline Ad 728x90</div>

            <?php if (!empty($user_ads)): ?>
                <div class="d-grid gap-4">
                    <?php foreach ($user_ads as $ad): ?>
                        <?php
                        $status_badges = [
                            'active' => ['label' => 'Active', 'class' => 'badge rounded-pill bg-success-subtle text-success fw-semibold', 'icon' => 'fa-check-circle'],
                            'pending' => ['label' => 'Pending', 'class' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold', 'icon' => 'fa-hourglass-half'],
                            'expired' => ['label' => 'Expired', 'class' => 'badge rounded-pill bg-secondary-subtle text-muted fw-semibold', 'icon' => 'fa-calendar-times'],
                            'rejected' => ['label' => 'Rejected', 'class' => 'badge rounded-pill bg-danger-subtle text-danger-emphasis fw-semibold', 'icon' => 'fa-times-circle']
                        ];
                        $status_info = $status_badges[$ad['status']] ?? ['label' => ucfirst($ad['status']), 'class' => 'badge rounded-pill bg-secondary-subtle text-muted fw-semibold', 'icon' => 'fa-circle-info'];
                        $ad_type_badge = $ad['ad_type'] === 'banner'
                            ? '<span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-semibold"><i class="fas fa-image me-1"></i>Banner</span>'
                            : '<span class="badge rounded-pill bg-secondary-subtle text-muted fw-semibold"><i class="fas fa-align-left me-1"></i>Text Ad</span>';
                        $visibility_badge = $ad['visibility_level'] === 'premium'
                            ? '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold"><i class="fas fa-star me-1"></i>Premium</span>'
                            : '';
                        $ctr = $ad['total_impressions'] > 0
                            ? ($ad['total_clicks'] / $ad['total_impressions']) * 100
                            : 0;
                        ?>
                        <div class="glass-card p-4 p-lg-5 position-relative animate-fade-in" data-aos="fade-up">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                                        <div>
                                            <h2 class="h4 text-white mb-1"><?php echo htmlspecialchars($ad['title']); ?></h2>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <?php echo $ad_type_badge; ?>
                                                <?php echo $visibility_badge; ?>
                                                <span class="<?php echo $status_info['class']; ?>">
                                                    <i class="fas <?php echo $status_info['icon']; ?> me-1"></i><?php echo htmlspecialchars($status_info['label']); ?>
                                                </span>
                                                <?php if ($ad['status'] === 'active'): ?>
                                                    <span class="badge rounded-pill bg-success-subtle text-success fw-semibold">
                                                        <i class="fas fa-clock me-1"></i><?php echo timeRemaining($ad['end_date']); ?> left
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-lg-end">
                                            <span class="badge rounded-pill bg-secondary-subtle text-muted fw-semibold">
                                                <i class="fas fa-ruler-combined me-1"></i><?php echo ($ad['width'] ?: 0); ?>x<?php echo ($ad['height'] ?: 0); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-lg-6">
                                            <div class="p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                                <h6 class="text-muted text-uppercase small fw-semibold mb-3">Campaign Details</h6>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-muted small">Duration</span>
                                                    <span class="text-white fw-semibold"><?php echo $ad['duration_days']; ?> days</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-muted small">Investment</span>
                                                    <span class="text-success fw-semibold">$<?php echo number_format($ad['cost_paid'] + $ad['premium_cost'], 2); ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-muted small">Destination</span>
                                                    <a href="<?php echo htmlspecialchars($ad['target_url']); ?>" target="_blank" rel="noopener" class="text-info small text-decoration-none">
                                                        <?php echo htmlspecialchars(substr($ad['target_url'], 0, 40)) . (strlen($ad['target_url']) > 40 ? '…' : ''); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                                <h6 class="text-muted text-uppercase small fw-semibold mb-3">Performance</h6>
                                                <div class="row row-cols-3 g-2">
                                                    <div class="col">
                                                        <div class="glass-stat-tile text-center h-100">
                                                            <span class="glass-stat-label">Views</span>
                                                            <span class="glass-stat-value"><?php echo number_format($ad['total_impressions']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="glass-stat-tile text-center h-100">
                                                            <span class="glass-stat-label">Clicks</span>
                                                            <span class="glass-stat-value"><?php echo number_format($ad['total_clicks']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="glass-stat-tile text-center h-100">
                                                            <span class="glass-stat-label">CTR</span>
                                                            <span class="glass-stat-value text-info"><?php echo number_format($ctr, 2); ?>%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex-shrink-0" style="min-width: 260px;">
                                    <div class="p-3 rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 h-100">
                                        <h6 class="text-muted text-uppercase small fw-semibold mb-3">Live Preview</h6>
                                        <?php if ($ad['ad_type'] === 'banner'): ?>
                                            <?php if ($ad['banner_image']): ?>
                                                <img src="<?php echo htmlspecialchars($ad['banner_image']); ?>"
                                                     alt="<?php echo htmlspecialchars($ad['banner_alt_text']); ?>"
                                                     class="img-fluid rounded-4"
                                                     style="max-width: <?php echo $ad['width'] ? intval($ad['width']) . 'px' : '300px'; ?>; max-height: <?php echo $ad['height'] ? intval($ad['height']) . 'px' : '250px'; ?>; object-fit: contain;">
                                            <?php else: ?>
                                                <div class="text-center text-muted small">No banner uploaded</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div>
                                                <div class="fw-bold text-info mb-1"><?php echo htmlspecialchars($ad['text_title']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($ad['text_description']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="glass-card p-5 text-center animate-fade-in" data-aos="fade-up">
                    <div class="display-5 mb-3 text-warning">📦</div>
                    <h3 class="text-white mb-2">No campaigns yet</h3>
                    <p class="text-muted mb-4">Launch your first promotion to showcase your project in high-intent discovery spots.</p>
                    <a href="buy-ads.php" class="btn btn-theme btn-gradient">
                        <i class="fas fa-plus me-2"></i>Launch Campaign
                    </a>
                    <div class="dev-slot1 mt-4">Starter Ad 300x250</div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
