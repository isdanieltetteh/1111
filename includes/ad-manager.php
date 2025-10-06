<?php
class AdManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get a random active ad for a specific space
     */
    public function getRandomAd($ad_type = 'banner', $prefer_premium = true, $space_id = null) {
        try {
            // Build query based on preferences
            $query = "SELECT ua.* FROM user_advertisements ua
                     LEFT JOIN ad_spaces ads ON ua.ad_space_id = ads.id
                     WHERE ua.status = 'active'
                     AND ua.ad_type = :ad_type
                     AND ua.start_date <= NOW()
                     AND ua.end_date >= NOW()";

            if ($space_id) {
                $query .= " AND ua.ad_space_id = :space_id";
            }

            if ($prefer_premium) {
                $query .= " ORDER BY
                           CASE ua.visibility_level
                             WHEN 'premium' THEN 1
                             ELSE 2
                           END,
                           RAND()
                           LIMIT 1";
            } else {
                $query .= " ORDER BY RAND() LIMIT 1";
            }

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ad_type', $ad_type);
            if ($space_id) {
                $stmt->bindParam(':space_id', $space_id);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get ad for specific space by space_id string
     */
    public function getAdForSpace($space_id) {
        try {
            $query = "SELECT ua.* FROM user_advertisements ua
                     WHERE ua.status = 'active'
                     AND ua.target_space_id = :space_id
                     AND (
                       (ua.start_date IS NULL OR ua.start_date <= NOW())
                       AND
                       (ua.end_date IS NULL OR ua.end_date >= NOW())
                     )
                     ORDER BY
                       CASE ua.visibility_level
                         WHEN 'premium' THEN 1
                         ELSE 2
                       END,
                       RAND()
                     LIMIT 1";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':space_id', $space_id);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get multiple ads for display
     */
    public function getAds($ad_type = 'banner', $limit = 3) {
        try {
            $query = "SELECT * FROM user_advertisements 
                     WHERE status = 'active' 
                     AND ad_type = :ad_type
                     AND end_date > NOW()
                     ORDER BY 
                       CASE visibility_level 
                         WHEN 'premium' THEN 1 
                         ELSE 2 
                       END,
                       RAND()
                     LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ad_type', $ad_type);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Track ad impression
     */
    public function trackImpression($ad_id, $user_id = null, $page_url = null) {
        try {
            // Update impression count
            $update_query = "UPDATE user_advertisements 
                           SET impression_count = impression_count + 1 
                           WHERE id = :ad_id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':ad_id', $ad_id);
            $update_stmt->execute();
            
            // Log impression
            $log_query = "INSERT INTO ad_impressions 
                         (ad_id, user_id, ip_address, user_agent, page_url) 
                         VALUES (:ad_id, :user_id, :ip_address, :user_agent, :page_url)";
            $log_stmt = $this->db->prepare($log_query);
            $log_stmt->bindParam(':ad_id', $ad_id);
            $log_stmt->bindParam(':user_id', $user_id);
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $log_stmt->bindParam(':ip_address', $ip_address);
            $log_stmt->bindParam(':user_agent', $user_agent);
            $log_stmt->bindParam(':page_url', $page_url);
            $log_stmt->execute();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Track ad click
     */
    public function trackClick($ad_id, $user_id = null, $referrer_url = null) {
        try {
            // Update click count
            $update_query = "UPDATE user_advertisements 
                           SET click_count = click_count + 1 
                           WHERE id = :ad_id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':ad_id', $ad_id);
            $update_stmt->execute();
            
            // Log click
            $log_query = "INSERT INTO ad_clicks 
                         (ad_id, user_id, ip_address, user_agent, referrer_url) 
                         VALUES (:ad_id, :user_id, :ip_address, :user_agent, :referrer_url)";
            $log_stmt = $this->db->prepare($log_query);
            $log_stmt->bindParam(':ad_id', $ad_id);
            $log_stmt->bindParam(':user_id', $user_id);
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $log_stmt->bindParam(':ip_address', $ip_address);
            $log_stmt->bindParam(':user_agent', $user_agent);
            $log_stmt->bindParam(':referrer_url', $referrer_url);
            $log_stmt->execute();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Render banner ad HTML
     */
    public function renderBannerAd($ad, $track_impression = true, $space_width = null, $space_height = null) {
        if (!$ad) {
            return '';
        }
        
        if ($track_impression) {
            $user_id = $_SESSION['user_id'] ?? null;
            $page_url = $_SERVER['REQUEST_URI'] ?? null;
            $this->trackImpression($ad['id'], $user_id, $page_url);
        }
        
        $randomClass = 'content-' . uniqid();
        $html = '<div class="' . $randomClass . '" data-ad-id="' . $ad['id'] . '">';
        $html .= '<a href="ad-click.php?id=' . $ad['id'] . '" target="_blank" rel="noopener">';
        
        if ($ad['banner_image']) {
            $html .= '<img src="' . htmlspecialchars($ad['banner_image']) . '" ';
            $html .= 'alt="' . htmlspecialchars($ad['banner_alt_text']) . '" ';
            $html .= 'style="';
            $html .= 'max-width: 100%; ';
            if ($space_width) $html .= 'max-width: ' . $space_width . 'px; ';
            if ($space_height) $html .= 'max-height: ' . $space_height . 'px; ';
            $html .= 'width: auto; height: auto; border-radius: 0.5rem;"';
        }
        
        $html .= '</a>';
        $html .= '<small style="display: block; text-align: center; color: #64748b; font-size: 0.75rem; margin-top: 0.5rem;">Featured</small>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render text ad HTML
     */
    public function renderTextAd($ad, $track_impression = true, $space_width = null, $space_height = null) {
        if (!$ad) {
            return '';
        }
        
        if ($track_impression) {
            $user_id = $_SESSION['user_id'] ?? null;
            $page_url = $_SERVER['REQUEST_URI'] ?? null;
            $this->trackImpression($ad['id'], $user_id, $page_url);
        }
        
        $randomClass = 'content-' . uniqid();
        $html = '<div class="' . $randomClass . '" data-ad-id="' . $ad['id'] . '" style="';
        $html .= 'background: rgba(59, 130, 246, 0.1); ';
        $html .= 'border: 1px solid rgba(59, 130, 246, 0.2); ';
        $html .= 'border-radius: 0.75rem; ';
        $html .= 'padding: 1.5rem; ';
        $html .= 'margin: 1rem 0;';
        
        if ($space_width) {
            $html .= 'max-width: ' . $space_width . 'px; ';
        }
        if ($space_height) {
            $html .= 'min-height: ' . max($space_height, 100) . 'px; ';
        }
        
        $html .= '">';
        
        $html .= '<a href="ad-click.php?id=' . $ad['id'] . '" target="_blank" style="text-decoration: none; color: inherit;">';
        $html .= '<h4 style="color: #3b82f6; font-weight: 700; margin-bottom: 0.5rem;">';
        $html .= htmlspecialchars($ad['text_title']);
        $html .= '</h4>';
        $html .= '<p style="color: #94a3b8; font-size: 0.875rem; margin-bottom: 0;">';
        $html .= htmlspecialchars($ad['text_description']);
        $html .= '</p>';
        $html .= '</a>';
        
        $html .= '<small style="display: block; color: #64748b; font-size: 0.75rem; margin-top: 0.75rem;">Featured</small>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Check and expire old ads
     */
    public function expireOldAds() {
        try {
            $query = "UPDATE user_advertisements 
                     SET status = 'expired' 
                     WHERE status = 'active' 
                     AND end_date <= NOW()";
            $stmt = $this->db->prepare($query);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
