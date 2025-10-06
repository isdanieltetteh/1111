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
include 'includes/header.php';
?>

<main class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h1>🏆 Badge Manager</h1>
            <p style="color: #94a3b8;">Manage your earned badges and display your achievements</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success mb-4">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-2 gap-5">
            <!-- Current Badge -->
            <div class="card">
                <h3 class="mb-4">Current Active Badge</h3>
                <div class="text-center p-4" style="background: rgba(51, 65, 85, 0.3); border-radius: 1rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">
                        <?php echo $user['active_badge_icon'] ?: '🆕'; ?>
                    </div>
                    <h4 style="color: <?php echo $user['active_badge_color'] ?: '#6b7280'; ?>;">
                        <?php echo htmlspecialchars($user['active_badge_name'] ?: 'Newcomer'); ?>
                    </h4>
                    <p style="color: #94a3b8; margin-bottom: 1rem;">
                        <?php echo htmlspecialchars($user['description'] ?: 'Welcome to the community!'); ?>
                    </p>
                    <span class="badge" style="background: <?php echo getDifficultyColor($user['active_badge_difficulty'] ?: 'newcomer'); ?>20; color: <?php echo getDifficultyColor($user['active_badge_difficulty'] ?: 'newcomer'); ?>;">
                        <?php echo getDifficultyText($user['active_badge_difficulty'] ?: 'newcomer'); ?>
                    </span>
                </div>
            </div>

            <!-- Badge Stats -->
            <div class="card">
                <h3 class="mb-4">Your Progress</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span>Badges Earned</span>
                        <span class="badge badge-primary"><?php echo count($user_badges); ?> / <?php echo count($all_badges); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Reputation Points</span>
                        <span class="badge badge-success"><?php echo number_format($user['reputation_points']); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Total Reviews</span>
                        <span class="badge badge-warning"><?php echo number_format($user['total_reviews']); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Total Upvotes</span>
                        <span class="badge badge-danger"><?php echo number_format($user['total_upvotes']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Earned Badges -->
        <div class="card mt-5">
            <h3 class="mb-4">Your Earned Badges (<?php echo count($user_badges); ?>)</h3>
            
            <?php if (!empty($user_badges)): ?>
                <div class="grid grid-3">
                    <?php foreach ($user_badges as $badge): ?>
                    <div class="card" style="background: rgba(51, 65, 85, 0.3);">
                        <div class="text-center">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">
                                <?php echo $badge['badge_icon']; ?>
                            </div>
                            <h5 style="color: <?php echo $badge['badge_color']; ?>;">
                                <?php echo htmlspecialchars($badge['name']); ?>
                            </h5>
                            <p style="color: #94a3b8; font-size: 0.875rem; margin-bottom: 1rem;">
                                <?php echo htmlspecialchars($badge['description']); ?>
                            </p>
                            <div class="mb-3">
                                <span class="badge" style="background: <?php echo getDifficultyColor($badge['difficulty']); ?>20; color: <?php echo getDifficultyColor($badge['difficulty']); ?>;">
                                    <?php echo getDifficultyText($badge['difficulty']); ?>
                                </span>
                            </div>
                            
                            <?php if ($user['active_badge_id'] == $badge['id']): ?>
                                <span class="btn btn-success btn-sm w-full">Currently Active</span>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                    <button type="submit" name="change_badge" class="btn btn-primary btn-sm w-full">
                                        Set as Active
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4" style="color: #94a3b8;">
                    <p>No badges earned yet. Start contributing to earn your first badge!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Badges (Progress) -->
        <div class="card mt-5">
            <h3 class="mb-4">All Available Badges</h3>
            <div class="grid grid-4">
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
                    <div class="card text-center" style="background: rgba(51, 65, 85, 0.3); <?php echo !$earned ? 'opacity: 0.5;' : ''; ?>">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                            <?php echo $earned ? $badge['badge_icon'] : '🔒'; ?>
                        </div>
                        <h6 style="color: <?php echo $badge['badge_color']; ?>;">
                            <?php echo htmlspecialchars($badge['name']); ?>
                        </h6>
                        <p style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($badge['requirements']); ?>
                        </p>
                        <span class="badge" style="background: <?php echo getDifficultyColor($badge['difficulty']); ?>20; color: <?php echo getDifficultyColor($badge['difficulty']); ?>; font-size: 0.7rem;">
                            <?php echo getDifficultyText($badge['difficulty']); ?>
                        </span>
                        <?php if ($earned): ?>
                            <div class="mt-2">
                                <span class="badge badge-success" style="font-size: 0.7rem;">✓ Earned</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
