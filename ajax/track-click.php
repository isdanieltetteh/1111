<?php
require_once __DIR__ . '/../config/database.php';
 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$site_id = intval($input['site_id'] ?? 0);

if (!$site_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid site ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Update click count
    $query = "UPDATE sites SET clicks = clicks + 1 WHERE id = :site_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':site_id', $site_id);
    $stmt->execute();
    
    // Log click for analytics (optional)
    $log_query = "INSERT INTO site_clicks (site_id, ip_address, user_agent, clicked_at) VALUES (:site_id, :ip, :user_agent, NOW())";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->bindParam(':site_id', $site_id);
    $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
    $log_stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    $log_stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
