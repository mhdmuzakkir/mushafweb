<?php
// extract-missing.php - Extract only missing PDFs (gap filler)
// Usage: http://yoursite.com/admin/extract-missing.php?key=your-secret-key-123

$secret = $_GET['key'] ?? '';
if ($secret !== 'your-secret-key-123') {
    http_response_code(403);
    exit('Access denied');
}

ini_set('memory_limit', '512M');
set_time_limit(0);

// Updated paths
$aiFilesDir = __DIR__ . '/../ai-files';
$cacheDir = __DIR__ . '/../cache';
$extracted = 0;
$missing = 0;

if (!file_exists($aiFilesDir)) {
    exit('ai-files/ directory not found');
}

$folderMap = [
    'Ajza' => 'ajza',
    'Review Task' => 'review',
    'Completed' => 'completed'
];

$riwayahs = array_filter(scandir($aiFilesDir), function($i) use ($aiFilesDir) {
    return $i[0] !== '.' && is_dir($aiFilesDir . '/' . $i);
});

foreach ($riwayahs as $riwayah) {
    $base = "$aiFilesDir/$riwayah";
    $cache = "$cacheDir/$riwayah";
    
    if (!file_exists($cache)) {
        mkdir($cache, 0755, true);
    }
    
    foreach ($folderMap as $folderName => $status) {
        $path = "$base/$folderName";
        if (!file_exists($path)) continue;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile() || !preg_match('/\.ai$/i', $file->getFilename())) {
                continue;
            }
            
            if (!preg_match('/^(\d+)/', $file->getFilename(), $matches)) continue;
            
            $pageFormatted = sprintf('%03d', intval($matches[1]));
            $cacheFile = "$cache/$pageFormatted-$status.pdf";
            
            if (file_exists($cacheFile)) {
                continue;
            }
            
            $missing++;
            
            $data = file_get_contents($file->getPathname());
            $pdfStart = strpos($data, '%PDF');
            $pdfEnd = strrpos($data, '%%EOF', $pdfStart);
            
            if ($pdfStart !== false && $pdfEnd !== false) {
                $pdfEnd += strlen('%%EOF');
                $pdfData = substr($data, $pdfStart, $pdfEnd - $pdfStart);
                
                if (strlen($pdfData) > 1000) {
                    file_put_contents($cacheFile, $pdfData);
                    $extracted++;
                    echo "Extracted: $riwayah/$pageFormatted-$status.pdf\n";
                    
                    if ($extracted % 10 == 0) {
                        echo "--- Progress: $extracted new files extracted ---\n";
                        flush();
                    }
                }
            }
        }
    }
}

echo "\n=== DONE ===\n";
echo "Missing found: $missing\n";
echo "Extracted: $extracted\n";
echo "Already cached: " . ($missing - $extracted) . "\n";
?>
