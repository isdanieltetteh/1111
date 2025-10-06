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

// Handle badge actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_badge':
            $name = trim($_POST['name']);
            $min_reputation = intval($_POST['min_reputation']);
            $badge_icon = trim($_POST['badge_icon']);
            $badge_color = trim($_POST['badge_color']);
            $difficulty = $_POST['difficulty'];
            $description = trim($_POST['description']);
            $requirements = trim($_POST['requirements']);
            
            $insert_query = "INSERT INTO levels (name, min_reputation, badge_icon, badge_color, difficulty, description, requirements) 
                            VALUES (:name, :min_reputation, :badge_icon, :badge_color, :difficulty, :description, :requirements)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':name', $name);
            $insert_stmt->bindParam(':min_reputation', $min_reputation);
            $insert_stmt->bindParam(':badge_icon', $badge_icon);
            $insert_stmt->bindParam(':badge_color', $badge_color);
            $insert_stmt->bindParam(':difficulty', $difficulty);
            $insert_stmt->bindParam(':description', $description);
            $insert_stmt->bindParam(':requirements', $requirements);
            
            if ($insert_stmt->execute()) {
                $success_message = 'Badge created successfully!';
            } else {
                $error_message = 'Error creating badge';
            }
            break;
            
        case 'update_badge':
            $badge_id = intval($_POST['badge_id']);
            $name = trim($_POST['name']);
            $min_reputation = intval($_POST['min_reputation']);
            $badge_icon = trim($_POST['badge_icon']);
            $badge_color = trim($_POST['badge_color']);
            $difficulty = $_POST['difficulty'];
            $description = trim($_POST['description']);
            $requirements = trim($_POST['requirements']);
            
            $update_query = "UPDATE levels SET 
                            name = :name, min_reputation = :min_reputation, badge_icon = :badge_icon,
                            badge_color = :badge_color, difficulty = :difficulty, description = :description,
                            requirements = :requirements
                            WHERE id = :badge_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':name', $name);
            $update_stmt->bindParam(':min_reputation', $min_reputation);
            $update_stmt->bindParam(':badge_icon', $badge_icon);
            $update_stmt->bindParam(':badge_color', $badge_color);
            $update_stmt->bindParam(':difficulty', $difficulty);
            $update_stmt->bindParam(':description', $description);
            $update_stmt->bindParam(':requirements', $requirements);
            $update_stmt->bindParam(':badge_id', $badge_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Badge updated successfully!';
            } else {
                $error_message = 'Error updating badge';
            }
            break;
            
        case 'award_badge':
            $user_id = intval($_POST['user_id']);
            $badge_id = intval($_POST['badge_id']);
            
            $award_query = "INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (:user_id, :badge_id)";
            $award_stmt = $db->prepare($award_query);
            $award_stmt->bindParam(':user_id', $user_id);
            $award_stmt->bindParam(':badge_id', $badge_id);
            
            if ($award_stmt->execute()) {
                $success_message = 'Badge awarded successfully!';
            } else {
                $error_message = 'Error awarding badge or user already has this badge';
            }
            break;
    }
}

// Get all badges
$badges_query = "SELECT l.*, 
                 (SELECT COUNT(*) FROM user_badges WHERE badge_id = l.id) as awarded_count
                 FROM levels l 
                 ORDER BY l.min_reputation ASC, l.id ASC";
$badges_stmt = $db->prepare($badges_query);
$badges_stmt->execute();
$badges = $badges_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get badge statistics
$badge_stats_query = "SELECT 
    COUNT(*) as total_badges,
    (SELECT COUNT(*) FROM user_badges) as total_awarded,
    (SELECT COUNT(DISTINCT user_id) FROM user_badges) as users_with_badges
    FROM levels";
$badge_stats_stmt = $db->prepare($badge_stats_query);
$badge_stats_stmt->execute();
$badge_stats = $badge_stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Badge Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Badge Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBadgeModal">
                    <i class="fas fa-plus"></i> Create Badge
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Badge Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Badges</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $badge_stats['total_badges']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-medal fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Awarded</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $badge_stats['total_awarded']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-trophy fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Users with Badges</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $badge_stats['users_with_badges']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Badges List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Badges</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($badges as $badge): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">
                                        <?php echo $badge['badge_icon']; ?>
                                    </div>
                                    <h5 style="color: <?php echo $badge['badge_color']; ?>;">
                                        <?php echo htmlspecialchars($badge['name']); ?>
                                    </h5>
                                    <p class="text-muted small"><?php echo htmlspecialchars($badge['description']); ?></p>
                                    
                                    <div class="mb-3">
                                        <span class="badge bg-secondary"><?php echo ucfirst($badge['difficulty']); ?></span>
                                        <span class="badge bg-primary"><?php echo $badge['awarded_count']; ?> awarded</span>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick="editBadge(<?php echo htmlspecialchars(json_encode($badge)); ?>)">
                                            Edit
                                        </button>
                                        <button class="btn btn-outline-success btn-sm" 
                                                onclick="awardBadge(<?php echo $badge['id']; ?>, '<?php echo htmlspecialchars($badge['name']); ?>')">
                                            Award to User
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Create Badge Modal -->
<div class="modal fade" id="createBadgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Badge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_badge">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Badge Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Reputation</label>
                                <input type="number" name="min_reputation" class="form-control" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Badge Icon (Emoji)</label>
                                <input type="text" name="badge_icon" class="form-control" placeholder="🏆" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Badge Color</label>
                                <input type="color" name="badge_color" class="form-control" value="#3b82f6" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Difficulty</label>
                        <select name="difficulty" class="form-select" required>
                            <option value="newcomer">Newcomer</option>
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                            <option value="extreme">Extreme</option>
                            <option value="special">Special</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Requirements</label>
                        <textarea name="requirements" class="form-control" rows="3" 
                                  placeholder="Describe how to earn this badge..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Badge Modal -->
<div class="modal fade" id="editBadgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Badge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editBadgeForm">
                <input type="hidden" name="action" value="update_badge">
                <input type="hidden" name="badge_id" id="editBadgeId">
                <div class="modal-body">
                    <!-- Same fields as create modal but with edit prefix -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Badge Name</label>
                                <input type="text" name="name" id="editName" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Reputation</label>
                                <input type="number" name="min_reputation" id="editMinReputation" class="form-control" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Badge Icon (Emoji)</label>
                                <input type="text" name="badge_icon" id="editBadgeIcon" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Badge Color</label>
                                <input type="color" name="badge_color" id="editBadgeColor" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Difficulty</label>
                        <select name="difficulty" id="editDifficulty" class="form-select" required>
                            <option value="newcomer">Newcomer</option>
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                            <option value="extreme">Extreme</option>
                            <option value="special">Special</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Requirements</label>
                        <textarea name="requirements" id="editRequirements" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Award Badge Modal -->
<div class="modal fade" id="awardBadgeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Award Badge to User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="awardBadgeForm">
                <input type="hidden" name="action" value="award_badge">
                <input type="hidden" name="badge_id" id="awardBadgeId">
                <div class="modal-body">
                    <p>Award badge: <strong id="awardBadgeName"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Select User</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Choose a user...</option>
                            <?php
                            $users_query = "SELECT id, username, reputation_points FROM users WHERE is_banned = 0 ORDER BY username ASC";
                            $users_stmt = $db->prepare($users_query);
                            $users_stmt->execute();
                            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($users as $user):
                            ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> (<?php echo $user['reputation_points']; ?> pts)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Award Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBadge(badge) {
    document.getElementById('editBadgeId').value = badge.id;
    document.getElementById('editName').value = badge.name;
    document.getElementById('editMinReputation').value = badge.min_reputation;
    document.getElementById('editBadgeIcon').value = badge.badge_icon;
    document.getElementById('editBadgeColor').value = badge.badge_color;
    document.getElementById('editDifficulty').value = badge.difficulty;
    document.getElementById('editDescription').value = badge.description;
    document.getElementById('editRequirements').value = badge.requirements;
    
    const modal = new bootstrap.Modal(document.getElementById('editBadgeModal'));
    modal.show();
}

function awardBadge(badgeId, badgeName) {
    document.getElementById('awardBadgeId').value = badgeId;
    document.getElementById('awardBadgeName').textContent = badgeName;
    
    const modal = new bootstrap.Modal(document.getElementById('awardBadgeModal'));
    modal.show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
