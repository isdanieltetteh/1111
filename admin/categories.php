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

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_category':
            $name = trim($_POST['name']);
            $slug = trim($_POST['slug']);
            $description = trim($_POST['description']);
            $icon = trim($_POST['icon']);
            
            if (empty($name) || empty($slug)) {
                $error_message = 'Name and slug are required';
                break;
            }
            
            // Check if slug already exists
            $check_query = "SELECT id FROM site_categories WHERE slug = :slug";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':slug', $slug);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = 'Category slug already exists';
                break;
            }
            
            $insert_query = "INSERT INTO site_categories (name, slug, description, icon) VALUES (:name, :slug, :description, :icon)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':name', $name);
            $insert_stmt->bindParam(':slug', $slug);
            $insert_stmt->bindParam(':description', $description);
            $insert_stmt->bindParam(':icon', $icon);
            
            if ($insert_stmt->execute()) {
                $success_message = 'Category added successfully!';
            } else {
                $error_message = 'Error adding category';
            }
            break;
            
        case 'update_category':
            $category_id = intval($_POST['category_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $icon = trim($_POST['icon']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $update_query = "UPDATE site_categories SET name = :name, description = :description, icon = :icon, is_active = :is_active WHERE id = :category_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':name', $name);
            $update_stmt->bindParam(':description', $description);
            $update_stmt->bindParam(':icon', $icon);
            $update_stmt->bindParam(':is_active', $is_active);
            $update_stmt->bindParam(':category_id', $category_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Category updated successfully!';
            } else {
                $error_message = 'Error updating category';
            }
            break;
    }
}

// Get all categories
$categories_query = "SELECT sc.*, 
                     (SELECT COUNT(*) FROM sites WHERE category = sc.slug) as site_count
                     FROM site_categories sc 
                     ORDER BY sc.sort_order ASC, sc.name ASC";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Category Management - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Category Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Categories List -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Slug</th>
                                    <th>Sites</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span style="font-size: 1.5rem; margin-right: 0.5rem;"><?php echo $category['icon']; ?></span>
                                            <div>
                                                <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                <?php if ($category['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($category['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                                    <td><?php echo number_format($category['site_count']); ?> sites</td>
                                    <td>
                                        <span class="badge bg-<?php echo $category['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control" pattern="[a-z0-9_]+" required>
                        <small class="form-text text-muted">Lowercase letters, numbers, and underscores only</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Icon (Emoji)</label>
                        <input type="text" name="icon" class="form-control" placeholder="🪙" maxlength="2">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCategoryForm">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="category_id" id="editCategoryId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" id="editCategoryName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" id="editCategorySlug" class="form-control" readonly>
                        <small class="form-text text-muted">Slug cannot be changed after creation</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editCategoryDescription" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Icon (Emoji)</label>
                        <input type="text" name="icon" id="editCategoryIcon" class="form-control" maxlength="2">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="editCategoryActive" class="form-check-input">
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('editCategoryId').value = category.id;
    document.getElementById('editCategoryName').value = category.name;
    document.getElementById('editCategorySlug').value = category.slug;
    document.getElementById('editCategoryDescription').value = category.description || '';
    document.getElementById('editCategoryIcon').value = category.icon || '';
    document.getElementById('editCategoryActive').checked = category.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
