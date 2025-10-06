<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Check if user is admin
if (!$auth->isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $space_key = trim($_POST['space_key']);
        $space_name = trim($_POST['space_name']);
        $page_location = trim($_POST['page_location']);
        $dimensions = trim($_POST['dimensions']);
        $max_ads = intval($_POST['max_ads']);
        $rotation_type = $_POST['rotation_type'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $query = "INSERT INTO ad_spaces (space_key, space_name, page_location, dimensions, max_ads, rotation_type, is_active) 
                     VALUES (:space_key, :space_name, :page_location, :dimensions, :max_ads, :rotation_type, :is_active)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':space_key' => $space_key,
                ':space_name' => $space_name,
                ':page_location' => $page_location,
                ':dimensions' => $dimensions,
                ':max_ads' => $max_ads,
                ':rotation_type' => $rotation_type,
                ':is_active' => $is_active
            ]);
            $success_message = 'Ad space created successfully!';
        } catch (PDOException $e) {
            $error_message = 'Error creating ad space: ' . $e->getMessage();
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id']);
        $space_name = trim($_POST['space_name']);
        $dimensions = trim($_POST['dimensions']);
        $max_ads = intval($_POST['max_ads']);
        $rotation_type = $_POST['rotation_type'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $query = "UPDATE ad_spaces SET space_name = :space_name, dimensions = :dimensions, 
                     max_ads = :max_ads, rotation_type = :rotation_type, is_active = :is_active 
                     WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':space_name' => $space_name,
                ':dimensions' => $dimensions,
                ':max_ads' => $max_ads,
                ':rotation_type' => $rotation_type,
                ':is_active' => $is_active,
                ':id' => $id
            ]);
            $success_message = 'Ad space updated successfully!';
        } catch (PDOException $e) {
            $error_message = 'Error updating ad space: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            $query = "DELETE FROM ad_spaces WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $id]);
            $success_message = 'Ad space deleted successfully!';
        } catch (PDOException $e) {
            $error_message = 'Error deleting ad space: ' . $e->getMessage();
        }
    }
}

// Get all ad spaces
$query = "SELECT * FROM ad_spaces ORDER BY page_location, space_key";
$stmt = $db->prepare($query);
$stmt->execute();
$ad_spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Ad Spaces - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            line-height: 1.6;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(148, 163, 184, 0.1);
            color: #cbd5e1;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .card {
            background: rgba(51, 65, 85, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.5rem;
            color: #e2e8f0;
            font-size: 1rem;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-checkbox input {
            width: 1.25rem;
            height: 1.25rem;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }

        th {
            font-weight: 600;
            color: #94a3b8;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        td {
            color: #e2e8f0;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1rem;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        .close-modal {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-th-large"></i> Manage Ad Spaces</h1>
            <div style="display: flex; gap: 1rem;">
                <button onclick="openCreateModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Ad Space
                </button>
                <a href="../dashboard" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Ad Spaces</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Space Key</th>
                            <th>Name</th>
                            <th>Page Location</th>
                            <th>Dimensions</th>
                            <th>Max Ads</th>
                            <th>Rotation</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ad_spaces as $space): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($space['space_key']); ?></code></td>
                            <td><?php echo htmlspecialchars($space['space_name']); ?></td>
                            <td><?php echo htmlspecialchars($space['page_location']); ?></td>
                            <td><?php echo htmlspecialchars($space['dimensions']); ?></td>
                            <td><?php echo $space['max_ads']; ?></td>
                            <td><?php echo ucfirst($space['rotation_type']); ?></td>
                            <td>
                                <?php if ($space['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <button onclick='openEditModal(<?php echo json_encode($space); ?>)' class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this ad space?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $space['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
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
        </div>
    </div>

     Create Modal 
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Ad Space</h3>
                <button class="close-modal" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Space Key *</label>
                    <input type="text" name="space_key" class="form-input" required placeholder="e.g., index_top_banner">
                    <small style="color: #94a3b8;">Unique identifier for this ad space</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Space Name *</label>
                    <input type="text" name="space_name" class="form-input" required placeholder="e.g., Homepage Top Banner">
                </div>

                <div class="form-group">
                    <label class="form-label">Page Location *</label>
                    <select name="page_location" class="form-select" required>
                        <option value="index">Homepage</option>
                        <option value="sites">Sites Page</option>
                        <option value="review">Review Page</option>
                        <option value="rankings">Rankings Page</option>
                        <option value="dashboard">Dashboard</option>
                        <option value="global">Global (All Pages)</option>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Dimensions</label>
                        <input type="text" name="dimensions" class="form-input" placeholder="e.g., 728x90">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Max Ads *</label>
                        <input type="number" name="max_ads" class="form-input" value="1" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Rotation Type *</label>
                    <select name="rotation_type" class="form-select" required>
                        <option value="random">Random</option>
                        <option value="sequential">Sequential</option>
                        <option value="weighted">Weighted (by impressions)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_active" checked>
                        <span>Active</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-plus"></i> Create Ad Space
                </button>
            </form>
        </div>
    </div>

     Edit Modal 
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Ad Space</h3>
                <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label class="form-label">Space Key</label>
                    <input type="text" id="edit_space_key" class="form-input" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">Space Name *</label>
                    <input type="text" name="space_name" id="edit_space_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Page Location</label>
                    <input type="text" id="edit_page_location" class="form-input" disabled>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Dimensions</label>
                        <input type="text" name="dimensions" id="edit_dimensions" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Max Ads *</label>
                        <input type="number" name="max_ads" id="edit_max_ads" class="form-input" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Rotation Type *</label>
                    <select name="rotation_type" id="edit_rotation_type" class="form-select" required>
                        <option value="random">Random</option>
                        <option value="sequential">Sequential</option>
                        <option value="weighted">Weighted (by impressions)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_active" id="edit_is_active">
                        <span>Active</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> Update Ad Space
                </button>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }

        function openEditModal(space) {
            document.getElementById('edit_id').value = space.id;
            document.getElementById('edit_space_key').value = space.space_key;
            document.getElementById('edit_space_name').value = space.space_name;
            document.getElementById('edit_page_location').value = space.page_location;
            document.getElementById('edit_dimensions').value = space.dimensions;
            document.getElementById('edit_max_ads').value = space.max_ads;
            document.getElementById('edit_rotation_type').value = space.rotation_type;
            document.getElementById('edit_is_active').checked = space.is_active == 1;
            
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>
