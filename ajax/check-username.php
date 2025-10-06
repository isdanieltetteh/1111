<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');

if (empty($username)) {
    echo json_encode(['available' => false, 'message' => 'Username is required']);
    exit();
}

// Validate username format
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    echo json_encode(['available' => false, 'message' => 'Invalid username format']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if username exists
    $query = "SELECT id FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $available = $stmt->rowCount() === 0;
    
    echo json_encode([
        'available' => $available,
        'message' => $available ? 'Username available' : 'Username already taken'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['available' => false, 'message' => 'Database error']);
}
?>
