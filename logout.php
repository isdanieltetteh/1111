<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple logout without requiring database connection
session_destroy();
session_start();

// Clear any remember me cookies
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

header('Location: index.php');
exit();
?>
