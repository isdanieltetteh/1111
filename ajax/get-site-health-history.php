<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/site-health-checker.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$site_id = intval($_GET['site_id'] ?? 0);

if (!$site_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid site ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $health_checker = new SiteHealthChecker($db);
    
    // Get site details
    $site_query = "SELECT name, url FROM sites WHERE id = :site_id";
    $site_stmt = $db->prepare($site_query);
    $site_stmt->bindParam(':site_id', $site_id);
    $site_stmt->execute();
    $site = $site_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$site) {
        echo json_encode(['success' => false, 'message' => 'Site not found']);
        exit();
    }
    
    // Get health history
    $history = $health_checker->getSiteHealthHistory($site_id, 20);
    
    // Build HTML
    $html = '<div class="site-health-history">';
    $html .= '<div class="mb-3">';
    $html .= '<h6>' . htmlspecialchars($site['name']) . '</h6>';
    $html .= '<p class="text-muted">' . htmlspecialchars($site['url']) . '</p>';
    $html .= '</div>';
    
    if (!empty($history)) {
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-sm">';
        $html .= '<thead><tr><th>Date</th><th>Status</th><th>Response Time</th><th>Error</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($history as $check) {
            $status_class = $check['is_accessible'] ? 'success' : 'danger';
            $status_text = $check['is_accessible'] ? 'Accessible' : 'Failed';
            $status_icon = $check['is_accessible'] ? 'check-circle' : 'times-circle';
            
            $html .= '<tr>';
            $html .= '<td>' . date('M j, g:i A', strtotime($check['last_checked'])) . '</td>';
            $html .= '<td><span class="badge bg-' . $status_class . '"><i class="fas fa-' . $status_icon . '"></i> ' . $status_text . '</span></td>';
            $html .= '<td>' . ($check['response_time'] ? number_format($check['response_time']) . 'ms' : '-') . '</td>';
            $html .= '<td><small class="text-muted">' . htmlspecialchars($check['error_message'] ?: '-') . '</small></td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
    } else {
        $html .= '<div class="text-center py-3">';
        $html .= '<p class="text-muted">No health check history available</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
