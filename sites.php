<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Pull aggregate stats for hero metrics
$stats_query = "SELECT
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1) as total_sites,
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1 AND status = 'paying') as paying_sites,
                (SELECT COUNT(*) FROM reviews) as total_reviews,
                (SELECT COALESCE(SUM(total_upvotes), 0) FROM sites WHERE is_approved = 1) as total_upvotes,
                (SELECT COUNT(*) FROM sites WHERE is_approved = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_sites_month";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$raw_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$stats = [
    'total_sites' => isset($raw_stats['total_sites']) ? (int)$raw_stats['total_sites'] : 0,
    'paying_sites' => isset($raw_stats['paying_sites']) ? (int)$raw_stats['paying_sites'] : 0,
    'total_reviews' => isset($raw_stats['total_reviews']) ? (int)$raw_stats['total_reviews'] : 0,
    'total_upvotes' => isset($raw_stats['total_upvotes']) ? (int)$raw_stats['total_upvotes'] : 0,
    'new_sites_month' => isset($raw_stats['new_sites_month']) ? (int)$raw_stats['new_sites_month'] : 0
];

$trust_rate = $stats['total_sites'] > 0
    ? round(($stats['paying_sites'] / $stats['total_sites']) * 100, 1)
    : 0;

// Get filters from URL
$category = $_GET['category'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'upvotes';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = intval($_GET['per_page'] ?? 12);
$view_mode = $_GET['view'] ?? 'grid';

// Validate inputs
$valid_categories = ['all', 'faucet', 'url_shortener'];
$valid_statuses = ['all', 'paying', 'scam_reported', 'scam'];
$valid_sorts = ['upvotes', 'newest', 'rating', 'trending'];
$valid_per_page = [12, 15, 20, 30];
$valid_views = ['grid', 'list'];

if (!in_array($category, $valid_categories)) $category = 'all';
if (!in_array($status, $valid_statuses)) $status = 'all';
if (!in_array($sort, $valid_sorts)) $sort = 'upvotes';
if (!in_array($per_page, $valid_per_page)) $per_page = 12;
if (!in_array($view_mode, $valid_views)) $view_mode = 'grid';

$offset = ($page - 1) * $per_page;

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
// Build WHERE clause for main sites
$where_conditions = ['s.is_approved = 1', 's.is_dead = FALSE', 's.admin_approved_dead = FALSE'];
$params = [];

if ($category !== 'all') {
    $where_conditions[] = "s.category = :category";
    $params[':category'] = $category;
}

if ($status !== 'all') {
    $where_conditions[] = "s.status = :status";
    $params[':status'] = $status;
}

if (!empty($search)) {
    $where_conditions[] = "(s.name LIKE :search OR s.description LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_by = match($sort) {
    'upvotes' => '(s.total_upvotes - s.total_downvotes) DESC, s.total_upvotes DESC',
    'newest' => 's.created_at DESC',
    'rating' => 'average_rating DESC, review_count DESC',
    'trending' => 's.total_upvotes DESC, s.created_at DESC',
    default => '(s.total_upvotes - s.total_downvotes) DESC'
};

// Get total count
$count_query = "SELECT COUNT(*) as total FROM sites s WHERE {$where_clause}";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_sites = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_sites / $per_page);

// Get main sites (including sponsored/boosted)
$sites_query = "SELECT s.*, 
                COALESCE(AVG(r.rating), 0) as average_rating,
                COUNT(r.id) as review_count,
                u.username as submitted_by_username,
                (s.total_upvotes - s.total_downvotes) as vote_score,
                CASE 
                    WHEN s.is_sponsored = 1 AND s.sponsored_until > NOW() THEN 'sponsored'
                    WHEN s.is_boosted = 1 AND s.boosted_until > NOW() THEN 'boosted'
                    ELSE 'normal'
                END as promotion_status
                FROM sites s 
                LEFT JOIN reviews r ON s.id = r.site_id 
                LEFT JOIN users u ON s.submitted_by = u.id
                WHERE {$where_clause}
                GROUP BY s.id 
                ORDER BY {$order_by}
                LIMIT {$per_page} OFFSET {$offset}";

$sites_stmt = $db->prepare($sites_query);
$sites_stmt->execute($params);
$sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function renderStars($rating, $size = '1rem') {
    $html = '<div class="star-rating" style="font-size: ' . $size . '">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="' . ($i <= $rating ? 'filled' : '') . '">★</span>';
    }
    $html .= '</div>';
    return $html;
}

function getStatusBadge($status) {
    $badges = [
        'paying' => '<span class="status-badge status-paying"><i class="fas fa-check-circle me-2"></i>Verified Paying</span>',
        'scam_reported' => '<span class="status-badge status-scam-reported"><i class="fas fa-exclamation-triangle me-2"></i>Under Review</span>',
        'scam' => '<span class="status-badge status-scam"><i class="fas fa-times-circle me-2"></i>Confirmed Scam</span>'
    ];
    return $badges[$status] ?? '';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}

function truncateText($text, $length = 100) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

$page_title = 'Site Rankings - ' . SITE_NAME;
$page_description = 'Discover the top-rated crypto earning sites ranked by community votes. Find legitimate faucets and URL shorteners trusted by thousands of users.';
$page_keywords = 'crypto site rankings, best faucets, top URL shorteners, crypto earning sites, community voted';
$current_page = 'sites';

$additional_head = '';

include 'includes/header.php';
?>

<main class="page-wrapper flex-grow-1">
    <section class="page-hero text-white text-center">
        <div class="container">
            <div class="hero-content mx-auto" data-aos="fade-up">
                <div class="hero-badge mb-4">
                    <i class="fas fa-satellite-dish"></i>
                    <span><?php echo number_format($total_sites); ?> verified listings • <?php echo $trust_rate; ?>% paying</span>
                </div>
                <h1 class="hero-title mb-3">Explore Elite Crypto Faucets &amp; URL Shorteners</h1>
                <p class="hero-lead mb-4">Find legitimate earning opportunities with transparent data, live community voting, and dedicated trust &amp; safety oversight.</p>
                <div class="d-flex flex-column flex-md-row justify-content-center gap-3 mb-4">
                    <a href="submit-site" class="btn btn-theme btn-outline-glass btn-lg"><i class="fas fa-paper-plane me-2"></i>Submit Your Site</a>
                    <a href="rankings" class="btn btn-theme btn-gradient btn-lg"><i class="fas fa-trophy me-2"></i>View Top Rankings</a>
                </div>
                <div class="row g-3 justify-content-center">
                    <div class="col-6 col-md-3">
                        <div class="hero-stat-card animate-fade-in">
                            <div class="hero-stat-value"><?php echo number_format($stats['total_reviews']); ?></div>
                            <div class="hero-stat-label">User Reviews</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="hero-stat-card animate-fade-in">
                            <div class="hero-stat-value"><?php echo number_format($stats['total_upvotes']); ?></div>
                            <div class="hero-stat-label">Community Votes</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="hero-stat-card animate-fade-in">
                            <div class="hero-stat-value"><?php echo number_format($stats['new_sites_month']); ?></div>
                            <div class="hero-stat-label">New Sites 30d</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <div class="dev-slot">Directory Banner Ad Slot 970x250 / 728x90</div>
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

    <div class="container py-5">
        <div class="row g-4">
            <div class="col-xl-9">
                <div class="glass-card filters-panel mb-4 animate-fade-in" data-aos="fade-up">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label for="searchInput" class="form-label text-uppercase small text-muted">Search Directory</label>
                            <div class="search-wrapper">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" class="form-control form-control-lg" placeholder="Search sites..." value="<?php echo htmlspecialchars($search); ?>">
                                <div id="searchSuggestions"></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label for="categoryFilter" class="form-label text-uppercase small text-muted">Category</label>
                            <select id="categoryFilter" class="form-select form-select-lg">
                                <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="faucet" <?php echo $category === 'faucet' ? 'selected' : ''; ?>>Crypto Faucets</option>
                                <option value="url_shortener" <?php echo $category === 'url_shortener' ? 'selected' : ''; ?>>URL Shorteners</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label for="statusFilter" class="form-label text-uppercase small text-muted">Status</label>
                            <select id="statusFilter" class="form-select form-select-lg">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="paying" <?php echo $status === 'paying' ? 'selected' : ''; ?>>Verified Paying</option>
                                <option value="scam_reported" <?php echo $status === 'scam_reported' ? 'selected' : ''; ?>>Scam Reported</option>
                                <option value="scam" <?php echo $status === 'scam' ? 'selected' : ''; ?>>Confirmed Scam</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label for="sortFilter" class="form-label text-uppercase small text-muted">Sort</label>
                            <select id="sortFilter" class="form-select form-select-lg">
                                <option value="upvotes" <?php echo $sort === 'upvotes' ? 'selected' : ''; ?>>Most Upvoted</option>
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                                <option value="trending" <?php echo $sort === 'trending' ? 'selected' : ''; ?>>Trending</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label for="perPageFilter" class="form-label text-uppercase small text-muted">Per Page</label>
                            <select id="perPageFilter" class="form-select form-select-lg">
                                <option value="12" <?php echo $per_page === 12 ? 'selected' : ''; ?>>12</option>
                                <option value="15" <?php echo $per_page === 15 ? 'selected' : ''; ?>>15</option>
                                <option value="20" <?php echo $per_page === 20 ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?php echo $per_page === 30 ? 'selected' : ''; ?>>30</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-2">
                            <label class="form-label text-uppercase small text-muted">View</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="view-btn <?php echo $view_mode === 'grid' ? 'active' : ''; ?>" data-view="grid" onclick="toggleView('grid', this)"><i class="fas fa-border-all me-2"></i>Grid</button>
                                <button type="button" class="view-btn <?php echo $view_mode === 'list' ? 'active' : ''; ?>" data-view="list" onclick="toggleView('list', this)"><i class="fas fa-bars me-2"></i>List</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sites-container glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                        <h2 class="section-heading mb-3 mb-md-0 text-white">
                            Community Rankings <span class="text-muted small">(<?php echo number_format($total_sites); ?> sites)</span>
                        </h2>
                        <div class="text-muted d-flex align-items-center gap-2"><i class="fas fa-arrows-rotate"></i> Updates every 30 seconds</div>
                    </div>

                    <?php if (!empty($sites)): ?>
                        <div id="sitesContainer" class="<?php echo $view_mode === 'grid' ? 'sites-grid' : 'sites-list'; ?>">
                            <?php
                            $rank_offset = ($page - 1) * $per_page + 1;
                            foreach ($sites as $index => $site):
                                $current_rank = $rank_offset + $index;
                                $crown_icon = '';
                                if ($current_rank <= 3) {
                                    $crown_icon = match($current_rank) {
                                        1 => '<i class="fas fa-crown text-warning me-2"></i>',
                                        2 => '<i class="fas fa-crown text-secondary me-2"></i>',
                                        3 => '<i class="fas fa-crown text-info me-2"></i>',
                                        default => ''
                                    };
                                }
                            ?>
                            <div class="site-card listing-card <?php echo $view_mode === 'list' ? 'list-view ' : ''; ?>position-relative animate-fade-in" data-site-id="<?php echo $site['id']; ?>" data-rank="<?php echo $current_rank; ?>">
                                <?php if ($current_rank <= 3): ?>
                                    <span class="stat-ribbon rank-<?php echo $current_rank; ?>"><?php echo $crown_icon; ?>Top <?php echo $current_rank; ?></span>
                                <?php endif; ?>
                                <div class="site-header">
                                    <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" alt="<?php echo htmlspecialchars($site['name']); ?>" class="site-logo">
                                    <div class="site-info">
                                        <!--<h3 class="text-white mb-1">
                                            #<?php echo $current_rank; ?> <?php echo htmlspecialchars($site['name']); ?>
                                            <?php if ($site['promotion_status'] === 'sponsored'): ?>
                                                <span class="badge bg-warning text-dark fw-semibold"><i class="fas fa-crown me-1"></i>Sponsored</span>
                                            <?php elseif ($site['promotion_status'] === 'boosted'): ?>
                                                <span class="badge bg-info text-dark fw-semibold"><i class="fas fa-rocket me-1"></i>Boosted</span>
                                            <?php endif; ?>
                                        </h3>-->
                                        <span class="site-category"><i class="fas fa-layer-group"></i><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></span>
                                    </div>
                                </div>

                                <div class="site-description"><?php echo htmlspecialchars(truncateText($site['description'], 60)); ?></div>

                                <div class="site-rating">
                                    <?php echo renderStars(round($site['average_rating']), '1rem'); ?>
                                    <span class="rating-value"><?php echo number_format($site['average_rating'], 1); ?>/5</span>
                                    <span class="text-muted small">(<?php echo $site['review_count']; ?>)</span>
                                </div>

                                <div class="site-metrics">
                                    <div class="metrics-left">
                                        <div class="metric positive"><i class="fas fa-thumbs-up"></i><span><?php echo $site['total_upvotes']; ?></span></div>
                                        <?php if ($site['total_downvotes'] > 0): ?>
                                        <div class="metric negative"><i class="fas fa-thumbs-down"></i><span><?php echo $site['total_downvotes']; ?></span></div>
                                        <?php endif; ?>
                                        <div class="metric"><i class="fas fa-comments"></i><span><?php echo $site['review_count']; ?></span></div>
                                        <div class="metric"><i class="fas fa-clock"></i><span><?php echo timeAgo($site['created_at']); ?></span></div>
                                    </div>
                                    <?php echo getStatusBadge($site['status'] ?? 'paying'); ?>
                                </div>

                                <div class="site-actions">
                                    <div class="vote-buttons">
                                        <button class="vote-btn upvote" onclick="vote(<?php echo $site['id']; ?>, 'upvote', 'site')" data-site-id="<?php echo $site['id']; ?>" data-vote-type="upvote">
                                            <i class="fas fa-thumbs-up"></i>
                                            <span class="vote-count"><?php echo $site['total_upvotes']; ?></span>
                                        </button>
                                        <button class="vote-btn downvote" onclick="vote(<?php echo $site['id']; ?>, 'downvote', 'site')" data-site-id="<?php echo $site['id']; ?>" data-vote-type="downvote">
                                            <i class="fas fa-thumbs-down"></i>
                                            <span class="vote-count"><?php echo $site['total_downvotes']; ?></span>
                                        </button>
                                    </div>
                                    <a href="review?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-gradient"><i class="fas fa-info-circle me-2"></i>View Details</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-shell pagination-container mt-4">
                            <div class="pagination premium-pagination">
                                <?php if ($page > 1): ?>
                                    <button type="button" class="page-btn nav-btn" onclick="changePage(<?php echo $page - 1; ?>)"><i class="fas fa-chevron-left"></i></button>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <button type="button" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>" onclick="changePage(<?php echo $i; ?>)"><?php echo $i; ?></button>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <button type="button" class="page-btn nav-btn" onclick="changePage(<?php echo $page + 1; ?>)"><i class="fas fa-chevron-right"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>No Sites Found</h3>
                            <p>No sites match your current filters. Try adjusting your search criteria.</p>
                            <button onclick="clearFilters()" class="btn btn-theme btn-gradient">Clear Filters</button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dev-slot2 mt-4">After Listings Ad Slot 468x60</div>
            </div>
            <div class="col-xl-3">
                <div class="dev-slot1 mb-4">Sidebar Tower Ad 300x600</div>
                <div class="glass-card p-4 mb-4 animate-fade-in" data-aos="fade-up">
                    <h4 class="text-white mb-3"><i class="fas fa-bullhorn me-2"></i>Boost Your Visibility</h4>
                    <p class="text-muted">Feature your platform to thousands of verified earners. Flexible sponsorships, premium placements, and ad packages available.</p>
                    <a href="promote-sites" class="btn btn-theme btn-gradient w-100"><i class="fas fa-rocket me-2"></i>Promote Now</a>
                </div>
                <div class="glass-card p-4 animate-fade-in" data-aos="fade-up">
                    <h4 class="text-white mb-3"><i class="fas fa-lightbulb me-2"></i>Analyst Notes</h4>
                    <ul class="text-muted list-unstyled d-grid gap-2 mb-0">
                        <li><i class="fas fa-check-circle me-2 text-success"></i>Watch status changes every 30 minutes.</li>
                        <li><i class="fas fa-check-circle me-2 text-success"></i>Combine community votes with your own KYC checks.</li>
                        <li><i class="fas fa-check-circle me-2 text-success"></i>Bookmark favourites from your dashboard.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <div class="glass-card p-4 p-lg-5 text-center animate-fade-in" data-aos="fade-up">
            <h2 class="section-heading mb-3">Need Additional Due Diligence?</h2>
            <p class="section-subtitle mb-4">Our Trust &amp; Safety team can audit traffic claims, payment proofs, and ownership before you invest your time.</p>
            <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                <a href="support-tickets" class="btn btn-theme btn-gradient btn-lg"><i class="fas fa-shield-halved me-2"></i>Request Verification</a>
                <a href="help" class="btn btn-theme btn-outline-glass btn-lg"><i class="fas fa-life-ring me-2"></i>Visit Help Center</a>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <div class="dev-slot2">Footer Inline Ad Slot 728x90</div>
    </div>
</main>

<script>
// Global variables
let currentFilters = {
    category: '<?php echo $category; ?>',
    status: '<?php echo $status; ?>',
    sort: '<?php echo $sort; ?>',
    search: '<?php echo $search; ?>',
    page: <?php echo $page; ?>,
    per_page: <?php echo $per_page; ?>,
    view: '<?php echo $view_mode; ?>'
};

let searchTimeout;
let userVotes = {}; // Track user votes

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
    initializeSearch();
    loadUserVotes();
    startLiveUpdates();
    applyViewMode();
});

// Filter handling
function initializeFilters() {
    document.getElementById('categoryFilter').addEventListener('change', updateFilters);
    document.getElementById('statusFilter').addEventListener('change', updateFilters);
    document.getElementById('sortFilter').addEventListener('change', updateFilters);
    document.getElementById('perPageFilter').addEventListener('change', updateFilters);
}

function updateFilters() {
    currentFilters.category = document.getElementById('categoryFilter').value;
    currentFilters.status = document.getElementById('statusFilter').value;
    currentFilters.sort = document.getElementById('sortFilter').value;
    currentFilters.per_page = parseInt(document.getElementById('perPageFilter').value);
    currentFilters.page = 1; // Reset to first page
    
    updateURL();
    loadSitesAjax();
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    const suggestionsContainer = document.getElementById('searchSuggestions');
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                fetchSearchSuggestions(query);
            }, 300);
        } else {
            suggestionsContainer.style.display = 'none';
        }
    });
    
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch();
        }
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });
}

function fetchSearchSuggestions(query) {
    fetch('ajax/search-suggestions.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suggestions.length > 0) {
                showSuggestions(data.suggestions);
            } else {
                document.getElementById('searchSuggestions').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Search suggestions error:', error);
        });
}

function showSuggestions(suggestions) {
    const container = document.getElementById('searchSuggestions');
    container.innerHTML = '';
    
    suggestions.forEach(suggestion => {
        const item = document.createElement('div');
        item.className = 'suggestion-item';
        item.textContent = suggestion;
        item.onclick = () => selectSuggestion(suggestion);
        container.appendChild(item);
    });
    
    container.style.display = 'block';
}

function selectSuggestion(suggestion) {
    document.getElementById('searchInput').value = suggestion;
    document.getElementById('searchSuggestions').style.display = 'none';
    performSearch();
}

function performSearch() {
    currentFilters.search = document.getElementById('searchInput').value.trim();
    currentFilters.page = 1;
    updateURL();
    loadSitesAjax();
}

// View toggle (no page refresh)
function toggleView(view, button) {
    currentFilters.view = view;

    document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
    if (button) {
        button.classList.add('active');
    } else {
        const fallback = document.querySelector(`.view-btn[data-view="${view}"]`);
        if (fallback) fallback.classList.add('active');
    }

    applyViewMode();
    updateURL();
}

// Pagination
function changePage(page) {
    currentFilters.page = page;
    updateURL();
    loadSitesAjax();
}

// Voting system
function vote(siteId, voteType, targetType) {
    if (!<?php echo $auth->isLoggedIn() ? 'true' : 'false'; ?>) {
        alert('Please login to vote');
        return;
    }
    
    const voteData = {
        target_id: siteId,
        vote_type: voteType,
        target_type: targetType
    };
    
    fetch('ajax/vote.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(voteData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateVoteDisplay(siteId, data.data);
            userVotes[siteId] = data.data.user_vote;
        } else {
            alert(data.message || 'Error voting');
        }
    })
    .catch(error => {
        console.error('Voting error:', error);
        alert('Error processing vote');
    });
}

function updateVoteDisplay(siteId, voteData) {
    const siteCard = document.querySelector(`[data-site-id="${siteId}"]`);
    if (!siteCard) return;
    
    // Update vote counts
    const upvoteBtn = siteCard.querySelector('.vote-btn.upvote .vote-count');
    const downvoteBtn = siteCard.querySelector('.vote-btn.downvote .vote-count');
    
    if (upvoteBtn) upvoteBtn.textContent = voteData.upvotes;
    if (downvoteBtn) downvoteBtn.textContent = voteData.downvotes;
    
    // Update button states
    const upvoteBtnEl = siteCard.querySelector('.vote-btn.upvote');
    const downvoteBtnEl = siteCard.querySelector('.vote-btn.downvote');
    
    // Reset states
    upvoteBtnEl.classList.remove('voted');
    downvoteBtnEl.classList.remove('voted');
    
    // Set active state
    if (voteData.user_vote === 'upvote') {
        upvoteBtnEl.classList.add('voted');
    } else if (voteData.user_vote === 'downvote') {
        downvoteBtnEl.classList.add('voted');
    }
    
    // Update metrics in card
    const upvoteMetric = siteCard.querySelector('.metric.positive span');
    if (upvoteMetric) upvoteMetric.textContent = voteData.upvotes;
}

function loadUserVotes() {
    if (!<?php echo $auth->isLoggedIn() ? 'true' : 'false'; ?>) return;
    
    const siteIds = Array.from(document.querySelectorAll('[data-site-id]')).map(el => el.dataset.siteId);
    
    if (siteIds.length === 0) return;
    
    fetch('ajax/get-user-votes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({site_ids: siteIds})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            data.votes.forEach(vote => {
                userVotes[vote.site_id] = vote.vote_type;
                updateVoteButtonState(vote.site_id, vote.vote_type);
            });
        }
    })
    .catch(error => console.error('Error loading user votes:', error));
}

function updateVoteButtonState(siteId, voteType) {
    const siteCard = document.querySelector(`[data-site-id="${siteId}"]`);
    if (!siteCard) return;
    
    const upvoteBtn = siteCard.querySelector('.vote-btn.upvote');
    const downvoteBtn = siteCard.querySelector('.vote-btn.downvote');
    
    // Reset states
    upvoteBtn.classList.remove('voted');
    downvoteBtn.classList.remove('voted');
    
    // Set active state
    if (voteType === 'upvote') {
        upvoteBtn.classList.add('voted');
    } else if (voteType === 'downvote') {
        downvoteBtn.classList.add('voted');
    }
}

// Live updates
function startLiveUpdates() {
    setInterval(updateVoteCounts, 30000); // Update every 30 seconds
}

function updateVoteCounts() {
    const siteIds = Array.from(document.querySelectorAll('[data-site-id]')).map(el => el.dataset.siteId);
    
    if (siteIds.length === 0) return;
    
    fetch('ajax/get-vote-counts.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({site_ids: siteIds})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            data.votes.forEach(vote => {
                updateVoteDisplay(vote.site_id, {
                    upvotes: vote.upvotes,
                    downvotes: vote.downvotes,
                    user_vote: userVotes[vote.site_id] || null
                });
            });
        }
    })
    .catch(error => console.error('Error updating vote counts:', error));
}

// URL management
function updateURL() {
    const params = new URLSearchParams();
    
    Object.keys(currentFilters).forEach(key => {
        if (currentFilters[key] && currentFilters[key] !== 'all' && currentFilters[key] !== '') {
            params.set(key, currentFilters[key]);
        }
    });
    
    const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newURL);
}

function applyViewMode() {
    const container = document.getElementById('sitesContainer');
    if (!container) return;

    container.className = currentFilters.view === 'list' ? 'sites-list' : 'sites-grid';
    container.querySelectorAll('.site-card').forEach(card => {
        card.classList.toggle('list-view', currentFilters.view === 'list');
    });
}

function loadSitesAjax() {
    showLoadingState();

    const params = new URLSearchParams(currentFilters);

    fetch('ajax/load-sites.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update sites container
                document.getElementById('sitesContainer').innerHTML = data.sites_html;
                applyViewMode();
                
                // Update pagination
                const paginationContainer = document.querySelector('.pagination-container');
                if (paginationContainer) {
                    if (data.pagination_html) {
                        paginationContainer.innerHTML = data.pagination_html;
                    } else {
                        paginationContainer.innerHTML = '';
                    }
                }
                
                // Update total count
                const totalCountSpan = document.querySelector('.sites-container h2 span');
                if (totalCountSpan) {
                    totalCountSpan.textContent = '(' + data.total_sites.toLocaleString() + ' sites)';
                }
                
                // Reload user votes for new sites
                loadUserVotes();
                
                // Scroll to top of sites container
                document.querySelector('.sites-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                // Handle cases where no sites are found more gracefully
                document.getElementById('sitesContainer').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Sites Found</h3>
                        <p>No sites match your current filters. Try adjusting your search criteria.</p>
                        <button onclick="clearFilters()" class="btn btn-primary">Clear Filters</button>
                    </div>
                `;
                // Clear pagination if no sites
                const paginationContainer = document.querySelector('.pagination-container');
                if (paginationContainer) paginationContainer.innerHTML = '';
            }
            hideLoadingState();
        })
        .catch(error => {
            console.error('[v0] Error loading sites:', error);
            hideLoadingState();
            showToast('Error loading sites. Please try again.', 'error');
        });
}

function showLoadingState() {
    const container = document.getElementById('sitesContainer');
    container.style.opacity = '0.5';
    container.style.pointerEvents = 'none';
}

function hideLoadingState() {
    const container = document.getElementById('sitesContainer');
    container.style.opacity = '1';
    container.style.pointerEvents = 'auto';
}

function clearFilters() {
    currentFilters = {
        category: 'all',
        status: 'all',
        sort: 'upvotes',
        search: '',
        page: 1,
        per_page: 12,
        view: 'grid'
    };
    
    // Reset form elements
    document.getElementById('categoryFilter').value = 'all';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('sortFilter').value = 'upvotes';
    document.getElementById('searchInput').value = '';
    document.getElementById('perPageFilter').value = '12';
    
    updateURL();
    loadSitesAjax();
}

// Utility functions
function showToast(message, type = 'info') {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Track clicks for analytics
function trackSiteClick(siteId) {
    fetch('ajax/track-click.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({site_id: siteId})
    }).catch(error => console.error('Click tracking error:', error));
}
</script>

<?php include 'includes/footer.php'; ?>