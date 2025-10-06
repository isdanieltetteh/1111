<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Check if user is admin
if (!$auth->isLoggedIn() || !$auth->getCurrentUser()['is_admin']) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle manual backlink check
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_backlink'])) {
    $backlink_id = intval($_POST['backlink_id']);
    
    try {
        // Get backlink details
        $get_query = "SELECT bt.*, s.name as site_name FROM backlink_tracking bt 
                     JOIN sites s ON bt.site_id = s.id WHERE bt.id = :id";
        $get_stmt = $db->prepare($get_query);
        $get_stmt->bindParam(':id', $backlink_id);
        $get_stmt->execute();
        $backlink = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($backlink) {
            // Check backlink
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'user_agent' => 'Mozilla/5.0 (compatible; BacklinkChecker/1.0)',
                    'follow_location' => true,
                    'max_redirects' => 3
                ]
            ]);
            
            $html = @file_get_contents($backlink['backlink_url'], false, $context);
            $status = 'failed';
            $notes = 'Could not fetch page';
            
            if ($html) {
                $site_domain = parse_url(SITE_URL, PHP_URL_HOST);
                $site_url_variations = [
                    SITE_URL,
                    rtrim(SITE_URL, '/'),
                    'http://' . $site_domain,
                    'https://' . $site_domain,
                    'www.' . $site_domain,
                    $site_domain
                ];
                
                $found = false;
                foreach ($site_url_variations as $variation) {
                    if (stripos($html, $variation) !== false) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    $status = 'verified';
                    $notes = 'Backlink found and verified';
                    $first_verified = $backlink['first_verified'] ?: date('Y-m-d H:i:s');
                } else {
                    $status = 'failed';
                    $notes = 'Backlink not found on page';
                }
            }
            
            // Update backlink status
            $update_query = "UPDATE backlink_tracking SET 
                           status = :status, 
                           last_checked = NOW(), 
                           check_count = check_count + 1,
                           failure_count = CASE WHEN :status = 'failed' THEN failure_count + 1 ELSE failure_count END,
                           first_verified = COALESCE(first_verified, CASE WHEN :status = 'verified' THEN NOW() ELSE NULL END),
                           last_verified = CASE WHEN :status = 'verified' THEN NOW() ELSE last_verified END,
                           notes = :notes
                           WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $status);
            $update_stmt->bindParam(':notes', $notes);
            $update_stmt->bindParam(':id', $backlink_id);
            $update_stmt->execute();
            
            $success_message = "Backlink check completed. Status: " . ucfirst($status);
        }
    } catch (Exception $e) {
        $error_message = "Error checking backlink: " . $e->getMessage();
    }
}

// Get backlink tracking data with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where_conditions = [];
$params = [];

if ($filter_status && in_array($filter_status, ['pending', 'verified', 'failed', 'removed'])) {
    $where_conditions[] = "bt.status = :status";
    $params[':status'] = $filter_status;
}

if ($search) {
    $where_conditions[] = "(s.name LIKE :search OR bt.backlink_url LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) FROM backlink_tracking bt 
               JOIN sites s ON bt.site_id = s.id $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get backlink data
$query = "SELECT bt.*, s.name as site_name, s.url as site_url,
          u.username as submitted_by
          FROM backlink_tracking bt 
          JOIN sites s ON bt.site_id = s.id 
          JOIN users u ON s.submitted_by = u.id
          $where_clause
          ORDER BY bt.created_at DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$backlinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'removed' THEN 1 ELSE 0 END) as removed
                FROM backlink_tracking";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Backlink Tracking - Admin';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">🔗 Backlink Tracking</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-arrows-rotate"></i> Refresh
                    </button>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

             Statistics Cards 
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo number_format($stats['total']); ?></h4>
                                    <p class="card-text">Total Backlinks</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-link fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo number_format($stats['verified']); ?></h4>
                                    <p class="card-text">Verified</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo number_format($stats['failed']); ?></h4>
                                    <p class="card-text">Failed</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo number_format($stats['pending']); ?></h4>
                                    <p class="card-text">Pending</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             Filters 
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status Filter</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="verified" <?php echo $filter_status === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="removed" <?php echo $filter_status === 'removed' ? 'selected' : ''; ?>>Removed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by site name or backlink URL" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

             Backlinks Table 
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Backlink Monitoring (<?php echo number_format($total_records); ?> total)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Site</th>
                                    <th>Backlink URL</th>
                                    <th>Status</th>
                                    <th>Checks</th>
                                    <th>Last Checked</th>
                                    <th>Submitted By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backlinks)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No backlinks found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($backlinks as $backlink): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($backlink['site_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($backlink['site_url']); ?></small>
                                            </td>
                                            <td>
                                                <a href="<?php echo htmlspecialchars($backlink['backlink_url']); ?>" target="_blank" class="text-decoration-none">
                                                    <?php echo htmlspecialchars(substr($backlink['backlink_url'], 0, 50)) . (strlen($backlink['backlink_url']) > 50 ? '...' : ''); ?>
                                                    <i class="fas fa-arrow-up-right-from-square fa-xs"></i>
                                                </a>
                                            </td>
                                            <td>
                                                <?php
                                                $status_classes = [
                                                    'verified' => 'success',
                                                    'failed' => 'danger',
                                                    'pending' => 'warning',
                                                    'removed' => 'secondary'
                                                ];
                                                $status_icons = [
                                                    'verified' => 'check-circle',
                                                    'failed' => 'times-circle',
                                                    'pending' => 'clock',
                                                    'removed' => 'trash'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $status_classes[$backlink['status']]; ?>">
                                                    <i class="fas fa-<?php echo $status_icons[$backlink['status']]; ?>"></i>
                                                    <?php echo ucfirst($backlink['status']); ?>
                                                </span>
                                                <?php if ($backlink['failure_count'] > 0): ?>
                                                    <br><small class="text-danger"><?php echo $backlink['failure_count']; ?> failures</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($backlink['check_count']); ?></strong>
                                                <?php if ($backlink['first_verified']): ?>
                                                    <br><small class="text-success">First: <?php echo date('M j, Y', strtotime($backlink['first_verified'])); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($backlink['last_checked']): ?>
                                                    <?php echo date('M j, Y H:i', strtotime($backlink['last_checked'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($backlink['submitted_by']); ?></strong><br>
                                                <small class="text-muted"><?php echo date('M j, Y', strtotime($backlink['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="backlink_id" value="<?php echo $backlink['id']; ?>">
                                                    <button type="submit" name="check_backlink" class="btn btn-sm btn-outline-primary" title="Check Now">
                                                        <i class="fas fa-arrows-rotate"></i>
                                                    </button>
                                                </form>
                                                <a href="sites.php?id=<?php echo $backlink['site_id']; ?>" class="btn btn-sm btn-outline-secondary" title="View Site">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

             Pagination 
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
