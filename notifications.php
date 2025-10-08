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

// Handle mark as read action
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
    $mark_read_stmt = $db->prepare($mark_read_query);
    $mark_read_stmt->bindParam(':id', $notification_id);
    $mark_read_stmt->bindParam(':user_id', $user_id);
    $mark_read_stmt->execute();

    header('Location: notifications.php');
    exit();
}

// Handle mark all as read action
if (isset($_POST['mark_all_read'])) {
    $mark_all_query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
    $mark_all_stmt = $db->prepare($mark_all_query);
    $mark_all_stmt->bindParam(':user_id', $user_id);
    $mark_all_stmt->execute();

    header('Location: notifications.php');
    exit();
}

// Get all notifications for the user
$notifications_query = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 50";
$notifications_stmt = $db->prepare($notifications_query);
$notifications_stmt->bindParam(':user_id', $user_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$unread_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0";
$unread_count_stmt = $db->prepare($unread_count_query);
$unread_count_stmt->bindParam(':user_id', $user_id);
$unread_count_stmt->execute();
$unread_count = $unread_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}

$page_title = 'Notifications - ' . SITE_NAME;
$page_description = 'View your notifications and updates from ' . SITE_NAME;
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
                                    <li class="breadcrumb-item active" aria-current="page">Notifications</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Stay in the Loop</h1>
                        <p class="text-muted mb-0">Track approvals, rewards, and platform alerts so you never miss an important update.</p>
                    </div>
                    <div class="text-lg-end">
                        <div class="option-chip justify-content-center ms-lg-auto">
                            <i class="fas fa-bell"></i>
                            <span><?php echo number_format($unread_count); ?> unread alerts</span>
                        </div>
                        <?php if ($unread_count > 0): ?>
                            <form method="POST" class="mt-3">
                                <button type="submit" name="mark_all_read" class="btn btn-theme btn-outline-glass">
                                    <i class="fas fa-check-double me-2"></i>Mark All Read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Notification Banner 970x250</div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="ad-slot dev-slot2 mb-4">Inline Notification Ad 728x90</div>

            <?php if (empty($notifications)): ?>
                <div class="glass-card p-5 text-center animate-fade-in" data-aos="fade-up">
                    <div class="display-5 mb-3 text-muted">🔕</div>
                    <h3 class="text-white mb-2">You're all caught up</h3>
                    <p class="text-muted mb-4">We'll let you know the moment new activity requires your attention.</p>
                    <a href="dashboard.php" class="btn btn-theme btn-gradient">
                        <i class="fas fa-gauge-high me-2"></i>Return to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="d-grid gap-3">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $icon = 'fas fa-info-circle';
                        $accent = 'text-info';
                        if (stripos($notification['title'], 'approved') !== false) {
                            $icon = 'fas fa-check-circle';
                            $accent = 'text-success';
                        } elseif (stripos($notification['title'], 'rejected') !== false) {
                            $icon = 'fas fa-times-circle';
                            $accent = 'text-danger';
                        } elseif (stripos($notification['title'], 'points') !== false || stripos($notification['title'], 'earned') !== false) {
                            $icon = 'fas fa-coins';
                            $accent = 'text-warning';
                        } elseif (stripos($notification['title'], 'reply') !== false) {
                            $icon = 'fas fa-reply';
                            $accent = 'text-primary';
                        }
                        ?>
                        <div class="notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" data-aos="fade-up">
                            <div class="d-flex flex-column flex-md-row gap-3 align-items-start justify-content-between">
                                <div class="d-flex gap-3 align-items-start flex-grow-1">
                                    <div class="notification-icon <?php echo $accent; ?>">
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <h4 class="h6 text-white mb-0"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="unread-badge">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-muted mb-2">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </p>
                                        <div class="notification-meta">
                                            <span><i class="fas fa-clock me-2"></i><?php echo timeAgo($notification['created_at']); ?></span>
                                            <?php if (!empty($notification['action_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" class="text-info text-decoration-none">
                                                    View details <i class="fas fa-arrow-up-right-from-square ms-1"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn btn-sm btn-theme btn-outline-glass" title="Mark as read">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
