<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

$key = $_SERVER['REMOTE_ADDR'] . '_fetch_metadata';
$limiter = new RateLimiter($key, 5, 60); // 5 requests per minute

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
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (compatible; MetadataFetcher/1.0)',
            'follow_location' => true,
            'max_redirects' => 3
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    if (!$html) {
        echo json_encode(['success' => false, 'error' => 'Could not fetch page content']);
        exit;
    }
    
    // Parse HTML to extract metadata
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    $metadata = [
        'title' => '',
        'description' => '',
        'logo_url' => ''
    ];
    
    // Extract title
    $titleNodes = $xpath->query('//title');
    if ($titleNodes->length > 0) {
        $metadata['title'] = trim($titleNodes->item(0)->textContent);
    }
    
    // Extract description from meta tags
    $descNodes = $xpath->query('//meta[@name="description"]/@content');
    if ($descNodes->length > 0) {
        $metadata['description'] = trim($descNodes->item(0)->textContent);
    }
    
    // Extract logo/favicon
    $logoSelectors = [
        '//link[@rel="icon"]/@href',
        '//link[@rel="shortcut icon"]/@href',
        '//link[@rel="apple-touch-icon"]/@href',
        '//meta[@property="og:image"]/@content',
        '//link[@rel="logo"]/@href'
    ];
    
    foreach ($logoSelectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
            $logoUrl = trim($nodes->item(0)->textContent);
            if ($logoUrl) {
                // Convert relative URLs to absolute
                if (strpos($logoUrl, 'http') !== 0) {
                    $logoUrl = rtrim($url, '/') . '/' . ltrim($logoUrl, '/');
                }
                $metadata['logo_url'] = $logoUrl;
                break;
            }
        }
    }
    
    // Clean up title and description
    $metadata['title'] = html_entity_decode($metadata['title'], ENT_QUOTES, 'UTF-8');
    $metadata['description'] = html_entity_decode($metadata['description'], ENT_QUOTES, 'UTF-8');
    
    // Limit lengths
    if (strlen($metadata['title']) > 100) {
        $metadata['title'] = substr($metadata['title'], 0, 97) . '...';
    }
    if (strlen($metadata['description']) > 500) {
        $metadata['description'] = substr($metadata['description'], 0, 497) . '...';
    }
    
    echo json_encode([
        'success' => true,
        'title' => $metadata['title'],
        'description' => $metadata['description'],
        'logo_url' => $metadata['logo_url']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error fetching metadata']);
}
?>
