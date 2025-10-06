<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email address is required']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if already subscribed
    $check_query = "SELECT id, is_active FROM newsletter_subscriptions WHERE email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        if ($existing['is_active']) {
            echo json_encode(['success' => false, 'message' => 'This email is already subscribed']);
        } else {
            // Reactivate subscription
            $reactivate_query = "UPDATE newsletter_subscriptions SET is_active = 1, updated_at = NOW() WHERE email = :email";
            $reactivate_stmt = $db->prepare($reactivate_query);
            $reactivate_stmt->bindParam(':email', $email);
            $reactivate_stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Subscription reactivated successfully!']);
        }
        exit();
    }
    
    // Default preferences for footer subscription
    $default_preferences = ['scam_alerts', 'new_sites', 'weekly_digest'];
    
    // Insert new subscription
    $insert_query = "INSERT INTO newsletter_subscriptions (email, preferences, verified_at) 
                    VALUES (:email, :preferences, NOW())";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':email', $email);
    $insert_stmt->bindParam(':preferences', json_encode($default_preferences));
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Successfully subscribed to newsletter!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error subscribing to newsletter']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
