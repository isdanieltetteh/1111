<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth();

// Check if user is logged in first
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php?redirect=' . urlencode('admin/dashboard.php'));
    exit();
}

// Check if user is admin
if (!$auth->isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

// Redirect to dashboard
header('Location: dashboard.php');
exit();
?>
