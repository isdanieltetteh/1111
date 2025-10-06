<?php
/**
 * Ad Space Management Functions
 * Handles display and management of ad spaces across the platform
 */


/**
 * Get active ad for a specific space
 * Uses rotation algorithm to fairly distribute impressions
 */
function getActiveAdForSpace($db, $space_id) {
    $ad_query = "SELECT ua.*
                 FROM user_advertisements ua
                 WHERE ua.status = 'active'
                 AND ua.start_date <= NOW()
                 AND ua.end_date >= NOW()
                 AND ua.target_space_id = :space_id
                 ORDER BY 
                    CASE ua.visibility_level
                      WHEN 'premium' THEN 1
                      ELSE 2
                    END,
                    RAND()
                 LIMIT 1";
    
    $ad_stmt = $db->prepare($ad_query);
    $ad_stmt->bindParam(':space_id', $space_id);
    $ad_stmt->execute();
    $ad = $ad_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ad) {
        // Increment impression count
        $impression_query = "UPDATE user_advertisements 
                            SET impression_count = impression_count + 1 
                            WHERE id = :ad_id";
        $impression_stmt = $db->prepare($impression_query);
        $impression_stmt->bindParam(':ad_id', $ad['id']);
        $impression_stmt->execute();
        
        // Log impression
        $log_query = "INSERT INTO ad_impressions (ad_id, user_agent, ip_address)
                     VALUES (:ad_id, :user_agent, :ip_address)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':ad_id', $ad['id']);
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_stmt->bindParam(':user_agent', $user_agent);
        $log_stmt->bindParam(':ip_address', $ip_address);
        $log_stmt->execute();
    }
    
    return $ad;
}

/**
 * Render an active ad
 */
function renderAd($ad, $space, $options = []) {
    $container_class = $options['container_class'] ?? 'ad-space-container';
    $show_label = $options['show_label'] ?? true;
    
    $html = '<div class="' . htmlspecialchars($container_class) . '" data-ad-id="' . $ad['id'] . '" data-space-id="' . htmlspecialchars($space['space_id']) . '">';
    
    if ($show_label) {
        $html .= '<div class="ad-label">Advertisement</div>';
    }
    
    if ($ad['ad_type'] === 'banner') {
        $html .= '<a href="ad-click.php?id=' . $ad['id'] . '" target="_blank" rel="nofollow sponsored" class="ad-link">';
        $html .= '<img src="' . htmlspecialchars($ad['banner_image']) . '" ';
        $html .= 'alt="' . htmlspecialchars($ad['banner_alt_text']) . '" ';
        $html .= 'class="ad-banner-image" ';
        if ($space['width'] && $space['height']) {
            $html .= 'style="max-width: ' . $space['width'] . 'px; max-height: ' . $space['height'] . 'px;" ';
        }
        $html .= '/>';
        $html .= '</a>';
    } else {
        // Text ad
        $html .= '<div class="ad-text-content">';
        $html .= '<a href="ad-click.php?id=' . $ad['id'] . '" target="_blank" rel="nofollow sponsored" class="ad-text-link">';
        $html .= '<h4 class="ad-text-title">' . htmlspecialchars($ad['text_title']) . '</h4>';
        $html .= '<p class="ad-text-description">' . htmlspecialchars($ad['text_description']) . '</p>';
        $html .= '</a>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render ad space placeholder when no ad is active
 */
function renderAdPlaceholder($space, $options = []) {
    $container_class = $options['container_class'] ?? 'ad-space-container';
    $show_cta = $options['show_cta'] ?? true;
    
    $dimensions = '';
    if ($space['width'] && $space['height']) {
        $dimensions = $space['width'] . 'x' . $space['height'];
    }
    
    $html = '<div class="' . htmlspecialchars($container_class) . ' ad-placeholder" data-space-id="' . htmlspecialchars($space['space_id']) . '" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.1)); border: 2px dashed rgba(59, 130, 246, 0.3); border-radius: 0.75rem; padding: 2rem; text-align: center; min-height: 150px; display: flex; align-items: center; justify-content: center;">';
    $html .= '<div class="ad-placeholder-content">';
    $html .= '<i class="fas fa-bullhorn" style="font-size: 2.5rem; color: #3b82f6; margin-bottom: 1rem;"></i>';
    $html .= '<h4 style="font-size: 1.25rem; font-weight: 700; color: #f1f5f9; margin-bottom: 0.5rem;">' . htmlspecialchars($space['space_name']) . '</h4>';
    
    if ($dimensions) {
        $html .= '<p class="ad-dimensions" style="color: #94a3b8; font-size: 0.875rem; margin-bottom: 0.75rem;">' . $dimensions . '</p>';
    }
    
    if ($show_cta) {
        $html .= '<p class="ad-placeholder-text" style="color: #cbd5e1; margin-bottom: 1rem; font-size: 0.875rem;">Advertise Here - Reach Thousands of Visitors!</p>';
        $html .= '<a href="buy-ads.php?space=' . urlencode($space['space_id']) . '" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; text-decoration: none; border-radius: 0.5rem; font-weight: 600; transition: all 0.3s ease;">';
        $html .= '<i class="fas fa-shopping-cart"></i> Purchase This Space';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Get all ad spaces for a specific page
 */
function getAdSpacesForPage($db, $page_location) {
    $query = "SELECT * FROM ad_spaces 
              WHERE page_location = :page_location 
              AND is_enabled = 1 
              ORDER BY display_order ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':page_location', $page_location);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get available ad spaces for purchase
 */
function getAvailableAdSpaces($db) {
    $query = "SELECT * FROM ad_spaces 
              WHERE is_enabled = 1 
              ORDER BY page_location ASC, display_order ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate price for a specific ad space
 */
function calculateAdSpacePrice($base_price, $space_multiplier, $duration_days, $visibility_level, $premium_multiplier) {
    $price = $base_price * $space_multiplier;
    
    if ($visibility_level === 'premium') {
        $price *= $premium_multiplier;
    }
    
    return $price;
}

/**
 * Check if ad space is available
 */
function isAdSpaceAvailable($db, $space_id) {
    $query = "SELECT COUNT(*) as active_count
              FROM user_advertisements
              WHERE target_space_id = :space_id
              AND status = 'active'
              AND start_date <= NOW()
              AND end_date >= NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':space_id', $space_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get max ads for this space
    $space_query = "SELECT max_ads_rotation FROM ad_spaces WHERE space_id = :space_id";
    $space_stmt = $db->prepare($space_query);
    $space_stmt->bindParam(':space_id', $space_id);
    $space_stmt->execute();
    $space = $space_stmt->fetch(PDO::FETCH_ASSOC);
    
    $max_ads = $space['max_ads_rotation'] ?? 5;
    
    return $result['active_count'] < $max_ads;
}
