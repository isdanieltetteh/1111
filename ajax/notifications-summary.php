<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

try {
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $count_stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $count_stmt->execute();
    $unread_count = (int) $count_stmt->fetchColumn();

    $latest_stmt = $db->prepare("SELECT title, message, created_at FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1");
    $latest_stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $latest_stmt->execute();
    $latest = $latest_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($latest) {
        $latest['preview'] = mb_strimwidth(strip_tags($latest['message']), 0, 80, '…');
    }

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'latest' => $latest,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch notifications']);
}
