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
$success_message = '';
$error_message = '';

// Update user's badges before displaying
$auth->updateUserBadges($user_id);

// Handle badge change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_badge'])) {
    $new_badge_id = intval($_POST['badge_id']);
    
    if ($auth->setActiveBadge($user_id, $new_badge_id)) {
        $success_message = 'Badge updated successfully!';
        $user = $auth->getCurrentUser(); // Refresh user data
    } else {
        $error_message = 'You have not earned this badge yet.';
    }
}

// Get user's earned badges
$user_badges = $auth->getUserBadges($user_id);

// Get all badges for reference
$all_badges_query = "SELECT * FROM levels ORDER BY min_reputation ASC, id ASC";
$all_badges_stmt = $db->prepare($all_badges_query);
$all_badges_stmt->execute();
$all_badges = $all_badges_stmt->fetchAll(PDO::FETCH_ASSOC);

function getDifficultyColor($difficulty) {
    return match($difficulty) {
        'newcomer' => '#6b7280',
        'easy' => '#10b981',
        'medium' => '#f59e0b',
        'hard' => '#ef4444',
        'extreme' => '#8b5cf6',
        'special' => '#3b82f6',
        default => '#6b7280'
    };
}

function getDifficultyText($difficulty) {
    return match($difficulty) {
        'newcomer' => 'Starter',
        'easy' => 'Easy',
        'medium' => 'Medium',
        'hard' => 'Hard',
        'extreme' => 'Extreme',
        'special' => 'Special',
        default => 'Unknown'
    };
}

$page_title = 'Badge Manager - ' . SITE_NAME;
$page_description = 'Manage your earned badges and rank progression.';
$current_page = 'dashboard';
include 'includes/header.php';
?>

<div class="page-wrapper flex-grow-1">
    <section class="page-hero pb-0">
        <div class="container">
            <div class="glass-card p-4 p-lg-5 animate-fade-in" data-aos="fade-up">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <div class="dashboard-breadcrumb mb-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Badge Manager</li>
                                </ol>
                            </nav>
                        </div>
                        <h1 class="text-white fw-bold mb-2">Badge Manager</h1>
                        <p class="text-muted mb-0">Display your achievements, switch spotlight badges, and track your journey to elite status.</p>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                            <span class="badge rounded-pill bg-info-subtle text-info fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2">
                                <span><?php echo htmlspecialchars($user['active_badge_icon'] ?: '🆕'); ?></span>
                                <span><?php echo htmlspecialchars($user['active_badge_name'] ?: 'Newcomer'); ?></span>
                            </span>
                            <span class="badge rounded-pill bg-success-subtle text-success fw-semibold px-3 py-2">
                                <?php echo count($user_badges); ?> / <?php echo count($all_badges); ?> badges unlocked
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 text-center h-100">
                            <div class="display-4 mb-3"><?php echo $user['active_badge_icon'] ?: '🆕'; ?></div>
                            <h3 class="h5 mb-1" style="color: <?php echo htmlspecialchars($user['active_badge_color'] ?: '#38bdf8'); ?>;">
                                <?php echo htmlspecialchars($user['active_badge_name'] ?: 'Newcomer'); ?>
                            </h3>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($user['description'] ?: 'Welcome to the community!'); ?></p>
                            <span class="badge rounded-pill" style="background: <?php echo getDifficultyColor($user['active_badge_difficulty'] ?: 'newcomer'); ?>33; color: <?php echo getDifficultyColor($user['active_badge_difficulty'] ?: 'newcomer'); ?>;">
                                <?php echo getDifficultyText($user['active_badge_difficulty'] ?: 'newcomer'); ?> tier
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ad-slot dev-slot mt-4">Badge Manager Banner 970x250</div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <?php if ($success_message): ?>
                <div class="alert alert-glass alert-success mb-4" role="alert">
                    <span class="icon text-success"><i class="fas fa-check-circle"></i></span>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-glass alert-danger mb-4" role="alert">
                    <span class="icon text-danger"><i class="fas fa-exclamation-triangle"></i></span>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <div class="row g-4 align-items-stretch">
                <div class="col-lg-5">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <span class="stat-ribbon">Current Spotlight</span>
                        <div class="text-center mt-3">
                            <div class="display-3 mb-3"><?php echo $user['active_badge_icon'] ?: '🆕'; ?></div>
                            <h2 class="h4 mb-2" style="color: <?php echo htmlspecialchars($user['active_badge_color'] ?: '#38bdf8'); ?>;">
                                <?php echo htmlspecialchars($user['active_badge_name'] ?: 'Newcomer'); ?>
                            </h2>
                            <p class="text-muted small mb-3"><?php echo htmlspecialchars($user['description'] ?: 'Earn badges by reviewing honestly, growing referrals, and helping the community.'); ?></p>
                            <span class="badge rounded-pill" style="background: <?php echo getDifficultyColor($user['active_badge_difficulty'] ?: 'newcomer'); ?>33; color: <?php echo getDifficultyColor($user['active_badge_difficulty'] ?: 'newcomer'); ?>;">
                                <?php echo getDifficultyText($user['active_badge_difficulty'] ?: 'newcomer'); ?> difficulty
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="glass-card p-4 p-lg-5 h-100">
                        <h2 class="h4 text-white mb-4">Your Progress</h2>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3 h-100">
                                    <span class="text-muted text-uppercase small">Badges Earned</span>
                                    <div class="d-flex align-items-baseline justify-content-between mt-2">
                                        <span class="display-6 fw-bold text-info"><?php echo count($user_badges); ?></span>
                                        <span class="text-muted">of <?php echo count($all_badges); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3 h-100">
                                    <span class="text-muted text-uppercase small">Reputation Points</span>
                                    <div class="display-6 fw-bold text-success mt-2"><?php echo number_format($user['reputation_points']); ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3 h-100">
                                    <span class="text-muted text-uppercase small">Total Reviews</span>
                                    <div class="display-6 fw-bold text-warning mt-2"><?php echo number_format($user['total_reviews']); ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-3 h-100">
                                    <span class="text-muted text-uppercase small">Community Upvotes</span>
                                    <div class="display-6 fw-bold text-primary mt-2"><?php echo number_format($user['total_upvotes']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ad-slot dev-slot2 mt-4 mb-4">Inline Badge Ad 728x90</div>

            <div class="glass-card p-4 p-lg-5 mb-5">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                    <h2 class="h4 text-white mb-0">Your Earned Badges <span class="text-muted">(<?php echo count($user_badges); ?>)</span></h2>
                    <p class="text-muted small mb-0">Switch between badges to highlight your proudest accomplishments.</p>
                </div>

                <?php if (!empty($user_badges)): ?>
                    <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-lg-3">
                        <?php foreach ($user_badges as $badge): ?>
                            <div class="col">
                                <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 h-100 text-center">
                                    <div class="display-5 mb-3"><?php echo $badge['badge_icon']; ?></div>
                                    <h3 class="h5 mb-2" style="color: <?php echo htmlspecialchars($badge['badge_color']); ?>;">
                                        <?php echo htmlspecialchars($badge['name']); ?>
                                    </h3>
                                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($badge['description']); ?></p>
                                    <span class="badge rounded-pill mb-3" style="background: <?php echo getDifficultyColor($badge['difficulty']); ?>33; color: <?php echo getDifficultyColor($badge['difficulty']); ?>;">
                                        <?php echo getDifficultyText($badge['difficulty']); ?>
                                    </span>

                                    <?php if ($user['active_badge_id'] == $badge['id']): ?>
                                        <span class="badge bg-success-subtle text-success fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2 justify-content-center w-100">
                                            <i class="fas fa-star"></i> Currently Active
                                        </span>
                                    <?php else: ?>
                                        <form method="POST" class="d-grid">
                                            <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                            <button type="submit" name="change_badge" class="btn btn-theme btn-outline-glass btn-sm">
                                                <i class="fas fa-check me-2"></i>Set as Active
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <div class="display-6 mb-3">🌟</div>
                        <p class="mb-2">No badges earned yet. Start contributing to earn your first badge!</p>
                        <a href="sites" class="btn btn-theme btn-outline-glass btn-sm"><i class="fas fa-pen me-2"></i>Write a Review</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="glass-card p-4 p-lg-5">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                    <h2 class="h4 text-white mb-0">All Available Badges</h2>
                    <p class="text-muted small mb-0">Preview every milestone you can unlock across the platform.</p>
                </div>
                <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4">
                    <?php foreach ($all_badges as $badge): ?>
                        <?php
                        $earned = false;
                        foreach ($user_badges as $user_badge) {
                            if ($user_badge['id'] == $badge['id']) {
                                $earned = true;
                                break;
                            }
                        }
                        ?>
                        <div class="col">
                            <div class="rounded-4 border border-light border-opacity-10 bg-dark bg-opacity-25 p-4 text-center h-100 <?php echo !$earned ? 'opacity-50' : ''; ?>">
                                <div class="fs-2 mb-2"><?php echo $earned ? $badge['badge_icon'] : '🔒'; ?></div>
                                <h3 class="h6 mb-2" style="color: <?php echo htmlspecialchars($badge['badge_color']); ?>;">
                                    <?php echo htmlspecialchars($badge['name']); ?>
                                </h3>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($badge['requirements']); ?></p>
                                <span class="badge rounded-pill mb-2" style="background: <?php echo getDifficultyColor($badge['difficulty']); ?>33; color: <?php echo getDifficultyColor($badge['difficulty']); ?>; font-size: 0.75rem;">
                                    <?php echo getDifficultyText($badge['difficulty']); ?>
                                </span>
                                <?php if ($earned): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-success-subtle text-success fw-semibold px-3 py-2"><i class="fas fa-check me-2"></i>Earned</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ad-slot dev-slot1 mt-4">Sidebar Showcase Ad 300x600</div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
