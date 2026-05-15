<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$riwayah = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $_GET['riwayah'] ?? '');

if (empty($riwayah)) {
    echo json_encode([]);
    exit;
}

// Updated path: ai-files is now in parent directory
$baseFolder = __DIR__ . '/../ai-files/' . $riwayah;
$files = [];

// 1. Scan Ajza (with juz subfolders 01-30)
if (file_exists($baseFolder . '/Ajza')) {
    scanJuzFolders($baseFolder . '/Ajza', 'ajza', $riwayah, $files);
}

// 2. Scan Review Task (FLAT - no subfolders)
if (file_exists($baseFolder . '/Review Task')) {
    scanFlatFolder($baseFolder . '/Review Task', 'review', $riwayah, $files);
}

// 3. Scan Completed (your structure: Completed/Ajza/01/)
if (file_exists($baseFolder . '/Completed')) {
    // Your structure has extra Ajza subfolder
    if (file_exists($baseFolder . '/Completed/Ajza')) {
        scanJuzFolders($baseFolder . '/Completed/Ajza', 'completed', $riwayah, $files);
    } else {
        // Standard structure without subfolder
        scanJuzFolders($baseFolder . '/Completed', 'completed', $riwayah, $files);
    }
}

// Sort by page number
usort($files, function($a, $b) {
    return intval($a['page']) - intval($b['page']);
});

echo json_encode($files);

// Helper: Scan folders with 01-30 subfolders (Ajza, Completed)
function scanJuzFolders($basePath, $status, $riwayah, &$files) {
    for ($i = 1; $i <= 30; $i++) {
        $juzFolder = $basePath . '/' . str_pad($i, 2, '0', STR_PAD_LEFT);
        if (!file_exists($juzFolder)) continue;
        
        $items = scandir($juzFolder);
        foreach ($items as $item) {
            if ($item[0] === '.' || !preg_match('/\.webp$/i', $item)) continue;
            
            $fullPath = $juzFolder . '/' . $item;
            if (!is_file($fullPath)) continue;
            
            // Match 020-Warsh.webp or 020.webp
            if (preg_match('/^(\d+)(-[^.]*)?\.webp$/i', $item, $matches)) {
                $page = intval($matches[1]);
                $files[] = [
                    'filename' => $item,
                    'page' => $page,
                    'riwayah' => $riwayah,
                    'status' => $status,
                    'juz' => str_pad($i, 2, '0', STR_PAD_LEFT),
                    'path' => str_replace(__DIR__ . '/../ai-files/', '', $fullPath)
                ];
            }
        }
    }
}

// Helper: Scan flat folder (Review Task - no subfolders)
function scanFlatFolder($path, $status, $riwayah, &$files) {
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item[0] === '.' || !preg_match('/\.webp$/i', $item)) continue;
        
        $fullPath = $path . '/' . $item;
        if (!is_file($fullPath)) continue;
        
        // Match 023-Warsh.webp
        if (preg_match('/^(\d+)(-[^.]*)?\.webp$/i', $item, $matches)) {
            $page = intval($matches[1]);
            $juz = getJuzFromPage($page); // Calculate juz from page number
            
            $files[] = [
                'filename' => $item,
                'page' => $page,
                'riwayah' => $riwayah,
                'status' => $status,
                'juz' => str_pad($juz, 2, '0', STR_PAD_LEFT),
                'path' => str_replace(__DIR__ . '/../ai-files/', '', $fullPath)
            ];
        }
    }
}

function getJuzFromPage($page) {
    $map = [1=>21,2=>41,3=>61,4=>81,5=>101,6=>121,7=>141,8=>161,9=>181,10=>201,11=>221,12=>241,13=>261,14=>281,15=>301,16=>321,17=>341,18=>361,19=>381,20=>401,21=>421,22=>441,23=>461,24=>481,25=>501,26=>521,27=>541,28=>561,29=>581,30=>604];
    foreach ($map as $j => $max) if ($page <= $max) return $j;
    return 1;
}
?>