<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to vote']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$vote_type = $input['vote_type'] ?? '';
$target_type = $input['target_type'] ?? '';
$target_id = intval($input['target_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!in_array($vote_type, ['upvote', 'downvote']) || !in_array($target_type, ['site', 'review']) || !$target_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify target exists
    if ($target_type === 'site') {
        $verify_query = "SELECT id FROM sites WHERE id = :target_id AND is_approved = 1";
    } else {
        $verify_query = "SELECT id FROM reviews WHERE id = :target_id AND is_deleted = 0";
    }
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->bindParam(':target_id', $target_id);
    $verify_stmt->execute();
    
    if (!$verify_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Target not found']);
        exit();
    }
    
    // Check if user already voted
    $check_query = "SELECT vote_type FROM votes WHERE user_id = :user_id AND {$target_type}_id = :target_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->bindParam(':target_id', $target_id);
    $check_stmt->execute();
    $existing_vote = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    $db->beginTransaction();
    
    if ($existing_vote) {
        if ($existing_vote['vote_type'] === $vote_type) {
            // Remove vote if clicking same button
            $delete_query = "DELETE FROM votes WHERE user_id = :user_id AND {$target_type}_id = :target_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':user_id', $user_id);
            $delete_stmt->bindParam(':target_id', $target_id);
            $delete_stmt->execute();
            $new_vote = null;
        } else {
            // Update vote type
            $update_query = "UPDATE votes SET vote_type = :vote_type WHERE user_id = :user_id AND {$target_type}_id = :target_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':vote_type', $vote_type);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->bindParam(':target_id', $target_id);
            $update_stmt->execute();
            $new_vote = $vote_type;
        }
    } else {
        // Insert new vote
        $insert_query = "INSERT INTO votes (user_id, {$target_type}_id, vote_type) VALUES (:user_id, :target_id, :vote_type)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':user_id', $user_id);
        $insert_stmt->bindParam(':target_id', $target_id);
        $insert_stmt->bindParam(':vote_type', $vote_type);
        $insert_stmt->execute();
        $new_vote = $vote_type;
    }
    
    // Update vote counts
    if ($target_type === 'site') {
        $count_query = "SELECT 
                        SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                        SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes
                        FROM votes WHERE site_id = :target_id";
        $count_stmt = $db->prepare($count_query);
        $count_stmt->bindParam(':target_id', $target_id);
        $count_stmt->execute();
        $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
        
        $update_counts = "UPDATE sites SET total_upvotes = :upvotes, total_downvotes = :downvotes WHERE id = :target_id";
        $update_stmt = $db->prepare($update_counts);
        $update_stmt->bindParam(':upvotes', $counts['upvotes']);
        $update_stmt->bindParam(':downvotes', $counts['downvotes']);
        $update_stmt->bindParam(':target_id', $target_id);
        $update_stmt->execute();
        
        // Award points to user for voting and update reputation
        if ($new_vote) {
            require_once __DIR__ . '/../includes/wallet.php';
            $wallet_manager = new WalletManager($db);
            $wallet_manager->addPoints($user_id, 1, 'earned', 'Voted on site', $target_id, 'vote');
            
            $update_reputation = "UPDATE users SET reputation_points = reputation_points + 1 WHERE id = :user_id";
            $rep_stmt = $db->prepare($update_reputation);
            $rep_stmt->bindParam(':user_id', $user_id);
            $rep_stmt->execute();
        }
    } else {
        $count_query = "SELECT 
                        SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                        SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes
                        FROM votes WHERE review_id = :target_id";
        $count_stmt = $db->prepare($count_query);
        $count_stmt->bindParam(':target_id', $target_id);
        $count_stmt->execute();
        $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
        
        $update_counts = "UPDATE reviews SET upvotes = :upvotes, downvotes = :downvotes WHERE id = :target_id";
        $update_stmt = $db->prepare($update_counts);
        $update_stmt->bindParam(':upvotes', $counts['upvotes']);
        $update_stmt->bindParam(':downvotes', $counts['downvotes']);
        $update_stmt->bindParam(':target_id', $target_id);
        $update_stmt->execute();
        
        if ($new_vote) {
            require_once __DIR__ . '/../includes/wallet.php';
            $wallet_manager = new WalletManager($db);
            $wallet_manager->addPoints($user_id, 1, 'earned', 'Voted on review', $target_id, 'vote');
            
            // Update user reputation points
            $update_reputation = "UPDATE users SET reputation_points = reputation_points + 1 WHERE id = :user_id";
            $rep_stmt = $db->prepare($update_reputation);
            $rep_stmt->bindParam(':user_id', $user_id);
            $rep_stmt->execute();
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Vote updated successfully',
        'data' => [
            'upvotes' => $counts['upvotes'],
            'downvotes' => $counts['downvotes'],
            'user_vote' => $new_vote
        ]
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
