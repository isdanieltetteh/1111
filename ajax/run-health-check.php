<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/site-health-checker.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $health_checker = new SiteHealthChecker($db);
    
    // Check up to 20 sites per manual run
    $results = $health_checker->checkAllSites(20);
    
    $total_checked = count($results);
    $dead_sites = array_filter($results, function($r) { 
        return !$r['result']['accessible']; 
    });
    $dead_count = count($dead_sites);
    
    echo json_encode([
        'success' => true,
        'message' => 'Health check completed',
        'checked' => $total_checked,
        'dead' => $dead_count,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Health check failed: ' . $e->getMessage()]);
}
?>
