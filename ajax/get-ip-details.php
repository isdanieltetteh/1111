<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$ip_address = $_GET['ip'] ?? '';

if (empty($ip_address)) {
    echo json_encode(['success' => false, 'message' => 'IP address is required']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get comprehensive IP information
    $ip_info_query = "SELECT 
        (SELECT COUNT(*) FROM security_logs WHERE ip_address = :ip_address) as total_events,
        (SELECT COUNT(*) FROM security_logs WHERE ip_address = :ip_address AND risk_level = 'high') as high_risk_events,
        (SELECT COUNT(*) FROM security_logs WHERE ip_address = :ip_address AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as events_24h,
        (SELECT COUNT(DISTINCT user_id) FROM security_logs WHERE ip_address = :ip_address AND user_id IS NOT NULL) as unique_users,
        (SELECT MAX(created_at) FROM security_logs WHERE ip_address = :ip_address) as last_activity,
        (SELECT MIN(created_at) FROM security_logs WHERE ip_address = :ip_address) as first_seen";
    
    $ip_info_stmt = $db->prepare($ip_info_query);
    $ip_info_stmt->bindParam(':ip_address', $ip_address);
    $ip_info_stmt->execute();
    $ip_info = $ip_info_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent events
    $events_query = "SELECT sl.*, u.username 
                    FROM security_logs sl
                    LEFT JOIN users u ON sl.user_id = u.id
                    WHERE sl.ip_address = :ip_address
                    ORDER BY sl.created_at DESC
                    LIMIT 20";
    $events_stmt = $db->prepare($events_query);
    $events_stmt->bindParam(':ip_address', $ip_address);
    $events_stmt->execute();
    $events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get accounts from this IP
    $accounts_query = "SELECT u.id, u.username, u.email, u.is_banned, u.created_at, u.last_active
                      FROM users u
                      WHERE u.last_ip = :ip_address OR u.id IN (
                          SELECT DISTINCT user_id FROM security_logs WHERE ip_address = :ip_address AND user_id IS NOT NULL
                      )
                      ORDER BY u.last_active DESC";
    $accounts_stmt = $db->prepare($accounts_query);
    $accounts_stmt->bindParam(':ip_address', $ip_address);
    $accounts_stmt->execute();
    $accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate risk score
    $risk_score = 0;
    $risk_factors = [];
    
    if ($ip_info['high_risk_events'] > 0) {
        $risk_score += $ip_info['high_risk_events'] * 10;
        $risk_factors[] = $ip_info['high_risk_events'] . ' high-risk events';
    }
    
    if ($ip_info['events_24h'] > 50) {
        $risk_score += 20;
        $risk_factors[] = 'High activity (24h)';
    }
    
    if (count($accounts) > 3) {
        $risk_score += 30;
        $risk_factors[] = 'Multiple accounts (' . count($accounts) . ')';
    }
    
    $risk_level = $risk_score >= 50 ? 'High' : ($risk_score >= 20 ? 'Medium' : 'Low');
    $risk_color = $risk_score >= 50 ? 'danger' : ($risk_score >= 20 ? 'warning' : 'success');
    
    // Build HTML
    $html = '<div class="ip-details">';
    
    // IP Summary
    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-md-6">';
    $html .= '<h6>IP Address Information</h6>';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><td><strong>IP Address:</strong></td><td><code>' . htmlspecialchars($ip_address) . '</code></td></tr>';
    $html .= '<tr><td><strong>Risk Level:</strong></td><td><span class="badge bg-' . $risk_color . '">' . $risk_level . '</span></td></tr>';
    $html .= '<tr><td><strong>Risk Score:</strong></td><td>' . $risk_score . '/100</td></tr>';
    $html .= '<tr><td><strong>Total Events:</strong></td><td>' . number_format($ip_info['total_events']) . '</td></tr>';
    $html .= '<tr><td><strong>High Risk Events:</strong></td><td>' . number_format($ip_info['high_risk_events']) . '</td></tr>';
    $html .= '<tr><td><strong>Events (24h):</strong></td><td>' . number_format($ip_info['events_24h']) . '</td></tr>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '<div class="col-md-6">';
    $html .= '<h6>Timeline</h6>';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><td><strong>First Seen:</strong></td><td>' . ($ip_info['first_seen'] ? date('M j, Y g:i A', strtotime($ip_info['first_seen'])) : 'Unknown') . '</td></tr>';
    $html .= '<tr><td><strong>Last Activity:</strong></td><td>' . ($ip_info['last_activity'] ? date('M j, Y g:i A', strtotime($ip_info['last_activity'])) : 'Unknown') . '</td></tr>';
    $html .= '<tr><td><strong>Unique Users:</strong></td><td>' . $ip_info['unique_users'] . '</td></tr>';
    $html .= '<tr><td><strong>Associated Accounts:</strong></td><td>' . count($accounts) . '</td></tr>';
    $html .= '</table>';
    if (!empty($risk_factors)) {
        $html .= '<div class="alert alert-' . $risk_color . ' p-2">';
        $html .= '<small><strong>Risk Factors:</strong><br>' . implode('<br>', $risk_factors) . '</small>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    
    // Associated accounts
    if (!empty($accounts)) {
        $html .= '<h6>Associated Accounts</h6>';
        $html .= '<div class="table-responsive mb-3">';
        $html .= '<table class="table table-sm">';
        $html .= '<thead><tr><th>Username</th><th>Email</th><th>Status</th><th>Joined</th><th>Last Active</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($accounts as $account) {
            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($account['username']) . '</strong></td>';
            $html .= '<td>' . htmlspecialchars($account['email']) . '</td>';
            $html .= '<td><span class="badge bg-' . ($account['is_banned'] ? 'danger' : 'success') . '">' . ($account['is_banned'] ? 'Banned' : 'Active') . '</span></td>';
            $html .= '<td>' . date('M j, Y', strtotime($account['created_at'])) . '</td>';
            $html .= '<td>' . date('M j, Y', strtotime($account['last_active'])) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '</div>';
    }
    
    // Recent events
    $html .= '<h6>Recent Security Events</h6>';
    $html .= '<div style="max-height: 300px; overflow-y: auto;">';
    if (!empty($events)) {
        foreach ($events as $event) {
            $html .= '<div class="border-bottom pb-1 mb-1">';
            $html .= '<div class="d-flex justify-content-between">';
            $html .= '<span class="badge bg-' . ($event['risk_level'] === 'high' ? 'danger' : ($event['risk_level'] === 'medium' ? 'warning' : 'info')) . '">';
            $html .= ucfirst($event['risk_level']);
            $html .= '</span>';
            $html .= '<small class="text-muted">' . date('M j, g:i A', strtotime($event['created_at'])) . '</small>';
            $html .= '</div>';
            $html .= '<small><strong>' . ucfirst(str_replace('_', ' ', $event['action'])) . '</strong>';
            if ($event['username']) {
                $html .= ' by ' . htmlspecialchars($event['username']);
            }
            $html .= '</small>';
            $html .= '</div>';
        }
    } else {
        $html .= '<p class="text-muted">No security events found</p>';
    }
    $html .= '</div>';
    
    $html .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
