<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Get filters from request
$category = $_GET['category'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'upvotes';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = intval($_GET['per_page'] ?? 12);
$view = $_GET['view'] ?? 'grid';

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
if (!in_array($view, $valid_views)) $view = 'grid';

$offset = ($page - 1) * $per_page;

$where_conditions = ['s.is_approved = 1', 's.is_dead = FALSE', 's.admin_approved_dead = FALSE', 's.is_sponsored = 0', 's.is_boosted = 0'];
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
$total_sites = (int) ($count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$total_pages = $per_page > 0 ? (int) ceil($total_sites / $per_page) : 0;

$sites_query = "SELECT s.*,
                COALESCE(AVG(r.rating), 0) as average_rating,
                COUNT(r.id) as review_count,
                u.username as submitted_by_username,
                (s.total_upvotes - s.total_downvotes) as vote_score
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

function truncateText($text, $length = 140) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

function timeAgo($datetime) {
    if (!$datetime) {
        return '';
    }
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    return floor($time / 86400) . 'd ago';
}

function getCrownIcon($rank) {
    return match($rank) {
        1 => '<i class="fas fa-crown text-warning me-1"></i>',
        2 => '<i class="fas fa-crown text-secondary me-1"></i>',
        3 => '<i class="fas fa-crown text-info me-1"></i>',
        default => ''
    };
}

$rank_offset = ($page - 1) * $per_page + 1;

ob_start();
foreach ($sites as $index => $site) {
    $current_rank = $rank_offset + $index;
    $logo = htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png');
    $name = htmlspecialchars($site['name']);
    $category = htmlspecialchars(ucfirst(str_replace('_', ' ', $site['category'])));
    $description = htmlspecialchars(truncateText($site['description'] ?? '', 140));
    $status_badge = getStatusBadge($site['status'] ?? 'paying');
    $list_class = $view === 'list' ? 'list-view ' : '';
    ?>
    <div class="site-card listing-card <?php echo $list_class; ?>position-relative animate-fade-in" data-site-id="<?php echo $site['id']; ?>" data-rank="<?php echo $current_rank; ?>">
        <?php if ($current_rank <= 3): ?>
            <span class="stat-ribbon rank-<?php echo $current_rank; ?>"><?php echo getCrownIcon($current_rank); ?>Top <?php echo $current_rank; ?></span>
        <?php endif; ?>
        <div class="site-header">
            <img src="<?php echo $logo; ?>" alt="<?php echo $name; ?>" class="site-logo">
            <div class="site-info">
                <h3 class="text-white mb-1">#<?php echo $current_rank; ?> <?php echo $name; ?></h3>
                <span class="site-category"><i class="fas fa-layer-group"></i><?php echo $category; ?></span>
            </div>
        </div>

        <div class="site-description"><?php echo $description; ?></div>

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
                <div class="metric"><i class="fas fa-clock"></i><span><?php echo timeAgo($site['created_at'] ?? null); ?></span></div>
            </div>
            <?php echo $status_badge; ?>
        </div>

        <div class="site-actions">
            <div class="vote-buttons">
                <button class="vote-btn upvote"
                        onclick="vote(<?php echo (int) $site['id']; ?>, 'upvote', 'site')"
                        data-site-id="<?php echo (int) $site['id']; ?>"
                        data-vote-type="upvote">
                    <i class="fas fa-thumbs-up"></i>
                    <span class="vote-count"><?php echo $site['total_upvotes']; ?></span>
                </button>
                <button class="vote-btn downvote"
                        onclick="vote(<?php echo (int) $site['id']; ?>, 'downvote', 'site')"
                        data-site-id="<?php echo (int) $site['id']; ?>"
                        data-vote-type="downvote">
                    <i class="fas fa-thumbs-down"></i>
                    <span class="vote-count"><?php echo $site['total_downvotes']; ?></span>
                </button>
            </div>
            <a href="review?id=<?php echo (int) $site['id']; ?>" class="btn btn-theme btn-gradient"><i class="fas fa-info-circle me-2"></i>View Details</a>
        </div>
    </div>
    <?php
}
$sites_html = ob_get_clean();

if (empty($sites)) {
    $sites_html = '<div class="empty-state">'
        . '<i class="fas fa-search"></i>'
        . '<h3>No Sites Found</h3>'
        . '<p>No sites match your current filters. Try adjusting your search criteria.</p>'
        . '<button onclick="clearFilters()" class="btn btn-theme btn-gradient">Clear Filters</button>'
        . '</div>';
}

ob_start();
if ($total_pages > 1) {
    ?>
    <div class="pagination-shell pagination-container">
        <div class="pagination premium-pagination">
            <?php if ($page > 1): ?>
                <button type="button" class="page-btn nav-btn" onclick="changePage(<?php echo $page - 1; ?>)"><i class="fas fa-chevron-left"></i></button>
            <?php endif; ?>
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            for ($i = $start_page; $i <= $end_page; $i++):
                $active = $i === $page ? 'active' : '';
                ?>
                <button type="button" class="page-btn <?php echo $active; ?>" onclick="changePage(<?php echo $i; ?>)"><?php echo $i; ?></button>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <button type="button" class="page-btn nav-btn" onclick="changePage(<?php echo $page + 1; ?>)"><i class="fas fa-chevron-right"></i></button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
$pagination_html = ob_get_clean();

echo json_encode([
    'success' => true,
    'sites_html' => $sites_html,
    'pagination_html' => $pagination_html,
    'total_sites' => $total_sites,
    'total_pages' => $total_pages,
    'current_page' => $page,
    'view' => $view
]);
