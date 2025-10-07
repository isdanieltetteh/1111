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
$name = trim($input['name'] ?? '');

if ($name === '') {
    echo json_encode(['available' => false, 'message' => 'Site name is required']);
    exit();
}

if (mb_strlen($name) < 3) {
    echo json_encode(['available' => false, 'message' => 'Site name must be at least 3 characters']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id FROM sites WHERE name = :name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->execute();

    $available = $stmt->rowCount() === 0;

    echo json_encode([
        'available' => $available,
        'message' => $available
            ? 'Site name available'
            : 'A listing with this name already exists'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['available' => false, 'message' => 'Database error']);
}
?>
