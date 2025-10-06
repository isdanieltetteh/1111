<?php
/**
 * Automated backlink checking cron job
 * Run this script periodically to check all backlinks
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "Starting backlink check process...\n";

try {
    // Get backlinks that need checking (older than 24 hours or never checked)
    $query = "SELECT bt.*, s.name as site_name 
              FROM backlink_tracking bt 
              JOIN sites s ON bt.site_id = s.id 
              WHERE bt.last_checked IS NULL 
                 OR bt.last_checked < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 OR (bt.status = 'failed' AND bt.last_checked < DATE_SUB(NOW(), INTERVAL 6 HOUR))
              ORDER BY bt.last_checked ASC 
              LIMIT 50"; // Process 50 at a time to avoid overload
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $backlinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($backlinks) . " backlinks to check\n";
    
    foreach ($backlinks as $backlink) {
        echo "Checking backlink for: " . $backlink['site_name'] . "\n";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (compatible; BacklinkChecker/1.0)',
                'follow_location' => true,
                'max_redirects' => 3
            ]
        ]);
        
        $html = @file_get_contents($backlink['backlink_url'], false, $context);
        $status = 'failed';
        $notes = 'Could not fetch page';
        
        if ($html) {
            $site_domain = parse_url(SITE_URL, PHP_URL_HOST);
            $site_url_variations = [
                SITE_URL,
                rtrim(SITE_URL, '/'),
                'http://' . $site_domain,
                'https://' . $site_domain,
                'www.' . $site_domain,
                $site_domain
            ];
            
            $found = false;
            foreach ($site_url_variations as $variation) {
                if (stripos($html, $variation) !== false) {
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                $status = 'verified';
                $notes = 'Backlink found and verified';
            } else {
                $status = 'failed';
                $notes = 'Backlink not found on page';
            }
        }
        
        // Update backlink status
        $update_query = "UPDATE backlink_tracking SET 
                       status = :status, 
                       last_checked = NOW(), 
                       check_count = check_count + 1,
                       failure_count = CASE WHEN :status = 'failed' THEN failure_count + 1 ELSE failure_count END,
                       first_verified = COALESCE(first_verified, CASE WHEN :status = 'verified' THEN NOW() ELSE NULL END),
                       last_verified = CASE WHEN :status = 'verified' THEN NOW() ELSE last_verified END,
                       notes = :notes
                       WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $status);
        $update_stmt->bindParam(':notes', $notes);
        $update_stmt->bindParam(':id', $backlink['id']);
        $update_stmt->execute();
        
        echo "  Status: " . $status . " - " . $notes . "\n";
        
        // Small delay to be respectful to servers
        sleep(2);
    }
    
    echo "Backlink check process completed\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
