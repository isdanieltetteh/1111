<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Get ranking type and period
$ranking_type = $_GET['type'] ?? 'sites';
$period = $_GET['period'] ?? 'all_time';

$valid_types = ['sites', 'users'];
$valid_periods = ['week', 'month', 'all_time'];

if (!in_array($ranking_type, $valid_types)) $ranking_type = 'sites';
if (!in_array($period, $valid_periods)) $period = 'all_time';

// Calculate date condition for votes, not site creation, using UTC
$vote_date_condition = '';
$vote_join_condition = '';
switch ($period) {
    case 'week':
        $vote_date_condition = "AND v.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 WEEK)";
        $vote_join_condition = "LEFT JOIN votes v ON s.id = v.site_id AND v.vote_type = 'upvote' AND v.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 WEEK)";
        break;
    case 'month':
        $vote_date_condition = "AND v.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MONTH)";
        $vote_join_condition = "LEFT JOIN votes v ON s.id = v.site_id AND v.vote_type = 'upvote' AND v.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MONTH)";
        break;
    case 'all_time':
        $vote_join_condition = "LEFT JOIN votes v ON s.id = v.site_id AND v.vote_type = 'upvote'";
        break;
}

// Get top sites
if ($ranking_type === 'sites') {
    if ($period === 'all_time') {
        // For all-time rankings, use the existing total_upvotes column
        $sites_query = "SELECT s.*, 
                        COALESCE(AVG(r.rating), 0) as average_rating,
                        COUNT(DISTINCT r.id) as review_count,
                        u.username as submitted_by_username,
                        (s.total_upvotes - s.total_downvotes) as vote_score,
                        s.total_upvotes as period_upvotes,
                        CASE 
                            WHEN s.is_sponsored = 1 AND s.sponsored_until > NOW() THEN 'sponsored'
                            WHEN s.is_boosted = 1 AND s.boosted_until > NOW() THEN 'boosted'
                            ELSE 'normal'
                        END as promotion_status
                        FROM sites s 
                        LEFT JOIN reviews r ON s.id = r.site_id AND r.is_deleted = 0
                        LEFT JOIN users u ON s.submitted_by = u.id
                        WHERE s.is_approved = 1 AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE
                        GROUP BY s.id 
                        ORDER BY s.total_upvotes DESC, vote_score DESC, average_rating DESC
                        LIMIT 15";
    } else {
        // For weekly/monthly rankings, count votes received during the period
        $sites_query = "SELECT s.*, 
                        COALESCE(AVG(r.rating), 0) as average_rating,
                        COUNT(DISTINCT r.id) as review_count,
                        u.username as submitted_by_username,
                        (s.total_upvotes - s.total_downvotes) as vote_score,
                        COUNT(DISTINCT v.id) as period_upvotes,
                        CASE 
                            WHEN s.is_sponsored = 1 AND s.sponsored_until > NOW() THEN 'sponsored'
                            WHEN s.is_boosted = 1 AND s.boosted_until > NOW() THEN 'boosted'
                            ELSE 'normal'
                        END as promotion_status
                        FROM sites s 
                        LEFT JOIN reviews r ON s.id = r.site_id AND r.is_deleted = 0
                        LEFT JOIN users u ON s.submitted_by = u.id
                        {$vote_join_condition}
                        WHERE s.is_approved = 1 AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE
                        GROUP BY s.id 
                        HAVING period_upvotes > 0
                        ORDER BY period_upvotes DESC, vote_score DESC, average_rating DESC
                        LIMIT 15";
    }
    $sites_stmt = $db->prepare($sites_query);
    $sites_stmt->execute();
    $top_sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $top_sites = [];
}

// Get top users
if ($ranking_type === 'users') {
    if ($period === 'all_time') {
        // For all-time, use the total reputation_points from users table
        $users_query = "SELECT u.*, l.name as level_name, l.badge_icon, l.badge_color,
                        (SELECT COUNT(*) FROM reviews WHERE user_id = u.id AND is_deleted = 0) as total_reviews,
                        (SELECT COUNT(*) FROM sites WHERE submitted_by = u.id AND is_approved = 1) as approved_sites,
                        u.reputation_points as period_reputation_points
                        FROM users u
                        LEFT JOIN levels l ON u.active_badge_id = l.id
                        WHERE u.is_banned = 0
                        GROUP BY u.id
                        HAVING period_reputation_points > 0
                        ORDER BY period_reputation_points DESC, total_reviews DESC
                        LIMIT 15";
    } else {
        // For weekly/monthly, sum reputation points earned during the period from points_transactions
        $date_condition = $period === 'week' 
            ? "AND pt.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 WEEK)"
            : "AND pt.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MONTH)";
        
        $users_query = "SELECT u.*, l.name as level_name, l.badge_icon, l.badge_color,
                        (SELECT COUNT(*) FROM reviews WHERE user_id = u.id AND is_deleted = 0) as total_reviews,
                        (SELECT COUNT(*) FROM sites WHERE submitted_by = u.id AND is_approved = 1) as approved_sites,
                        COALESCE(SUM(pt.points), 0) as period_reputation_points
                        FROM users u
                        LEFT JOIN levels l ON u.active_badge_id = l.id
                        LEFT JOIN points_transactions pt ON u.id = pt.user_id 
                            AND pt.type = 'earned' 
                            {$date_condition}
                        WHERE u.is_banned = 0
                        GROUP BY u.id
                        HAVING period_reputation_points > 0
                        ORDER BY period_reputation_points DESC, total_reviews DESC
                        LIMIT 15";
    }
    
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute();
    $top_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $top_users = [];
}

function renderStars($rating, $size = '1rem') {
    $html = '<div class="star-rating" style="font-size: ' . $size . '">';
    for ($i = 1; $i <= 5; $i++) {
        $class = $i <= $rating ? 'star filled' : 'star';
        $html .= '<span class="' . $class . '">★</span>';
    }
    $html .= '</div>';
    return $html;
}

function getStatusBadge($status) {
    $badges = [
        'paying' => '<span class="status-badge status-paying">✅ Paying</span>',
        'scam_reported' => '<span class="status-badge status-scam-reported">⚠ Scam Reported</span>',
        'scam' => '<span class="status-badge status-scam">❌ Scam</span>'
    ];
    return $badges[$status] ?? '';
}


// Get sponsored sites (top 5 with rotation)
$sponsored_query = "SELECT s.*, 
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(r.id) as review_count,
                   u.username as submitted_by_username
                   FROM sites s 
                   LEFT JOIN reviews r ON s.id = r.site_id 
                   LEFT JOIN users u ON s.submitted_by = u.id
                   WHERE s.is_approved = 1 AND s.is_sponsored = 1 AND s.sponsored_until > NOW()
                   AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE
                   GROUP BY s.id 
                   ORDER BY COALESCE(s.sponsored_last_shown, '1970-01-01') ASC, RAND()
LIMIT 5
";
$sponsored_stmt = $db->prepare($sponsored_query);
$sponsored_stmt->execute();
$sponsored_sites = $sponsored_stmt->fetchAll(PDO::FETCH_ASSOC);
// Mark sponsored sites as shown
if ($sponsored_sites) {
    $ids = array_column($sponsored_sites, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE sites SET sponsored_last_shown = NOW() WHERE id IN ($placeholders)");
    $stmt->execute($ids);
}
// Get boosted sites (top 3 with rotation)
$boosted_query = "SELECT s.*, 
                  COALESCE(AVG(r.rating), 0) as average_rating,
                  COUNT(r.id) as review_count,
                  u.username as submitted_by_username
                  FROM sites s 
                  LEFT JOIN reviews r ON s.id = r.site_id 
                  LEFT JOIN users u ON s.submitted_by = u.id
                  WHERE s.is_approved = 1 AND s.is_boosted = 1 AND s.boosted_until > NOW()
                  AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE
                  GROUP BY s.id 
                  ORDER BY COALESCE(s.boosted_last_shown, '1970-01-01') ASC, RAND()
LIMIT 3
";
$boosted_stmt = $db->prepare($boosted_query);
$boosted_stmt->execute();
$boosted_sites = $boosted_stmt->fetchAll(PDO::FETCH_ASSOC);
// Mark boosted sites as shown
if ($boosted_sites) {
    $ids = array_column($boosted_sites, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE sites SET boosted_last_shown = NOW() WHERE id IN ($placeholders)");
    $stmt->execute($ids);
}

function truncateText($text, $length = 100) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

$page_title = 'Rankings - ' . SITE_NAME;
$page_description = 'Top ranked crypto faucets, URL shorteners, and community contributors based on user votes and reviews.';
$page_keywords = 'rankings, top sites, best crypto faucets, highest rated, community leaders';
$current_page = 'rankings';

$additional_head = '';

include 'includes/header.php';
?>
<main class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="rankings-hero glass-card p-4 p-lg-5 text-center mb-5">
                <div class="d-inline-flex align-items-center justify-content-center mb-4" style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(56, 189, 248, 0.25)); border: 1px solid rgba(245, 158, 11, 0.4); box-shadow: 0 20px 40px rgba(245, 158, 11, 0.25);">
                    <i class="fas fa-trophy fa-3x text-warning"></i>
                </div>
                <h1 class="text-white fw-bold mb-3">Community Performance Rankings</h1>
                <p class="text-muted mb-4">Track the hottest earning platforms and top contributors backed by real votes, ratings, and reputation points.</p>
                <div class="ranking-toggles">
                    <div class="toggle-group">
                        <a href="?type=sites&period=<?php echo $period; ?>" class="toggle-btn <?php echo $ranking_type === 'sites' ? 'active' : ''; ?>"><i class="fas fa-globe me-2"></i>Top Sites</a>
                        <a href="?type=users&period=<?php echo $period; ?>" class="toggle-btn <?php echo $ranking_type === 'users' ? 'active' : ''; ?>"><i class="fas fa-users me-2"></i>Top Contributors</a>
                    </div>
                </div>
                <div class="ranking-toggles mt-3">
                    <div class="toggle-group">
                        <a href="?type=<?php echo $ranking_type; ?>&period=week" class="toggle-btn <?php echo $period === 'week' ? 'active' : ''; ?>"><i class="fas fa-calendar-week me-2"></i>This Week</a>
                        <a href="?type=<?php echo $ranking_type; ?>&period=month" class="toggle-btn <?php echo $period === 'month' ? 'active' : ''; ?>"><i class="fas fa-calendar me-2"></i>This Month</a>
                        <a href="?type=<?php echo $ranking_type; ?>&period=all_time" class="toggle-btn <?php echo $period === 'all_time' ? 'active' : ''; ?>"><i class="fas fa-infinity me-2"></i>All Time</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot mb-4">Leaderboards Banner 970x250</div>
    </div>


    <?php if (!empty($sponsored_sites)): ?>
    <section class="py-5">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 mb-4 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                    <div>
                        <h2 class="section-heading mb-2 text-white"><i class="fas fa-crown me-2 text-warning"></i>Premium Sponsors</h2>
                        <p class="text-muted mb-0">High-converting partners earning trusted placement and additional visibility.</p>
                    </div>
                    <div class="dev-slot2 mt-4 mt-md-0 ms-md-4" style="min-width:220px; min-height:80px;">Inline Ad 320x100</div>
                </div>
                <div class="row g-4">
                    <?php foreach ($sponsored_sites as $site): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="listing-card featured-card featured-card--premium h-100 animate-fade-in" data-site-id="<?php echo $site['id']; ?>">
                            <div class="featured-card__badge-row">
                                <span class="featured-badge featured-badge--premium"><i class="fas fa-crown me-2"></i>Premium Sponsor</span>
                            </div>
                            <div class="featured-card__header">
                                <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($site['name']); ?>" class="site-logo">
                                <div>
                                    <h5 class="featured-card__title mb-1 text-white"><?php echo htmlspecialchars($site['name']); ?></h5>
                                    <div class="featured-card__meta">
                                        <span class="site-category"><i class="fas fa-layer-group"></i><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="featured-card__rating">
                                <?php echo renderStars(round($site['average_rating'])); ?>
                                <span class="rating-value"><?php echo number_format($site['average_rating'], 1); ?>/5</span>
                            </div>
                            <p class="featured-card__description"><?php echo htmlspecialchars(truncateText($site['description'], 60)); ?></p>
                            <div class="featured-card__status">
                                <div class="featured-card__status-badges">
                                    <?php echo getStatusBadge($site['status'] ?? 'paying'); ?>
                                </div>
                                <div class="featured-card__trust featured-card__trust--premium"><i class="fas fa-gem me-2"></i>High Conversion Partner</div>
                            </div>
                            <div class="featured-card__actions">
                                <a href="visit?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-gradient" target="_blank" rel="nofollow"><i class="fas fa-arrow-up-right-from-square me-2"></i>Visit Site</a>
                                <a href="review?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-outline-glass"><i class="fas fa-info-circle me-2"></i>Details</a>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>








    <section class="pb-5">
        <div class="container">
            <?php if ($ranking_type === 'sites'): ?>
                <?php if (!empty($top_sites)): ?>
                    <div class="ranking-table">
                        <?php foreach ($top_sites as $index => $site): ?>
                            <?php
                                $position = $index + 1;
                                $position_class = $position === 1 ? 'position-1' : ($position === 2 ? 'position-2' : ($position === 3 ? 'position-3' : 'position-default'));
                                $period_votes = $site['period_upvotes'] ?? $site['total_upvotes'];
                            ?>
                            <div class="ranking-row">
                                <div class="ranking-position <?php echo $position_class; ?>"><?php echo $position; ?></div>
                                <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($site['name']); ?>" class="ranking-logo">
                                <div class="ranking-info">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <h3 class="ranking-name mb-0"><?php echo htmlspecialchars($site['name']); ?></h3>
                                        <?php if ($site['promotion_status'] === 'sponsored'): ?>
                                            <span class="featured-badge featured-badge--premium featured-badge--compact"><i class="fas fa-crown me-1"></i>Premium Sponsor</span>
                                        <?php elseif ($site['promotion_status'] === 'boosted'): ?>
                                            <span class="featured-badge featured-badge--boosted featured-badge--compact"><i class="fas fa-rocket me-1"></i>Boosted</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ranking-stats">
                                        <span><i class="fas fa-thumbs-up me-1"></i><?php echo number_format($period_votes); ?> period upvotes</span>
                                        <span><i class="fas fa-arrow-trend-up me-1"></i><?php echo number_format($site['vote_score']); ?> net votes</span>
                                        <span><i class="fas fa-star me-1"></i><?php echo number_format($site['average_rating'], 1); ?>/5 rating</span>
                                        <span><i class="fas fa-comments me-1"></i><?php echo $site['review_count']; ?> reviews</span>
                                    </div>
                                </div>
                                <div class="ranking-score text-nowrap"><i class="fas fa-signal me-1"></i><?php echo ucfirst($period); ?></div>
                                <div class="ranking-actions">
                                    <a href="visit?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-gradient btn-sm" target="_blank" rel="nofollow"><i class="fas fa-arrow-up-right-from-square me-1"></i>Visit</a>
                                    <a href="review?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-circle-info me-1"></i>Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glass-card p-5 text-center text-muted">
                        <i class="fas fa-circle-info fa-3x mb-3"></i>
                        <h4 class="text-white mb-2">No site rankings yet</h4>
                        <p>Once platforms start receiving votes this leaderboard will light up.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (!empty($top_users)): ?>
                    <div class="ranking-table">
                        <?php foreach ($top_users as $index => $user): ?>
                            <?php
                                $position = $index + 1;
                                $position_class = $position === 1 ? 'position-1' : ($position === 2 ? 'position-2' : ($position === 3 ? 'position-3' : 'position-default'));
                                $period_points = $user['period_reputation_points'] ?? $user['reputation_points'];
                            ?>
                            <div class="ranking-row">
                                <div class="ranking-position <?php echo $position_class; ?>"><?php echo $position; ?></div>
                                <div class="ranking-logo d-flex align-items-center justify-content-center" style="background: rgba(56, 189, 248, 0.18); border: 1px solid rgba(56, 189, 248, 0.32); color: #e0f2fe;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="ranking-info">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <h3 class="ranking-name mb-0"><?php echo htmlspecialchars($user['username']); ?></h3>
                                        <?php if ($user['level_name']): ?>
                                            <span class="badge bg-info-subtle text-uppercase"><i class="fas fa-medal me-1"></i><?php echo htmlspecialchars($user['level_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ranking-stats">
                                        <span><i class="fas fa-gem me-1"></i><?php echo number_format($period_points); ?> period points</span>
                                        <span><i class="fas fa-comments me-1"></i><?php echo $user['total_reviews']; ?> reviews</span>
                                        <span><i class="fas fa-link me-1"></i><?php echo $user['approved_sites']; ?> approved sites</span>
                                    </div>
                                </div>
                                <div class="ranking-score"><i class="fas fa-trophy me-1"></i><?php echo number_format($user['reputation_points']); ?> rep</div>
                                <div class="ranking-actions">
                                    <a href="profile.php?user=<?php echo urlencode($user['username']); ?>" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-id-card me-1"></i>View Profile</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glass-card p-5 text-center text-muted">
                        <i class="fas fa-user-astronaut fa-3x mb-3"></i>
                        <h4 class="text-white mb-2">No contributor rankings yet</h4>
                        <p>Earn reputation by posting reviews and helpful insights to appear here.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="dev-slot1 mt-5">Leaderboard Sidebar 300x600</div>
        </div>
    </section>
    
    
    <?php if (!empty($boosted_sites)): ?>
    <section class="pt-0 pb-5">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                    <div>
                        <h2 class="section-heading mb-2 text-white"><i class="fas fa-rocket me-2 text-info"></i>Boosted Highlights</h2>
                        <p class="text-muted mb-0">Community-loved sites enjoying elevated visibility.</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php foreach ($boosted_sites as $site): ?>
                    <div class="col-md-4">
                        <article class="listing-card featured-card featured-card--boosted h-100 animate-fade-in" data-site-id="<?php echo $site['id']; ?>">
                            <div class="featured-card__badge-row">
                                <span class="featured-badge featured-badge--boosted"><i class="fas fa-rocket me-2"></i>Boosted Favorite</span>
                            </div>
                            <div class="featured-card__header">
                                <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($site['name']); ?>" class="site-logo">
                                <div>
                                    <h5 class="featured-card__title mb-1 text-white"><?php echo htmlspecialchars($site['name']); ?></h5>
                                    <div class="featured-card__meta">
                                        <span class="site-category"><i class="fas fa-layer-group"></i><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="featured-card__rating">
                                <?php echo renderStars(round($site['average_rating'])); ?>
                                <span class="rating-value"><?php echo number_format($site['average_rating'], 1); ?>/5</span>
                                <span class="text-muted">(<?php echo number_format($site['review_count']); ?> reviews)</span>
                            </div>
                            <p class="featured-card__description"><?php echo htmlspecialchars(truncateText($site['description'], 60)); ?></p>
                            <div class="featured-card__status">
                                <div class="featured-card__status-badges">
                                    <?php echo getStatusBadge($site['status'] ?? 'paying'); ?>
                                </div>
                                <div class="featured-card__trust featured-card__trust--boosted"><i class="fas fa-bolt me-2"></i>Community Power Boost</div>
                            </div>
                            <div class="featured-card__actions">
                                <a href="review?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-gradient"><i class="fas fa-eye me-2"></i>View Review</a>
                                <a href="visit?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-outline-glass" target="_blank" rel="nofollow"><i class="fas fa-arrow-up-right-from-square me-2"></i>Visit</a>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>