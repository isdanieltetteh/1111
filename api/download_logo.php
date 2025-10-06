<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

$key = $_SERVER['REMOTE_ADDR'] . '_download_logo';
$limiter = new RateLimiter($key, 3, 60); // 3 requests per minute

if (!$limiter->allow()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests, try again later.']);
    exit;
}

$logo_url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
$site_url = filter_input(INPUT_GET, 'site_url', FILTER_VALIDATE_URL);

if (!$logo_url || !$site_url) {
    echo json_encode(['success' => false, 'error' => 'Invalid URLs']);
    exit;
}

// Prevent SSRF
$host = parse_url($logo_url, PHP_URL_HOST);
if (!$host || preg_match('/^(localhost|127\.|10\.|192\.168\.)/', $host)) {
    echo json_encode(['success' => false, 'error' => 'Unsafe host']);
    exit;
}

try {
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'follow_location' => true,
            'max_redirects' => 5,
            'header' => [
                'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache',
                'Pragma: no-cache'
            ]
        ]
    ]);
    
    $image_data = false;
    $attempts = [
        $logo_url,
        // Try common favicon paths if original fails
        rtrim($site_url, '/') . '/favicon.ico',
        rtrim($site_url, '/') . '/favicon.png',
        rtrim($site_url, '/') . '/apple-touch-icon.png',
        rtrim($site_url, '/') . '/logo.png'
    ];
    
    foreach ($attempts as $attempt_url) {
        $image_data = @file_get_contents($attempt_url, false, $context);
        if ($image_data !== false && strlen($image_data) > 0) {
            $logo_url = $attempt_url; // Update to successful URL
            break;
        }
    }
    
    if (!$image_data) {
        echo json_encode(['success' => false, 'error' => 'Could not download logo from any source']);
        exit;
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_data);
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];
    if (!in_array($mime_type, $allowed_types)) {
        // Try to detect by file extension if MIME detection fails
        $ext = strtolower(pathinfo(parse_url($logo_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid image type: ' . $mime_type]);
            exit;
        }
        // Override MIME type based on extension
        $mime_type = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 
                    ($ext === 'png' ? 'image/png' : 
                    ($ext === 'gif' ? 'image/gif' : 
                    ($ext === 'webp' ? 'image/webp' : 'image/x-icon')));
    }
    
    // Check file size (max 5MB for logos)
    if (strlen($image_data) > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Image too large (max 5MB)']);
        exit;
    }
    
    if (strlen($image_data) < 100) {
        echo json_encode(['success' => false, 'error' => 'Image too small (likely a tracking pixel)']);
        exit;
    }
    
    // Create upload directory
    $upload_dir = __DIR__ . '/../assets/images/temp/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension_map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico'
    ];
    
    $extension = $extension_map[$mime_type] ?? 'png';
    $filename = 'temp_logo_' . time() . '_' . uniqid() . '.' . $extension;
    $file_path = $upload_dir . $filename;
    
    // Save file
    if (file_put_contents($file_path, $image_data) === false) {
        echo json_encode(['success' => false, 'error' => 'Could not save logo']);
        exit;
    }
    
    $image_info = @getimagesize($file_path);
    $width = $image_info[0] ?? 0;
    $height = $image_info[1] ?? 0;
    
    echo json_encode([
        'success' => true,
        'local_path' => 'assets/images/temp/' . $filename,
        'filename' => $filename,
        'size' => strlen($image_data),
        'mime_type' => $mime_type,
        'dimensions' => $width . 'x' . $height,
        'source_url' => $logo_url
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error downloading logo: ' . $e->getMessage()]);
}
?>
