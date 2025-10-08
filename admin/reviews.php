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
$displaying_reviews = count($reviews);

$review_summary = [
    'scam_reports' => 0,
    'highlighted' => 0,
    'low_rating' => 0,
    'high_rating' => 0,
];

try {
    $review_summary_stmt = $db->query("SELECT
        SUM(CASE WHEN is_scam_report = 1 THEN 1 ELSE 0 END) AS scam_reports,
        SUM(CASE WHEN is_highlighted = 1 THEN 1 ELSE 0 END) AS highlighted,
        SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS low_rating,
        SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) AS high_rating
    FROM reviews WHERE is_deleted = 0");

    if ($review_summary_stmt) {
        $fetched = $review_summary_stmt->fetch(PDO::FETCH_ASSOC);
        if ($fetched) {
            foreach ($review_summary as $key => $default) {
                if (isset($fetched[$key]) && is_numeric($fetched[$key])) {
                    $review_summary[$key] = (int) $fetched[$key];
                }
            }
        }
    }
} catch (Exception $exception) {
    // Ignore telemetry issues.
}

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

<div class="container-fluid">
    <div class="row g-0">
        <?php include 'includes/admin_sidebar.php'; ?>

        <main class="main-content-shell col-12 col-xl-10 ms-auto">
            <div class="page-hero glass-card p-4 p-xl-5 mb-4 fade-in">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h1 class="page-title mb-2">Review Intelligence</h1>
                        <p class="page-subtitle mb-0">Investigate sentiment, highlight trusted voices, and escalate risks instantly.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="analytics.php#reviews" class="btn btn-primary px-4">
                            <i class="fas fa-chart-line me-2"></i>Sentiment Trends
                        </a>
                        <a href="logs.php" class="btn btn-outline-light px-4">
                            <i class="fas fa-bug me-2"></i>Flagged Activity
                        </a>
                    </div>
                </div>
                <div class="row g-4 mt-1">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="metric-icon primary"><i class="fas fa-comments"></i></span>
                            <div>
                                <div class="metric-label">Total reviews</div>
                                <div class="metric-value"><?php echo number_format($total_reviews); ?></div>
                                <span class="metric-trend text-muted small">Showing <?php echo number_format($displaying_reviews); ?> this view</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="metric-icon danger"><i class="fas fa-triangle-exclamation"></i></span>
                            <div>
                                <div class="metric-label">Scam reports</div>
                                <div class="metric-value"><?php echo number_format((int) ($review_summary['scam_reports'] ?? 0)); ?></div>
                                <span class="metric-trend text-muted small">Requires manual follow-up</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="metric-icon success"><i class="fas fa-highlighter"></i></span>
                            <div>
                                <div class="metric-label">Spotlight stories</div>
                                <div class="metric-value"><?php echo number_format((int) ($review_summary['highlighted'] ?? 0)); ?></div>
                                <span class="metric-trend text-muted small">Pinned to frontend</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="metric-icon warning"><i class="fas fa-face-frown"></i></span>
                            <div>
                                <div class="metric-label">Low ratings</div>
                                <div class="metric-value"><?php echo number_format((int) ($review_summary['low_rating'] ?? 0)); ?></div>
                                <span class="metric-trend text-muted small">Watch for churn signals</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="glass-card page-alert alert alert-success fade-in mb-4" role="alert">
                    <div class="d-flex align-items-center gap-3">
                        <span class="alert-icon text-success"><i class="fas fa-circle-check"></i></span>
                        <div>
                            <h6 class="text-uppercase small fw-bold mb-1">Action completed</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="glass-card page-alert alert alert-danger fade-in mb-4" role="alert">
                    <div class="d-flex align-items-center gap-3">
                        <span class="alert-icon text-danger"><i class="fas fa-circle-exclamation"></i></span>
                        <div>
                            <h6 class="text-uppercase small fw-bold mb-1">Action required</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="glass-card p-4 mb-4 fade-in">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Filter narratives</h2>
                        <p class="text-muted small mb-0">Drill into scam reports, highlights, or sentiment slices.</p>
                    </div>
                    <a href="reviews.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-rotate"></i> Reset
                    </a>
                </div>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-12 col-lg-5">
                        <label class="form-label text-uppercase small fw-semibold">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Site name, comment, or username" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-12 col-lg-5">
                        <label class="form-label text-uppercase small fw-semibold">Filter</label>
                        <select name="filter" class="form-select">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All reviews</option>
                            <option value="scam_reports" <?php echo $filter === 'scam_reports' ? 'selected' : ''; ?>>Scam reports</option>
                            <option value="highlighted" <?php echo $filter === 'highlighted' ? 'selected' : ''; ?>>Highlighted</option>
                            <option value="low_rating" <?php echo $filter === 'low_rating' ? 'selected' : ''; ?>>Low rating (1-2 stars)</option>
                            <option value="high_rating" <?php echo $filter === 'high_rating' ? 'selected' : ''; ?>>High rating (4-5 stars)</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label text-uppercase small fw-semibold">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply
                        </button>
                    </div>
                </form>
            </div>

            <div class="glass-card p-0 fade-in overflow-hidden">
                <?php if (!empty($reviews)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($reviews as $review): ?>
                        <div class="list-group-item">
                            <div class="d-flex flex-wrap align-items-start gap-3">
                                <img src="../<?php echo htmlspecialchars($review['avatar']); ?>" alt="<?php echo htmlspecialchars($review['username']); ?>" class="avatar-circle">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                        <?php if ($review['level_name']): ?>
                                            <span class="badge badge-soft text-primary">
                                                <?php echo $review['badge_icon']; ?> <?php echo htmlspecialchars($review['level_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($review['is_highlighted']): ?>
                                            <span class="badge badge-soft text-warning"><i class="fas fa-star me-1"></i>Highlighted</span>
                                        <?php endif; ?>
                                        <?php if ($review['is_scam_report']): ?>
                                            <span class="badge badge-soft text-danger"><i class="fas fa-circle-exclamation me-1"></i>Scam Report</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 text-warning mb-2">
                                        <?php echo renderStars($review['rating']); ?>
                                        <span class="small text-muted">Rated <?php echo $review['rating']; ?>/5</span>
                                    </div>
                                    <p class="mb-2 text-muted">“<?php echo nl2br(htmlspecialchars($review['comment'])); ?>”</p>
                                    <div class="d-flex flex-wrap gap-3 small text-muted">
                                        <span><i class="fas fa-globe me-1"></i><?php echo htmlspecialchars($review['site_name']); ?></span>
                                        <span><i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?></span>
                                        <span><i class="fas fa-thumbs-up me-1"></i><?php echo number_format($review['likes']); ?> helpful</span>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-2">
                                    <form method="POST">
                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                        <input type="hidden" name="action" value="<?php echo $review['is_highlighted'] ? 'unhighlight' : 'highlight'; ?>">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-highlighter me-1"></i><?php echo $review['is_highlighted'] ? 'Unhighlight' : 'Highlight'; ?>
                                        </button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash me-1"></i>Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-magnifying-glass-minus fa-2x mb-3"></i>
                        <p class="mb-0">No reviews found for the current filters.</p>
                    </div>
                <?php endif; ?>

                <?php if ($total_pages > 1): ?>
                    <div class="p-3 border-top border-0">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
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
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-12">
                    <div class="glass-card ad-slot p-4 text-center fade-in">
                        <span class="text-uppercase small text-muted d-block">Sponsored banner slot</span>
                        <span class="display-6 fw-bold text-muted">728 × 90</span>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="glass-card ad-slot p-4 text-center fade-in h-100">
                        <span class="text-uppercase small text-muted d-block">Community rewards</span>
                        <span class="h3 fw-bold text-muted">300 × 250</span>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="glass-card ad-slot p-4 text-center fade-in h-100">
                        <span class="text-uppercase small text-muted d-block">Feedback survey</span>
                        <span class="h3 fw-bold text-muted">468 × 60</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
