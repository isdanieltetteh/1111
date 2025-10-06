<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/wallet.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to submit a review']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$site_id = intval($input['site_id'] ?? 0);
$rating = intval($input['rating'] ?? 0);
$review_text = trim($input['review'] ?? '');
$user_id = $_SESSION['user_id'];

// Validation
if (!$site_id || $rating < 1 || $rating > 5 || strlen($review_text) < 10) {
    echo json_encode(['success' => false, 'message' => 'Invalid input. Rating must be 1-5 and review must be at least 10 characters.']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if site exists and is approved
    $site_check = "SELECT id FROM sites WHERE id = :site_id AND is_approved = 1";
    $site_stmt = $db->prepare($site_check);
    $site_stmt->bindParam(':site_id', $site_id);
    $site_stmt->execute();
    
    if (!$site_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Site not found']);
        exit();
    }
    
    // Check if user already reviewed this site
    $existing_check = "SELECT id FROM reviews WHERE user_id = :user_id AND site_id = :site_id AND is_deleted = 0";
    $existing_stmt = $db->prepare($existing_check);
    $existing_stmt->bindParam(':user_id', $user_id);
    $existing_stmt->bindParam(':site_id', $site_id);
    $existing_stmt->execute();
    
    if ($existing_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this site']);
        exit();
    }
    
    $db->beginTransaction();
    
    // Insert review
    $insert_review = "INSERT INTO reviews (user_id, site_id, rating, review_text) 
                      VALUES (:user_id, :site_id, :rating, :review_text)";
    $review_stmt = $db->prepare($insert_review);
    $review_stmt->bindParam(':user_id', $user_id);
    $review_stmt->bindParam(':site_id', $site_id);
    $review_stmt->bindParam(':rating', $rating);
    $review_stmt->bindParam(':review_text', $review_text);
    $review_stmt->execute();
    
    $review_id = $db->lastInsertId();
    
    // Update user total reviews count
    $update_user_reviews = "UPDATE users SET total_reviews = total_reviews + 1 WHERE id = :user_id";
    $user_stmt = $db->prepare($update_user_reviews);
    $user_stmt->bindParam(':user_id', $user_id);
    $user_stmt->execute();
    
    // Award points for review submission
    $wallet_manager = new WalletManager($db);
    $wallet_manager->addPoints($user_id, 5, 'earned', 'Review posted', $review_id, 'review');
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Review submitted successfully! You earned 5 reputation points.',
        'review_id' => $review_id
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
