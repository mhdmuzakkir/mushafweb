<?php
/**
 * Mushaf Review Form - Get Available Riwayahs
 * New Structure: mushaftasks/riwayah-tasks/{riwayah}/
 */

$config = [
    'tasksBasePath' => __DIR__ . '/../mushaftasks/riwayah-tasks/',
    'enableCors' => true
];

if ($config['enableCors']) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$riwayahs = [];

if (file_exists($config['tasksBasePath']) && is_dir($config['tasksBasePath'])) {
    $items = scandir($config['tasksBasePath']);

    foreach ($items as $item) {
        if ($item[0] === '.') continue;

        $fullPath = $config['tasksBasePath'] . DIRECTORY_SEPARATOR . $item;

        // Only include directories (riwayah folders)
        if (is_dir($fullPath)) {
            $riwayahs[] = $item;
        }
    }

    sort($riwayahs);
} else {
    // Return default riwayahs if folder doesn't exist yet
    $riwayahs = ['Warsh', 'Hafs', 'Qaloon'];
}

echo json_encode($riwayahs);