<?php
/**
 * Ad Placement Example
 *
 * This file demonstrates how to insert ad spaces into your pages.
 * Copy these code snippets to your actual pages where you want ads to appear.
 *
 * All ad spaces from the database are already created. You just need to call
 * displayAdSpace() with the space_id from the ad_spaces table.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/ad-widget.php';

$database = new Database();
$db = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Placement Example</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 2rem;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .example-section {
            background: rgba(51, 65, 85, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .example-section h2 {
            color: #3b82f6;
            margin-bottom: 1rem;
        }

        .code-block {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: #10b981;
            overflow-x: auto;
        }

        .ad-preview {
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(30, 41, 59, 0.3);
            border-radius: 0.5rem;
        }

        .page-section {
            border-left: 3px solid #3b82f6;
            padding-left: 1rem;
            margin: 1rem 0;
        }

        h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-code"></i> Ad Placement Examples</h1>

        <!-- Homepage Examples -->
        <div class="example-section">
            <h2>Homepage (index.php)</h2>

            <div class="page-section">
                <h3>1. Top Banner</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'index_top_banner'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'index_top_banner'); ?>
                </div>
            </div>

            <div class="page-section">
                <h3>2. Sidebar Ad #1</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'index_sidebar_1'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'index_sidebar_1'); ?>
                </div>
            </div>

            <div class="page-section">
                <h3>3. Middle Content Banner</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'index_middle_banner'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'index_middle_banner'); ?>
                </div>
            </div>
        </div>

        <!-- Sites Page Examples -->
        <div class="example-section">
            <h2>Sites Listing (sites.php)</h2>

            <div class="page-section">
                <h3>Top Banner</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'sites_top_banner'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'sites_top_banner'); ?>
                </div>
            </div>

            <div class="page-section">
                <h3>Between Results</h3>
                <p style="color: #94a3b8; font-size: 0.875rem; margin-bottom: 1rem;">
                    Insert this between site listings (e.g., after every 5 sites)
                </p>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'sites_between_results_1'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'sites_between_results_1'); ?>
                </div>
            </div>
        </div>

        <!-- Dashboard Examples -->
        <div class="example-section">
            <h2>User Dashboard (dashboard.php)</h2>

            <div class="page-section">
                <h3>Dashboard Sidebar</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'dashboard_sidebar'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'dashboard_sidebar'); ?>
                </div>
            </div>

            <div class="page-section">
                <h3>Bottom Text Ad</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'dashboard_bottom_text'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'dashboard_bottom_text'); ?>
                </div>
            </div>
        </div>

        <!-- Review Page Examples -->
        <div class="example-section">
            <h2>Review Page (review.php)</h2>

            <div class="page-section">
                <h3>Top Banner</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'review_top_banner'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'review_top_banner'); ?>
                </div>
            </div>

            <div class="page-section">
                <h3>Sidebar Ads</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'review_sidebar_1'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'review_sidebar_1'); ?>
                </div>
            </div>
        </div>

        <!-- Rankings Page Examples -->
        <div class="example-section">
            <h2>Rankings Page (rankings.php)</h2>

            <div class="page-section">
                <h3>Top Banner</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'rankings_top_banner'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'rankings_top_banner'); ?>
                </div>
            </div>

            <div class="page-section">
                <h3>Between Rankings</h3>
                <div class="code-block">&lt;?php echo displayAdSpace($db, 'rankings_between_ranks'); ?&gt;</div>
                <div class="ad-preview">
                    <?php echo displayAdSpace($db, 'rankings_between_ranks'); ?>
                </div>
            </div>
        </div>

        <!-- Complete Integration Example -->
        <div class="example-section">
            <h2>Complete Page Integration Example</h2>
            <p style="color: #94a3b8; margin-bottom: 1rem;">
                Here's how to structure a complete page with multiple ad placements:
            </p>
            <div class="code-block">
&lt;?php
require_once 'includes/header.php';
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/ad-widget.php';

$database = new Database();
$db = $database->getConnection();
?&gt;

&lt;!-- Top Banner Ad --&gt;
&lt;?php echo displayAdSpace($db, 'index_top_banner'); ?&gt;

&lt;div class="container"&gt;
    &lt;div class="main-content"&gt;
        &lt;!-- Your page content here --&gt;
        &lt;h1&gt;Page Title&lt;/h1&gt;
        &lt;p&gt;Content...&lt;/p&gt;

        &lt;!-- Middle Content Ad --&gt;
        &lt;?php echo displayAdSpace($db, 'index_middle_banner'); ?&gt;

        &lt;!-- More content --&gt;
        &lt;p&gt;More content...&lt;/p&gt;
    &lt;/div&gt;

    &lt;aside class="sidebar"&gt;
        &lt;!-- Sidebar Ad #1 --&gt;
        &lt;?php echo displayAdSpace($db, 'index_sidebar_1'); ?&gt;

        &lt;!-- Other sidebar content --&gt;

        &lt;!-- Sidebar Ad #2 --&gt;
        &lt;?php echo displayAdSpace($db, 'index_sidebar_2'); ?&gt;
    &lt;/aside&gt;
&lt;/div&gt;

&lt;!-- Bottom Banner Ad --&gt;
&lt;?php echo displayAdSpace($db, 'index_bottom_banner'); ?&gt;

&lt;?php require_once 'includes/footer.php'; ?&gt;
            </div>
        </div>

        <!-- Available Ad Spaces -->
        <div class="example-section">
            <h2>All Available Ad Spaces</h2>
            <p style="color: #94a3b8; margin-bottom: 1rem;">
                These are all the ad spaces currently configured in your database:
            </p>

            <?php
            $database = new Database();
            $db = $database->getConnection();
            $query = "SELECT * FROM ad_spaces ORDER BY page_location, display_order";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <table style="width: 100%; border-collapse: collapse; color: #e2e8f0;">
                <thead>
                    <tr style="background: rgba(59, 130, 246, 0.1);">
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Space ID</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Name</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Page</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Dimensions</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.2);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spaces as $space): ?>
                    <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.1);">
                        <td style="padding: 0.75rem;"><code style="color: #10b981;"><?php echo htmlspecialchars($space['space_id']); ?></code></td>
                        <td style="padding: 0.75rem;"><?php echo htmlspecialchars($space['space_name']); ?></td>
                        <td style="padding: 0.75rem;"><?php echo htmlspecialchars($space['page_location']); ?></td>
                        <td style="padding: 0.75rem;">
                            <?php if ($space['width'] && $space['height']): ?>
                                <?php echo $space['width']; ?>x<?php echo $space['height']; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php if ($space['is_enabled']): ?>
                                <span style="color: #10b981;">✓ Enabled</span>
                            <?php else: ?>
                                <span style="color: #ef4444;">✗ Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Instructions -->
        <div class="example-section">
            <h2>Instructions</h2>
            <ol style="color: #cbd5e1; line-height: 2;">
                <li>Add <code style="color: #10b981;">&lt;?php require_once 'config/config.php'; ?&gt;</code> at the top of your page</li>
                <li>Add <code style="color: #10b981;">&lt;?php require_once 'config/database.php'; ?&gt;</code> after config</li>
                <li>Add <code style="color: #10b981;">&lt;?php require_once 'includes/ad-widget.php'; ?&gt;</code> after database</li>
                <li>Create database connection: <code style="color: #10b981;">$database = new Database(); $db = $database->getConnection();</code></li>
                <li>Insert <code style="color: #10b981;">&lt;?php echo displayAdSpace($db, 'space_id'); ?&gt;</code> where you want the ad to appear</li>
                <li>Replace 'space_id' with one of the space IDs from the table above</li>
                <li>If no active ads exist for that space, a placeholder with "Advertise Here" will appear</li>
                <li>Admins can manage ad spaces from the <a href="admin/ad-control.php" style="color: #3b82f6;">Ad Control Panel</a></li>
            </ol>
        </div>
    </div>
</body>
</html>
