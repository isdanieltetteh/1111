<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/xml; charset=utf-8');

$database = new Database();
$db = $database->getConnection();

// Get all approved sites
$sites_query = "SELECT id, name, created_at, updated_at FROM sites WHERE is_approved = 1 AND is_dead = FALSE AND admin_approved_dead = FALSE ORDER BY updated_at DESC";
$sites_stmt = $db->prepare($sites_query);
$sites_stmt->execute();
$sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Static Pages -->
    <url>
        <loc><?php echo SITE_URL; ?>/</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/sites.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/rankings.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/about.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/contact.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/faq.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/trust-safety.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/terms.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/privacy.php</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>

    <!-- Site Detail Pages -->
    <?php foreach ($sites as $site): ?>
    <url>
        <loc><?php echo SITE_URL; ?>/site-details.php?id=<?php echo $site['id']; ?></loc>
        <lastmod><?php echo date('c', strtotime($site['updated_at'] ?: $site['created_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>
    
    <!-- Category Pages -->
    <url>
        <loc><?php echo SITE_URL; ?>/sites.php?category=faucet</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    
    <url>
        <loc><?php echo SITE_URL; ?>/sites.php?category=url_shortener</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
</urlset>
