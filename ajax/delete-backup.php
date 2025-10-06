<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['filename'] ?? '';

if (empty($filename) || !preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
    echo json_encode(['success' => false, 'message' => 'Invalid filename']);
    exit();
}

$backup_path = __DIR__ . '/../backups/' . $filename;

if (file_exists($backup_path)) {
    if (unlink($backup_path)) {
        echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting backup file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Backup file not found']);
}
?>
