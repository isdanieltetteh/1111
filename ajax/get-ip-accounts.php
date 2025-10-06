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
    
    // Get all accounts from this IP with detailed information
    $accounts_query = "SELECT u.id, u.username, u.email, u.avatar, u.is_banned, u.ban_reason,
                       u.reputation_points, u.created_at as registration_date, u.last_active,
                       (SELECT COUNT(*) FROM sites WHERE submitted_by = u.id) as submitted_sites,
                       (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as total_reviews,
                       (SELECT COALESCE(SUM(deposit_balance), 0) FROM user_wallets WHERE user_id = u.id) as wallet_balance,
                       ir.created_at as ip_registration_date
                       FROM users u
                       JOIN ip_registrations ir ON u.id = ir.user_id
                       WHERE ir.ip_address = :ip_address
                       ORDER BY ir.created_at DESC";
    
    $accounts_stmt = $db->prepare($accounts_query);
    $accounts_stmt->bindParam(':ip_address', $ip_address);
    $accounts_stmt->execute();
    $accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo json_encode(['success' => false, 'message' => 'No accounts found for this IP']);
        exit();
    }
    
    // Build HTML
    $html = '<div class="ip-accounts-details">';
    $html .= '<div class="alert alert-info mb-3">';
    $html .= '<strong>IP Address:</strong> ' . htmlspecialchars($ip_address) . '<br>';
    $html .= '<strong>Total Accounts:</strong> ' . count($accounts) . '<br>';
    $html .= '<strong>Registration Span:</strong> ' . date('M j, Y', strtotime($accounts[count($accounts)-1]['ip_registration_date'])) . ' - ' . date('M j, Y', strtotime($accounts[0]['ip_registration_date']));
    $html .= '</div>';
    
    // Risk assessment
    $risk_level = 'low';
    $risk_message = 'Normal activity pattern';
    $risk_color = 'success';
    
    if (count($accounts) >= 5) {
        $risk_level = 'high';
        $risk_message = 'High risk: 5+ accounts from same IP';
        $risk_color = 'danger';
    } elseif (count($accounts) >= 3) {
        $risk_level = 'medium';
        $risk_message = 'Medium risk: Multiple accounts detected';
        $risk_color = 'warning';
    }
    
    $html .= '<div class="alert alert-' . $risk_color . ' mb-3">';
    $html .= '<h6><i class="fas fa-exclamation-triangle"></i> Risk Assessment</h6>';
    $html .= '<p class="mb-0">' . $risk_message . '</p>';
    if (count($accounts) >= 3) {
        $html .= '<small>Consider reviewing these accounts for potential duplicate account violations.</small>';
    }
    $html .= '</div>';
    
    $html .= '<div class="table-responsive">';
    $html .= '<table class="table table-striped">';
    $html .= '<thead>';
    $html .= '<tr>';
    $html .= '<th>User</th>';
    $html .= '<th>Status</th>';
    $html .= '<th>Activity</th>';
    $html .= '<th>Wallet</th>';
    $html .= '<th>Registered</th>';
    $html .= '<th>Actions</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    
    foreach ($accounts as $account) {
        $status_class = $account['is_banned'] ? 'danger' : 'success';
        $status_text = $account['is_banned'] ? 'Banned' : 'Active';
        $status_icon = $account['is_banned'] ? 'ban' : 'check-circle';
        
        $html .= '<tr>';
        $html .= '<td>';
        $html .= '<div class="d-flex align-items-center">';
        $html .= '<img src="../' . htmlspecialchars($account['avatar']) . '" class="rounded-circle me-2" width="32" height="32">';
        $html .= '<div>';
        $html .= '<strong>' . htmlspecialchars($account['username']) . '</strong><br>';
        $html .= '<small class="text-muted">' . htmlspecialchars($account['email']) . '</small>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</td>';
        
        $html .= '<td>';
        $html .= '<span class="badge bg-' . $status_class . '">';
        $html .= '<i class="fas fa-' . $status_icon . '"></i> ' . $status_text;
        $html .= '</span>';
        if ($account['is_banned'] && $account['ban_reason']) {
            $html .= '<br><small class="text-muted">' . htmlspecialchars($account['ban_reason']) . '</small>';
        }
        $html .= '</td>';
        
        $html .= '<td>';
        $html .= '<small>';
        $html .= $account['submitted_sites'] . ' sites<br>';
        $html .= $account['total_reviews'] . ' reviews<br>';
        $html .= $account['reputation_points'] . ' points';
        $html .= '</small>';
        $html .= '</td>';
        
        $html .= '<td>';
        $html .= '<small>$' . number_format($account['wallet_balance'], 4) . '</small>';
        $html .= '</td>';
        
        $html .= '<td>';
        $html .= '<small>' . date('M j, Y g:i A', strtotime($account['ip_registration_date'])) . '</small>';
        $html .= '</td>';
        
        $html .= '<td>';
        if (!$account['is_banned']) {
            $html .= '<button class="btn btn-danger btn-sm" onclick="banAccount(' . $account['id'] . ', \'' . htmlspecialchars($account['username']) . '\')">';
            $html .= '<i class="fas fa-ban"></i> Ban';
            $html .= '</button>';
        } else {
            $html .= '<span class="text-muted">Already Banned</span>';
        }
        $html .= '</td>';
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
