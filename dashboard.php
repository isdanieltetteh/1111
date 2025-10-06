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

require_once __DIR__ . '/includes/wallet.php';
$wallet_manager = new WalletManager($db);

// Get wallet data
$wallet = $wallet_manager->getUserWallet($user_id);

// Get user statistics
$stats_query = "SELECT
                (SELECT COUNT(*) FROM sites WHERE submitted_by = :user_id) as submitted_sites,
                (SELECT COUNT(*) FROM sites WHERE submitted_by = :user_id AND is_approved = 1) as approved_sites,
                (SELECT COUNT(*) FROM reviews WHERE user_id = :user_id) as total_reviews,
                (SELECT COUNT(*) FROM votes WHERE user_id = :user_id) as total_votes,
                (SELECT COALESCE(SUM(upvotes), 0) FROM reviews WHERE user_id = :user_id) as review_upvotes
                ";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user_id);
$stats_stmt->execute();
$user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get user's recent activity
$recent_reviews_query = "SELECT r.*, s.name as site_name
                        FROM reviews r
                        JOIN sites s ON r.site_id = s.id
                        WHERE r.user_id = :user_id
                        ORDER BY r.created_at DESC
                        LIMIT 3";
$recent_reviews_stmt = $db->prepare($recent_reviews_query);
$recent_reviews_stmt->bindParam(':user_id', $user_id);
$recent_reviews_stmt->execute();
$recent_reviews = $recent_reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's submitted sites
$submitted_sites_query = "SELECT s.*,
                         CASE
                             WHEN s.is_sponsored = 1 AND s.sponsored_until > NOW() THEN 'sponsored'
                             WHEN s.is_boosted = 1 AND s.boosted_until > NOW() THEN 'boosted'
                             ELSE 'normal'
                         END as promotion_status,
                         s.sponsored_until,
                         s.boosted_until
                         FROM sites s
                         WHERE submitted_by = :user_id
                         ORDER BY created_at DESC
                         LIMIT 3";
$submitted_sites_stmt = $db->prepare($submitted_sites_query);
$submitted_sites_stmt->bindParam(':user_id', $user_id);
$submitted_sites_stmt->execute();
$submitted_sites = $submitted_sites_stmt->fetchAll(PDO::FETCH_ASSOC);

$backlink_tracking_query = "SELECT bt.*, s.name as site_name, s.url as site_url
                           FROM backlink_tracking bt
                           JOIN sites s ON bt.site_id = s.id
                           WHERE s.submitted_by = :user_id
                           ORDER BY bt.created_at DESC
                           LIMIT 5";
$backlink_tracking_stmt = $db->prepare($backlink_tracking_query);
$backlink_tracking_stmt->bindParam(':user_id', $user_id);
$backlink_tracking_stmt->execute();
$user_backlinks = $backlink_tracking_stmt->fetchAll(PDO::FETCH_ASSOC);

$backlink_stats_query = "SELECT
                        COUNT(*) as total_backlinks,
                        SUM(CASE WHEN bt.status = 'verified' THEN 1 ELSE 0 END) as verified_backlinks,
                        SUM(CASE WHEN bt.status = 'failed' THEN 1 ELSE 0 END) as failed_backlinks,
                        SUM(CASE WHEN bt.status = 'pending' THEN 1 ELSE 0 END) as pending_backlinks
                        FROM backlink_tracking bt
                        JOIN sites s ON bt.site_id = s.id
                        WHERE s.submitted_by = :user_id";
$backlink_stats_stmt = $db->prepare($backlink_stats_query);
$backlink_stats_stmt->bindParam(':user_id', $user_id);
$backlink_stats_stmt->execute();
$backlink_stats = $backlink_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get notifications
$notifications_query = "SELECT * FROM notifications WHERE user_id = :user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 3";
$notifications_stmt = $db->prepare($notifications_query);
$notifications_stmt->bindParam(':user_id', $user_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current leaderboard position
$leaderboard_query = "SELECT
                      (SELECT COUNT(*) + 1 FROM users u2 WHERE u2.reputation_points > u.reputation_points) as position
                      FROM users u
                      WHERE u.id = :user_id";
$leaderboard_stmt = $db->prepare($leaderboard_query);
$leaderboard_stmt->bindParam(':user_id', $user_id);
$leaderboard_stmt->execute();
$leaderboard_position = $leaderboard_stmt->fetch(PDO::FETCH_ASSOC);

// Get referrals - simplified query without wallet_transactions table
$referrals_query = "SELECT r.username, r.created_at as joined_date,
                   (SELECT COUNT(*) FROM reviews WHERE user_id = r.id) as activities,
                   r.reputation_points as points_earned
                   FROM users r WHERE r.referred_by = :user_id ORDER BY r.created_at DESC LIMIT 6";
$referrals_stmt = $db->prepare($referrals_query);
$referrals_stmt->bindParam(':user_id', $user_id);
$referrals_stmt->execute();
$referrals = $referrals_stmt->fetchAll(PDO::FETCH_ASSOC);

function renderStars($rating, $size = '1rem') {
    $html = '<div class="star-rating" style="font-size: ' . $size . '">';
    for ($i = 1; $i <= 5; $i++) {
        $class = $i <= $rating ? 'star filled' : 'star';
        $html .= '<span class="' . $class . '">★</span>';
    }
    $html .= '</div>';
    return $html;
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}

function timeRemaining($datetime) {
    $time = strtotime($datetime) - time();
    if ($time <= 0) return 'Expired';

    $days = floor($time / 86400);
    $hours = floor(($time % 86400) / 3600);

    if ($days > 0) return $days . 'd ' . $hours . 'h';
    return $hours . 'h';
}

$page_title = 'Dashboard - ' . SITE_NAME;
$page_description = 'Manage your ' . SITE_NAME . ' account, track your reviews, submissions, and reputation.';
$current_page = 'dashboard';

include __DIR__ . '/includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="row align-items-center g-4">
                    <div class="col-md-auto">
                        <div class="position-relative">
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>"
                                 alt="<?php echo htmlspecialchars($user['username']); ?> avatar"
                                 class="rounded-circle border border-info-subtle border-opacity-75"
                                 style="width: 96px; height: 96px; object-fit: cover;">
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <h1 class="h3 text-white mb-0">Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                            <span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2">
                                <span><?php echo htmlspecialchars($user['active_badge_icon'] ?: '🆕'); ?></span>
                                <span><?php echo htmlspecialchars($user['active_badge_name'] ?: 'Newcomer'); ?></span>
                            </span>
                        </div>
                        <p class="text-muted mb-0 mt-3">Stay on top of your reviews, submissions, and rewards with your personal command center.</p>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="option-chip">
                                <i class="fas fa-trophy text-warning"></i>
                                <span><?php echo number_format($user['reputation_points']); ?> reputation</span>
                            </span>
                            <span class="option-chip">
                                <i class="fas fa-coins text-success"></i>
                                <span>$<?php echo number_format((float)($user['credits'] ?? 0), 2); ?> credits</span>
                            </span>
                            <span class="option-chip">
                                <i class="fas fa-users text-info"></i>
                                <span><?php echo count($referrals ?? []); ?> referrals</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dev-slot mt-4">Dashboard Banner Ad Slot 970x250</div>
        </div>
    </section>

    <section class="py-5">
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
                    'href' => 'transactions.php',
                    'icon' => 'fa-list',
                    'label' => 'Transactions',
                    'description' => 'Track deposits, spending & rewards'
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
                    'href' => 'badge-manager.php',
                    'icon' => 'fa-award',
                    'label' => 'Badge Manager',
                    'description' => 'Showcase and activate achievements'
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
            <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h5 text-white mb-1">Navigate Your Toolkit</h2>
                        <p class="text-muted mb-0">Quick links to every dashboard feature.</p>
                    </div>
                    <a href="submit-site.php" class="btn btn-theme btn-gradient btn-sm">
                        <i class="fas fa-plus-circle me-2"></i>Submit New Site
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
            <div class="row g-4" data-aos="fade-up">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="glass-card h-100 p-4">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="text-uppercase text-muted small fw-semibold">Submitted Sites</span>
                                <div class="fs-2 fw-bold text-white mt-1"><?php echo number_format($user_stats['submitted_sites'] ?? 0); ?></div>
                            </div>
                            <div class="rounded-circle bg-primary bg-opacity-25 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="fas fa-globe"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <span class="badge rounded-pill bg-success-subtle text-success fw-semibold"><?php echo number_format($user_stats['approved_sites'] ?? 0); ?> approved</span>
                            <a href="my-submissions.php" class="text-decoration-none text-muted small">View</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="glass-card h-100 p-4">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="text-uppercase text-muted small fw-semibold">Reviews Written</span>
                                <div class="fs-2 fw-bold text-white mt-1"><?php echo number_format($user_stats['total_reviews'] ?? 0); ?></div>
                            </div>
                            <div class="rounded-circle bg-warning bg-opacity-25 text-warning d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="fas fa-comments"></i>
                            </div>
                        </div>
                        <p class="text-muted small mb-0 mt-3"><?php echo number_format($user_stats['review_upvotes'] ?? 0); ?> upvotes received</p>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="glass-card h-100 p-4">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="text-uppercase text-muted small fw-semibold">Votes Cast</span>
                                <div class="fs-2 fw-bold text-white mt-1"><?php echo number_format($user_stats['total_votes'] ?? 0); ?></div>
                            </div>
                            <div class="rounded-circle bg-secondary bg-opacity-25 text-secondary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="fas fa-hands-helping"></i>
                            </div>
                        </div>
                        <p class="text-muted small mb-0 mt-3">Community participation</p>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="glass-card h-100 p-4">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="text-uppercase text-muted small fw-semibold">Leaderboard</span>
                                <div class="fs-2 fw-bold text-white mt-1">#<?php echo $leaderboard_position && isset($leaderboard_position['position']) ? number_format($leaderboard_position['position']) : '—'; ?></div>
                            </div>
                            <div class="rounded-circle bg-success bg-opacity-25 text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="fas fa-ranking-star"></i>
                            </div>
                        </div>
                        <a href="rankings.php" class="text-decoration-none text-warning small fw-semibold d-inline-flex align-items-center gap-1 mt-3">
                            View rankings <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-xl-8">
                    <div class="glass-card p-4 mb-4 animate-fade-in" data-aos="fade-up" data-aos-delay="50">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom border-light-subtle border-opacity-25">
                            <div class="d-flex align-items-center gap-3">
                                <span class="rounded-circle bg-primary bg-opacity-25 text-primary d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                    <i class="fas fa-comments"></i>
                                </span>
                                <div>
                                    <h2 class="h5 text-white mb-0">Recent Reviews</h2>
                                    <small class="text-muted">Your latest community insights</small>
                                </div>
                            </div>
                            <a href="profile.php" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-arrow-right me-2"></i>View All</a>
                        </div>
                        <?php if (!empty($recent_reviews)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_reviews as $review): ?>
                                    <?php
                                        $comment_preview = trim($review['comment']);
                                        if (strlen($comment_preview) > 140) {
                                            $comment_preview = substr($comment_preview, 0, 140) . '...';
                                        }
                                        $upvotes = isset($review['upvotes']) ? (int)$review['upvotes'] : 0;
                                    ?>
                                    <div class="list-group-item bg-transparent px-0 border-light-subtle border-opacity-10 py-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-3">
                                            <div class="flex-grow-1">
                                                <h3 class="h6 mb-1">
                                                    <a href="review.php?id=<?php echo (int)$review['site_id']; ?>" class="text-decoration-none text-white">
                                                        <?php echo htmlspecialchars($review['site_name']); ?>
                                                    </a>
                                                </h3>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php echo renderStars((int)$review['rating'], '1rem'); ?>
                                                    <span class="badge rounded-pill bg-warning-subtle text-warning fw-semibold"><?php echo (int)$review['rating']; ?>/5</span>
                                                </div>
                                                <p class="text-muted small mb-0 mt-2"><?php echo htmlspecialchars($comment_preview); ?></p>
                                            </div>
                                            <div class="text-end">
                                                <span class="text-muted small d-block"><?php echo timeAgo($review['created_at']); ?></span>
                                                <span class="badge rounded-pill bg-success-subtle text-success mt-2"><i class="fas fa-thumbs-up me-1"></i><?php echo number_format($upvotes); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comments fa-2x text-secondary mb-3"></i>
                                <h3 class="h5 text-white">No reviews yet</h3>
                                <p class="text-muted mb-4">Start reviewing sites to earn reputation rewards.</p>
                                <a href="sites.php" class="btn btn-theme btn-gradient"><i class="fas fa-pen me-2"></i>Start Reviewing</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="glass-card p-4 mb-4 animate-fade-in" data-aos="fade-up" data-aos-delay="100">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom border-light-subtle border-opacity-25">
                            <div class="d-flex align-items-center gap-3">
                                <span class="rounded-circle bg-success bg-opacity-20 text-success d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                    <i class="fas fa-globe"></i>
                                </span>
                                <div>
                                    <h2 class="h5 text-white mb-0">Your Submissions</h2>
                                    <small class="text-muted">Latest sites you've added</small>
                                </div>
                            </div>
                            <a href="my-submissions.php" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-list me-2"></i>Manage</a>
                        </div>
                        <?php if (!empty($submitted_sites)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($submitted_sites as $site): ?>
                                    <?php
                                        $status_badge = '<span class="badge rounded-pill bg-warning-subtle text-warning">Pending</span>';
                                        if (!empty($site['is_approved'])) {
                                            $status_badge = '<span class="badge rounded-pill bg-success-subtle text-success">Approved</span>';
                                        }
                                        $promotion_badge = '';
                                        if ($site['promotion_status'] === 'sponsored') {
                                            $promotion_badge = '<span class="badge rounded-pill bg-warning-subtle text-warning">⭐ Sponsored</span>';
                                        } elseif ($site['promotion_status'] === 'boosted') {
                                            $promotion_badge = '<span class="badge rounded-pill bg-info-subtle text-info">🔥 Boosted</span>';
                                        }
                                        $expiry_notice = '';
                                        if ($site['promotion_status'] === 'sponsored' && !empty($site['sponsored_until'])) {
                                            $expiry_notice = 'Expires in ' . timeRemaining($site['sponsored_until']);
                                        } elseif ($site['promotion_status'] === 'boosted' && !empty($site['boosted_until'])) {
                                            $expiry_notice = 'Expires in ' . timeRemaining($site['boosted_until']);
                                        }
                                    ?>
                                    <div class="list-group-item bg-transparent px-0 border-light-subtle border-opacity-10 py-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-3">
                                            <div class="flex-grow-1">
                                                <h3 class="h6 mb-1 text-white"><?php echo htmlspecialchars($site['name']); ?></h3>
                                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                                    <?php echo $status_badge; ?>
                                                    <?php if ($promotion_badge): ?>
                                                        <?php echo $promotion_badge; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($site['category'])): ?>
                                                    <p class="text-muted small mb-1 mt-2">Category: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $site['category']))); ?></p>
                                                <?php endif; ?>
                                                <?php if ($expiry_notice): ?>
                                                    <small class="text-warning d-block"><?php echo htmlspecialchars($expiry_notice); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex flex-column align-items-end gap-2">
                                                <span class="text-muted small"><?php echo timeAgo($site['created_at']); ?></span>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php if (!empty($site['is_approved'])): ?>
                                                        <a href="review.php?id=<?php echo (int)$site['id']; ?>" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-eye me-2"></i>View</a>
                                                    <?php endif; ?>
                                                    <?php if ($site['promotion_status'] === 'normal'): ?>
                                                        <a href="promote-sites.php" class="btn btn-theme btn-gradient btn-sm"><i class="fas fa-rocket me-2"></i>Promote</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-globe fa-2x text-secondary mb-3"></i>
                                <h3 class="h5 text-white">No submissions yet</h3>
                                <p class="text-muted mb-4">Submit your first site to start building your presence.</p>
                                <a href="submit-site.php" class="btn btn-theme btn-gradient"><i class="fas fa-plus-circle me-2"></i>Submit Site</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="glass-card p-4 animate-fade-in" data-aos="fade-up" data-aos-delay="150">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom border-light-subtle border-opacity-25">
                            <div class="d-flex align-items-center gap-3">
                                <span class="rounded-circle bg-info bg-opacity-20 text-info d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                    <i class="fas fa-link"></i>
                                </span>
                                <div>
                                    <h2 class="h5 text-white mb-0">Backlink Tracking</h2>
                                    <small class="text-muted">Monitor referral partners</small>
                                </div>
                            </div>
                            <a href="my-submissions.php" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-sliders me-2"></i>Manage</a>
                        </div>
                        <?php if (!empty($user_backlinks)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($user_backlinks as $backlink): ?>
                                    <?php
                                        $statusClass = 'bg-warning-subtle text-warning';
                                        if ($backlink['status'] === 'verified') {
                                            $statusClass = 'bg-success-subtle text-success';
                                        } elseif ($backlink['status'] === 'failed') {
                                            $statusClass = 'bg-danger-subtle text-danger';
                                        }
                                    ?>
                                    <div class="list-group-item bg-transparent px-0 border-light-subtle border-opacity-10 py-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-3">
                                            <div>
                                                <h3 class="h6 mb-1">
                                                    <a href="<?php echo htmlspecialchars($backlink['site_url']); ?>" target="_blank" rel="noopener" class="text-decoration-none text-white">
                                                        <?php echo htmlspecialchars($backlink['site_name']); ?>
                                                    </a>
                                                </h3>
                                                <p class="text-muted small mb-2"><strong>Source:</strong> <?php echo htmlspecialchars($backlink['backlink_url']); ?></p>
                                                <span class="badge rounded-pill <?php echo $statusClass; ?> text-uppercase fw-semibold"><?php echo htmlspecialchars(ucfirst($backlink['status'])); ?></span>
                                            </div>
                                            <div class="text-end">
                                                <span class="text-muted small d-block"><?php echo timeAgo($backlink['created_at']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-link fa-2x text-secondary mb-3"></i>
                                <h3 class="h5 text-white">No backlinks tracked yet</h3>
                                <p class="text-muted mb-4">Start tracking your backlinks to monitor your site's authority.</p>
                                <a href="my-submissions.php" class="btn btn-theme btn-gradient"><i class="fas fa-plus me-2"></i>Track Backlinks</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dev-slot2 mt-4">After Content Ad Slot 728x90</div>
                </div>
                <div class="col-xl-4">
                    <div class="glass-card p-4 mb-4 animate-fade-in" data-aos="fade-up" data-aos-delay="50">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom border-light-subtle border-opacity-25">
                            <div class="d-flex align-items-center gap-3">
                                <span class="rounded-circle bg-danger bg-opacity-20 text-danger d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                    <i class="fas fa-bell"></i>
                                </span>
                                <div>
                                    <h2 class="h5 text-white mb-0">Notifications</h2>
                                    <small class="text-muted">Latest alerts</small>
                                </div>
                            </div>
                            <a href="notifications.php" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-inbox me-2"></i>View All</a>
                        </div>
                        <?php if (!empty($notifications)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item bg-transparent px-0 border-light-subtle border-opacity-10 py-3">
                                        <div class="d-flex flex-column gap-2">
                                            <div class="d-flex justify-content-between gap-3">
                                                <h3 class="h6 mb-0 text-white"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                                <span class="text-muted small"><?php echo timeAgo($notification['created_at']); ?></span>
                                            </div>
                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bell-slash fa-2x text-secondary mb-3"></i>
                                <h3 class="h6 text-white">You're all caught up</h3>
                                <p class="text-muted small mb-0">We'll notify you when there's something new.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="glass-card p-4 mb-4 animate-fade-in" data-aos="fade-up" data-aos-delay="100">
                        <h2 class="h5 text-white mb-3">Quick Actions</h2>
                        <div class="d-grid gap-2">
                            <a href="submit-site.php" class="btn btn-theme btn-gradient"><i class="fas fa-plus-circle me-2"></i>Submit New Site</a>
                            <a href="sites.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-edit me-2"></i>Write Reviews</a>
                            <a href="promote-sites.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-rocket me-2"></i>Promote Sites</a>
                            <a href="buy-credits.php" class="btn btn-theme btn-outline-glass"><i class="fas fa-credit-card me-2"></i>Buy Credits</a>
                        </div>
                    </div>

                    <div class="dev-slot1 mb-4">Sidebar Tower Ad 300x600</div>
                </div>
            </div>

            <div class="glass-card p-4 p-lg-5 mt-4 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-secondary bg-opacity-20 text-secondary d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            <i class="fas fa-users"></i>
                        </span>
                        <div>
                            <h2 class="h5 text-white mb-0">Your Referral Program</h2>
                            <small class="text-muted">Share your invite link to earn bonus points</small>
                        </div>
                    </div>
                    <a href="wallet.php" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-wallet me-2"></i>Wallet</a>
                </div>

                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-link"></i></span>
                    <input type="text"
                           class="form-control border-start-0"
                           value="<?php echo SITE_URL; ?>/register?ref=<?php echo htmlspecialchars($user['referral_code']); ?>"
                           readonly
                           id="dashboardReferralLink">
                    <button class="btn btn-theme btn-gradient" type="button" onclick="copyDashboardReferralLink(this)"><i class="fas fa-copy me-2"></i>Copy</button>
                </div>
                <small class="text-muted d-block mt-2">Earn <?php echo htmlspecialchars($settings['referral_percentage'] ?? 10); ?>% of your referrals' points.</small>

                <div class="row g-3 mt-4">
                    <div class="col-md-4">
                        <div class="p-3 border border-info-subtle border-opacity-50 rounded-4 h-100">
                            <span class="text-uppercase text-muted small fw-semibold">Total Referrals</span>
                            <div class="fs-4 fw-bold text-white mt-2"><?php echo number_format(count($referrals)); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border border-success-subtle border-opacity-50 rounded-4 h-100">
                            <span class="text-uppercase text-muted small fw-semibold">Points Earned</span>
                            <div class="fs-4 fw-bold text-white mt-2"><?php echo number_format(array_sum(array_column($referrals, 'points_earned'))); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border border-warning-subtle border-opacity-50 rounded-4 h-100">
                            <span class="text-uppercase text-muted small fw-semibold">Total Activities</span>
                            <div class="fs-4 fw-bold text-white mt-2"><?php echo number_format(array_sum(array_column($referrals, 'activities'))); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($referrals)): ?>
                    <div class="list-group list-group-flush mt-4">
                        <?php foreach ($referrals as $referral): ?>
                            <div class="list-group-item bg-transparent px-0 border-light-subtle border-opacity-10 py-3">
                                <div class="d-flex flex-wrap justify-content-between gap-3">
                                    <div>
                                        <h3 class="h6 mb-1 text-white"><?php echo htmlspecialchars($referral['username']); ?></h3>
                                        <p class="text-muted small mb-0">Joined <?php echo timeAgo($referral['joined_date']); ?> • <?php echo number_format($referral['activities']); ?> activities</p>
                                    </div>
                                    <div class="badge rounded-pill bg-info-subtle text-info fw-semibold align-self-start"><?php echo number_format($referral['points_earned']); ?> pts</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-2x text-secondary mb-3"></i>
                        <h3 class="h6 text-white">No referrals yet</h3>
                        <p class="text-muted small mb-3">Share your referral link to start earning bonus points!</p>
                        <button class="btn btn-theme btn-gradient" type="button" onclick="copyDashboardReferralLink(this)"><i class="fas fa-share-nodes me-2"></i>Copy Referral Link</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dev-slot2 mt-4">Footer Ad Slot 728x90</div>
        </div>
    </section>
</div>

<?php
$additional_scripts = <<<HTML
<script>
function copyDashboardReferralLink(button) {
    const referralLink = document.getElementById('dashboardReferralLink');
    if (!referralLink) {
        return;
    }

    referralLink.focus();
    referralLink.select();
    referralLink.setSelectionRange(0, referralLink.value.length);

    const fallbackCopy = () => {
        try {
            document.execCommand('copy');
        } catch (err) {
            console.error('Copy failed', err);
        }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(referralLink.value).then(() => {
            handleCopyFeedback(button);
        }).catch(() => {
            fallbackCopy();
            handleCopyFeedback(button);
        });
    } else {
        fallbackCopy();
        handleCopyFeedback(button);
    }
}

function handleCopyFeedback(button) {
    if (!button) {
        return;
    }

    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    button.disabled = true;

    setTimeout(() => {
        button.innerHTML = originalHtml;
        button.disabled = false;
    }, 2000);
}
</script>
HTML;

include __DIR__ . '/includes/footer.php';
