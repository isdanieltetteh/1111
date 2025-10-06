<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to reply']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$review_id = intval($_POST['review_id'] ?? 0);
$reply_content = trim($_POST['reply_content'] ?? '');
$user_id = $_SESSION['user_id'];

if (!$review_id || empty($reply_content)) {
    echo json_encode(['success' => false, 'message' => 'Review ID and reply content are required']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $table_check_query = "SHOW TABLES LIKE 'review_replies'";
    $table_check_stmt = $db->prepare($table_check_query);
    $table_check_stmt->execute();
    $table_exists = $table_check_stmt->rowCount() > 0;
    
    if (!$table_exists) {
        // Create the review_replies table
        $create_table_query = "
            CREATE TABLE review_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                review_id INT NOT NULL,
                user_id INT NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_deleted TINYINT(1) DEFAULT 0,
                FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_review_id (review_id),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($create_table_query);
    }
    
    // Verify review exists and get site info
    $review_query = "SELECT id, site_id FROM reviews WHERE id = :review_id AND is_deleted = 0";
    $review_stmt = $db->prepare($review_query);
    $review_stmt->bindParam(':review_id', $review_id);
    $review_stmt->execute();
    $review = $review_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit();
    }
    
    // Insert reply
    $insert_query = "INSERT INTO review_replies (review_id, user_id, content) VALUES (:review_id, :user_id, :content)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':review_id', $review_id);
    $insert_stmt->bindParam(':user_id', $user_id);
    $insert_stmt->bindParam(':content', $reply_content);
    $insert_stmt->execute();
    
    // Award points for reply
    require_once '../includes/wallet.php';
    $wallet_manager = new WalletManager($db);
    $wallet_manager->addPoints($user_id, 3, 'earned', 'Reply to review', $review_id, 'reply');
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reply posted successfully! Points awarded.',
        'reload' => true
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
