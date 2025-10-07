<?php
if (!isset($page_title)) $page_title = SITE_NAME;
if (!isset($page_description)) $page_description = SITE_DESCRIPTION;
if (!isset($page_keywords)) $page_keywords = SITE_KEYWORDS;
if (!isset($current_page)) $current_page = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:url" content="<?php echo SITE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">

    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/v4-shims.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <?php if (isset($additional_head)) echo $additional_head; ?>
</head>
<body data-logged-in="<?php echo isset($auth) && $auth instanceof Auth && $auth->isLoggedIn() ? 'true' : 'false'; ?>" class="d-flex flex-column min-vh-100">

<header class="glass-nav position-sticky top-0 w-100 z-3 shadow-sm">
    <nav class="navbar navbar-expand-lg navbar-dark py-3">
        <div class="container">
            <a class="navbar-brand brand-highlight" href="/">
                <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNav" aria-controls="primaryNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="primaryNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'home' ? 'active' : ''; ?>" href="/">Home</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'sites' ? 'active' : ''; ?>" href="sites">Browse Sites</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'rankings' ? 'active' : ''; ?>" href="rankings">Rankings</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'submit' ? 'active' : ''; ?>" href="submit-site">Submit Site</a></li>
                    <?php if (isset($auth) && $auth instanceof Auth && $auth->isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="dashboard">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'coupons' ? 'active' : ''; ?>" href="redeem-coupon">Coupons</a></li>
                        <li class="nav-item position-relative">
                            <a class="nav-link d-flex align-items-center gap-2 <?php echo $current_page === 'notifications' ? 'active' : ''; ?>" href="notifications.php">
                                <i class="fas fa-bell"></i>
                                <span class="d-lg-none">Notifications</span>
                                <span class="notification-indicator" data-notification-badge hidden>0</span>
                            </a>
                        </li>
                        <?php if ($auth->isAdmin()): ?>
                            <li class="nav-item"><a class="nav-link" href="admin/dashboard">Admin</a></li>
                        <?php endif; ?>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-theme btn-outline-glass" href="logout"><i class="fas fa-right-from-bracket me-2"></i>Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-theme btn-outline-glass me-lg-2 mb-2 mb-lg-0" href="login"><i class="fas fa-right-to-bracket me-2"></i>Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-theme btn-gradient" href="register"><i class="fas fa-user-plus me-2"></i>Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>