<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Redirect if not admin
if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $review_id = intval($_POST['review_id'] ?? 0);
    
    switch ($action) {
        case 'highlight':
            $update_query = "UPDATE reviews SET is_highlighted = 1 WHERE id = :review_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':review_id', $review_id);
            if ($update_stmt->execute()) {
                $success_message = 'Review highlighted successfully!';
            } else {
                $error_message = 'Error highlighting review.';
            }
            break;
            
        case 'unhighlight':
            $update_query = "UPDATE reviews SET is_highlighted = 0 WHERE id = :review_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':review_id', $review_id);
            if ($update_stmt->execute()) {
                $success_message = 'Review unhighlighted.';
            } else {
                $error_message = 'Error unhighlighting review.';
            }
            break;
            
        case 'delete':
            $update_query = "UPDATE reviews SET is_deleted = 1 WHERE id = :review_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':review_id', $review_id);
            if ($update_stmt->execute()) {
                $success_message = 'Review deleted successfully.';
            } else {
                $error_message = 'Error deleting review.';
            }
            break;
    }
}

// Get filters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = ['r.is_deleted = 0'];
$params = [];

if ($filter !== 'all') {
    switch ($filter) {
        case 'scam_reports':
            $where_conditions[] = "r.is_scam_report = 1";
            break;
        case 'highlighted':
            $where_conditions[] = "r.is_highlighted = 1";
            break;
        case 'low_rating':
            $where_conditions[] = "r.rating <= 2";
            break;
        case 'high_rating':
            $where_conditions[] = "r.rating >= 4";
            break;
    }
}

if (!empty($search)) {
    $where_conditions[] = "(s.name LIKE :search OR r.comment LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total 
                FROM reviews r 
                JOIN sites s ON r.site_id = s.id 
                JOIN users u ON r.user_id = u.id 
                WHERE {$where_clause}";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_reviews = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_reviews / $per_page);

// Get reviews
$reviews_query = "SELECT r.*, s.name as site_name, u.username, u.avatar,
                  l.name as level_name, l.badge_icon
                  FROM reviews r
                  JOIN sites s ON r.site_id = s.id
                  JOIN users u ON r.user_id = u.id
                  LEFT JOIN levels l ON u.level_id = l.id
                  WHERE {$where_clause}
                  ORDER BY r.created_at DESC
                  LIMIT {$per_page} OFFSET {$offset}";

$reviews_stmt = $db->prepare($reviews_query);
$reviews_stmt->execute($params);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

$review_overview_stmt = $db->query(
    "SELECT 
        COUNT(*) AS total_count,
        SUM(CASE WHEN is_highlighted = 1 THEN 1 ELSE 0 END) AS highlighted_count,
        SUM(CASE WHEN is_scam_report = 1 THEN 1 ELSE 0 END) AS scam_count,
        AVG(rating) AS average_rating,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS recent_count
     FROM reviews
     WHERE is_deleted = 0"
);
$review_overview = $review_overview_stmt ? $review_overview_stmt->fetch(PDO::FETCH_ASSOC) : [];
$review_overview = $review_overview ?: [
    'total_count' => 0,
    'highlighted_count' => 0,
    'scam_count' => 0,
    'average_rating' => 0,
    'recent_count' => 0,
];

$rating_distribution_stmt = $db->query(
    "SELECT rating, COUNT(*) AS total
     FROM reviews
     WHERE is_deleted = 0
     GROUP BY rating
     ORDER BY rating DESC"
);
$rating_distribution = $rating_distribution_stmt ? $rating_distribution_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$recent_scam_stmt = $db->prepare(
    "SELECT r.id, r.comment, r.created_at, s.name AS site_name, u.username
     FROM reviews r
     JOIN sites s ON r.site_id = s.id
     JOIN users u ON r.user_id = u.id
     WHERE r.is_deleted = 0 AND r.is_scam_report = 1
     ORDER BY r.created_at DESC
     LIMIT 5"
);
$recent_scam_stmt->execute();
$recent_scam_reports = $recent_scam_stmt->fetchAll(PDO::FETCH_ASSOC);

$global_total_reviews = (int) $review_overview['total_count'];

function renderStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $class = $i <= $rating ? 'text-warning' : 'text-muted';
        $html .= '<i class="fas fa-star ' . $class . '"></i>';
    }
    return $html;
}

$page_title = 'Reviews Management - Admin Panel';
include 'includes/admin_header.php';
?>

<?php include 'includes/admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-page-header">
        <div>
            <div class="admin-breadcrumb">
                <i class="fas fa-comments text-primary"></i>
                <span>Community</span>
                <span class="text-muted">Reviews</span>
            </div>
            <h1>Feedback Radar</h1>
            <p class="text-muted mb-0">Moderate trust signals and surface standout experiences.</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success shadow-sm border-0"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Total Reviews</p>
                        <p class="metric-value mb-1"><?php echo number_format($review_overview['total_count']); ?></p>
                        <span class="metric-trend up"><i class="fas fa-plus"></i><?php echo number_format($review_overview['recent_count']); ?> new this week</span>
                    </div>
                    <span class="metric-icon info"><i class="fas fa-comments"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Highlighted</p>
                        <p class="metric-value mb-1"><?php echo number_format($review_overview['highlighted_count']); ?></p>
                        <span class="metric-trend up"><i class="fas fa-star"></i>Premium voices</span>
                    </div>
                    <span class="metric-icon success"><i class="fas fa-star"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Scam Alerts</p>
                        <p class="metric-value mb-1"><?php echo number_format($review_overview['scam_count']); ?></p>
                        <span class="metric-trend down"><i class="fas fa-triangle-exclamation"></i>Monitor closely</span>
                    </div>
                    <span class="metric-icon danger"><i class="fas fa-shield-halved"></i></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Average Rating</p>
                        <p class="metric-value mb-1"><?php echo number_format((float) $review_overview['average_rating'], 1); ?>/5</p>
                        <span class="metric-trend up"><i class="fas fa-tachometer-alt"></i>Experience pulse</span>
                    </div>
                    <span class="metric-icon warning"><i class="fas fa-tachometer-alt"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-content-wrapper mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="admin-section-title">Review Filters</h2>
                <p class="admin-section-subtitle mb-0">Zero in on fraud signals, highlights, and sentiment.</p>
            </div>
        </div>
        <form method="GET" class="admin-toolbar">
            <div>
                <label class="form-label">Search Reviews</label>
                <input type="text" name="search" class="form-control" placeholder="Site name, comment, or username..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div>
                <label class="form-label">Filter</label>
                <select name="filter" class="form-select">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Reviews</option>
                    <option value="scam_reports" <?php echo $filter === 'scam_reports' ? 'selected' : ''; ?>>Scam Reports</option>
                    <option value="highlighted" <?php echo $filter === 'highlighted' ? 'selected' : ''; ?>>Highlighted</option>
                    <option value="low_rating" <?php echo $filter === 'low_rating' ? 'selected' : ''; ?>>Low Rating (1-2 stars)</option>
                    <option value="high_rating" <?php echo $filter === 'high_rating' ? 'selected' : ''; ?>>High Rating (4-5 stars)</option>
                </select>
            </div>
            <div class="ms-auto">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <a href="reviews.php" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary shadow-hover">Apply</button>
                </div>
            </div>
        </form>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="admin-section-title">Quality Snapshot</h2>
                        <p class="admin-section-subtitle mb-0">Distribution of ratings across the entire community.</p>
                    </div>
                </div>
                <?php if (!empty($rating_distribution)): ?>
                    <div class="quality-distribution">
                        <?php foreach ($rating_distribution as $segment): ?>
                            <?php $ratingPercent = $global_total_reviews > 0 ? round(($segment['total'] / $global_total_reviews) * 100) : 0; ?>
                            <div class="quality-row">
                                <span class="quality-label"><?php echo $segment['rating']; ?> ★</span>
                                <div class="quality-progress">
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" style="width: <?php echo $ratingPercent; ?>%"></div>
                                    </div>
                                </div>
                                <span class="quality-value"><?php echo number_format($segment['total']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No ratings recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="admin-content-wrapper h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="admin-section-title">Recent Scam Reports</h2>
                        <p class="admin-section-subtitle mb-0">Latest escalations requiring moderator follow-up.</p>
                    </div>
                </div>
                <?php if (!empty($recent_scam_reports)): ?>
                    <ul class="system-timeline mb-0">
                        <?php foreach ($recent_scam_reports as $report): ?>
                            <?php
                            $comment_preview = $report['comment'];
                            if (strlen($comment_preview) > 120) {
                                $comment_preview = substr($comment_preview, 0, 117) . '...';
                            }
                            ?>
                            <li class="system-timeline-item">
                                <span class="system-timeline-marker system-timeline-marker-danger"></span>
                                <div class="system-timeline-content">
                                    <strong><?php echo htmlspecialchars($report['site_name']); ?></strong>
                                    <p class="mb-1 text-muted">"<?php echo htmlspecialchars($comment_preview); ?>"</p>
                                    <small class="text-muted">By <?php echo htmlspecialchars($report['username']); ?> • <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">No scam escalations logged in the latest submissions.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="admin-content-wrapper">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="admin-section-title">Review Feed</h2>
                <p class="admin-section-subtitle mb-0"><?php echo number_format($total_reviews); ?> matched reviews (page <?php echo $page; ?> of <?php echo max($total_pages, 1); ?>).</p>
            </div>
        </div>

        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
                <?php $reviewAvatar = !empty($review['avatar']) ? $review['avatar'] : 'assets/images/default-avatar.png'; ?>
                <div class="admin-review-card mb-4">
                    <div class="admin-review-header">
                        <span class="avatar-ring md">
                            <img src="../<?php echo htmlspecialchars($reviewAvatar); ?>" alt="<?php echo htmlspecialchars($review['username']); ?>">
                        </span>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h5 class="mb-0"><?php echo htmlspecialchars($review['username']); ?></h5>
                                <?php if ($review['level_name']): ?>
                                    <span class="status-chip info"><?php echo $review['badge_icon']; ?> <?php echo htmlspecialchars($review['level_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($review['is_highlighted']): ?>
                                    <span class="status-chip warning"><i class="fas fa-star"></i>Highlighted</span>
                                <?php endif; ?>
                                <?php if ($review['is_scam_report']): ?>
                                    <span class="status-chip danger"><i class="fas fa-triangle-exclamation"></i>Scam Report</span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-review-meta mt-2">
                                <span><i class="fas fa-link"></i> <a href="../review.php?id=<?php echo $review['site_id']; ?>" target="_blank" class="text-decoration-none"><?php echo htmlspecialchars($review['site_name']); ?></a></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-review-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <div class="review-rating">
                                <span class="review-stars"><?php echo renderStars($review['rating']); ?></span>
                                <span class="fw-semibold"><?php echo $review['rating']; ?>/5</span>
                            </div>
                            <span class="admin-pill"><span class="dot"></span>Up <?php echo number_format($review['upvotes']); ?> · Down <?php echo number_format($review['downvotes']); ?></span>
                        </div>

                        <p class="review-comment mb-3"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>

                        <?php if ($review['proof_url'] || $review['proof_image']): ?>
                            <div class="review-evidence">
                                <?php if ($review['proof_url']): ?>
                                    <a href="<?php echo htmlspecialchars($review['proof_url']); ?>" target="_blank" rel="nofollow" class="btn btn-link px-0 review-proof-link"><i class="fas fa-external-link-alt"></i> Proof URL</a>
                                <?php endif; ?>
                                <?php if ($review['proof_image']): ?>
                                    <a href="../<?php echo htmlspecialchars($review['proof_image']); ?>" target="_blank" class="review-proof-thumb">
                                        <img src="../<?php echo htmlspecialchars($review['proof_image']); ?>" alt="Proof image" class="img-fluid">
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="table-action-group mt-3">
                            <?php if ($review['is_highlighted']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="unhighlight">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <button type="submit" class="btn btn-warning btn-sm shadow-hover">Remove Highlight</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="highlight">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm shadow-hover">Highlight</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm shadow-hover">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="admin-subtle-card text-center">
                <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                <h6>No reviews found</h6>
                <p class="mb-0">Try broadening your filters to reveal more community feedback.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/admin_footer.php'; ?>
