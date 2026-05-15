<?php
while (ob_get_level()) ob_end_clean();

$riwayah = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $_GET['riwayah'] ?? '');
$page = intval($_GET['page'] ?? 0);
$status = preg_replace('/[^a-z]/', '', strtolower($_GET['status'] ?? 'ajza'));

if (empty($riwayah) || $page < 1) {
    http_response_code(400);
    exit('Invalid parameters');
}

$folderMap = [
    'ajza' => 'Ajza',
    'completed' => 'Completed',
    'review' => 'Review Task'
];

$folderName = $folderMap[$status] ?? 'Ajza';
$baseFolder = __DIR__ . '/../ai-files/' . $riwayah . '/' . $folderName;

$padded = str_pad($page, 3, '0', STR_PAD_LEFT);

// WEBP ONLY - No .ai files
$webpNames = [
    $padded . '-' . $riwayah . '.webp',
    $padded . '-' . strtolower($riwayah) . '.webp',
    $padded . '.webp'
];

$filePath = null;

if ($status === 'ajza') {
    $juz = getJuzFromPage($page);
    $juzFolder = $baseFolder . '/' . str_pad($juz, 2, '0', STR_PAD_LEFT);
    
    foreach ($webpNames as $name) {
        if (file_exists($juzFolder . '/' . $name)) {
            $filePath = $juzFolder . '/' . $name;
            break;
        }
    }
} elseif ($status === 'completed') {
    $juz = getJuzFromPage($page);
    $juzFolderWithSub = $baseFolder . '/Ajza/' . str_pad($juz, 2, '0', STR_PAD_LEFT);
    $juzFolderDirect = $baseFolder . '/' . str_pad($juz, 2, '0', STR_PAD_LEFT);
    
    foreach ($webpNames as $name) {
        if (file_exists($juzFolderWithSub . '/' . $name)) {
            $filePath = $juzFolderWithSub . '/' . $name;
            break;
        }
        if (file_exists($juzFolderDirect . '/' . $name)) {
            $filePath = $juzFolderDirect . '/' . $name;
            break;
        }
    }
} else {
    foreach ($webpNames as $name) {
        if (file_exists($baseFolder . '/' . $name)) {
            $filePath = $baseFolder . '/' . $name;
            break;
        }
    }
}

if (!$filePath) {
    http_response_code(404);
    exit('WebP file not found');
}

$cacheDir = __DIR__ . '/../cache/' . $riwayah;
$pageFormatted = sprintf('%03d', $page);
$cacheFile = $cacheDir . '/' . $pageFormatted . '-' . $status . '.webp';

$useCache = false;
if (file_exists($cacheFile)) {
    $cacheTime = filemtime($cacheFile);
    $webpTime = filemtime($filePath);
    if ($cacheTime > $webpTime) {
        $useCache = true;
    } else {
        unlink($cacheFile);
    }
}

// Clean old cache
foreach (glob($cacheDir . '/' . $pageFormatted . '-' . $status . '.*') ?: [] as $oldFile) {
    if ($oldFile !== $cacheFile) unlink($oldFile);
}

if ($useCache) {
    serveWebP($cacheFile, basename($cacheFile));
    exit;
}

// Cache the WebP
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
copy($filePath, $cacheFile);
serveWebP($cacheFile, basename($cacheFile));

function serveWebP($file, $filename) {
    $data = file_get_contents($file);
    header('Content-Type: image/webp');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: public, max-age=3600');
    header('Accept-Ranges: bytes');
    echo $data;
}

function getJuzFromPage($page) {
    $map = [1=>21,2=>41,3=>61,4=>81,5=>101,6=>121,7=>141,8=>161,9=>181,10=>201,11=>221,12=>241,13=>261,14=>281,15=>301,16=>321,17=>341,18=>361,19=>381,20=>401,21=>421,22=>441,23=>461,24=>481,25=>501,26=>521,27=>541,28=>561,29=>581,30=>604];
    foreach ($map as $j => $max) if ($page <= $max) return $j;
    return 1;
}
?>