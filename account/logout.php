<?php
// logout.php
// Force immediate expiration of the cookie
if (isset($_COOKIE['token'])) {
    unset($_COOKIE['token']);
    setcookie('token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Destroy session if exists
session_start();
$_SESSION = [];
session_destroy();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to index with JavaScript fallback
header("Location: /index.php");
exit();
?>