<?php
// clear-cache.php - Run this after major updates
// Updated path: cache is in parent directory
$cacheDir = __DIR__ . '/../cache';
if (file_exists($cacheDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isFile()) unlink($file->getPathname());
    }
    echo "Cache cleared!";
}
?>
