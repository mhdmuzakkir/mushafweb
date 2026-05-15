<?php
/**
 * Get page-specific tasks from server
 * New Structure: mushaftasks/page-tasks/{riwayah}/{page}-tasks.json
 */

$config = [
    'tasksBasePath' => __DIR__ . '/../mushaftasks/',
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

$riwayah = isset($_GET['riwayah']) ? preg_replace('/[^a-zA-Z0-9\s_-]/', '', $_GET['riwayah']) : '';
$page = isset($_GET['page']) ? str_pad(intval($_GET['page']), 3, '0', STR_PAD_LEFT) : '';

if (empty($riwayah) || empty($page)) {
    echo json_encode(['success' => false, 'message' => 'Missing riwayah or page']);
    exit;
}

// NEW STRUCTURE: mushaftasks/page-tasks/{riwayah}/{page}-tasks.json
$pageTasksPath = $config['tasksBasePath'] . 'page-tasks' . DIRECTORY_SEPARATOR . $riwayah;
$filePath = $pageTasksPath . DIRECTORY_SEPARATOR . $page . '-tasks.json';

if (file_exists($filePath)) {
    $data = json_decode(file_get_contents($filePath), true);
    echo json_encode(['success' => true, 'tasks' => $data['tasks'] ?? []]);
} else {
    echo json_encode(['success' => true, 'tasks' => []]);
}
