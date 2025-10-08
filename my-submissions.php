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

// Handle site deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_site'])) {
    $site_id = intval($_POST['site_id']);
    $confirm_name = trim($_POST['confirm_name']);

    // Verify the site belongs to the user
    $verify_query = "SELECT name FROM sites WHERE id = :site_id AND submitted_by = :user_id";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->bindParam(':site_id', $site_id);
    $verify_stmt->bindParam(':user_id', $user_id);
    $verify_stmt->execute();
    $site = $verify_stmt->fetch(PDO::FETCH_ASSOC);

    if ($site && $confirm_name === $site['name']) {
        try {
            $db->beginTransaction();

            // Delete related data
            $delete_votes = "DELETE FROM votes WHERE site_id = :site_id";
            $db->prepare($delete_votes)->execute([':site_id' => $site_id]);

            $delete_reviews = "DELETE FROM reviews WHERE site_id = :site_id";
            $db->prepare($delete_reviews)->execute([':site_id' => $site_id]);

            $delete_backlinks = "DELETE FROM backlink_tracking WHERE site_id = :site_id";
            $db->prepare($delete_backlinks)->execute([':site_id' => $site_id]);

            // Delete the site
            $delete_site = "DELETE FROM sites WHERE id = :site_id";
            $db->prepare($delete_site)->execute([':site_id' => $site_id]);

            // Update user's total submissions count
            $update_user = "UPDATE users SET total_submissions = total_submissions - 1 WHERE id = :user_id";
            $db->prepare($update_user)->execute([':user_id' => $user_id]);

            $db->commit();

            $_SESSION['success_message'] = 'Site deleted successfully!';
            header('Location: user-submissions.php');
            exit();
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['error_message'] = 'Error deleting site. Please try again.';
        }
    } else {
        $_SESSION['error_message'] = 'Site name confirmation does not match or site not found.';
    }
}

// Get all user's submitted sites with detailed stats and rank
$sites_query = "SELECT s.*,
                CASE
                    WHEN s.is_sponsored = 1 AND s.sponsored_until > NOW() THEN 'sponsored'
                    WHEN s.is_boosted = 1 AND s.boosted_until > NOW() THEN 'boosted'
                    ELSE 'normal'
                END as promotion_status,
                (s.total_upvotes - s.total_downvotes) as net_votes,
                (SELECT COUNT(*) + 1
                 FROM sites s2
                 WHERE s2.is_approved = 1
                 AND s2.is_dead = FALSE
                 AND s2.admin_approved_dead = FALSE
                 AND (
                        s2.total_upvotes > s.total_upvotes
                        OR (
                            s2.total_upvotes = s.total_upvotes
                            AND (s2.total_upvotes - s2.total_downvotes) > (s.total_upvotes - s.total_downvotes)
                        )
                     )
                ) as site_rank
                FROM sites s
                WHERE submitted_by = :user_id
                ORDER BY s.total_upvotes DESC, net_votes DESC";
$sites_stmt = $db->prepare($sites_query);
$sites_stmt->bindParam(':user_id', $user_id);
$sites_stmt->execute();
$submitted_sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 604800) return floor($time/86400) . 'd ago';
    if ($time < 2592000) return floor($time/604800) . 'w ago';
    return date('M j, Y', strtotime($datetime));
}

function timeRemaining($datetime) {
    $time = strtotime($datetime) - time();
    if ($time <= 0) return 'Expired';

    $days = floor($time / 86400);
    $hours = floor(($time % 86400) / 3600);
    $minutes = floor(($time % 3600) / 60);

    if ($days > 0) return $days . 'd ' . $hours . 'h';
    if ($hours > 0) return $hours . 'h ' . $minutes . 'm';
    return $minutes . 'm';
}

$page_title = 'My Submissions - ' . SITE_NAME;
$page_description = 'View and manage all your submitted sites on ' . SITE_NAME;
$current_page = 'dashboard';

include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-4">
                    <div class="flex-grow-1">
                        <div class="dashboard-breadcrumb mb-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">My Submissions</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Curate Your Listings</h1>
                        <p class="text-muted mb-0">Track performance, promotion status, and trust signals for every site you have submitted.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-globe"></i>
                            <span><?php echo count($submitted_sites); ?> total submissions</span>
                        </div>
                        <a href="submit-site.php" class="btn btn-theme btn-gradient mt-3">
                            <i class="fas fa-plus-circle me-2"></i>Submit New Site
                        </a>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Hero Banner 970x250</div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="ad-slot dev-slot2 mb-4">Inline Ad 728x90</div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-glass alert-success mb-4" role="alert">
                    <span class="icon text-success"><i class="fas fa-check-circle"></i></span>
                    <div><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-glass alert-danger mb-4" role="alert">
                    <span class="icon text-danger"><i class="fas fa-exclamation-triangle"></i></span>
                    <div><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <?php if (!empty($submitted_sites)): ?>
                        <?php foreach ($submitted_sites as $site): ?>
                            <?php
                            $status_badge_map = [
                                'paying' => 'badge rounded-pill bg-success-subtle text-success fw-semibold',
                                'not_paying' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold',
                                'scam' => 'badge rounded-pill bg-danger-subtle text-danger-emphasis fw-semibold',
                                'scam_reported' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold'
                            ];
                            $status_badge_class = $status_badge_map[$site['status']] ?? 'badge rounded-pill bg-secondary-subtle fw-semibold';
                            $promotion_badge = '';
                            if ($site['promotion_status'] === 'sponsored') {
                                $promotion_badge = '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold"><i class="fas fa-crown me-1"></i>Sponsored · ' . timeRemaining($site['sponsored_until']) . '</span>';
                            } elseif ($site['promotion_status'] === 'boosted') {
                                $promotion_badge = '<span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-semibold"><i class="fas fa-rocket me-1"></i>Boosted · ' . timeRemaining($site['boosted_until']) . '</span>';
                            }
                            ?>
                            <div class="glass-card p-4 p-lg-5 mb-4 position-relative animate-fade-in" data-aos="fade-up">
                                <?php if ($promotion_badge): ?>
                                    <span class="stat-ribbon"><i class="fas fa-bolt me-1"></i>Premium Spotlight</span>
                                <?php endif; ?>
                                <div class="d-flex flex-column flex-md-row gap-4 align-items-start">
                                    <div class="d-flex flex-column flex-md-row gap-3 flex-grow-1 align-items-start">
                                        <?php if (!empty($site['logo'])): ?>
                                            <img src="<?php echo htmlspecialchars($site['logo']); ?>"
                                                 alt="<?php echo htmlspecialchars($site['name']); ?> logo"
                                                 class="rounded-4 border border-info-subtle border-opacity-50 flex-shrink-0"
                                                 style="width: 84px; height: 84px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-4 bg-info bg-opacity-10 border border-info-subtle border-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0"
                                                 style="width: 84px; height: 84px;">
                                                <i class="fas fa-globe text-info"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                <h2 class="h4 text-white mb-0"><?php echo htmlspecialchars($site['name']); ?></h2>
                                                <?php if (!empty($site['site_rank'])): ?>
                                                    <span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-semibold">
                                                        <i class="fas fa-ranking-star me-1"></i>#<?php echo number_format($site['site_rank']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php echo $promotion_badge; ?>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" rel="noopener"
                                               class="text-info text-decoration-none small d-inline-flex align-items-center gap-2">
                                                <i class="fas fa-link"></i>
                                                <span class="text-truncate" style="max-width: 100%;"><?php echo htmlspecialchars($site['url']); ?></span>
                                            </a>
                                            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                                <?php if ($site['is_approved']): ?>
                                                    <span class="badge rounded-pill bg-success-subtle text-success fw-semibold">
                                                        <i class="fas fa-check-circle me-1"></i>Approved
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold">
                                                        <i class="fas fa-clock me-1"></i>Pending Review
                                                    </span>
                                                <?php endif; ?>
                                                <span class="<?php echo $status_badge_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $site['status'])); ?>
                                                </span>
                                                <span class="badge rounded-pill bg-secondary-subtle text-muted fw-semibold">
                                                    <i class="fas fa-folder-open me-1"></i><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row row-cols-2 row-cols-md-3 g-3 mt-4">
                                    <div class="col">
                                        <div class="glass-stat-tile text-center h-100">
                                            <span class="glass-stat-label"><i class="fas fa-eye me-1"></i>Views</span>
                                            <span class="glass-stat-value"><?php echo number_format($site['views']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="glass-stat-tile text-center h-100">
                                            <span class="glass-stat-label"><i class="fas fa-mouse-pointer me-1"></i>Clicks</span>
                                            <span class="glass-stat-value"><?php echo number_format($site['clicks']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="glass-stat-tile text-center h-100">
                                            <span class="glass-stat-label"><i class="fas fa-arrow-up me-1"></i>Upvotes</span>
                                            <span class="glass-stat-value text-success"><?php echo number_format($site['total_upvotes']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="glass-stat-tile text-center h-100">
                                            <span class="glass-stat-label"><i class="fas fa-arrow-down me-1"></i>Downvotes</span>
                                            <span class="glass-stat-value text-danger"><?php echo number_format($site['total_downvotes']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="glass-stat-tile text-center h-100">
                                            <span class="glass-stat-label"><i class="fas fa-comments me-1"></i>Reviews</span>
                                            <span class="glass-stat-value"><?php echo number_format($site['total_reviews']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="glass-stat-tile text-center h-100">
                                            <span class="glass-stat-label"><i class="fas fa-star me-1"></i>Rating</span>
                                            <span class="glass-stat-value"><?php echo number_format($site['average_rating'], 1); ?>/5</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap align-items-center gap-3 text-muted small mt-4">
                                    <span><i class="fas fa-calendar me-1"></i>Submitted <?php echo timeAgo($site['created_at']); ?></span>
                                    <?php if ($site['updated_at'] !== $site['created_at']): ?>
                                        <span class="d-flex align-items-center gap-2">
                                            <span class="separator-dot"></span>
                                            <span><i class="fas fa-pen me-1"></i>Updated <?php echo timeAgo($site['updated_at']); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
                                    <?php if ($site['is_approved']): ?>
                                        <a href="review.php?id=<?php echo $site['id']; ?>" class="btn btn-theme btn-gradient">
                                            <i class="fas fa-eye me-2"></i>View Listing
                                        </a>
                                    <?php endif; ?>
                                    <a href="promote-sites.php" class="btn btn-theme btn-outline-glass">
                                        <i class="fas fa-rocket me-2"></i>Promote
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" onclick="showDeleteModal(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars(addslashes($site['name'])); ?>')">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="glass-card p-5 text-center animate-fade-in" data-aos="fade-up">
                            <div class="display-5 mb-3 text-info">🌐</div>
                            <h3 class="text-white mb-2">No submissions yet</h3>
                            <p class="text-muted mb-4">Submit your first site to start tracking performance, reviews, and reputation scores.</p>
                            <a href="submit-site.php" class="btn btn-theme btn-gradient">
                                <i class="fas fa-plus-circle me-2"></i>Submit Your First Site
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-4">
                    <?php if (!empty($submitted_sites)): ?>
                        <?php
                        $total_views = array_sum(array_column($submitted_sites, 'views'));
                        $total_clicks = array_sum(array_column($submitted_sites, 'clicks'));
                        $total_upvotes = array_sum(array_column($submitted_sites, 'total_upvotes'));
                        $total_reviews = array_sum(array_column($submitted_sites, 'total_reviews'));
                        $avg_rating = count($submitted_sites) > 0 ? array_sum(array_column($submitted_sites, 'average_rating')) / count($submitted_sites) : 0;
                        ?>
                        <div class="glass-card p-4 p-lg-4 mb-4">
                            <h3 class="h5 text-white mb-3"><i class="fas fa-chart-bar me-2 text-info"></i>Quick Pulse</h3>
                            <div class="d-grid gap-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small text-uppercase">Total Sites</span>
                                    <span class="fw-semibold text-white"><?php echo count($submitted_sites); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small text-uppercase">Total Views</span>
                                    <span class="fw-semibold text-white"><?php echo number_format($total_views); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small text-uppercase">Total Clicks</span>
                                    <span class="fw-semibold text-white"><?php echo number_format($total_clicks); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small text-uppercase">Total Upvotes</span>
                                    <span class="fw-semibold text-success"><?php echo number_format($total_upvotes); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small text-uppercase">Total Reviews</span>
                                    <span class="fw-semibold text-white"><?php echo number_format($total_reviews); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small text-uppercase">Average Rating</span>
                                    <span class="fw-semibold text-warning"><?php echo number_format($avg_rating, 1); ?>/5</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="glass-card p-4 p-lg-4 mb-4">
                        <h3 class="h6 text-white mb-3"><i class="fas fa-rocket me-2 text-warning"></i>Boost Visibility</h3>
                        <p class="text-muted small mb-4">Upgrade to sponsored or boosted slots to capture premium exposure in the directory.</p>
                        <a href="promote-sites.php" class="btn btn-theme btn-gradient w-100 mb-3">
                            <i class="fas fa-bullhorn me-2"></i>Promote Now
                        </a>
                        <div class="ad-slot dev-slot1">Sidebar Ad 300x250</div>
                    </div>

                    <div class="glass-card p-4 p-lg-4">
                        <h3 class="h6 text-white mb-3"><i class="fas fa-lightbulb me-2 text-info"></i>Growth Tips</h3>
                        <ul class="list-unstyled text-muted small d-grid gap-2 mb-0">
                            <li><i class="fas fa-pen-to-square me-2 text-info"></i>Refresh descriptions with clear earning proofs.</li>
                            <li><i class="fas fa-image me-2 text-warning"></i>Upload crisp logos for instant recognition.</li>
                            <li><i class="fas fa-share-nodes me-2 text-success"></i>Share your listing to earn referral points.</li>
                        </ul>
                        <div class="ad-slot dev-slot2 mt-4">Inline Ad 300x100</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card p-0">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <h5 class="modal-title text-danger fw-semibold"><i class="fas fa-exclamation-triangle me-2"></i>Delete Site</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="deleteForm" class="px-4 pb-4">
                <div class="modal-body px-0">
                    <div class="alert alert-glass alert-danger mb-4" role="alert">
                        <span class="icon text-danger"><i class="fas fa-shield-halved"></i></span>
                        <div>
                            <strong>Warning:</strong> This action cannot be undone. Deleting this site will permanently remove:
                            <ul class="mt-2 mb-0 ps-3">
                                <li>All reviews and ratings</li>
                                <li>All votes (upvotes/downvotes)</li>
                                <li>All backlink tracking data</li>
                                <li>Site statistics and analytics</li>
                            </ul>
                        </div>
                    </div>
                    <input type="hidden" name="delete_site" value="1">
                    <input type="hidden" name="site_id" id="deleteSiteId">
                    <div class="mb-3">
                        <label for="confirm_name" class="form-label fw-semibold">Type the site name "<span id="siteNameDisplay" class="text-info"></span>" to confirm:</label>
                        <input type="text" id="confirm_name" name="confirm_name" class="form-control" placeholder="Enter site name exactly as shown" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer border-0 px-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Confirm Deletion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    const deleteModalElement = document.getElementById('deleteModal');
    const deleteModalInstance = deleteModalElement ? new bootstrap.Modal(deleteModalElement) : null;

    function showDeleteModal(siteId, siteName) {
        document.getElementById('deleteSiteId').value = siteId;
        document.getElementById('siteNameDisplay').textContent = siteName;
        document.getElementById('confirm_name').value = '';
        if (deleteModalInstance) {
            deleteModalInstance.show();
        }
    }

    deleteModalElement?.addEventListener('hidden.bs.modal', () => {
        const confirmInput = document.getElementById('confirm_name');
        if (confirmInput) {
            confirmInput.value = '';
        }
    });
</script>
