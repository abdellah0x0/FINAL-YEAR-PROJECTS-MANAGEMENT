<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/config/mysql-config.php';

$file = isset($_GET['file']) ? $_GET['file'] : null;

if ($file) {
    // Security check - prevent directory traversal
    $file = basename($file);
    $filePath = $_SERVER["DOCUMENT_ROOT"].'/docs/'.$file;
    
    if (file_exists($filePath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.basename($filePath).'"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// If file doesn't exist or not provided
header("HTTP/1.0 404 Not Found");
echo "<h1>Fichier PDF non trouvé</h1>";
exit;
?>