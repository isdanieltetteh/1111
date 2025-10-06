<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel - ' . SITE_NAME; ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-pQfmB7T9nIBRk08LomPvKgJo0vvArkH5vWck/dwZ0pniS3pSkeCZMt2rt7NmexG99nmCaTKty+O32A+Fi7C9VA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Theme Styles -->
    <link rel="stylesheet" href="assets/css/theme.css?v=1.0.0">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body" data-theme="<?php echo htmlspecialchars($_COOKIE['adminTheme'] ?? 'light'); ?>">
    <nav class="navbar navbar-expand-lg admin-navbar">
        <div class="container-fluid">
            <button class="btn btn-icon d-lg-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <a class="navbar-brand" href="dashboard.php">
                <span class="brand-logo">
                    <i class="fas fa-crown"></i>
                </span>
                <span class="brand-text">
                    <span class="text-uppercase small">God Mode</span>
                    <strong><?php echo SITE_NAME; ?></strong>
                </span>
            </a>

            <form class="navbar-search d-none d-md-flex ms-3" method="get" action="dashboard.php" role="search">
                <span class="search-icon"><i class="fas fa-magnifying-glass"></i></span>
                <input class="form-control" type="search" placeholder="Search the command center" aria-label="Search" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
            </form>

            <div class="navbar-actions ms-auto">
                <button class="btn btn-icon" type="button" id="themeToggle" aria-label="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="dropdown user-menu">
                    <button class="btn btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-shield"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                        <li class="px-3 pb-2 small text-muted">Signed in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../support-tickets.php" target="_blank"><i class="fas fa-life-ring me-2"></i>Support Center</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-right-from-bracket me-2"></i>Sign out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
