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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $title = trim($_POST['title']);
            $type = $_POST['type'];
            $image_url = trim($_POST['image_url'] ?? '');
            $link_url = trim($_POST['link_url'] ?? '');
            $html_content = trim($_POST['html_content'] ?? '');
            $sort_order = intval($_POST['sort_order']);
            
            $insert_query = "INSERT INTO redirect_ads (title, type, image_url, link_url, html_content, sort_order) 
                            VALUES (:title, :type, :image_url, :link_url, :html_content, :sort_order)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':title', $title);
            $insert_stmt->bindParam(':type', $type);
            $insert_stmt->bindParam(':image_url', $image_url);
            $insert_stmt->bindParam(':link_url', $link_url);
            $insert_stmt->bindParam(':html_content', $html_content);
            $insert_stmt->bindParam(':sort_order', $sort_order);
            
            if ($insert_stmt->execute()) {
                $success_message = 'Ad created successfully!';
            } else {
                $error_message = 'Error creating ad.';
            }
            break;
            
        case 'edit':
            $ad_id = intval($_POST['ad_id']);
            $title = trim($_POST['title']);
            $type = $_POST['type'];
            $image_url = trim($_POST['image_url'] ?? '');
            $link_url = trim($_POST['link_url'] ?? '');
            $html_content = trim($_POST['html_content'] ?? '');
            $sort_order = intval($_POST['sort_order']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $update_query = "UPDATE redirect_ads SET 
                            title = :title, type = :type, image_url = :image_url, 
                            link_url = :link_url, html_content = :html_content, 
                            sort_order = :sort_order, is_active = :is_active 
                            WHERE id = :ad_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':title', $title);
            $update_stmt->bindParam(':type', $type);
            $update_stmt->bindParam(':image_url', $image_url);
            $update_stmt->bindParam(':link_url', $link_url);
            $update_stmt->bindParam(':html_content', $html_content);
            $update_stmt->bindParam(':sort_order', $sort_order);
            $update_stmt->bindParam(':is_active', $is_active);
            $update_stmt->bindParam(':ad_id', $ad_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Ad updated successfully!';
            } else {
                $error_message = 'Error updating ad.';
            }
            break;
            
        case 'delete':
            $ad_id = intval($_POST['ad_id']);
            $delete_query = "DELETE FROM redirect_ads WHERE id = :ad_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':ad_id', $ad_id);
            
            if ($delete_stmt->execute()) {
                $success_message = 'Ad deleted successfully!';
            } else {
                $error_message = 'Error deleting ad.';
            }
            break;
            
        case 'toggle':
            $ad_id = intval($_POST['ad_id']);
            $toggle_query = "UPDATE redirect_ads SET is_active = NOT is_active WHERE id = :ad_id";
            $toggle_stmt = $db->prepare($toggle_query);
            $toggle_stmt->bindParam(':ad_id', $ad_id);
            
            if ($toggle_stmt->execute()) {
                $success_message = 'Ad status updated!';
            } else {
                $error_message = 'Error updating ad status.';
            }
            break;
    }
}

// Get all ads
$ads_query = "SELECT * FROM redirect_ads ORDER BY sort_order ASC, created_at DESC";
$ads_stmt = $db->prepare($ads_query);
$ads_stmt->execute();
$ads = $ads_stmt->fetchAll(PDO::FETCH_ASSOC);

$active_ads = 0;
$paused_ads = 0;
foreach ($ads as $ad_overview) {
    if (!empty($ad_overview['is_active'])) {
        $active_ads++;
    } else {
        $paused_ads++;
    }
}

$page_title = 'Ad Management - Admin Panel';
include 'includes/admin_header.php';
?>

<?php include 'includes/admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-page-header">
        <div>
            <div class="admin-breadcrumb">
                <i class="fas fa-ad text-primary"></i>
                <span>Revenue</span>
                <span class="text-muted">Ad Control</span>
            </div>
            <h1>Ad Command Center</h1>
            <p class="text-muted mb-0">Deploy, pause, and optimize every placement in one view.</p>
        </div>
        <button class="btn btn-primary shadow-hover" data-bs-toggle="modal" data-bs-target="#addAdModal">
            <i class="fas fa-plus"></i> Add New Ad
        </button>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success shadow-sm border-0"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm border-0"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Active Placements</p>
                        <p class="metric-value mb-1"><?php echo number_format($active_ads); ?></p>
                        <span class="metric-trend up"><i class="fas fa-signal"></i>Live inventory</span>
                    </div>
                    <span class="metric-icon success"><i class="fas fa-circle-play"></i></span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="admin-metric-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="metric-label">Paused Spots</p>
                        <p class="metric-value mb-1"><?php echo number_format($paused_ads); ?></p>
                        <span class="metric-trend warning"><i class="fas fa-circle-pause"></i>Ready to relaunch</span>
                    </div>
                    <span class="metric-icon warning"><i class="fas fa-circle-pause"></i></span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="admin-subtle-card h-100">
                <h6>Placement Strategy</h6>
                <p class="mb-2">Keep your highest converting creatives at the top of the list. Use the sort order to prioritize featured sponsors and seasonal promos.</p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="admin-pill"><span class="dot"></span> Banner Ready</span>
                    <span class="admin-pill"><span class="dot"></span> Direct HTML</span>
                    <span class="admin-pill"><span class="dot"></span> Rotation Friendly</span>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-content-wrapper">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="admin-section-title">Live Placements</h2>
                <p class="admin-section-subtitle mb-0">Manage delivery rules, creative assets, and placement visibility.</p>
            </div>
        </div>

        <?php if (!empty($ads)): ?>
            <div class="table-responsive">
                <table class="table table-hover admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Sort Order</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ads as $ad): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($ad['title']); ?></strong>
                                <?php if ($ad['type'] === 'image' && $ad['image_url']): ?>
                                    <div class="table-meta">Image: <?php echo htmlspecialchars($ad['image_url']); ?></div>
                                <?php endif; ?>
                                <?php if ($ad['type'] === 'html' && $ad['html_content']): ?>
                                    <div class="table-meta">Custom HTML payload</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-chip <?php echo $ad['type'] === 'image' ? 'info' : 'muted'; ?>">
                                    <i class="fas <?php echo $ad['type'] === 'image' ? 'fa-image' : 'fa-code'; ?>"></i>
                                    <?php echo ucfirst($ad['type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-chip <?php echo $ad['is_active'] ? 'success' : 'danger'; ?>">
                                    <i class="fas <?php echo $ad['is_active'] ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                                    <?php echo $ad['is_active'] ? 'Active' : 'Paused'; ?>
                                </span>
                            </td>
                            <td><?php echo (int) $ad['sort_order']; ?></td>
                            <td class="table-meta"><?php echo date('M j, Y', strtotime($ad['created_at'])); ?></td>
                            <td class="text-end">
                                <div class="table-action-group justify-content-end">
                                    <button class="btn btn-outline-primary btn-sm"
                                            onclick="editAd(<?php echo htmlspecialchars(json_encode($ad)); ?>)">
                                        <i class="fas fa-pen"></i>
                                    </button>

                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                            <?php echo $ad['is_active'] ? '<i class=\'fas fa-pause\'></i>' : '<i class=\'fas fa-play\'></i>'; ?>
                                        </button>
                                    </form>

                                    <form method="POST">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-subtle-card text-center">
                <h6>No placements configured</h6>
                <p class="mb-0">Use the add placement button to launch your first banner, interstitial, or HTML slot.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add Ad Modal -->
<div class="modal fade" id="addAdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Ad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ad Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ad Type</label>
                        <select name="type" class="form-select" onchange="toggleAdFields(this.value)" required>
                            <option value="image">Image Ad</option>
                            <option value="html">HTML Ad</option>
                        </select>
                    </div>
                    
                    <div id="imageFields">
                        <div class="mb-3">
                            <label class="form-label">Image URL</label>
                            <input type="url" name="image_url" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Click URL (Optional)</label>
                            <input type="url" name="link_url" class="form-control">
                        </div>
                    </div>
                    
                    <div id="htmlFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">HTML Content</label>
                            <textarea name="html_content" class="form-control" rows="5"></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Ad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Ad Modal -->
<div class="modal fade" id="editAdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editAdForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="ad_id" id="editAdId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ad Title</label>
                        <input type="text" name="title" id="editTitle" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ad Type</label>
                        <select name="type" id="editType" class="form-select" onchange="toggleEditAdFields(this.value)" required>
                            <option value="image">Image Ad</option>
                            <option value="html">HTML Ad</option>
                        </select>
                    </div>
                    
                    <div id="editImageFields">
                        <div class="mb-3">
                            <label class="form-label">Image URL</label>
                            <input type="url" name="image_url" id="editImageUrl" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Click URL (Optional)</label>
                            <input type="url" name="link_url" id="editLinkUrl" class="form-control">
                        </div>
                    </div>
                    
                    <div id="editHtmlFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">HTML Content</label>
                            <textarea name="html_content" id="editHtmlContent" class="form-control" rows="5"></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="editSortOrder" class="form-control" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="editIsActive" class="form-check-input">
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Ad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAdFields(type) {
    const imageFields = document.getElementById('imageFields');
    const htmlFields = document.getElementById('htmlFields');
    
    if (type === 'image') {
        imageFields.style.display = 'block';
        htmlFields.style.display = 'none';
    } else {
        imageFields.style.display = 'none';
        htmlFields.style.display = 'block';
    }
}

function toggleEditAdFields(type) {
    const imageFields = document.getElementById('editImageFields');
    const htmlFields = document.getElementById('editHtmlFields');
    
    if (type === 'image') {
        imageFields.style.display = 'block';
        htmlFields.style.display = 'none';
    } else {
        imageFields.style.display = 'none';
        htmlFields.style.display = 'block';
    }
}

function editAd(ad) {
    document.getElementById('editAdId').value = ad.id;
    document.getElementById('editTitle').value = ad.title;
    document.getElementById('editType').value = ad.type;
    document.getElementById('editImageUrl').value = ad.image_url || '';
    document.getElementById('editLinkUrl').value = ad.link_url || '';
    document.getElementById('editHtmlContent').value = ad.html_content || '';
    document.getElementById('editSortOrder').value = ad.sort_order;
    document.getElementById('editIsActive').checked = ad.is_active == 1;
    
    toggleEditAdFields(ad.type);
    
    const editModal = new bootstrap.Modal(document.getElementById('editAdModal'));
    editModal.show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
