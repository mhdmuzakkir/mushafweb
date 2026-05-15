<?php
$config = [
    'basePath' => __DIR__ . '/../mushaftasks/',
    'secret' => 'your-random-secret-123',
    'maxAgeMinutes' => 1440 // Only return files archived in last 15 min
];

if (!isset($_GET['secret']) || $_GET['secret'] !== $config['secret']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$files = [];
$ptDonePath = $config['basePath'] . 'pt-done/';
$cutoffTime = time() - ($config['maxAgeMinutes'] * 60);

if (is_dir($ptDonePath)) {
    $riwayahs = array_diff(scandir($ptDonePath), ['.', '..']);
    
    foreach ($riwayahs as $riwayah) {
        $riwayahPath = $ptDonePath . $riwayah;
        if (!is_dir($riwayahPath)) continue;
        
        $archivedFiles = array_diff(scandir($riwayahPath), ['.', '..']);
        foreach ($archivedFiles as $file) {
            if (preg_match('/^(\d+)-tasks\.json$/', $file, $matches)) {
                $filePath = $riwayahPath . '/' . $file;
                // CRITICAL: Only include recently archived files
                if (filemtime($filePath) > $cutoffTime) {
                    $files[] = [
                        'riwayah' => $riwayah,
                        'page' => $matches[1],
                        'archivedAt' => date('Y-m-d H:i:s', filemtime($filePath))
                    ];
                }
            }
        }
    }
}

echo json_encode(['archived' => $files, 'cutoff' => date('Y-m-d H:i:s', $cutoffTime)]);
?>