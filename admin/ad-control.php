<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Check if user is admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'toggle_space':
            $space_id = intval($_POST['space_id']);
            $is_enabled = intval($_POST['is_enabled']);

            $toggle_query = "UPDATE ad_spaces SET is_enabled = :is_enabled WHERE id = :space_id";
            $toggle_stmt = $db->prepare($toggle_query);
            $toggle_stmt->bindParam(':is_enabled', $is_enabled);
            $toggle_stmt->bindParam(':space_id', $space_id);

            if ($toggle_stmt->execute()) {
                $success_message = "Ad space " . ($is_enabled ? "enabled" : "disabled") . " successfully!";
            } else {
                $error_message = "Failed to update ad space.";
            }
            break;

        case 'update_pricing':
            $space_id = intval($_POST['space_id']);
            $price_multiplier = floatval($_POST['price_multiplier']);

            $update_query = "UPDATE ad_spaces SET base_price_multiplier = :price_multiplier WHERE id = :space_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':price_multiplier', $price_multiplier);
            $update_stmt->bindParam(':space_id', $space_id);

            if ($update_stmt->execute()) {
                $success_message = "Pricing updated successfully!";
            } else {
                $error_message = "Failed to update pricing.";
            }
            break;

        case 'update_dimensions':
            $space_id = intval($_POST['space_id']);
            $width = intval($_POST['width']);
            $height = intval($_POST['height']);

            $update_query = "UPDATE ad_spaces SET width = :width, height = :height WHERE id = :space_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':width', $width);
            $update_stmt->bindParam(':height', $height);
            $update_stmt->bindParam(':space_id', $space_id);

            if ($update_stmt->execute()) {
                $success_message = "Dimensions updated successfully!";
            } else {
                $error_message = "Failed to update dimensions.";
            }
            break;
    }
}

// Get all ad spaces with performance metrics
$spaces_query = "SELECT
    ads.*,
    COUNT(DISTINCT CASE WHEN ua.status = 'active' AND ua.end_date >= NOW() THEN ua.id END) as active_ads_count,
    COALESCE(SUM(CASE WHEN ua.status = 'active' THEN ua.impression_count ELSE 0 END), 0) as total_impressions,
    COALESCE(SUM(CASE WHEN ua.status = 'active' THEN ua.click_count ELSE 0 END), 0) as total_clicks,
    COALESCE(SUM(ua.cost_paid + ua.premium_cost), 0) as total_revenue
    FROM ad_spaces ads
    LEFT JOIN user_advertisements ua ON ads.space_id = ua.target_space_id
    GROUP BY ads.id
    ORDER BY ads.page_location, ads.display_order";
$spaces_stmt = $db->prepare($spaces_query);
$spaces_stmt->execute();
$ad_spaces = $spaces_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get overall statistics
$stats_query = "SELECT
    COUNT(DISTINCT ads.id) as total_spaces,
    SUM(CASE WHEN ads.is_enabled = 1 THEN 1 ELSE 0 END) as enabled_spaces,
    COUNT(DISTINCT CASE WHEN ua.status = 'active' AND ua.end_date >= NOW() THEN ua.id END) as total_active_ads,
    COALESCE(SUM(ua.impression_count), 0) as total_impressions,
    COALESCE(SUM(ua.click_count), 0) as total_clicks,
    COALESCE(SUM(ua.cost_paid + ua.premium_cost), 0) as total_revenue
    FROM ad_spaces ads
    LEFT JOIN user_advertisements ua ON ads.space_id = ua.target_space_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Space Control - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            line-height: 1.6;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1800px;
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
            font-weight: 800;
            background: linear-gradient(135deg, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #94a3b8;
            text-decoration: none;
            margin-bottom: 2rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #3b82f6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(51, 65, 85, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #94a3b8;
        }

        .section {
            background: rgba(51, 65, 85, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1.25rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 1.5rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
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

        .spaces-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
        }

        .spaces-table th,
        .spaces-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }

        .spaces-table th {
            font-weight: 600;
            color: #94a3b8;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        .spaces-table td {
            color: #e2e8f0;
        }

        .spaces-table tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-enabled {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-disabled {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.75rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-secondary {
            background: rgba(148, 163, 184, 0.1);
            color: #cbd5e1;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            font-size: 1.25rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        .close-modal {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: #ef4444;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.5rem;
            color: #e2e8f0;
            font-size: 0.875rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-help {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .spaces-table {
                font-size: 0.75rem;
            }

            .spaces-table th,
            .spaces-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Admin Dashboard
        </a>

        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Ad Space Control Panel</h1>
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_spaces']); ?></div>
                <div class="stat-label">Total Spaces</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['enabled_spaces']); ?></div>
                <div class="stat-label">Enabled Spaces</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_active_ads']); ?></div>
                <div class="stat-label">Active Ads</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_impressions']); ?></div>
                <div class="stat-label">Total Impressions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_clicks']); ?></div>
                <div class="stat-label">Total Clicks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Ad Spaces List -->
        <div class="section">
            <h2>Ad Spaces Management</h2>
            <div style="overflow-x: auto;">
                <table class="spaces-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Space Name</th>
                            <th>Page</th>
                            <th>Position</th>
                            <th>Dimensions</th>
                            <th>Price Mult.</th>
                            <th>Active Ads</th>
                            <th>Impressions</th>
                            <th>Clicks</th>
                            <th>CTR</th>
                            <th>Revenue</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ad_spaces as $space):
                            $ctr = $space['total_impressions'] > 0 ? ($space['total_clicks'] / $space['total_impressions']) * 100 : 0;
                        ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($space['space_id']); ?></code></td>
                            <td><?php echo htmlspecialchars($space['space_name']); ?></td>
                            <td><?php echo htmlspecialchars($space['page_location']); ?></td>
                            <td><?php echo htmlspecialchars($space['position']); ?></td>
                            <td>
                                <?php if ($space['width'] && $space['height']): ?>
                                    <?php echo $space['width']; ?>x<?php echo $space['height']; ?>
                                <?php else: ?>
                                    <span style="color: #64748b;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($space['base_price_multiplier'], 2); ?>x</td>
                            <td><?php echo number_format($space['active_ads_count']); ?></td>
                            <td><?php echo number_format($space['total_impressions']); ?></td>
                            <td><?php echo number_format($space['total_clicks']); ?></td>
                            <td><?php echo number_format($ctr, 2); ?>%</td>
                            <td>$<?php echo number_format($space['total_revenue'], 2); ?></td>
                            <td>
                                <?php if ($space['is_enabled']): ?>
                                    <span class="badge badge-enabled">
                                        <i class="fas fa-check"></i> Enabled
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-disabled">
                                        <i class="fas fa-times"></i> Disabled
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_space">
                                        <input type="hidden" name="space_id" value="<?php echo $space['id']; ?>">
                                        <input type="hidden" name="is_enabled" value="<?php echo $space['is_enabled'] ? 0 : 1; ?>">
                                        <button type="submit" class="btn <?php echo $space['is_enabled'] ? 'btn-danger' : 'btn-success'; ?>">
                                            <i class="fas fa-<?php echo $space['is_enabled'] ? 'ban' : 'check'; ?>"></i>
                                            <?php echo $space['is_enabled'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </form>
                                    <button class="btn btn-primary" onclick="openPricingModal(<?php echo htmlspecialchars(json_encode($space)); ?>)">
                                        <i class="fas fa-dollar-sign"></i> Pricing
                                    </button>
                                    <button class="btn btn-secondary" onclick="openDimensionsModal(<?php echo htmlspecialchars(json_encode($space)); ?>)">
                                        <i class="fas fa-ruler-combined"></i> Size
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pricing Modal -->
    <div id="pricingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Pricing</h3>
                <button class="close-modal" onclick="closeModal('pricingModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_pricing">
                <input type="hidden" name="space_id" id="pricingSpaceId">

                <div class="form-group">
                    <label class="form-label">Space Name</label>
                    <input type="text" id="pricingSpaceName" class="form-input" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">Price Multiplier *</label>
                    <input type="number" name="price_multiplier" id="pricingMultiplier" class="form-input" step="0.1" min="0.1" required>
                    <div class="form-help">
                        Base ad prices will be multiplied by this value. 1.0 = standard pricing, 2.0 = double price, 0.5 = half price.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> Update Pricing
                </button>
            </form>
        </div>
    </div>

    <!-- Dimensions Modal -->
    <div id="dimensionsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Dimensions</h3>
                <button class="close-modal" onclick="closeModal('dimensionsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_dimensions">
                <input type="hidden" name="space_id" id="dimensionsSpaceId">

                <div class="form-group">
                    <label class="form-label">Space Name</label>
                    <input type="text" id="dimensionsSpaceName" class="form-input" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">Width (pixels)</label>
                    <input type="number" name="width" id="dimensionsWidth" class="form-input" min="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Height (pixels)</label>
                    <input type="number" name="height" id="dimensionsHeight" class="form-input" min="1" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> Update Dimensions
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openPricingModal(space) {
            document.getElementById('pricingSpaceId').value = space.id;
            document.getElementById('pricingSpaceName').value = space.space_name;
            document.getElementById('pricingMultiplier').value = space.base_price_multiplier;
            openModal('pricingModal');
        }

        function openDimensionsModal(space) {
            document.getElementById('dimensionsSpaceId').value = space.id;
            document.getElementById('dimensionsSpaceName').value = space.space_name;
            document.getElementById('dimensionsWidth').value = space.width || '';
            document.getElementById('dimensionsHeight').value = space.height || '';
            openModal('dimensionsModal');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
