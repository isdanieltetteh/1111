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
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

    <!-- Filters -->
    <div class="admin-content-wrapper mb-4">
        <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Reviews</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Site name, comment, or username..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter</label>
                            <select name="filter" class="form-select">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Reviews</option>
                                <option value="scam_reports" <?php echo $filter === 'scam_reports' ? 'selected' : ''; ?>>Scam Reports</option>
                                <option value="highlighted" <?php echo $filter === 'highlighted' ? 'selected' : ''; ?>>Highlighted</option>
                                <option value="low_rating" <?php echo $filter === 'low_rating' ? 'selected' : ''; ?>>Low Rating (1-2 stars)</option>
                                <option value="high_rating" <?php echo $filter === 'high_rating' ? 'selected' : ''; ?>>High Rating (4-5 stars)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">Filter</button>
                        </div>
        </form>
    </div>

    <!-- Reviews List -->
    <div class="admin-content-wrapper">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-start mb-2">
                                        <img src="../<?php echo htmlspecialchars($review['avatar']); ?>" 
                                             class="rounded-circle me-3" width="40" height="40">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                                <?php if ($review['level_name']): ?>
                                                    <span class="badge bg-primary">
                                                        <?php echo $review['badge_icon']; ?> <?php echo htmlspecialchars($review['level_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($review['is_highlighted']): ?>
                                                    <span class="badge bg-warning text-dark">⭐ Highlighted</span>
                                                <?php endif; ?>
                                                <?php if ($review['is_scam_report']): ?>
                                                    <span class="badge bg-danger">🚨 Scam Report</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <strong>Site:</strong> 
                                                <a href="../review.php?id=<?php echo $review['site_id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($review['site_name']); ?>
                                                </a>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <strong>Rating:</strong> <?php echo renderStars($review['rating']); ?> 
                                                (<?php echo $review['rating']; ?>/5)
                                            </div>
                                            
                                            <div class="mb-2">
                                                <strong>Comment:</strong>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                            </div>
                                            
                                            <?php if ($review['proof_url']): ?>
                                                <div class="mb-2">
                                                    <strong>Proof URL:</strong> 
                                                    <a href="<?php echo htmlspecialchars($review['proof_url']); ?>" target="_blank" rel="nofollow">
                                                        View Proof
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($review['proof_image']): ?>
                                                <div class="mb-2">
                                                    <strong>Proof Image:</strong>
                                                    <br>
                                                    <img src="../<?php echo htmlspecialchars($review['proof_image']); ?>" 
                                                         class="img-thumbnail" style="max-width: 200px;">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <small class="text-muted">
                                                Posted: <?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?> |
                                                Upvotes: <?php echo $review['upvotes']; ?> |
                                                Downvotes: <?php echo $review['downvotes']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="d-grid gap-2">
                                        <?php if ($review['is_highlighted']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="unhighlight">
                                                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm">Remove Highlight</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="highlight">
                                                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm">Highlight</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
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
                        <div class="text-center py-4">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h5>No reviews found</h5>
                            <p class="text-muted">No reviews match your current filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
</main>

<?php include 'includes/admin_footer.php'; ?>
