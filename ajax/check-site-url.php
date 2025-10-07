<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/site-health-checker.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$url = trim($input['url'] ?? '');

if (empty($url)) {
    echo json_encode(['valid' => false, 'message' => 'URL is required']);
    exit();
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['valid' => false, 'message' => 'Please enter a valid URL']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $health_checker = new SiteHealthChecker($db);
    
    // Check if URL already exists
    $duplicate_query = "SELECT id, name, submitted_by FROM sites WHERE url = :url";
    $duplicate_stmt = $db->prepare($duplicate_query);
    $duplicate_stmt->bindParam(':url', $url);
    $duplicate_stmt->execute();
    $existing_site = $duplicate_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_site) {
        echo json_encode([
            'valid' => false,
            'duplicate' => true,
            'message' => 'This URL already exists in our directory. Please submit a unique site.',
            'existing_name' => $existing_site['name']
        ]);
        exit();
    }
    
    // Check if site is accessible
    $health_result = $health_checker->checkUrl($url);
    
    if ($health_result['accessible']) {
        echo json_encode([
            'valid' => true,
            'accessible' => true,
            'message' => 'Site is accessible and ready for submission',
            'response_time' => $health_result['response_time']
        ]);
    } else {
        echo json_encode([
            'valid' => false,
            'accessible' => false,
            'message' => 'Site appears to be inaccessible: ' . $health_result['error_message'],
            'error_details' => $health_result['error_message']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'Error checking URL']);
}
?>
