<?php
require_once 'config/config.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get parameters
$site_id = intval($_GET['site'] ?? 0);
$type = $_GET['type'] ?? 'card'; // card, badge, rating, status, trust, minimal, banner, compact, modern
$theme = $_GET['theme'] ?? 'dark';
$size = $_GET['size'] ?? 'medium'; // small, medium, large
$color = $_GET['color'] ?? 'blue'; // blue, green, purple, orange, red
$button_text = $_GET['button_text'] ?? 'Rate & Review';
$show_logo = $_GET['show_logo'] ?? '1';
$show_rating = $_GET['show_rating'] ?? '1';
$show_trust = $_GET['show_trust'] ?? '1';
$show_reviews = $_GET['show_reviews'] ?? '1';
$border_radius = $_GET['border_radius'] ?? 'medium'; // small, medium, large, none
$animation = $_GET['animation'] ?? 'hover'; // none, hover, pulse, glow

if (!$site_id) {
    http_response_code(400);
    echo 'Invalid site ID';
    exit();
}

// Get site details
$query = "SELECT s.*, 
          COALESCE(AVG(r.rating), 0) as average_rating,
          COUNT(r.id) as review_count
          FROM sites s 
          LEFT JOIN reviews r ON s.id = r.site_id 
          WHERE s.id = :id AND s.is_approved = 1
          GROUP BY s.id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $site_id);
$stmt->execute();
$site = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    http_response_code(404);
    echo 'Site not found';
    exit();
}

// Calculate trust score
function calculateTrustScore($rating, $reviewCount, $upvotes, $downvotes) {
    $ratingScore = ($rating / 5) * 20;
    $reviewFactor = min($reviewCount / 10, 1) * 30;
    $totalVotes = $upvotes + $downvotes;
    $voteRatio = $totalVotes > 0 ? ($upvotes / $totalVotes) : 0.5;
    $voteScore = $voteRatio * 50;
    $trustScore = $ratingScore + $reviewFactor + $voteScore;
    return max(0, min(100, round($trustScore)));
}

$trust_score = calculateTrustScore($site['average_rating'], $site['review_count'], $site['total_upvotes'], $site['total_downvotes']);

function renderStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $class = $i <= $rating ? 'filled' : '';
        $html .= '<span class="star ' . $class . '">★</span>';
    }
    return $html;
}

function getStatusColor($status) {
    return match($status) {
        'paying' => '#10b981',
        'not_paying' => '#f59e0b',
        'scam' => '#ef4444',
        default => '#6b7280'
    };
}

function getStatusText($status) {
    return match($status) {
        'paying' => '✓ Paying',
        'not_paying' => '⚠ Not Paying',
        'scam' => '✗ Scam',
        default => 'Unknown'
    };
}

// Color schemes
$color_schemes = [
    'blue' => ['primary' => '#3b82f6', 'secondary' => '#1d4ed8', 'accent' => '#60a5fa'],
    'green' => ['primary' => '#10b981', 'secondary' => '#059669', 'accent' => '#34d399'],
    'purple' => ['primary' => '#8b5cf6', 'secondary' => '#7c3aed', 'accent' => '#a78bfa'],
    'orange' => ['primary' => '#f59e0b', 'secondary' => '#d97706', 'accent' => '#fbbf24'],
    'red' => ['primary' => '#ef4444', 'secondary' => '#dc2626', 'accent' => '#f87171']
];

$current_colors = $color_schemes[$color] ?? $color_schemes['blue'];

// Size configurations
$size_configs = [
    'small' => ['width' => '200px', 'padding' => '12px', 'font_size' => '0.8rem', 'logo_size' => '32px'],
    'medium' => ['width' => '300px', 'padding' => '16px', 'font_size' => '0.9rem', 'logo_size' => '48px'],
    'large' => ['width' => '400px', 'padding' => '20px', 'font_size' => '1rem', 'logo_size' => '64px']
];

$current_size = $size_configs[$size] ?? $size_configs['medium'];

// Border radius configurations
$border_configs = [
    'none' => '0px',
    'small' => '6px',
    'medium' => '12px',
    'large' => '20px'
];

$current_border = $border_configs[$border_radius] ?? $border_configs['medium'];

// Set content type
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: ALLOWALL');
header('Content-Security-Policy: frame-ancestors *');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site['name']); ?> Widget</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: transparent;
            padding: 0;
            margin: 0;
            overflow: hidden;
            font-size: <?php echo $current_size['font_size']; ?>;
        }
        
        .widget-container {
            width: 100%;
            max-width: <?php echo $current_size['width']; ?>;
            margin: 0 auto;
            position: relative;
        }
        
        /* Base Widget Styles */
        .widget {
            background: <?php echo $theme === 'dark' ? 'linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(51, 65, 85, 0.9))' : 'linear-gradient(135deg, #ffffff, #f8fafc)'; ?>;
            border-radius: <?php echo $current_border; ?>;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, <?php echo $theme === 'dark' ? '0.4' : '0.15'; ?>);
            border: 1px solid <?php echo $theme === 'dark' ? 'rgba(148, 163, 184, 0.2)' : '#e2e8f0'; ?>;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            backdrop-filter: blur(20px);
        }
        
        <?php if ($animation === 'hover'): ?>
        .widget:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, <?php echo $theme === 'dark' ? '0.5' : '0.2'; ?>);
        }
        <?php elseif ($animation === 'pulse'): ?>
        .widget {
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        <?php elseif ($animation === 'glow'): ?>
        .widget {
            animation: glow 3s ease-in-out infinite;
        }
        @keyframes glow {
            0%, 100% { box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4); }
            50% { box-shadow: 0 10px 30px <?php echo $current_colors['primary']; ?>40; }
        }
        <?php endif; ?>
        
        .widget::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, <?php echo $current_colors['primary']; ?>, <?php echo $current_colors['secondary']; ?>, <?php echo $current_colors['accent']; ?>);
            border-radius: <?php echo $current_border; ?> <?php echo $current_border; ?> 0 0;
        }
        
        .site-logo {
            border-radius: <?php echo intval($current_border) / 2; ?>px;
            object-fit: cover;
            border: 2px solid <?php echo $theme === 'dark' ? 'rgba(148, 163, 184, 0.3)' : '#e2e8f0'; ?>;
            transition: all 0.3s ease;
            width: <?php echo $current_size['logo_size']; ?>;
            height: <?php echo $current_size['logo_size']; ?>;
        }
        
        .site-logo:hover {
            transform: scale(1.1) rotate(5deg);
            border-color: <?php echo $current_colors['primary']; ?>;
        }
        
        .site-name {
            font-weight: 700;
            color: <?php echo $theme === 'dark' ? '#f1f5f9' : '#1f2937'; ?>;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 1.1em;
            letter-spacing: -0.025em;
        }
        
        .site-category {
            display: inline-block;
            padding: 4px 12px;
            background: <?php echo $current_colors['primary']; ?>20;
            color: <?php echo $current_colors['primary']; ?>;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .star {
            color: <?php echo $theme === 'dark' ? '#374151' : '#d1d5db'; ?>;
            transition: all 0.2s ease;
            font-size: 1em;
        }
        
        .star.filled {
            color: #fbbf24;
            text-shadow: 0 0 10px rgba(251, 191, 36, 0.5);
        }
        
        .rating-value {
            font-weight: 700;
            color: <?php echo $theme === 'dark' ? '#f1f5f9' : '#1f2937'; ?>;
            font-size: 1.2em;
        }
        
        .rating-count {
            font-size: 0.8em;
            color: <?php echo $theme === 'dark' ? '#94a3b8' : '#6b7280'; ?>;
            font-weight: 500;
        }
        
        .trust-bar {
            height: 8px;
            background: <?php echo $theme === 'dark' ? 'rgba(148, 163, 184, 0.2)' : '#e2e8f0'; ?>;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .trust-progress {
            height: 100%;
            background: linear-gradient(90deg, <?php echo $trust_score > 70 ? '#10b981' : ($trust_score > 40 ? '#f59e0b' : '#ef4444'); ?>, <?php echo $trust_score > 70 ? '#059669' : ($trust_score > 40 ? '#d97706' : '#dc2626'); ?>);
            border-radius: 4px;
            width: <?php echo $trust_score; ?>%;
            transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .trust-progress::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.8em;
            font-weight: 600;
            background: <?php echo getStatusColor($site['status']); ?>20;
            color: <?php echo getStatusColor($site['status']); ?>;
            border: 1px solid <?php echo getStatusColor($site['status']); ?>40;
        }
        
        .visit-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 16px;
            background: linear-gradient(135deg, <?php echo $current_colors['primary']; ?>, <?php echo $current_colors['secondary']; ?>);
            color: white;
            text-decoration: none;
            border-radius: <?php echo intval($current_border) / 2; ?>px;
            font-weight: 600;
            font-size: 0.9em;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            overflow: hidden;
        }
        
        .visit-btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .visit-btn:hover::before {
            left: 100%;
        }
        
        .visit-btn:hover {
            background: linear-gradient(135deg, <?php echo $current_colors['secondary']; ?>, <?php echo $current_colors['primary']; ?>);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px <?php echo $current_colors['primary']; ?>40;
        }
        
        .powered-by {
            text-align: center;
            padding: 12px 16px;
            font-size: 0.7em;
            color: <?php echo $theme === 'dark' ? '#6b7280' : '#9ca3af'; ?>;
            border-top: 1px solid <?php echo $theme === 'dark' ? 'rgba(148, 163, 184, 0.1)' : '#e2e8f0'; ?>;
            background: <?php echo $theme === 'dark' ? 'rgba(15, 23, 42, 0.5)' : 'rgba(248, 250, 252, 0.8)'; ?>;
        }
        
        .powered-by a {
            color: <?php echo $current_colors['primary']; ?>;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        
        .powered-by a:hover {
            color: <?php echo $current_colors['secondary']; ?>;
        }
        
        /* Modern Card Widget */
        .widget-modern {
            background: <?php echo $theme === 'dark' ? 'linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(51, 65, 85, 0.9))' : 'linear-gradient(135deg, #ffffff, #f8fafc)'; ?>;
            backdrop-filter: blur(20px);
            border: 1px solid <?php echo $theme === 'dark' ? 'rgba(148, 163, 184, 0.2)' : 'rgba(0, 0, 0, 0.1)'; ?>;
        }
        
        .widget-modern .header {
            background: linear-gradient(135deg, <?php echo $current_colors['primary']; ?>15, <?php echo $current_colors['accent']; ?>10);
            padding: <?php echo $current_size['padding']; ?>;
            position: relative;
        }
        
        .widget-modern .body {
            padding: <?php echo $current_size['padding']; ?>;
        }
        
        .widget-modern .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 16px 0;
        }
        
        .widget-modern .stat-item {
            text-align: center;
            padding: 12px 8px;
            background: <?php echo $theme === 'dark' ? 'rgba(51, 65, 85, 0.5)' : 'rgba(248, 250, 252, 0.8)'; ?>;
            border-radius: <?php echo intval($current_border) / 2; ?>px;
            transition: all 0.3s ease;
            border: 1px solid <?php echo $theme === 'dark' ? 'rgba(148, 163, 184, 0.1)' : 'rgba(0, 0, 0, 0.05)'; ?>;
        }
        
        .widget-modern .stat-item:hover {
            background: <?php echo $current_colors['primary']; ?>10;
            border-color: <?php echo $current_colors['primary']; ?>30;
            transform: translateY(-2px);
        }
        
        .widget-modern .stat-value {
            font-size: 1.1em;
            font-weight: 700;
            color: <?php echo $current_colors['primary']; ?>;
            margin-bottom: 4px;
        }
        
        .widget-modern .stat-label {
            font-size: 0.7em;
            color: <?php echo $theme === 'dark' ? '#94a3b8' : '#6b7280'; ?>;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Banner Widget */
        .widget-banner {
            max-width: 600px;
            height: 120px;
            display: flex;
            align-items: center;
            padding: <?php echo $current_size['padding']; ?>;
            gap: 16px;
        }
        
        .widget-banner .content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .widget-banner .actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .widget-banner .visit-btn {
            width: auto;
            padding: 8px 16px;
            font-size: 0.8em;
        }
        
        /* Compact Widget */
        .widget-compact {
            max-width: 250px;
            padding: 12px;
            text-align: center;
        }
        
        .widget-compact .site-logo {
            width: 40px;
            height: 40px;
            margin: 0 auto 8px;
        }
        
        .widget-compact .site-name {
            font-size: 0.9em;
            margin-bottom: 6px;
        }
        
        .widget-compact .rating-section {
            margin-bottom: 8px;
        }
        
        .widget-compact .visit-btn {
            padding: 8px 12px;
            font-size: 0.75em;
        }
        
        /* Badge Widget */
        .widget-badge {
            max-width: 180px;
            padding: 16px;
            text-align: center;
            background: <?php echo $theme === 'dark' ? 'linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(51, 65, 85, 0.9))' : 'linear-gradient(135deg, #ffffff, #f8fafc)'; ?>;
        }
        
        .widget-badge .site-logo {
            width: 56px;
            height: 56px;
            margin: 0 auto 12px;
        }
        
        .widget-badge .site-name {
            font-size: 1em;
            margin-bottom: 8px;
        }
        
        .widget-badge .status-badge {
            width: 100%;
            justify-content: center;
            margin-bottom: 12px;
        }
        
        /* Trust Widget */
        .widget-trust {
            max-width: 200px;
            padding: 16px;
            text-align: center;
        }
        
        .widget-trust .trust-score-display {
            font-size: 2.5em;
            font-weight: 800;
            color: <?php echo $trust_score > 70 ? '#10b981' : ($trust_score > 40 ? '#f59e0b' : '#ef4444'); ?>;
            margin: 12px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .widget-trust .trust-label {
            font-size: 0.8em;
            color: <?php echo $theme === 'dark' ? '#94a3b8' : '#6b7280'; ?>;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        /* Minimal Widget */
        .widget-minimal {
            max-width: 160px;
            padding: 12px;
            text-align: center;
            background: <?php echo $theme === 'dark' ? 'rgba(30, 41, 59, 0.9)' : 'rgba(255, 255, 255, 0.95)'; ?>;
        }
        
        .widget-minimal .site-logo {
            width: 32px;
            height: 32px;
            margin: 0 auto 8px;
        }
        
        .widget-minimal .site-name {
            font-size: 0.8em;
            margin-bottom: 6px;
        }
        
        .widget-minimal .star {
            font-size: 0.8em;
        }
        
        .widget-minimal .visit-btn {
            padding: 6px 12px;
            font-size: 0.7em;
        }
        
        /* Responsive Design */
        @media (max-width: 480px) {
            .widget-container {
                max-width: 100%;
            }
            
            .widget-banner {
                flex-direction: column;
                height: auto;
                text-align: center;
            }
            
            .widget-banner .actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="widget-container">
        <?php if ($type === 'modern'): ?>
            <!-- Modern Card Widget -->
            <div class="widget widget-modern">
                <div class="header">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <?php if ($show_logo): ?>
                            <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($site['name']); ?>" 
                                 class="site-logo">
                        <?php endif; ?>
                        <div style="flex: 1;">
                            <div class="site-name"><?php echo htmlspecialchars($site['name']); ?></div>
                            <div class="site-category"><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="body">
                    <?php if ($show_rating): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?php echo renderStars(round($site['average_rating'])); ?>
                            </div>
                            <div>
                                <div class="rating-value"><?php echo number_format($site['average_rating'], 1); ?></div>
                                <?php if ($show_reviews): ?>
                                    <div class="rating-count"><?php echo $site['review_count']; ?> reviews</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($show_trust): ?>
                        <div style="margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.8em; color: <?php echo $theme === 'dark' ? '#94a3b8' : '#6b7280'; ?>;">
                                <span>Trust Score</span>
                                <span><?php echo $trust_score; ?>%</span>
                            </div>
                            <div class="trust-bar">
                                <div class="trust-progress"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $site['total_upvotes']; ?></div>
                            <div class="stat-label">Upvotes</div>
                        </div>
                        <?php if ($show_reviews): ?>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $site['review_count']; ?></div>
                                <div class="stat-label">Reviews</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="status-badge" style="margin-bottom: 16px;">
                        <?php echo getStatusText($site['status']); ?>
                    </div>
                    
                    <a href="<?php echo "https://{$_SERVER['HTTP_HOST']}/review.php?id={$site_id}"; ?>" 
                       class="visit-btn" 
                       target="_blank" 
                       rel="nofollow">
                        <i class="fas fa-star"></i> <?php echo htmlspecialchars($button_text); ?>
                    </a>
                </div>
                
                <div class="powered-by">
                    Powered by <a href="<?php echo SITE_URL; ?>" target="_blank"><?php echo SITE_NAME; ?></a>
                </div>
            </div>
            
        <?php elseif ($type === 'banner'): ?>
            <!-- Banner Widget -->
            <div class="widget widget-banner">
                <?php if ($show_logo): ?>
                    <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                         alt="<?php echo htmlspecialchars($site['name']); ?>" 
                         class="site-logo">
                <?php endif; ?>
                
                <div class="content">
                    <div class="site-name"><?php echo htmlspecialchars($site['name']); ?></div>
                    <?php if ($show_rating): ?>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <?php echo renderStars(round($site['average_rating'])); ?>
                            <span class="rating-value"><?php echo number_format($site['average_rating'], 1); ?></span>
                            <?php if ($show_reviews): ?>
                                <span class="rating-count">(<?php echo $site['review_count']; ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="status-badge">
                        <?php echo getStatusText($site['status']); ?>
                    </div>
                </div>
                
                <div class="actions">
                    <a href="<?php echo "https://{$_SERVER['HTTP_HOST']}/review.php?id={$site_id}"; ?>" 
                       class="visit-btn" 
                       target="_blank" 
                       rel="nofollow">
                        <i class="fas fa-star"></i> <?php echo htmlspecialchars($button_text); ?>
                    </a>
                </div>
            </div>
            
        <?php elseif ($type === 'compact'): ?>
            <!-- Compact Widget -->
            <div class="widget widget-compact">
                <?php if ($show_logo): ?>
                    <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                         alt="<?php echo htmlspecialchars($site['name']); ?>" 
                         class="site-logo">
                <?php endif; ?>
                
                <div class="site-name"><?php echo htmlspecialchars($site['name']); ?></div>
                
                <?php if ($show_rating): ?>
                    <div class="rating-section">
                        <?php echo renderStars(round($site['average_rating'])); ?>
                        <div class="rating-value"><?php echo number_format($site['average_rating'], 1); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="status-badge" style="margin-bottom: 12px;">
                    <?php echo getStatusText($site['status']); ?>
                </div>
                
                <a href="<?php echo "https://{$_SERVER['HTTP_HOST']}/review.php?id={$site_id}"; ?>" 
                   class="visit-btn" 
                   target="_blank" 
                   rel="nofollow">
                    <?php echo htmlspecialchars($button_text); ?>
                </a>
                
                <div class="powered-by">
                    <a href="<?php echo SITE_URL; ?>" target="_blank"><?php echo SITE_NAME; ?></a>
                </div>
            </div>
            
        <?php elseif ($type === 'badge'): ?>
            <!-- Badge Widget -->
            <div class="widget widget-badge">
                <?php if ($show_logo): ?>
                    <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                         alt="<?php echo htmlspecialchars($site['name']); ?>" 
                         class="site-logo">
                <?php endif; ?>
                
                <div class="site-name"><?php echo htmlspecialchars($site['name']); ?></div>
                
                <div class="status-badge">
                    <?php echo getStatusText($site['status']); ?>
                </div>
                
                <a href="<?php echo "https://{$_SERVER['HTTP_HOST']}/review.php?id={$site_id}"; ?>" 
                   class="visit-btn" 
                   target="_blank" 
                   rel="nofollow">
                    <?php echo htmlspecialchars($button_text); ?>
                </a>
                
                <div class="powered-by">
                    <a href="<?php echo SITE_URL; ?>" target="_blank"><?php echo SITE_NAME; ?></a>
                </div>
            </div>
            
        <?php elseif ($type === 'trust'): ?>
            <!-- Trust Widget -->
            <div class="widget widget-trust">
                <?php if ($show_logo): ?>
                    <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                         alt="<?php echo htmlspecialchars($site['name']); ?>" 
                         class="site-logo" style="margin-bottom: 8px;">
                <?php endif; ?>
                
                <div class="site-name"><?php echo htmlspecialchars($site['name']); ?></div>
                
                <div class="trust-score-display"><?php echo $trust_score; ?>%</div>
                <div class="trust-label">Trust Score</div>
                
                <div class="trust-bar" style="margin: 12px 0;">
                    <div class="trust-progress"></div>
                </div>
                
                <a href="<?php echo "https://{$_SERVER['HTTP_HOST']}/review.php?id={$site_id}"; ?>" 
                   class="visit-btn" 
                   target="_blank" 
                   rel="nofollow">
                    <?php echo htmlspecialchars($button_text); ?>
                </a>
                
                <div class="powered-by">
                    <a href="<?php echo SITE_URL; ?>" target="_blank"><?php echo SITE_NAME; ?></a>
                </div>
            </div>
            
        <?php elseif ($type === 'minimal'): ?>
            <!-- Minimal Widget -->
            <div class="widget widget-minimal">
                <?php if ($show_logo): ?>
                    <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                         alt="<?php echo htmlspecialchars($site['name']); ?>" 
                         class="site-logo">
                <?php endif; ?>
                
                <div class="site-name"><?php echo htmlspecialchars($site['name']); ?></div>
                
                <?php if ($show_rating): ?>
                    <div style="margin-bottom: 8px;">
                        <?php echo renderStars(round($site['average_rating'])); ?>
                        <div class="rating-value" style="font-size: 0.8em;"><?php echo number_format($site['average_rating'], 1); ?></div>
                    </div>
                <?php endif; ?>
                
                <a href="<?php echo "https://{$_SERVER['HTTP_HOST']}/review.php?id={$site_id}"; ?>" 
                   class="visit-btn" 
                   target="_blank" 
                   rel="nofollow">
                    <?php echo htmlspecialchars($button_text); ?>
                </a>
                
                <div class="powered-by">
                    <a href="<?php echo SITE_URL; ?>" target="_blank"><?php echo SITE_NAME; ?></a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Default Card Widget -->
            <div class="widget">
                <div style="padding: <?php echo $current_size['padding']; ?>;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <?php if ($show_logo): ?>
                            <img src="<?php echo htmlspecialchars($site['logo'] ?: 'assets/images/default-logo.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($site['name']); ?>" 
                                 class="site-logo">
                        <?php endif; ?>
                        <div style="flex: 1;">
                            <div class="site-name"><?php echo htmlspecialchars($site['name']); ?></div>
                            <div class="site-category"><?php echo ucfirst(str_replace('_', ' ', $site['category'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($show_rating): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?php echo renderStars(round($site['average_rating'])); ?>
                            </div>
                            <div style="text-align: right;">
                                <div class="rating-value"><?php echo number_format($site['average_rating'], 1); ?></div>
                                <?php if ($show_reviews): ?>
                                    <div class="rating-count"><?php echo $site['review_count']; ?> reviews</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($show_trust): ?>
                        <div style="margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.8em; color: <?php echo $theme === 'dark' ? '#94a3b8' : '#6b7280'; ?>;">
                                <span>Trust Score</span>
                                <span><?php echo $trust_score; ?>%</span>
                            </div>
                            <div class="trust-bar">
                                <div class="trust-progress"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="status-badge" style="margin-bottom: 16px;">
                        <?php echo getStatusText($site['status']); ?>
                    </div>
                    
                    <a href="<?php echo "https://{$_SERVER['HTTP_HOST']}/review.php?id={$site_id}"; ?>" 
                       class="visit-btn" 
                       target="_blank" 
                       rel="nofollow">
                        <i class="fas fa-star"></i> <?php echo htmlspecialchars($button_text); ?>
                    </a>
                </div>
                
                <div class="powered-by">
                    Powered by <a href="<?php echo SITE_URL; ?>" target="_blank"><?php echo SITE_NAME; ?></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
