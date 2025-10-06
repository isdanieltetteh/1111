# Quick Start Guide - Adding Ads to Your Pages

## 5-Minute Integration

### Step 1: Add the Widget Include

At the top of your page (after other includes), add:

\`\`\`php
<?php require_once __DIR__ . '/includes/ad-widget.php'; ?>
\`\`\`

### Step 2: Insert Ad Spaces

Add ad spaces where you want them. For example, in **index.php**:

\`\`\`php
<!DOCTYPE html>
<html>
<head>
    <title>Homepage</title>
</head>
<body>
    <?php require_once 'includes/header.php'; ?>
    <?php require_once 'includes/ad-widget.php'; ?>

    <!-- Top Banner Ad -->
    <div class="ad-container">
        <?php displayAdSpace('index_top_banner'); ?>
    </div>

    <div class="container">
        <div class="main-content">
            <h1>Welcome to FaucetGuard</h1>

            <p>Your content here...</p>

            <!-- Middle Banner Ad -->
            <div class="ad-container">
                <?php displayAdSpace('index_middle_banner'); ?>
            </div>

            <p>More content...</p>
        </div>

        <aside class="sidebar">
            <!-- Sidebar Ad #1 -->
            <?php displayAdSpace('index_sidebar_1'); ?>

            <!-- Other sidebar widgets -->
            <div class="widget">...</div>

            <!-- Sidebar Ad #2 -->
            <?php displayAdSpace('index_sidebar_2'); ?>
        </aside>
    </div>

    <!-- Bottom Banner Ad -->
    <div class="ad-container">
        <?php displayAdSpace('index_bottom_banner'); ?>
    </div>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
\`\`\`

### Step 3: Add to Sites Listing Page

In **sites.php**, add ads between site listings:

\`\`\`php
<?php
require_once 'includes/ad-widget.php';

// Top banner before listings
displayAdSpace('sites_top_banner');

// Loop through sites
$count = 0;
foreach ($sites as $site) {
    // Display site
    echo renderSite($site);

    // Show ad every 5 sites
    $count++;
    if ($count % 5 == 0) {
        displayAdSpace('sites_between_results_1');
    }
}

// Bottom banner after listings
displayAdSpace('sites_bottom_banner');
?>
\`\`\`

### Step 4: Add to Dashboard

In **dashboard.php**:

\`\`\`php
<div class="dashboard-layout">
    <!-- Top Banner -->
    <?php displayAdSpace('dashboard_top_banner'); ?>

    <div class="dashboard-main">
        <!-- Your dashboard content -->
    </div>

    <aside class="dashboard-sidebar">
        <!-- Sidebar Ad -->
        <?php displayAdSpace('dashboard_sidebar'); ?>

        <!-- Other sidebar content -->
    </aside>

    <!-- Bottom Text Ad -->
    <?php displayAdSpace('dashboard_bottom_text'); ?>
</div>
\`\`\`

## Available Ad Spaces By Page

### Homepage (index.php)
\`\`\`php
displayAdSpace('index_top_banner');      // 728x90
displayAdSpace('index_sidebar_1');       // 300x250
displayAdSpace('index_middle_banner');   // 728x90
displayAdSpace('index_sidebar_2');       // 300x250
displayAdSpace('index_bottom_banner');   // 728x90
\`\`\`

### Sites Listing (sites.php)
\`\`\`php
displayAdSpace('sites_top_banner');         // 728x90
displayAdSpace('sites_sidebar_1');          // 300x250
displayAdSpace('sites_between_results_1');  // 468x60
displayAdSpace('sites_between_results_2');  // 468x60
displayAdSpace('sites_bottom_banner');      // 728x90
\`\`\`

### Review Page (review.php)
\`\`\`php
displayAdSpace('review_top_banner');     // 728x90
displayAdSpace('review_sidebar_1');      // 300x250
displayAdSpace('review_sidebar_2');      // 300x600
displayAdSpace('review_bottom_banner');  // 728x90
\`\`\`

### Rankings (rankings.php)
\`\`\`php
displayAdSpace('rankings_top_banner');      // 728x90
displayAdSpace('rankings_sidebar');         // 300x250
displayAdSpace('rankings_between_ranks');   // 468x60
displayAdSpace('rankings_bottom_banner');   // 728x90
\`\`\`

### Dashboard (dashboard.php)
\`\`\`php
displayAdSpace('dashboard_top_banner');   // 728x90
displayAdSpace('dashboard_sidebar');      // 300x250
displayAdSpace('dashboard_bottom_text');  // Text only
\`\`\`

### Redirect Page (redirect.php)
\`\`\`php
displayAdSpace('redirect_top_banner');    // 728x90 (Premium only)
displayAdSpace('redirect_middle_banner'); // 468x60
displayAdSpace('redirect_sidebar');       // 300x250
\`\`\`

## What Happens Automatically

### When an Ad Exists:
- Ad displays automatically
- Impression is tracked
- Click tracking works automatically

### When No Ad Exists:
- Beautiful placeholder appears
- Shows "Advertise Here" button
- Button links to purchase page with space pre-selected
- Shows recommended dimensions

## Admin Quick Actions

### Access Admin Panel
Go to: `/admin/ad-control.php`

### Enable/Disable Spaces
Click the Enable/Disable button next to any space

### Adjust Pricing
1. Click "Pricing" button
2. Enter multiplier (1.0 = normal, 2.0 = double price)
3. Click "Update Pricing"

### Change Dimensions
1. Click "Size" button
2. Enter width and height
3. Click "Update Dimensions"

## Testing Your Integration

1. Visit your page where you added `displayAdSpace()`
2. If no ads purchased yet, you'll see placeholder with "Advertise Here"
3. Click placeholder button to test purchase flow
4. Purchase a test ad (requires credits)
5. After admin approval, ad appears in place of placeholder

## Styling Tips

You can wrap ad spaces in containers for better control:

\`\`\`php
<div class="ad-wrapper" style="margin: 2rem 0; text-align: center;">
    <?php displayAdSpace('index_top_banner'); ?>
</div>
\`\`\`

Or use CSS classes:

\`\`\`php
<div class="ad-container top-banner">
    <?php displayAdSpace('index_top_banner'); ?>
</div>
\`\`\`

## Common Patterns

### Responsive Layout
\`\`\`php
<div class="ad-responsive">
    <?php displayAdSpace('index_top_banner'); ?>
</div>
\`\`\`

### Between Content Sections
\`\`\`php
<section class="content-section">
    <h2>Section Title</h2>
    <p>Content...</p>
</section>

<?php displayAdSpace('index_middle_banner'); ?>

<section class="content-section">
    <h2>Next Section</h2>
    <p>More content...</p>
</section>
\`\`\`

### In Loops
\`\`\`php
<?php foreach ($items as $index => $item): ?>
    <?php echo renderItem($item); ?>

    <?php if (($index + 1) % 3 == 0): ?>
        <?php displayAdSpace('sites_between_results_1'); ?>
    <?php endif; ?>
<?php endforeach; ?>
\`\`\`

## Need Help?

1. **See live examples**: `/ad-placement-example.php`
2. **Full documentation**: `/AD_SYSTEM_README.md`
3. **Admin panel**: `/admin/ad-control.php`

## That's It!

Your ad system is now fully integrated. Users can purchase ads, and they'll appear automatically in the spaces you've defined. The system handles everything else:

✅ Ad rotation
✅ Impression tracking
✅ Click tracking
✅ Performance metrics
✅ Revenue tracking
✅ Placeholder display

Start adding `displayAdSpace()` calls to your pages and you're done!
