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

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_backup':
            $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_path = '../backups/' . $backup_name;
            
            // Create backups directory if it doesn't exist
            if (!is_dir('../backups')) {
                mkdir('../backups', 0755, true);
            }
            
            // Generate SQL dump
            $tables = ['users', 'sites', 'reviews', 'votes', 'levels', 'user_badges', 'user_wallets', 
                      'deposit_transactions', 'points_transactions', 'user_referrals', 'withdrawal_requests',
                      'notifications', 'support_tickets', 'support_replies', 'email_campaigns', 'email_queue'];
            
            $sql_dump = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n";
            $sql_dump .= "-- Site: " . SITE_NAME . "\n\n";
            
            foreach ($tables as $table) {
                // Get table structure
                $structure_query = "SHOW CREATE TABLE {$table}";
                $structure_stmt = $db->prepare($structure_query);
                $structure_stmt->execute();
                $structure = $structure_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($structure) {
                    $sql_dump .= "-- Table structure for {$table}\n";
                    $sql_dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                    $sql_dump .= $structure['Create Table'] . ";\n\n";
                    
                    // Get table data
                    $data_query = "SELECT * FROM {$table}";
                    $data_stmt = $db->prepare($data_query);
                    $data_stmt->execute();
                    $rows = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($rows)) {
                        $sql_dump .= "-- Data for table {$table}\n";
                        foreach ($rows as $row) {
                            $values = array_map(function($value) use ($db) {
                                return $value === null ? 'NULL' : $db->quote($value);
                            }, array_values($row));
                            
                            $sql_dump .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $sql_dump .= "\n";
                    }
                }
            }
            
            if (file_put_contents($backup_path, $sql_dump)) {
                // Log the backup creation in admin_actions
                $log_query = "INSERT INTO admin_actions (admin_id, action, target_type, notes) 
                             VALUES (:admin_id, 'create_backup', 'backup', :notes)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $log_stmt->bindParam(':notes', $backup_name);
                $log_stmt->execute();

                $success_message = "Backup created successfully: {$backup_name}";
            } else {
                $error_message = 'Error creating backup file';
            }
            break;
    }
}

// Get existing backups
$backups = [];
if (is_dir('../backups')) {
    $backup_files = glob('../backups/backup_*.sql');
    foreach ($backup_files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'created' => filemtime($file),
            'path' => $file
        ];
    }
    
    // Sort by creation time (newest first)
    usort($backups, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}

// Get recent activity from admin_actions
$recent_activity = [];
try {
    $activity_query = "SELECT aa.created_at AS activity_time, u.username AS user_name, aa.action AS description
                      FROM admin_actions aa
                      JOIN users u ON aa.admin_id = u.id
                      WHERE aa.action IN ('create_backup', 'adjust_site_stats', 'bulk_adjust_stats', 'generate_realistic_data')
                      ORDER BY aa.created_at DESC
                      LIMIT 10";
    $activity_stmt = $db->prepare($activity_query);
    $activity_stmt->execute();
    $recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Error loading recent activity: ' . $e->getMessage();
}

$page_title = 'Database Backup - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Database Backup</h1>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Create New Backup
                    </button>
                </form>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Backup Information -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Backups</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($backups); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-database fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Latest Backup</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo !empty($backups) ? date('M j, Y', $backups[0]['created']) : 'None'; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Size</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $total_size = array_sum(array_column($backups, 'size'));
                                        echo $total_size > 0 ? number_format($total_size / 1024 / 1024, 2) . ' MB' : '0 MB';
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-hdd fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Files -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Available Backups</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($backups)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Backup Name</th>
                                        <th>Size</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-file-archive text-primary me-2"></i>
                                            <?php echo htmlspecialchars($backup['name']); ?>
                                        </td>
                                        <td><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                                        <td><?php echo date('M j, Y g:i A', $backup['created']); ?></td>
                                        <td>
                                            <a href="<?php echo $backup['path']; ?>" class="btn btn-sm btn-success" download>
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="deleteBackup('<?php echo $backup['name']; ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h5>No backups available</h5>
                            <p class="text-muted">Create your first backup to secure your data.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent System Activity</h6>
                </div>
                <div class="card-body">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php if (!empty($recent_activity)): ?>
                            <?php foreach ($recent_activity as $activity): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                    <small class="text-muted"><?php echo date('M j, g:i A', strtotime($activity['activity_time'])); ?></small>
                                </div>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($activity['description']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No recent activity recorded.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function deleteBackup(filename) {
    if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
        fetch('ajax/delete-backup.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({filename: filename})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting backup: ' + data.message);
            }
        });
    }
}
</script>

<?php include 'includes/admin_footer.php'; ?>
