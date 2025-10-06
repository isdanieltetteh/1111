<?php
class SiteHealthChecker {
    private $db;
    private $timeout = 15;
    private $max_redirects = 3;
    private $user_agent = 'Mozilla/5.0 (compatible; CryptoEarn Health Checker/1.0)';
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check if a URL is accessible with comprehensive testing
     */
    public function checkUrl($url, $site_id = null) {
        $start_time = microtime(true);
        $result = [
            'accessible' => false,
            'status_code' => 0,
            'response_time' => 0,
            'error_message' => '',
            'final_url' => $url,
            'ssl_valid' => false,
            'content_length' => 0,
            'server_info' => ''
        ];
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['error_message'] = 'Invalid URL format';
            return $result;
        }
        
        // Parse URL to check for suspicious patterns
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            $result['error_message'] = 'Invalid URL structure';
            return $result;
        }
        
        // Check for blocked domains
        $blocked_domains = ['localhost', '127.0.0.1', '0.0.0.0', 'example.com', 'test.com'];
        if (in_array(strtolower($parsed['host']), $blocked_domains)) {
            $result['error_message'] = 'Blocked domain';
            return $result;
        }
        
        // Initialize cURL with comprehensive options
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->max_redirects,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => $this->user_agent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Cache-Control: no-cache'
            ]
        ]);
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $content_length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $ssl_verify_result = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
        $error = curl_error($ch);
        curl_close($ch);
        
        $response_time = round((microtime(true) - $start_time) * 1000, 3);
        
        // Determine if site is accessible
        $is_accessible = false;
        $error_message = '';
        
        if ($error) {
            $error_message = "Connection error: " . $error;
        } elseif ($status_code >= 200 && $status_code < 400) {
            $is_accessible = true;
            
            // Additional content validation
            if ($response !== false && strlen($response) > 100) {
                // Check for common error indicators
                $error_indicators = [
                    'page not found' => 'Page not found',
                    '404 error' => '404 error page',
                    'site not found' => 'Site not found',
                    'domain expired' => 'Domain expired',
                    'suspended' => 'Account suspended',
                    'coming soon' => 'Coming soon page',
                    'under construction' => 'Under construction',
                    'parked domain' => 'Parked domain',
                    'this domain may be for sale' => 'Domain for sale'
                ];
                
                $response_lower = strtolower($response);
                foreach ($error_indicators as $indicator => $description) {
                    if (strpos($response_lower, $indicator) !== false) {
                        $is_accessible = false;
                        $error_message = "Site inactive: " . $description;
                        break;
                    }
                }
                
                // Check for minimal content
                if ($is_accessible && strlen(strip_tags($response)) < 50) {
                    $is_accessible = false;
                    $error_message = "Minimal content detected";
                }
            } elseif ($response === false || strlen($response) < 100) {
                $is_accessible = false;
                $error_message = "Empty or minimal response";
            }
        } elseif ($status_code >= 400) {
            $error_message = "HTTP Error: " . $status_code;
            switch ($status_code) {
                case 404: $error_message .= " (Page Not Found)"; break;
                case 403: $error_message .= " (Access Forbidden)"; break;
                case 500: $error_message .= " (Server Error)"; break;
                case 502: $error_message .= " (Bad Gateway)"; break;
                case 503: $error_message .= " (Service Unavailable)"; break;
                case 504: $error_message .= " (Gateway Timeout)"; break;
            }
        } else {
            $error_message = "No response received (Status: " . $status_code . ")";
        }
        
        $result = [
            'accessible' => $is_accessible,
            'status_code' => $status_code,
            'response_time' => $response_time,
            'error_message' => $error_message,
            'final_url' => $final_url,
            'ssl_valid' => $ssl_verify_result === 0,
            'content_length' => $content_length ?: strlen($response ?: ''),
            'server_info' => $this->extractServerInfo($response)
        ];
        
        // Log the health check if site_id is provided
        if ($site_id) {
            $this->logHealthCheck($site_id, $url, $result);
        }
        
        return $result;
    }
    
    /**
     * Extract server information from response
     */
    private function extractServerInfo($response) {
        if (!$response) return '';
        
        $info = [];
        
        // Look for common CMS/framework indicators
        if (preg_match('/generator.*?content="([^"]+)"/i', $response, $matches)) {
            $info[] = 'Generator: ' . $matches[1];
        }
        
        if (preg_match('/powered by ([^<\n]+)/i', $response, $matches)) {
            $info[] = 'Powered by: ' . trim($matches[1]);
        }
        
        return implode(', ', array_slice($info, 0, 2));
    }
    
    /**
     * Log health check result with enhanced data
     */
    private function logHealthCheck($site_id, $url, $result) {
        try {
            // Insert or update health check record
            $query = "INSERT INTO site_health_checks 
                     (site_id, url_checked, status_code, response_time, is_accessible, error_message, ssl_valid, content_length, server_info, last_checked) 
                     VALUES (:site_id, :url, :status_code, :response_time, :accessible, :error_message, :ssl_valid, :content_length, :server_info, NOW())
                     ON DUPLICATE KEY UPDATE
                     status_code = VALUES(status_code),
                     response_time = VALUES(response_time),
                     is_accessible = VALUES(is_accessible),
                     error_message = VALUES(error_message),
                     ssl_valid = VALUES(ssl_valid),
                     content_length = VALUES(content_length),
                     server_info = VALUES(server_info),
                     last_checked = NOW(),
                     check_count = check_count + 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':site_id', $site_id);
            $stmt->bindParam(':url', $url);
            $stmt->bindParam(':status_code', $result['status_code']);
            $stmt->bindParam(':response_time', $result['response_time']);
            $stmt->bindParam(':accessible', $result['accessible']);
            $stmt->bindParam(':error_message', $result['error_message']);
            $stmt->bindParam(':ssl_valid', $result['ssl_valid']);
            $stmt->bindParam(':content_length', $result['content_length']);
            $stmt->bindParam(':server_info', $result['server_info']);
            $stmt->execute();
            
            // Update site health status
            $this->updateSiteHealthStatus($site_id, $result['accessible'], $result['response_time']);
            
        } catch (Exception $e) {
            error_log("Health check logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Update site health status with enhanced tracking
     */
    private function updateSiteHealthStatus($site_id, $is_accessible, $response_time) {
        if ($is_accessible) {
            // Calculate uptime percentage
            $uptime_query = "SELECT 
                           COUNT(*) as total_checks,
                           SUM(CASE WHEN is_accessible = 1 THEN 1 ELSE 0 END) as successful_checks
                           FROM site_health_checks 
                           WHERE site_id = :site_id AND last_checked >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $uptime_stmt = $this->db->prepare($uptime_query);
            $uptime_stmt->bindParam(':site_id', $site_id);
            $uptime_stmt->execute();
            $uptime_data = $uptime_stmt->fetch(PDO::FETCH_ASSOC);
            
            $uptime_percentage = $uptime_data['total_checks'] > 0 ? 
                ($uptime_data['successful_checks'] / $uptime_data['total_checks']) * 100 : 100;
            
            // Reset failure count and update health metrics
            $update_query = "UPDATE sites SET 
                           consecutive_failures = 0, 
                           last_health_check = NOW(),
                           is_dead = FALSE,
                           avg_response_time = :response_time,
                           uptime_percentage = :uptime_percentage,
                           last_online = NOW()
                           WHERE id = :site_id";
            $stmt = $this->db->prepare($update_query);
            $stmt->bindParam(':site_id', $site_id);
            $stmt->bindParam(':response_time', $response_time);
            $stmt->bindParam(':uptime_percentage', $uptime_percentage);
            $stmt->execute();
        } else {
            // Increment failure count and check if should mark as dead
            $update_query = "UPDATE sites SET 
                           consecutive_failures = consecutive_failures + 1, 
                           last_health_check = NOW(),
                           is_dead = CASE 
                               WHEN consecutive_failures >= 2 THEN TRUE 
                               ELSE is_dead 
                           END,
                           first_failure_at = CASE 
                               WHEN consecutive_failures = 0 THEN NOW() 
                               ELSE first_failure_at 
                           END
                           WHERE id = :site_id";
            $stmt = $this->db->prepare($update_query);
            $stmt->bindParam(':site_id', $site_id);
            $stmt->execute();
            
            // Send notification if site becomes dead
            $this->checkAndNotifyDeadSite($site_id);
        }
    }
    
    /**
     * Check if site should be marked as dead and notify admins
     */
    private function checkAndNotifyDeadSite($site_id) {
        $site_query = "SELECT s.*, u.email as owner_email 
                      FROM sites s 
                      LEFT JOIN users u ON s.submitted_by = u.id 
                      WHERE s.id = :site_id AND s.consecutive_failures >= 3 AND s.is_dead = FALSE";
        $site_stmt = $this->db->prepare($site_query);
        $site_stmt->bindParam(':site_id', $site_id);
        $site_stmt->execute();
        $site = $site_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($site) {
            // Mark as dead
            $mark_dead = "UPDATE sites SET is_dead = TRUE WHERE id = :site_id";
            $mark_stmt = $this->db->prepare($mark_dead);
            $mark_stmt->bindParam(':site_id', $site_id);
            $mark_stmt->execute();
            
            // Create admin notification
            $this->createDeadLinkNotification($site);
        }
    }
    
    /**
     * Create notification for dead link detection
     */
    private function createDeadLinkNotification($site) {
        try {
            // Get all admin users
            $admin_query = "SELECT id FROM users WHERE is_admin = 1";
            $admin_stmt = $this->db->prepare($admin_query);
            $admin_stmt->execute();
            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($admins as $admin) {
                $notification_query = "INSERT INTO notifications (user_id, title, message, type, reference_id, reference_type) 
                                     VALUES (:user_id, :title, :message, 'warning', :site_id, 'dead_link')";
                $notification_stmt = $this->db->prepare($notification_query);
                $notification_stmt->bindParam(':user_id', $admin['id']);
                $title = 'Dead Link Detected';
                $message = "Site '{$site['name']}' has been marked as dead after {$site['consecutive_failures']} failed checks.";
                $notification_stmt->bindParam(':title', $title);
                $notification_stmt->bindParam(':message', $message);
                $notification_stmt->bindParam(':site_id', $site['id']);
                $notification_stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Dead link notification failed: " . $e->getMessage());
        }
    }
    
    /**
     * Check all active sites for health with enhanced reporting
     */
    public function checkAllSites($limit = 50) {
        // Get sites that need checking (prioritize by last check time and failure count)
        $query = "SELECT id, url, name, consecutive_failures
                 FROM sites 
                 WHERE is_approved = 1 
                 AND admin_approved_dead = FALSE
                 AND (last_health_check IS NULL OR last_health_check < DATE_SUB(NOW(), INTERVAL 24 HOUR))
                 ORDER BY 
                     CASE WHEN consecutive_failures > 0 THEN 1 ELSE 2 END,
                     last_health_check ASC, 
                     id ASC
                 LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        $dead_count = 0;
        $restored_count = 0;
        
        foreach ($sites as $site) {
            $result = $this->checkUrl($site['url'], $site['id']);
            
            // Track status changes
            if (!$result['accessible']) {
                $dead_count++;
            } elseif ($site['consecutive_failures'] > 0) {
                $restored_count++;
            }
            
            $results[] = [
                'site_id' => $site['id'],
                'site_name' => $site['name'],
                'url' => $site['url'],
                'previous_failures' => $site['consecutive_failures'],
                'result' => $result
            ];
            
            // Respectful delay between checks
            usleep(500000); // 0.5 second delay
        }
        
        // Log batch check summary
        $this->logBatchCheckSummary(count($sites), $dead_count, $restored_count);
        
        return $results;
    }
    
    /**
     * Log batch check summary
     */
    private function logBatchCheckSummary($total_checked, $dead_count, $restored_count) {
        try {
            $summary_query = "INSERT INTO health_check_batches (sites_checked, dead_detected, sites_restored, check_duration) 
                             VALUES (:total, :dead, :restored, NOW())";
            $summary_stmt = $this->db->prepare($summary_query);
            $summary_stmt->bindParam(':total', $total_checked);
            $summary_stmt->bindParam(':dead', $dead_count);
            $summary_stmt->bindParam(':restored', $restored_count);
            $summary_stmt->execute();
        } catch (Exception $e) {
            error_log("Batch summary logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get sites that appear to be dead with enhanced data
     */
    public function getDeadSites() {
        $query = "SELECT s.*, 
                 shc.error_message, 
                 shc.last_checked, 
                 shc.response_time,
                 shc.status_code,
                 shc.check_count,
                 s.consecutive_failures,
                 s.first_failure_at,
                 s.uptime_percentage,
                 u.username as submitted_by_username
                 FROM sites s
                 LEFT JOIN site_health_checks shc ON s.id = shc.site_id
                 LEFT JOIN users u ON s.submitted_by = u.id
                 WHERE s.is_dead = TRUE 
                 AND s.admin_approved_dead = FALSE
                 AND s.is_approved = 1
                 ORDER BY s.consecutive_failures DESC, shc.last_checked DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get health statistics for dashboard
     */
    public function getHealthStatistics() {
        $stats_query = "SELECT 
            (SELECT COUNT(*) FROM sites WHERE is_approved = 1) as total_sites,
            (SELECT COUNT(*) FROM sites WHERE is_dead = TRUE AND admin_approved_dead = FALSE) as dead_sites,
            (SELECT COUNT(*) FROM sites WHERE consecutive_failures > 0 AND is_dead = FALSE) as warning_sites,
            (SELECT AVG(response_time) FROM site_health_checks WHERE last_checked >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as avg_response_time,
            (SELECT AVG(uptime_percentage) FROM sites WHERE is_approved = 1) as avg_uptime,
            (SELECT COUNT(*) FROM site_health_checks WHERE last_checked >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as checks_24h";
        
        $stats_stmt = $this->db->prepare($stats_query);
        $stats_stmt->execute();
        return $stats_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Bulk restore sites
     */
    public function bulkRestoreSites($site_ids, $admin_notes = '') {
        try {
            $this->db->beginTransaction();
            
            $placeholders = str_repeat('?,', count($site_ids) - 1) . '?';
            $update_query = "UPDATE sites SET 
                           admin_approved_dead = FALSE,
                           is_approved = TRUE,
                           is_dead = FALSE,
                           consecutive_failures = 0,
                           status = 'paying'
                           WHERE id IN ({$placeholders})";
            $stmt = $this->db->prepare($update_query);
            $stmt->execute($site_ids);
            
            // Log bulk action
            foreach ($site_ids as $site_id) {
                $log_query = "INSERT INTO admin_actions (admin_id, action, target_type, target_id, notes) 
                             VALUES (:admin_id, 'bulk_restore_site', 'site', :site_id, :notes)";
                $log_stmt = $this->db->prepare($log_query);
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':site_id', $site_id);
                $log_stmt->bindParam(':notes', $admin_notes);
                $log_stmt->execute();
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Bulk restore failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bulk mark sites as dead
     */
    public function bulkMarkAsDead($site_ids, $admin_notes = '') {
        try {
            $this->db->beginTransaction();
            
            $placeholders = str_repeat('?,', count($site_ids) - 1) . '?';
            $update_query = "UPDATE sites SET 
                           admin_approved_dead = TRUE,
                           is_approved = FALSE,
                           status = 'dead'
                           WHERE id IN ({$placeholders})";
            $stmt = $this->db->prepare($update_query);
            $stmt->execute($site_ids);
            
            // Log bulk action
            foreach ($site_ids as $site_id) {
                $log_query = "INSERT INTO admin_actions (admin_id, action, target_type, target_id, notes) 
                             VALUES (:admin_id, 'bulk_mark_dead', 'site', :site_id, :notes)";
                $log_stmt = $this->db->prepare($log_query);
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':site_id', $site_id);
                $log_stmt->bindParam(':notes', $admin_notes);
                $log_stmt->execute();
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Bulk mark dead failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get site health history with pagination
     */
    public function getSiteHealthHistory($site_id, $limit = 20, $offset = 0) {
        $query = "SELECT * FROM site_health_checks 
                 WHERE site_id = :site_id 
                 ORDER BY last_checked DESC 
                 LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':site_id', $site_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Fetch favicon from website
     */
    public function fetchFavicon($url) {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }
        
        $base_url = $parsed['scheme'] . '://' . $parsed['host'];
        $favicon_urls = [
            $base_url . '/favicon.ico',
            $base_url . '/favicon.png',
            $base_url . '/apple-touch-icon.png',
            $base_url . '/assets/favicon.ico',
            $base_url . '/images/favicon.ico'
        ];
        
        foreach ($favicon_urls as $favicon_url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $favicon_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => $this->user_agent,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $favicon_data = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($status_code === 200 && $favicon_data && strlen($favicon_data) > 100) {
                return [
                    'data' => $favicon_data,
                    'url' => $favicon_url,
                    'type' => $this->detectImageType($favicon_data)
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Detect image type from binary data
     */
    private function detectImageType($data) {
        $header = substr($data, 0, 8);
        
        if (substr($header, 0, 3) === "\xFF\xD8\xFF") return 'image/jpeg';
        if (substr($header, 0, 8) === "\x89PNG\r\n\x1a\n") return 'image/png';
        if (substr($header, 0, 6) === "GIF87a" || substr($header, 0, 6) === "GIF89a") return 'image/gif';
        if (substr($header, 0, 4) === "\x00\x00\x01\x00") return 'image/x-icon';
        
        return 'image/unknown';
    }
    
    /**
     * Mark site as admin-approved dead
     */
    public function markSiteAsDead($site_id, $admin_notes = '') {
        try {
            $this->db->beginTransaction();
            
            // Update site status
            $update_site = "UPDATE sites SET 
                           admin_approved_dead = TRUE,
                           is_approved = FALSE,
                           status = 'dead'
                           WHERE id = :site_id";
            $stmt = $this->db->prepare($update_site);
            $stmt->bindParam(':site_id', $site_id);
            $stmt->execute();
            
            // Update health check record
            $update_health = "UPDATE site_health_checks SET 
                             admin_approved_dead = TRUE,
                             admin_notes = :notes
                             WHERE site_id = :site_id
                             ORDER BY last_checked DESC LIMIT 1";
            $stmt = $this->db->prepare($update_health);
            $stmt->bindParam(':site_id', $site_id);
            $stmt->bindParam(':notes', $admin_notes);
            $stmt->execute();
            
            // Log admin action
            $log_query = "INSERT INTO admin_actions (admin_id, action, target_type, target_id, notes) 
                         VALUES (:admin_id, 'mark_site_dead', 'site', :site_id, :notes)";
            $log_stmt = $this->db->prepare($log_query);
            $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':site_id', $site_id);
            $log_stmt->bindParam(':notes', $admin_notes);
            $log_stmt->execute();
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Mark site as dead failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Restore dead site
     */
    public function restoreSite($site_id, $admin_notes = '') {
        try {
            $this->db->beginTransaction();
            
            // Update site status
            $update_site = "UPDATE sites SET 
                           admin_approved_dead = FALSE,
                           is_approved = TRUE,
                           is_dead = FALSE,
                           consecutive_failures = 0,
                           status = 'paying'
                           WHERE id = :site_id";
            $stmt = $this->db->prepare($update_site);
            $stmt->bindParam(':site_id', $site_id);
            $stmt->execute();
            
            // Update health check record
            $update_health = "UPDATE site_health_checks SET 
                             admin_approved_dead = FALSE,
                             admin_notes = :notes,
                             is_accessible = TRUE
                             WHERE site_id = :site_id
                             ORDER BY last_checked DESC LIMIT 1";
            $stmt = $this->db->prepare($update_health);
            $stmt->bindParam(':site_id', $site_id);
            $stmt->bindParam(':notes', $admin_notes);
            $stmt->execute();
            
            // Log admin action
            $log_query = "INSERT INTO admin_actions (admin_id, action, target_type, target_id, notes) 
                         VALUES (:admin_id, 'restore_site', 'site', :site_id, :notes)";
            $log_stmt = $this->db->prepare($log_query);
            $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':site_id', $site_id);
            $log_stmt->bindParam(':notes', $admin_notes);
            $log_stmt->execute();
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Restore site failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
