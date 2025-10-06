<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/site-health-checker.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();
$health_checker = new SiteHealthChecker($db);

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
    
    switch ($action) {
        case 'recheck_site':
            $site_id = intval($_POST['site_id']);
            $site_query = "SELECT url FROM sites WHERE id = :site_id";
            $site_stmt = $db->prepare($site_query);
            $site_stmt->bindParam(':site_id', $site_id);
            $site_stmt->execute();
            $site = $site_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($site) {
                $result = $health_checker->checkUrl($site['url'], $site_id);
                if ($result['accessible']) {
                    $success_message = 'Site is now accessible and has been restored!';
                } else {
                    $error_message = 'Site is still inaccessible: ' . $result['error_message'];
                }
            }
            break;
            
        case 'confirm_dead':
            $site_id = intval($_POST['site_id']);
            $admin_notes = trim($_POST['admin_notes'] ?? '');
            if ($health_checker->markSiteAsDead($site_id, $admin_notes)) {
                $success_message = 'Site marked as dead and removed from listings.';
            } else {
                $error_message = 'Error marking site as dead.';
            }
            break;
            
        case 'restore_site':
            $site_id = intval($_POST['site_id']);
            $admin_notes = trim($_POST['admin_notes'] ?? '');
            if ($health_checker->restoreSite($site_id, $admin_notes)) {
                $success_message = 'Site restored and marked as active!';
            } else {
                $error_message = 'Error restoring site.';
            }
            break;
            
        case 'bulk_restore':
            $site_ids = $_POST['site_ids'] ?? [];
            $admin_notes = trim($_POST['bulk_notes'] ?? '');
            if (!empty($site_ids)) {
                if ($health_checker->bulkRestoreSites($site_ids, $admin_notes)) {
                    $success_message = 'Successfully restored ' . count($site_ids) . ' sites!';
                } else {
                    $error_message = 'Error during bulk restore operation.';
                }
            }
            break;
            
        case 'bulk_confirm_dead':
            $site_ids = $_POST['site_ids'] ?? [];
            $admin_notes = trim($_POST['bulk_notes'] ?? '');
            if (!empty($site_ids)) {
                if ($health_checker->bulkMarkAsDead($site_ids, $admin_notes)) {
                    $success_message = 'Successfully marked ' . count($site_ids) . ' sites as dead!';
                } else {
                    $error_message = 'Error during bulk dead marking operation.';
                }
            }
            break;
    }
}

// Get dead sites and health statistics
$dead_sites = $health_checker->getDeadSites();
$health_stats = $health_checker->getHealthStatistics();

$page_title = 'Dead Links Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dead Links Management</h1>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="runHealthCheck()">
                        <i class="fas fa-sync"></i> Run Health Check
                    </button>
                    <button class="btn btn-success" onclick="showBulkActions()">
                        <i class="fas fa-tasks"></i> Bulk Actions
                    </button>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Health Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Healthy Sites</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($health_stats['total_sites'] - $health_stats['dead_sites']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Dead Sites</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($health_stats['dead_sites']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Warning Sites</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($health_stats['warning_sites']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Response</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($health_stats['avg_response_time'] ?? 0); ?>ms
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-gauge-high fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Panel -->
            <div class="card shadow mb-4" id="bulkActionsPanel" style="display: none;">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Bulk Actions</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="bulkActionForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Action</label>
                                    <select name="action" id="bulkAction" class="form-select" required>
                                        <option value="">Select action...</option>
                                        <option value="bulk_restore">Restore Selected Sites</option>
                                        <option value="bulk_confirm_dead">Confirm Selected as Dead</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Admin Notes</label>
                                    <input type="text" name="bulk_notes" class="form-control" placeholder="Reason for bulk action...">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Selected Sites: <span id="selectedCount">0</span></label>
                            <div id="selectedSites" class="text-muted">No sites selected</div>
                        </div>
                        <button type="submit" class="btn btn-primary" disabled id="bulkSubmitBtn">
                            Execute Bulk Action
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideBulkActions()">
                            Cancel
                        </button>
                    </form>
                </div>
            </div>

            <!-- Dead Sites List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Sites Detected as Dead</h6>
                    <div class="form-check">
                        <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll()">
                        <label class="form-check-label" for="selectAll">Select All</label>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($dead_sites)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="masterCheckbox" onchange="toggleSelectAll()">
                                        </th>
                                        <th>Site</th>
                                        <th>URL</th>
                                        <th>Health Metrics</th>
                                        <th>Error Details</th>
                                        <th>Timeline</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dead_sites as $site): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="site-checkbox" value="<?php echo $site['id']; ?>" onchange="updateSelection()">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="../<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                                                     class="rounded me-2" width="32" height="32">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($site['name']); ?></strong>
                                                    <br>
                                                    <span class="badge bg-secondary">
                                                        <?php echo get_category_display_name($site['category']); ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        by <?php echo htmlspecialchars($site['submitted_by_username'] ?: 'Unknown'); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" rel="nofollow" class="text-decoration-none">
                                                <?php echo htmlspecialchars($site['url']); ?>
                                                <i class="fas fa-arrow-up-right-from-square ms-1"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="health-metrics">
                                                <div class="mb-1">
                                                    <span class="badge bg-danger">
                                                        <?php echo $site['consecutive_failures']; ?> failures
                                                    </span>
                                                </div>
                                                <?php if ($site['uptime_percentage']): ?>
                                                    <div class="mb-1">
                                                        <small>Uptime: <?php echo number_format($site['uptime_percentage'], 1); ?>%</small>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($site['response_time']): ?>
                                                    <div>
                                                        <small>Response: <?php echo number_format($site['response_time']); ?>ms</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="error-details">
                                                <?php if ($site['status_code']): ?>
                                                    <div class="mb-1">
                                                        <span class="badge bg-<?php echo $site['status_code'] >= 500 ? 'danger' : 'warning'; ?>">
                                                            HTTP <?php echo $site['status_code']; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($site['error_message'] ?: 'No error details'); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="timeline-info">
                                                <div class="mb-1">
                                                    <small><strong>Last Check:</strong></small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $site['last_checked'] ? date('M j, g:i A', strtotime($site['last_checked'])) : 'Never'; ?>
                                                    </small>
                                                </div>
                                                <?php if ($site['first_failure_at']): ?>
                                                    <div>
                                                        <small><strong>First Failed:</strong></small>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, g:i A', strtotime($site['first_failure_at'])); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="recheck_site">
                                                    <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                                    <button type="submit" class="btn btn-info btn-sm">
                                                        <i class="fas fa-sync"></i> Recheck
                                                    </button>
                                                </form>
                                                
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="confirmDead(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars($site['name']); ?>')">
                                                    <i class="fas fa-times"></i> Confirm Dead
                                                </button>
                                                
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="restoreSite(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars($site['name']); ?>')">
                                                    <i class="fas fa-undo"></i> Restore
                                                </button>
                                                
                                                <button class="btn btn-secondary btn-sm" 
                                                        onclick="viewHistory(<?php echo $site['id']; ?>)">
                                                    <i class="fas fa-history"></i> History
                                                </button>
                                                
                                                <a href="../review.php?id=<?php echo $site['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm" target="_blank">
                                                    <i class="fas fa-arrow-up-right-from-square"></i> View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>No Dead Links Detected</h5>
                            <p class="text-muted">All sites are currently accessible and healthy.</p>
                            <button class="btn btn-primary" onclick="runHealthCheck()">
                                <i class="fas fa-sync"></i> Run Health Check Now
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Health Check Reports -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Health Check Reports</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get recent batch reports
                    $batch_reports_query = "SELECT * FROM health_check_batches ORDER BY created_at DESC LIMIT 10";
                    $batch_reports_stmt = $db->prepare($batch_reports_query);
                    $batch_reports_stmt->execute();
                    $batch_reports = $batch_reports_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (!empty($batch_reports)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Sites Checked</th>
                                        <th>Dead Detected</th>
                                        <th>Sites Restored</th>
                                        <th>Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batch_reports as $report): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></td>
                                        <td><?php echo $report['sites_checked']; ?></td>
                                        <td class="text-danger"><?php echo $report['dead_detected']; ?></td>
                                        <td class="text-success"><?php echo $report['sites_restored']; ?></td>
                                        <td>
                                            <?php 
                                            $success_rate = $report['sites_checked'] > 0 ? 
                                                (($report['sites_checked'] - $report['dead_detected']) / $report['sites_checked']) * 100 : 100;
                                            ?>
                                            <span class="badge bg-<?php echo $success_rate >= 90 ? 'success' : ($success_rate >= 70 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($success_rate, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No health check reports available</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Confirm Dead Modal -->
<div class="modal fade" id="confirmDeadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Site as Dead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="confirmDeadForm">
                <input type="hidden" name="action" value="confirm_dead">
                <input type="hidden" name="site_id" id="confirmDeadSiteId">
                <div class="modal-body">
                    <p>Mark site as dead: <strong id="confirmDeadSiteName"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Admin Notes</label>
                        <textarea name="admin_notes" class="form-control" rows="3" 
                                  placeholder="Reason for marking as dead..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will remove the site from all listings and mark it as permanently dead.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Dead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Restore Site Modal -->
<div class="modal fade" id="restoreSiteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restore Site</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="restoreSiteForm">
                <input type="hidden" name="action" value="restore_site">
                <input type="hidden" name="site_id" id="restoreSiteId">
                <div class="modal-body">
                    <p>Restore site: <strong id="restoreSiteName"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Admin Notes</label>
                        <textarea name="admin_notes" class="form-control" rows="3" 
                                  placeholder="Reason for restoration..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> This will mark the site as active and return it to listings.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Restore Site</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Site History Modal -->
<div class="modal fade" id="siteHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Site Health History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="siteHistoryContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedSites = new Set();

function confirmDead(siteId, siteName) {
    document.getElementById('confirmDeadSiteId').value = siteId;
    document.getElementById('confirmDeadSiteName').textContent = siteName;
    
    const modal = new bootstrap.Modal(document.getElementById('confirmDeadModal'));
    modal.show();
}

function restoreSite(siteId, siteName) {
    document.getElementById('restoreSiteId').value = siteId;
    document.getElementById('restoreSiteName').textContent = siteName;
    
    const modal = new bootstrap.Modal(document.getElementById('restoreSiteModal'));
    modal.show();
}

function viewHistory(siteId) {
    fetch(`ajax/get-site-health-history.php?site_id=${siteId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('siteHistoryContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('siteHistoryModal'));
                modal.show();
            }
        })
        .catch(error => console.error('Error loading site history:', error));
}

function runHealthCheck() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Check...';
    
    fetch('ajax/run-health-check.php', {method: 'POST'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Health check completed!\nChecked: ${data.checked} sites\nDead sites found: ${data.dead}`);
                location.reload();
            } else {
                alert('Health check failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Health check error:', error);
            alert('Health check failed');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

function showBulkActions() {
    document.getElementById('bulkActionsPanel').style.display = 'block';
    updateSelection();
}

function hideBulkActions() {
    document.getElementById('bulkActionsPanel').style.display = 'none';
    selectedSites.clear();
    updateSelection();
}

function toggleSelectAll() {
    const masterCheckbox = document.getElementById('masterCheckbox');
    const checkboxes = document.querySelectorAll('.site-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = masterCheckbox.checked;
        if (masterCheckbox.checked) {
            selectedSites.add(checkbox.value);
        } else {
            selectedSites.delete(checkbox.value);
        }
    });
    
    updateSelection();
}

function updateSelection() {
    const checkboxes = document.querySelectorAll('.site-checkbox:checked');
    selectedSites.clear();
    
    checkboxes.forEach(checkbox => {
        selectedSites.add(checkbox.value);
    });
    
    const count = selectedSites.size;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('bulkSubmitBtn').disabled = count === 0;
    
    if (count > 0) {
        const siteNames = Array.from(checkboxes).map(cb => {
            const row = cb.closest('tr');
            const siteName = row.querySelector('strong').textContent;
            return siteName;
        });
        document.getElementById('selectedSites').innerHTML = siteNames.slice(0, 5).join(', ') + 
            (siteNames.length > 5 ? ` and ${siteNames.length - 5} more...` : '');
    } else {
        document.getElementById('selectedSites').textContent = 'No sites selected';
    }
}

// Handle bulk form submission
document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const action = document.getElementById('bulkAction').value;
    const notes = document.querySelector('input[name="bulk_notes"]').value;
    
    if (!action) {
        alert('Please select an action');
        return;
    }
    
    if (selectedSites.size === 0) {
        alert('Please select at least one site');
        return;
    }
    
    const confirmMessage = action === 'bulk_restore' ? 
        `Restore ${selectedSites.size} sites?` : 
        `Mark ${selectedSites.size} sites as dead?`;
    
    if (confirm(confirmMessage)) {
        // Create form with selected site IDs
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="${action}">
            <input type="hidden" name="bulk_notes" value="${notes}">
        `;
        
        selectedSites.forEach(siteId => {
            form.innerHTML += `<input type="hidden" name="site_ids[]" value="${siteId}">`;
        });
        
        document.body.appendChild(form);
        form.submit();
    }
});
</script>

<style>
.health-metrics, .error-details, .timeline-info {
    font-size: 0.875rem;
}

.site-checkbox {
    transform: scale(1.2);
}

#bulkActionsPanel {
    border-left: 4px solid #007bff;
}

.table td {
    vertical-align: middle;
}

.btn-group-vertical .btn {
    margin-bottom: 2px;
}
</style>

<?php include 'includes/admin_footer.php'; ?>
