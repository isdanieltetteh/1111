<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/MailService.php';
require_once __DIR__ . '/../includes/newsletter_helpers.php';

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
    
    $mailer = MailService::getInstance();

    // Check if already subscribed
    $check_query = "SELECT id, is_active FROM newsletter_subscriptions WHERE email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ((int) $existing['is_active'] === 1) {
            echo json_encode(['success' => false, 'message' => 'This email is already subscribed and active.']);
            exit();
        }

        $token = newsletter_generate_verification_token();
        $reactivate_query = "UPDATE newsletter_subscriptions SET verification_token = :token, verified_at = NULL, is_active = 0, updated_at = NOW() WHERE id = :id";
        $reactivate_stmt = $db->prepare($reactivate_query);
        $reactivate_stmt->execute([
            ':token' => $token,
            ':id' => (int) $existing['id'],
        ]);

        newsletter_send_confirmation_email($mailer, $email, $token);

        echo json_encode(['success' => true, 'message' => 'Check your inbox to confirm your subscription.']);
        exit();
    }

    // Default preferences for footer subscription
    $default_preferences = ['scam_alerts', 'new_sites', 'weekly_digest'];

    // Insert new subscription
    $token = newsletter_generate_verification_token();
    $insert_query = "INSERT INTO newsletter_subscriptions (email, preferences, verification_token, is_active)
                    VALUES (:email, :preferences, :token, 0)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':email', $email);
    $insert_stmt->bindParam(':preferences', json_encode($default_preferences));
    $insert_stmt->bindParam(':token', $token);

    if ($insert_stmt->execute()) {
        newsletter_send_confirmation_email($mailer, $email, $token);

        echo json_encode(['success' => true, 'message' => 'Almost done! Please confirm your email address via the message we just sent.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error subscribing to newsletter']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
