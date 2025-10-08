<?php
/**
 * Ad Widget - Include this file where you want to display ads
 *
 * Usage:
 * <?php
 * require_once 'includes/ad-widget.php';
 * displayAdSpace('index_top_banner'); // space_id from ad_spaces table
 * ?>
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ad-manager.php';

/**
 * Display ad space with active ad or placeholder
 * @param PDO $db Database connection
 * @param string $space_id The space_id from ad_spaces table
 * @param array $options Display options
 */
function displayAdSpace($db, $space_id, $options = []) {
    static $ad_managers = [];

    // Create ad manager for this connection if not exists
    $conn_hash = spl_object_hash($db);
    if (!isset($ad_managers[$conn_hash])) {
        $ad_managers[$conn_hash] = new AdManager($db);
    }
    $ad_manager = $ad_managers[$conn_hash];

    // Get space details
    $space_query = "SELECT * FROM ad_spaces WHERE space_id = :space_id AND is_enabled = 1";
    $space_stmt = $db->prepare($space_query);
    $space_stmt->bindParam(':space_id', $space_id);
    $space_stmt->execute();
    $space = $space_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$space) {
        return ''; // Space not found or disabled
    }

    // Get ad for this space
    $ad = $ad_manager->getAdForSpace($space_id, $space);

    if ($ad) {
        // Display active ad
        if ($ad['ad_type'] === 'banner') {
            echo $ad_manager->renderBannerAd($ad, true, $space['width'], $space['height']);
        } else {
            echo $ad_manager->renderTextAd($ad, true, $space['width'], $space['height']);
        }
    } else {
        // Display placeholder
        echo renderAdPlaceholder($space, $options);
    }
}

/**
 * Render ad placeholder with call to action
 */
function renderAdPlaceholder($space, $options = []) {
    $show_cta = $options['show_cta'] ?? true;

    $dimensions = '';
    if ($space['width'] && $space['height']) {
        $dimensions = $space['width'] . 'x' . $space['height'];
    }

    $style = 'background: #ffffff; '
        . 'border: 1px solid #e5e7eb; '
        . 'border-radius: 0.5rem; '
        . 'padding: 1.5rem; '
        . 'text-align: center; '
        . 'margin: 1rem 0; '
        . 'display: flex; '
        . 'align-items: center; '
        . 'justify-content: center;';

    if (!empty($space['width'])) {
        $style .= ' max-width: ' . (int) $space['width'] . 'px; width: ' . (int) $space['width'] . 'px;';
    }

    if (!empty($space['height'])) {
        $style .= ' min-height: ' . (int) $space['height'] . 'px; height: ' . (int) $space['height'] . 'px;';
    } else {
        $style .= ' min-height: 150px;';
    }

    $html = '<div class="content-block" data-space-id="' . htmlspecialchars($space['space_id']) . '" style="' . $style . '">';

    $html .= '<div>';
    $html .= '<div style="color: #64748b; margin-bottom: 0.75rem;">';
    $html .= '<i class="fas fa-info-circle" style="font-size: 2rem; opacity: 0.5;"></i>';
    $html .= '</div>';

    if ($show_cta) {
        $html .= '<p style="color: #64748b; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 500;">Space Available</p>';
        if ($dimensions) {
            $html .= '<p style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 0.75rem;">' . htmlspecialchars($dimensions) . '</p>';
        }
        $html .= '<a href="buy-ads.php?space=' . urlencode($space['space_id']) . '" style="';
        $html .= 'display: inline-flex; ';
        $html .= 'align-items: center; ';
        $html .= 'gap: 0.5rem; ';
        $html .= 'padding: 0.5rem 1rem; ';
        $html .= 'background: #e5e7eb; ';
        $html .= 'color: #1e40af; ';
        $html .= 'text-decoration: none; ';
        $html .= 'border-radius: 0.5rem; ';
        $html .= 'font-size: 0.875rem; ';
        $html .= 'font-weight: 600; ';
        $html .= 'transition: all 0.3s ease; ';
        $html .= '">';
        $html .= '<i class="fas fa-bullhorn"></i> Promote Here';
        $html .= '</a>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Legacy function for backward compatibility
 */
function displayAd($ad_type = 'banner', $inline = true) {
    static $ad_manager = null;

    if ($ad_manager === null) {
        $database = new Database();
        $db = $database->getConnection();
        $ad_manager = new AdManager($db);
    }

    // Get random ad
    $ad = $ad_manager->getRandomAd($ad_type);

    if ($ad) {
        if ($ad_type === 'banner') {
            echo $ad_manager->renderBannerAd($ad, true);
        } else {
            echo $ad_manager->renderTextAd($ad, true);
        }
    } else {
        // Show basic placeholder if no ads available
        if ($inline) {
            echo '<div class="content-block" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 1rem; padding: 2rem; text-align: center; color: #64748b;">';
            echo '<p>Space Available</p>';
            echo '<small><a href="buy-ads.php" style="color: #1e40af;">Promote Here</a></small>';
            echo '</div>';
        }
    }
}

/**
 * Display ad container for AJAX loading
 */
function displayAdContainer($ad_type = 'banner', $container_id = 'content-container') {
    echo '<div id="' . htmlspecialchars($container_id) . '" class="content-block"></div>';
    echo '<script>';
    echo 'fetch("ajax/get-ad.php?type=' . htmlspecialchars($ad_type) . '&format=html")';
    echo '.then(response => response.json())';
    echo '.then(data => {';
    echo '  if (data.success && data.html) {';
    echo '    document.getElementById("' . htmlspecialchars($container_id) . '").innerHTML = data.html;';
    echo '  }';
    echo '});';
    echo '</script>';
}
?>
