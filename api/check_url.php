<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

$key = $_SERVER['REMOTE_ADDR'] . '_check_url';
$limiter = new RateLimiter($key, 10, 60); // Increased limit for better UX

if (!$limiter->allow()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests, try again later.']);
    exit;
}

$url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
if (!$url) {
    echo json_encode(['success' => false, 'error' => 'Invalid URL']);
    exit;
}

// Prevent SSRF
$host = parse_url($url, PHP_URL_HOST);
if (!$host || preg_match('/^(localhost|127\.|10\.|192\.168\.)/', $host)) {
    echo json_encode(['success' => false, 'error' => 'Unsafe host']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if URL already exists in database
    $check_query = "SELECT id, name FROM sites WHERE url = :url";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':url', $url);
    $check_stmt->execute();
    $existing_site = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_site) {
        echo json_encode([
            'success' => true, 
            'exists' => true, 
            'site_name' => $existing_site['name'],
            'message' => 'URL already exists in database'
        ]);
        exit;
    }
    
    // Check if URL is reachable
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (compatible; SiteChecker/1.0)'
        ]
    ]);
    
    $headers = @get_headers($url, 1, $context);
    if (!$headers || !preg_match('/2\d\d/', $headers[0])) {
        echo json_encode([
            'success' => false, 
            'error' => 'Site not reachable or returned error status'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'exists' => false, 
        'message' => 'URL is available and reachable'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
