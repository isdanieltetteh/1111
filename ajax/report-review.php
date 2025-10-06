<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to report reviews']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$review_id = intval($input['review_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$review_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if review exists
    $review_query = "SELECT id, user_id FROM reviews WHERE id = :review_id AND is_deleted = 0";
    $review_stmt = $db->prepare($review_query);
    $review_stmt->bindParam(':review_id', $review_id);
    $review_stmt->execute();
    $review = $review_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit();
    }
    
    // Check if user already reported this review
    $existing_report_query = "SELECT id FROM review_reports WHERE review_id = :review_id AND reported_by = :user_id";
    $existing_report_stmt = $db->prepare($existing_report_query);
    $existing_report_stmt->bindParam(':review_id', $review_id);
    $existing_report_stmt->bindParam(':user_id', $user_id);
    $existing_report_stmt->execute();
    
    if ($existing_report_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reported this review']);
        exit();
    }
    
    // Insert report
    $report_query = "INSERT INTO review_reports (review_id, reported_by, reason, ip_address) 
                    VALUES (:review_id, :user_id, 'inappropriate_content', :ip_address)";
    $report_stmt = $db->prepare($report_query);
    $report_stmt->bindParam(':review_id', $review_id);
    $report_stmt->bindParam(':user_id', $user_id);
    $report_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
    $report_stmt->execute();
    
    // Check if review should be auto-flagged (if multiple reports)
    $report_count_query = "SELECT COUNT(*) as count FROM review_reports WHERE review_id = :review_id";
    $report_count_stmt = $db->prepare($report_count_query);
    $report_count_stmt->bindParam(':review_id', $review_id);
    $report_count_stmt->execute();
    $report_count = $report_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($report_count >= 3) {
        // Flag review for admin attention
        $flag_query = "UPDATE reviews SET is_flagged = 1 WHERE id = :review_id";
        $flag_stmt = $db->prepare($flag_query);
        $flag_stmt->bindParam(':review_id', $review_id);
        $flag_stmt->execute();
        
        // Create admin notification
        $admin_notification_query = "INSERT INTO notifications (user_id, title, message, type, reference_id, reference_type)
                                    SELECT id, 'Review Flagged', 'Review has been flagged due to multiple reports', 'warning', :review_id, 'review'
                                    FROM users WHERE is_admin = 1";
        $admin_notification_stmt = $db->prepare($admin_notification_query);
        $admin_notification_stmt->bindParam(':review_id', $review_id);
        $admin_notification_stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Review reported successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
