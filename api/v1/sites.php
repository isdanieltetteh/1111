<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "api_rate_limit_{$ip}";
$max_requests = 100;
$time_window = 3600; // 1 hour

// Simple file-based rate limiting
$rate_file = __DIR__ . "/../../cache/rate_limit_{$ip}.txt";
if (file_exists($rate_file)) {
    $rate_data = json_decode(file_get_contents($rate_file), true);
    if ($rate_data['timestamp'] > time() - $time_window) {
        if ($rate_data['requests'] >= $max_requests) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Rate limit exceeded. Try again later.'
                ]
            ]);
            exit();
        }
        $rate_data['requests']++;
    } else {
        $rate_data = ['timestamp' => time(), 'requests' => 1];
    }
} else {
    $rate_data = ['timestamp' => time(), 'requests' => 1];
    if (!is_dir(__DIR__ . '/../../cache')) {
        mkdir(__DIR__ . '/../../cache', 0755, true);
    }
}

file_put_contents($rate_file, json_encode($rate_data));

// Add rate limit headers
header("X-RateLimit-Limit: {$max_requests}");
header("X-RateLimit-Remaining: " . ($max_requests - $rate_data['requests']));
header("X-RateLimit-Reset: " . ($rate_data['timestamp'] + $time_window));

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get specific site if ID provided
    if (isset($_GET['id'])) {
        $site_id = intval($_GET['id']);
        
        $site_query = "SELECT s.*, 
                       COALESCE(AVG(r.rating), 0) as average_rating,
                       COUNT(r.id) as review_count
                       FROM sites s 
                       LEFT JOIN reviews r ON s.id = r.site_id AND r.is_deleted = 0
                       WHERE s.id = :site_id AND s.is_approved = 1 AND s.is_dead = FALSE AND s.admin_approved_dead = FALSE
                       GROUP BY s.id";
        $site_stmt = $db->prepare($site_query);
        $site_stmt->bindParam(':site_id', $site_id);
        $site_stmt->execute();
        $site = $site_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$site) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'SITE_NOT_FOUND',
                    'message' => 'Site not found or not approved'
                ]
            ]);
            exit();
        }
        
        // Get recent reviews
        $reviews_query = "SELECT r.rating, r.comment, r.created_at, u.username
                         FROM reviews r
                         JOIN users u ON r.user_id = u.id
                         WHERE r.site_id = :site_id AND r.is_deleted = 0
                         ORDER BY r.created_at DESC
                         LIMIT 5";
        $reviews_stmt = $db->prepare($reviews_query);
        $reviews_stmt->bindParam(':site_id', $site_id);
        $reviews_stmt->execute();
        $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $site['reviews'] = $reviews;
        
        echo json_encode([
            'success' => true,
            'data' => $site
        ]);
        exit();
    }
    
    // Get parameters
    $category = $_GET['category'] ?? 'all';
    $status = $_GET['status'] ?? 'paying';
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $page = max(1, intval($_GET['page'] ?? 1));
    $sort = $_GET['sort'] ?? 'newest';
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where_conditions = ["s.is_approved = 1", "s.is_dead = FALSE", "s.admin_approved_dead = FALSE"];
    $params = [];
    
    if ($category !== 'all') {
        $where_conditions[] = "s.category = :category";
        $params[':category'] = $category;
    }
    
    if ($status !== 'all') {
        $where_conditions[] = "s.status = :status";
        $params[':status'] = $status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Build ORDER BY clause
    $order_by = match($sort) {
        'newest' => 's.created_at DESC',
        'rating' => 'average_rating DESC, s.total_reviews DESC',
        'upvotes' => 's.total_upvotes DESC',
        'views' => 's.views DESC',
        default => 's.created_at DESC'
    };
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM sites s WHERE {$where_clause}";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_sites = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_sites / $limit);
    
    // Get sites
    $sites_query = "SELECT s.id, s.name, s.url, s.category, s.status, s.description, 
                    s.supported_coins, s.total_upvotes, s.total_downvotes, s.views, s.created_at,
                    COALESCE(AVG(r.rating), 0) as average_rating,
                    COUNT(r.id) as review_count
                    FROM sites s 
                    LEFT JOIN reviews r ON s.id = r.site_id AND r.is_deleted = 0
                    WHERE {$where_clause}
                    GROUP BY s.id 
                    ORDER BY {$order_by}
                    LIMIT {$limit} OFFSET {$offset}";
    
    $sites_stmt = $db->prepare($sites_query);
    $sites_stmt->execute($params);
    $sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    echo json_encode([
        'success' => true,
        'data' => $sites,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_results' => $total_sites,
            'per_page' => $limit
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'An internal error occurred'
        ]
    ]);
}
?>
