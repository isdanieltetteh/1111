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

// Handle statistics adjustments
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'adjust_stats':
            $site_id = intval($_POST['site_id']);
            $views = intval($_POST['views']);
            $clicks = intval($_POST['clicks']);
            $upvotes = intval($_POST['upvotes']);
            $downvotes = intval($_POST['downvotes']);
            $reason = trim($_POST['reason']);
            
            if ($site_id && !empty($reason)) {
                try {
                    $db->beginTransaction();
                    
                    // Update site statistics
                    $update_query = "UPDATE sites SET 
                                   views = :views,
                                   clicks = :clicks,
                                   total_upvotes = :upvotes,
                                   total_downvotes = :downvotes,
                                   updated_at = NOW()
                                   WHERE id = :site_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':views', $views);
                    $update_stmt->bindParam(':clicks', $clicks);
                    $update_stmt->bindParam(':upvotes', $upvotes);
                    $update_stmt->bindParam(':downvotes', $downvotes);
                    $update_stmt->bindParam(':site_id', $site_id);
                    $update_stmt->execute();
                    
                    // Log the adjustment
                    $log_query = "INSERT INTO admin_actions (admin_id, action, target_type, target_id, notes, details) 
                                 VALUES (:admin_id, 'adjust_site_stats', 'site', :site_id, :reason, :details)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $log_stmt->bindParam(':site_id', $site_id);
                    $log_stmt->bindParam(':reason', $reason);
                    $details = json_encode(['views' => $views, 'clicks' => $clicks, 'upvotes' => $upvotes, 'downvotes' => $downvotes]);
                    $log_stmt->bindParam(':details', $details);
                    $log_stmt->execute();
                    
                    $db->commit();
                    $success_message = 'Site statistics updated successfully!';
                } catch (Exception $e) {
                    $db->rollback();
                    $error_message = 'Error updating statistics: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Site ID and reason are required';
            }
            break;
            
        case 'bulk_adjust':
            $adjustment_type = $_POST['adjustment_type'];
            $adjustment_value = intval($_POST['adjustment_value']);
            $target_sites = $_POST['target_sites'] ?? 'all';
            $reason = trim($_POST['bulk_reason']);
            
            if (empty($reason)) {
                $error_message = 'Reason is required for bulk adjustments';
                break;
            }
            
            try {
                $db->beginTransaction();
                
                // Build WHERE clause based on target
                $where_clause = "WHERE is_approved = 1";
                $params = [];
                
                if ($target_sites === 'paying') {
                    $where_clause .= " AND status = 'paying'";
                } elseif ($target_sites === 'new') {
                    $where_clause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                } elseif ($target_sites === 'low_activity') {
                    $where_clause .= " AND views < 100";
                }
                
                // Apply adjustment
                $field_map = [
                    'views' => 'views',
                    'clicks' => 'clicks',
                    'upvotes' => 'total_upvotes'
                ];
                
                if (isset($field_map[$adjustment_type])) {
                    $field = $field_map[$adjustment_type];
                    $update_query = "UPDATE sites SET {$field} = {$field} + :adjustment_value {$where_clause}";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':adjustment_value', $adjustment_value);
                    $update_stmt->execute();
                    $affected_rows = $update_stmt->rowCount();
                    
                    // Log bulk adjustment
                    $log_query = "INSERT INTO admin_actions (admin_id, action, target_type, notes, details) 
                                 VALUES (:admin_id, 'bulk_adjust_stats', 'bulk', :reason, :details)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $log_stmt->bindParam(':reason', $reason);
                    $details = json_encode([
                        'adjustment_type' => $adjustment_type,
                        'adjustment_value' => $adjustment_value,
                        'target_sites' => $target_sites,
                        'affected_rows' => $affected_rows
                    ]);
                    $log_stmt->bindParam(':details', $details);
                    $log_stmt->execute();
                    
                    $db->commit();
                    $success_message = "Bulk adjustment applied to {$affected_rows} sites!";
                } else {
                    $error_message = 'Invalid adjustment type';
                }
            } catch (Exception $e) {
                $db->rollback();
                $error_message = 'Error applying bulk adjustment: ' . $e->getMessage();
            }
            break;
            
        case 'generate_realistic_data':
            $site_id = intval($_POST['site_id']);
            $data_type = $_POST['data_type'];
            
            if ($site_id) {
                $realistic_data = $this->generateRealisticData($site_id, $data_type);
                
                if ($realistic_data) {
                    try {
                        $db->beginTransaction();
                        
                        $update_query = "UPDATE sites SET 
                                       views = :views,
                                       clicks = :clicks,
                                       total_upvotes = :upvotes,
                                       total_downvotes = :downvotes
                                       WHERE id = :site_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':views', $realistic_data['views']);
                        $update_stmt->bindParam(':clicks', $realistic_data['clicks']);
                        $update_stmt->bindParam(':upvotes', $realistic_data['upvotes']);
                        $update_stmt->bindParam(':downvotes', $realistic_data['downvotes']);
                        $update_stmt->bindParam(':site_id', $site_id);
                        $update_stmt->execute();
                        
                        // Log the generation
                        $log_query = "INSERT INTO admin_actions (admin_id, action, target_type, target_id, notes, details) 
                                     VALUES (:admin_id, 'generate_realistic_data', 'site', :site_id, 'Generated realistic test data', :details)";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                        $log_stmt->bindParam(':site_id', $site_id);
                        $log_stmt->bindParam(':details', json_encode($realistic_data));
                        $log_stmt->execute();
                        
                        $db->commit();
                        $success_message = 'Realistic data generated successfully!';
                    } catch (Exception $e) {
                        $db->rollback();
                        $error_message = 'Error generating data: ' . $e->getMessage();
                    }
                }
            }
            break;
    }
}

// Get sites for statistics management
$sites_query = "SELECT s.*, 
                COALESCE(AVG(r.rating), 0) as average_rating,
                COUNT(r.id) as review_count,
                u.username as submitted_by_username
                FROM sites s 
                LEFT JOIN reviews r ON s.id = r.site_id 
                LEFT JOIN users u ON s.submitted_by = u.id
                WHERE s.is_approved = 1
                GROUP BY s.id 
                ORDER BY s.views DESC
                LIMIT 50";
$sites_stmt = $db->prepare($sites_query);
$sites_stmt->execute();
$sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent adjustments
$recent_adjustments_query = "SELECT aa.*, u.username as admin_username, s.name as site_name
                            FROM admin_actions aa
                            JOIN users u ON aa.admin_id = u.id
                            LEFT JOIN sites s ON aa.target_id = s.id AND aa.target_type = 'site'
                            WHERE aa.action IN ('adjust_site_stats', 'bulk_adjust_stats', 'generate_realistic_data')
                            ORDER BY aa.created_at DESC
                            LIMIT 20";
$recent_adjustments_stmt = $db->prepare($recent_adjustments_query);
$recent_adjustments_stmt->execute();
$recent_adjustments = $recent_adjustments_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Site Statistics Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Site Statistics Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkAdjustModal">
                    <i class="fas fa-chart-bar"></i> Bulk Adjustments
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Sites Statistics Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Site Statistics (Top 50 by Views)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th>Views</th>
                                    <th>Clicks</th>
                                    <th>CTR</th>
                                    <th>Upvotes</th>
                                    <th>Downvotes</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sites as $site): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                                                 class="rounded me-2" width="32" height="32">
                                            <div>
                                                <strong><?php echo htmlspecialchars($site['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo get_category_display_name($site['category']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo number_format($site['views']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo number_format($site['clicks']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $ctr = $site['views'] > 0 ? ($site['clicks'] / $site['views']) * 100 : 0;
                                        ?>
                                        <span class="badge bg-<?php echo $ctr > 5 ? 'success' : ($ctr > 2 ? 'warning' : 'secondary'); ?>">
                                            <?php echo number_format($ctr, 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo number_format($site['total_upvotes']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><?php echo number_format($site['total_downvotes']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo number_format($site['average_rating'], 1); ?>/5</span>
                                        <br>
                                        <small class="text-muted">(<?php echo $site['review_count']; ?> reviews)</small>
                                    </td>
                                    <td>
                                        <div class="btn-group-vertical btn-group-sm">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="adjustStats(<?php echo htmlspecialchars(json_encode($site)); ?>)">
                                                <i class="fas fa-edit"></i> Adjust
                                            </button>
                                            <button class="btn btn-outline-success btn-sm" 
                                                    onclick="generateRealisticData(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars($site['name']); ?>')">
                                                <i class="fas fa-magic"></i> Generate
                                            </button>
                                            <a href="../review.php?id=<?php echo $site['id']; ?>" 
                                               class="btn btn-outline-info btn-sm" target="_blank">
                                                <i class="fas fa-arrow-up-right-from-square"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Adjustments Log -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Statistics Adjustments</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_adjustments)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Admin</th>
                                        <th>Action</th>
                                        <th>Target</th>
                                        <th>Reason</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_adjustments as $adjustment): ?>
                                    <tr>
                                        <td><?php echo date('M j, g:i A', strtotime($adjustment['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($adjustment['admin_username']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $adjustment['action'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($adjustment['site_name'] ?: 'Bulk Operation'); ?></td>
                                        <td><?php echo htmlspecialchars($adjustment['notes']); ?></td>
                                        <td>
                                            <?php if ($adjustment['details']): ?>
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        onclick="viewAdjustmentDetails('<?php echo htmlspecialchars($adjustment['details']); ?>')">
                                                    <i class="fas fa-info"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent adjustments</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Adjust Statistics Modal -->
<div class="modal fade" id="adjustStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Site Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="adjustStatsForm">
                <input type="hidden" name="action" value="adjust_stats">
                <input type="hidden" name="site_id" id="adjustSiteId">
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Site: <span id="adjustSiteName"></span></h6>
                        <p class="text-muted">Current statistics will be replaced with the values below</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Views</label>
                                <input type="number" name="views" id="adjustViews" class="form-control" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Clicks</label>
                                <input type="number" name="clicks" id="adjustClicks" class="form-control" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Upvotes</label>
                                <input type="number" name="upvotes" id="adjustUpvotes" class="form-control" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Downvotes</label>
                                <input type="number" name="downvotes" id="adjustDownvotes" class="form-control" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Adjustment</label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="Explain why these statistics are being adjusted..." required></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will permanently change the site's statistics. 
                        All changes are logged for audit purposes.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Statistics</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Adjust Modal -->
<div class="modal fade" id="bulkAdjustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Statistics Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_adjust">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select name="adjustment_type" class="form-select" required>
                            <option value="views">Views</option>
                            <option value="clicks">Clicks</option>
                            <option value="upvotes">Upvotes</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Value</label>
                        <input type="number" name="adjustment_value" class="form-control" 
                               placeholder="Amount to add (can be negative)" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Target Sites</label>
                        <select name="target_sites" class="form-select" required>
                            <option value="all">All Approved Sites</option>
                            <option value="paying">Only Paying Sites</option>
                            <option value="new">New Sites (Last 30 Days)</option>
                            <option value="low_activity">Low Activity Sites (&lt;100 views)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="bulk_reason" class="form-control" rows="3" 
                                  placeholder="Explain the reason for this bulk adjustment..." required></textarea>
                    </div>
                    
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This will affect multiple sites simultaneously. 
                        Use with extreme caution.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Apply Bulk Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generate Data Modal -->
<div class="modal fade" id="generateDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Realistic Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="generateDataForm">
                <input type="hidden" name="action" value="generate_realistic_data">
                <input type="hidden" name="site_id" id="generateSiteId">
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Site: <span id="generateSiteName"></span></h6>
                        <p class="text-muted">Generate realistic statistics based on site age and category</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data Profile</label>
                        <select name="data_type" class="form-select" required>
                            <option value="conservative">Conservative (Low activity)</option>
                            <option value="moderate" selected>Moderate (Average activity)</option>
                            <option value="popular">Popular (High activity)</option>
                            <option value="viral">Viral (Very high activity)</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> This will generate realistic statistics based on:
                        <ul class="mb-0 mt-2">
                            <li>Site age and category</li>
                            <li>Industry benchmarks</li>
                            <li>Realistic click-through rates</li>
                            <li>Natural voting patterns</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Generate Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function adjustStats(site) {
    document.getElementById('adjustSiteId').value = site.id;
    document.getElementById('adjustSiteName').textContent = site.name;
    document.getElementById('adjustViews').value = site.views;
    document.getElementById('adjustClicks').value = site.clicks;
    document.getElementById('adjustUpvotes').value = site.total_upvotes;
    document.getElementById('adjustDownvotes').value = site.total_downvotes;
    
    const modal = new bootstrap.Modal(document.getElementById('adjustStatsModal'));
    modal.show();
}

function generateRealisticData(siteId, siteName) {
    document.getElementById('generateSiteId').value = siteId;
    document.getElementById('generateSiteName').textContent = siteName;
    
    const modal = new bootstrap.Modal(document.getElementById('generateDataModal'));
    modal.show();
}

function viewAdjustmentDetails(detailsJson) {
    try {
        const details = JSON.parse(detailsJson);
        let message = 'Adjustment Details:\n\n';
        
        Object.keys(details).forEach(key => {
            message += `${key.replace('_', ' ')}: ${details[key]}\n`;
        });
        
        alert(message);
    } catch (e) {
        alert('Error parsing adjustment details');
    }
}

// Auto-update CTR when views or clicks change
document.addEventListener('input', function(e) {
    if (e.target.name === 'views' || e.target.name === 'clicks') {
        const form = e.target.closest('form');
        if (form && form.id === 'adjustStatsForm') {
            const views = parseInt(document.getElementById('adjustViews').value) || 0;
            const clicks = parseInt(document.getElementById('adjustClicks').value) || 0;
            
            // Ensure clicks don't exceed views
            if (clicks > views) {
                document.getElementById('adjustClicks').value = views;
            }
        }
    }
});
</script>

<?php include 'includes/admin_footer.php'; ?>
