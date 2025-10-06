<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$ticket_id = intval($_GET['id'] ?? 0);

if (!$ticket_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get ticket details
    $ticket_query = "SELECT st.*, u.username, u.email 
                     FROM support_tickets st
                     LEFT JOIN users u ON st.user_id = u.id
                     WHERE st.id = :ticket_id";
    $ticket_stmt = $db->prepare($ticket_query);
    $ticket_stmt->bindParam(':ticket_id', $ticket_id);
    $ticket_stmt->execute();
    $ticket = $ticket_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found']);
        exit();
    }
    
    // Get replies
    $replies_query = "SELECT sr.*, u.username as admin_username
                      FROM support_replies sr
                      JOIN users u ON sr.admin_id = u.id
                      WHERE sr.ticket_id = :ticket_id
                      ORDER BY sr.created_at ASC";
    $replies_stmt = $db->prepare($replies_query);
    $replies_stmt->bindParam(':ticket_id', $ticket_id);
    $replies_stmt->execute();
    $replies = $replies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build HTML
    $html = '<div class="ticket-history">';
    
    // Original ticket
    $html .= '<div class="mb-4 p-3 border rounded">';
    $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
    $html .= '<strong>' . htmlspecialchars($ticket['username'] ?: $ticket['name']) . '</strong>';
    $html .= '<small class="text-muted">' . date('M j, Y g:i A', strtotime($ticket['created_at'])) . '</small>';
    $html .= '</div>';
    $html .= '<h6>' . htmlspecialchars($ticket['subject']) . '</h6>';
    $html .= '<p>' . nl2br(htmlspecialchars($ticket['message'])) . '</p>';
    $html .= '</div>';
    
    // Replies
    foreach ($replies as $reply) {
        $html .= '<div class="mb-3 p-3 border rounded bg-light">';
        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $html .= '<strong class="text-primary">' . htmlspecialchars($reply['admin_username']) . ' (Admin)</strong>';
        $html .= '<small class="text-muted">' . date('M j, Y g:i A', strtotime($reply['created_at'])) . '</small>';
        $html .= '</div>';
        $html .= '<p class="mb-0">' . nl2br(htmlspecialchars($reply['message'])) . '</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
