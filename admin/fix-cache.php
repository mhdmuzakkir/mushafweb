<?php
// fix-cache.php - One-time cache cleanup and standardization
// Run this once: php fix-cache.php

header('Content-Type: text/plain');

// Updated path: cache is in parent directory
$cacheBase = __DIR__ . '/../cache';
$totalFixed = 0;
$totalDeleted = 0;
$totalRenamed = 0;

if (!file_exists($cacheBase)) {
    echo "No cache directory found.\n";
    exit;
}

$riwayahs = array_filter(scandir($cacheBase), function($item) use ($cacheBase) {
    return $item[0] !== '.' && is_dir($cacheBase . '/' . $item);
});

if (empty($riwayahs)) {
    echo "No riwayah cache folders found.\n";
    exit;
}

foreach ($riwayahs as $riwayah) {
    echo "Processing: $riwayah\n";
    
    $cacheDir = $cacheBase . '/' . $riwayah;
    $files = glob($cacheDir . '/*.pdf');
    
    if (empty($files)) {
        echo "  No PDF files found.\n";
        continue;
    }
    
    $groups = [];
    
    foreach ($files as $file) {
        $basename = basename($file);
        
        if (!preg_match('/^(\d+)-([a-z]+)\.pdf$/', $basename, $matches)) {
            continue;
        }
        
        $pageNum = intval($matches[1]);
        $status = $matches[2];
        $key = $pageNum . '-' . $status;
        
        if (!isset($groups[$key])) {
            $groups[$key] = [];
        }
        
        $groups[$key][] = [
            'path' => $file,
            'basename' => $basename,
            'mtime' => filemtime($file),
            'size' => filesize($file)
        ];
    }
    
    foreach ($groups as $key => $fileGroup) {
        usort($fileGroup, function($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });
        
        list($pageNum, $status) = explode('-', $key);
        $properName = sprintf('%03d', $pageNum) . '-' . $status . '.pdf';
        $properPath = $cacheDir . '/' . $properName;
        
        $newestFile = $fileGroup[0];
        $keptFile = $newestFile['path'];
        
        if ($newestFile['basename'] !== $properName) {
            if (file_exists($properPath)) {
                unlink($properPath);
                $totalDeleted++;
            }
            
            rename($keptFile, $properPath);
            echo "  Renamed: {$newestFile['basename']} → $properName\n";
            $keptFile = $properPath;
            $totalRenamed++;
        } else {
            echo "  Kept: $properName (newest)\n";
        }
        
        $others = array_slice($fileGroup, 1);
        foreach ($others as $oldFile) {
            if (file_exists($oldFile['path'])) {
                unlink($oldFile['path']);
                echo "    Deleted duplicate: {$oldFile['basename']}\n";
                $totalDeleted++;
            }
        }
        
        $totalFixed++;
    }
    
    echo "\n";
}

echo "=== CLEANUP COMPLETE ===\n";
echo "Groups processed: $totalFixed\n";
echo "Files renamed: $totalRenamed\n";
echo "Duplicates removed: $totalDeleted\n";
echo "\nAll cache files now use standardized 3-digit naming (004-ajza.pdf)\n";
?>
