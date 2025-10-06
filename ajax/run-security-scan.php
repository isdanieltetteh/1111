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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $threats_detected = 0;
    $scan_results = [];
    
    // 1. Check for suspicious IP patterns
    $suspicious_ips_query = "SELECT ip_address, COUNT(*) as event_count,
                            SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_risk_count
                            FROM security_logs 
                            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                            GROUP BY ip_address
                            HAVING event_count > 20 OR high_risk_count > 5";
    $suspicious_ips_stmt = $db->prepare($suspicious_ips_query);
    $suspicious_ips_stmt->execute();
    $suspicious_ips = $suspicious_ips_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($suspicious_ips)) {
        $threats_detected += count($suspicious_ips);
        $scan_results[] = count($suspicious_ips) . ' suspicious IP addresses detected';
    }
    
    // 2. Check for multiple accounts from same IP
    $multi_accounts_query = "SELECT ip_address, COUNT(DISTINCT user_id) as account_count
                            FROM ip_registrations 
                            GROUP BY ip_address
                            HAVING account_count > 5";
    $multi_accounts_stmt = $db->prepare($multi_accounts_query);
    $multi_accounts_stmt->execute();
    $multi_accounts = $multi_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($multi_accounts)) {
        $threats_detected += count($multi_accounts);
        $scan_results[] = count($multi_accounts) . ' IPs with excessive account creation';
    }
    
    // 3. Check for failed login patterns
    $failed_logins_query = "SELECT ip_address, COUNT(*) as failed_count
                           FROM security_logs 
                           WHERE action = 'login_failed' 
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                           GROUP BY ip_address
                           HAVING failed_count > 10";
    $failed_logins_stmt = $db->prepare($failed_logins_query);
    $failed_logins_stmt->execute();
    $failed_logins = $failed_logins_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($failed_logins)) {
        $threats_detected += count($failed_logins);
        $scan_results[] = count($failed_logins) . ' IPs with excessive failed logins';
    }
    
    // 4. Check for unusual user behavior
    $unusual_behavior_query = "SELECT user_id, COUNT(*) as action_count
                              FROM security_logs 
                              WHERE user_id IS NOT NULL 
                              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                              GROUP BY user_id
                              HAVING action_count > 50";
    $unusual_behavior_stmt = $db->prepare($unusual_behavior_query);
    $unusual_behavior_stmt->execute();
    $unusual_behavior = $unusual_behavior_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($unusual_behavior)) {
        $threats_detected += count($unusual_behavior);
        $scan_results[] = count($unusual_behavior) . ' users with unusual activity patterns';
    }
    
    // 5. Check for potential spam reviews
    $spam_reviews_query = "SELECT user_id, COUNT(*) as review_count
                          FROM reviews 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                          GROUP BY user_id
                          HAVING review_count > 10";
    $spam_reviews_stmt = $db->prepare($spam_reviews_query);
    $spam_reviews_stmt->execute();
    $spam_reviews = $spam_reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($spam_reviews)) {
        $threats_detected += count($spam_reviews);
        $scan_results[] = count($spam_reviews) . ' users with potential spam reviews';
    }
    
    // Log security scan
    $scan_log_query = "INSERT INTO admin_actions (admin_id, action, target_type, notes, details) 
                      VALUES (:admin_id, 'security_scan', 'system', 'Automated security scan', :details)";
    $scan_log_stmt = $db->prepare($scan_log_query);
    $scan_log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
    $scan_details = json_encode([
        'threats_detected' => $threats_detected,
        'scan_results' => $scan_results,
        'scan_timestamp' => date('Y-m-d H:i:s')
    ]);
    $scan_log_stmt->bindParam(':details', $scan_details);
    $scan_log_stmt->execute();
    
    $summary = empty($scan_results) ? 'No security threats detected' : implode("\n", $scan_results);
    
    echo json_encode([
        'success' => true,
        'threats' => $threats_detected,
        'summary' => $summary,
        'details' => $scan_results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Security scan failed: ' . $e->getMessage()]);
}
?>
