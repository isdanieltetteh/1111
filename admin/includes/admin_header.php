<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel - ' . SITE_NAME; ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2Lw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/theme.css?v=1">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('admin-theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-app">
    <nav class="navbar navbar-expand-lg admin-navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-link text-white p-0 fs-4 d-lg-none" id="sidebarToggle" aria-label="Toggle navigation">
                    <i class="fas fa-bars"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    <span class="brand-initial">GM</span>
                    <span><?php echo SITE_NAME; ?> God Mode</span>
                </a>
            </div>

            <div class="d-flex align-items-center gap-3 ms-auto">
                <form class="d-none d-md-flex" action="sites.php" method="get" role="search">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-white-50"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" placeholder="Quick search..." name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                </form>

                <button class="theme-toggle" type="button" id="themeToggle" aria-label="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>

                <div class="dropdown">
                    <a class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="me-2 text-end d-none d-sm-block">
                            <small class="d-block text-white-50">Administrator</small>
                            <span class="fw-semibold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                        </div>
                        <span class="avatar bg-white bg-opacity-25 rounded-circle d-inline-flex justify-content-center align-items-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-user-shield"></i>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><h6 class="dropdown-header">Signed in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></h6></li>
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-id-badge me-2 text-primary"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-sliders me-2 text-primary"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <div class="admin-layout">
