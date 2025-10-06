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

$user_id = intval($_GET['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user details
    $user_query = "SELECT * FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $user_id);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Get user activity data
    $activities = [];
    
    // Get reviews
    $reviews_query = "SELECT r.*, s.name as site_name 
                     FROM reviews r 
                     JOIN sites s ON r.site_id = s.id 
                     WHERE r.user_id = :user_id 
                     ORDER BY r.created_at DESC LIMIT 10";
    $reviews_stmt = $db->prepare($reviews_query);
    $reviews_stmt->bindParam(':user_id', $user_id);
    $reviews_stmt->execute();
    $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get site submissions
    $submissions_query = "SELECT * FROM sites WHERE submitted_by = :user_id ORDER BY created_at DESC LIMIT 10";
    $submissions_stmt = $db->prepare($submissions_query);
    $submissions_stmt->bindParam(':user_id', $user_id);
    $submissions_stmt->execute();
    $submissions = $submissions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get security logs
    $security_query = "SELECT * FROM security_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10";
    $security_stmt = $db->prepare($security_query);
    $security_stmt->bindParam(':user_id', $user_id);
    $security_stmt->execute();
    $security_logs = $security_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get wallet transactions
    $transactions_query = "SELECT * FROM points_transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10";
    $transactions_stmt = $db->prepare($transactions_query);
    $transactions_stmt->bindParam(':user_id', $user_id);
    $transactions_stmt->execute();
    $transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build HTML
    $html = '<div class="user-activity-details">';
    
    // User summary
    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-md-3 text-center">';
    $html .= '<img src="../' . htmlspecialchars($user['avatar']) . '" class="rounded-circle mb-2" width="80" height="80">';
    $html .= '<h6>' . htmlspecialchars($user['username']) . '</h6>';
    $html .= '<small class="text-muted">' . htmlspecialchars($user['email']) . '</small>';
    $html .= '</div>';
    $html .= '<div class="col-md-9">';
    $html .= '<div class="row">';
    $html .= '<div class="col-md-3"><strong>Reputation:</strong><br>' . number_format($user['reputation_points']) . ' points</div>';
    $html .= '<div class="col-md-3"><strong>Credits:</strong><br>$' . number_format($user['credits'], 4) . '</div>';
    $html .= '<div class="col-md-3"><strong>Joined:</strong><br>' . date('M j, Y', strtotime($user['created_at'])) . '</div>';
    $html .= '<div class="col-md-3"><strong>Last Active:</strong><br>' . date('M j, Y', strtotime($user['last_active'])) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Activity tabs
    $html .= '<ul class="nav nav-tabs" id="activityTabs" role="tablist">';
    $html .= '<li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#reviews-tab">Reviews (' . count($reviews) . ')</a></li>';
    $html .= '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#submissions-tab">Submissions (' . count($submissions) . ')</a></li>';
    $html .= '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#security-tab">Security (' . count($security_logs) . ')</a></li>';
    $html .= '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#transactions-tab">Transactions (' . count($transactions) . ')</a></li>';
    $html .= '</ul>';
    
    $html .= '<div class="tab-content mt-3">';
    
    // Reviews tab
    $html .= '<div class="tab-pane fade show active" id="reviews-tab">';
    if (!empty($reviews)) {
        foreach ($reviews as $review) {
            $html .= '<div class="border-bottom pb-2 mb-2">';
            $html .= '<div class="d-flex justify-content-between">';
            $html .= '<strong>' . htmlspecialchars($review['site_name']) . '</strong>';
            $html .= '<small class="text-muted">' . date('M j, Y', strtotime($review['created_at'])) . '</small>';
            $html .= '</div>';
            $html .= '<div class="mb-1">';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<i class="fas fa-star ' . ($i <= $review['rating'] ? 'text-warning' : 'text-muted') . '"></i>';
            }
            $html .= ' (' . $review['rating'] . '/5)';
            if ($review['is_scam_report']) {
                $html .= ' <span class="badge bg-danger">Scam Report</span>';
            }
            $html .= '</div>';
            $html .= '<p class="mb-1">' . htmlspecialchars(substr($review['comment'], 0, 100)) . '...</p>';
            $html .= '<small class="text-muted">👍 ' . $review['upvotes'] . ' | 👎 ' . $review['downvotes'] . '</small>';
            $html .= '</div>';
        }
    } else {
        $html .= '<p class="text-muted">No reviews found</p>';
    }
    $html .= '</div>';
    
    // Submissions tab
    $html .= '<div class="tab-pane fade" id="submissions-tab">';
    if (!empty($submissions)) {
        foreach ($submissions as $submission) {
            $html .= '<div class="border-bottom pb-2 mb-2">';
            $html .= '<div class="d-flex justify-content-between">';
            $html .= '<strong>' . htmlspecialchars($submission['name']) . '</strong>';
            $html .= '<small class="text-muted">' . date('M j, Y', strtotime($submission['created_at'])) . '</small>';
            $html .= '</div>';
            $html .= '<div class="mb-1">';
            $html .= '<span class="badge bg-' . ($submission['is_approved'] ? 'success' : 'warning') . '">';
            $html .= $submission['is_approved'] ? 'Approved' : 'Pending';
            $html .= '</span>';
            $html .= ' <span class="badge bg-secondary">' . ucfirst(str_replace('_', ' ', $submission['category'])) . '</span>';
            $html .= '</div>';
            $html .= '<p class="mb-1">' . htmlspecialchars(substr($submission['description'], 0, 100)) . '...</p>';
            $html .= '<small class="text-muted">👍 ' . $submission['total_upvotes'] . ' | 👀 ' . number_format($submission['views']) . '</small>';
            $html .= '</div>';
        }
    } else {
        $html .= '<p class="text-muted">No submissions found</p>';
    }
    $html .= '</div>';
    
    // Security tab
    $html .= '<div class="tab-pane fade" id="security-tab">';
    if (!empty($security_logs)) {
        foreach ($security_logs as $log) {
            $html .= '<div class="border-bottom pb-2 mb-2">';
            $html .= '<div class="d-flex justify-content-between">';
            $html .= '<span class="badge bg-' . ($log['risk_level'] === 'high' ? 'danger' : ($log['risk_level'] === 'medium' ? 'warning' : 'info')) . '">';
            $html .= ucfirst($log['risk_level']);
            $html .= '</span>';
            $html .= '<small class="text-muted">' . date('M j, Y g:i A', strtotime($log['created_at'])) . '</small>';
            $html .= '</div>';
            $html .= '<strong>' . ucfirst(str_replace('_', ' ', $log['action'])) . '</strong>';
            $html .= '<br><small class="text-muted">IP: ' . htmlspecialchars($log['ip_address']) . '</small>';
            $html .= '</div>';
        }
    } else {
        $html .= '<p class="text-muted">No security events found</p>';
    }
    $html .= '</div>';
    
    // Transactions tab
    $html .= '<div class="tab-pane fade" id="transactions-tab">';
    if (!empty($transactions)) {
        foreach ($transactions as $transaction) {
            $html .= '<div class="border-bottom pb-2 mb-2">';
            $html .= '<div class="d-flex justify-content-between">';
            $html .= '<strong>' . ucfirst(str_replace('_', ' ', $transaction['type'])) . '</strong>';
            $html .= '<small class="text-muted">' . date('M j, Y g:i A', strtotime($transaction['created_at'])) . '</small>';
            $html .= '</div>';
            $html .= '<div class="mb-1">';
            $html .= '<span class="badge bg-' . ($transaction['points'] > 0 ? 'success' : 'danger') . '">';
            $html .= ($transaction['points'] > 0 ? '+' : '') . number_format($transaction['points']) . ' points';
            $html .= '</span>';
            $html .= '</div>';
            $html .= '<p class="mb-0">' . htmlspecialchars($transaction['description']) . '</p>';
            $html .= '</div>';
        }
    } else {
        $html .= '<p class="text-muted">No transactions found</p>';
    }
    $html .= '</div>';
    
    $html .= '</div>'; // Close tab-content
    $html .= '</div>'; // Close container
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
