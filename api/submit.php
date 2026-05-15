<?php
/**
 * Mushaf Page Task Form - Submission Handler
 * Saves page task data to JSON files on SERVER
 * New Structure: mushaftasks/page-tasks/{riwayah}/{page}-tasks.json
 */

$config = [
    'tasksBasePath' => __DIR__ . '/../mushaftasks/',
    'enableCors' => true,
    'maxTasks' => 10,
    'debug' => true
];

if ($config['enableCors']) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sendSuccess($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method', 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    sendError('Invalid JSON data');
}

$required = ['page', 'riwayah', 'tasks'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        sendError("Missing required field: {$field}");
    }
}

$page = intval($data['page']);
if ($page < 1 || $page > 604) {
    sendError('Page number must be between 1 and 604');
}

$riwayah = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $data['riwayah']);
if (empty($riwayah)) {
    sendError('Invalid riwayah name');
}

$tasks = $data['tasks'];
if (!is_array($tasks) || count($tasks) === 0) {
    sendError('At least one task is required');
}

if (count($tasks) > $config['maxTasks']) {
    sendError("Maximum {$config['maxTasks']} tasks allowed per submission");
}

foreach ($tasks as $task) {
    if (empty($task['title'])) {
        sendError('All tasks must have a title');
    }
}

function getJuzFromPage($page) {
    $pageToJuz = [
        1 => [1, 21], 2 => [22, 41], 3 => [42, 61], 4 => [62, 81], 5 => [82, 101],
        6 => [102, 121], 7 => [122, 141], 8 => [142, 161], 9 => [162, 181], 10 => [182, 201],
        11 => [202, 221], 12 => [222, 241], 13 => [242, 261], 14 => [262, 281], 15 => [282, 301],
        16 => [302, 321], 17 => [322, 341], 18 => [342, 361], 19 => [362, 381], 20 => [382, 401],
        21 => [402, 421], 22 => [422, 441], 23 => [442, 461], 24 => [462, 481], 25 => [482, 501],
        26 => [502, 521], 27 => [522, 541], 28 => [542, 561], 29 => [562, 581], 30 => [582, 604]
    ];
    foreach ($pageToJuz as $juz => $range) {
        if ($page >= $range[0] && $page <= $range[1]) return $juz;
    }
    return null;
}

function ensureDirectory($path) {
    if (!file_exists($path)) {
        if (!mkdir($path, 0755, true)) return false;
    }
    return is_dir($path) && is_writable($path);
}

function sanitizeFilename($filename) {
    $filename = basename($filename);
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
}

$pageFormatted = str_pad($page, 3, '0', STR_PAD_LEFT);
$juz = isset($data['juz']) ? intval($data['juz']) : getJuzFromPage($page);

// NEW STRUCTURE: mushaftasks/page-tasks/{riwayah}/{page}-tasks.json
$pageTasksPath = $config['tasksBasePath'] . 'page-tasks' . DIRECTORY_SEPARATOR . sanitizeFilename($riwayah);
$filename = sanitizeFilename("{$pageFormatted}-tasks.json");
$filePath = $pageTasksPath . DIRECTORY_SEPARATOR . $filename;

if ($config['debug']) {
    error_log("Saving page tasks to: {$filePath}");
}

if (!ensureDirectory($pageTasksPath)) {
    sendError('Failed to create page-tasks directory', 500);
}

$existingData = ['tasks' => []];
if (file_exists($filePath)) {
    $existing = json_decode(file_get_contents($filePath), true);
    if ($existing && isset($existing['tasks'])) {
        $existingData = $existing;
    }
}

$newTasks = array_map(function ($task) {
    return [
        'id' => 'ptask_' . uniqid(),
        'title' => htmlspecialchars(trim($task['title'])),
        'description' => isset($task['description']) ? htmlspecialchars(trim($task['description'])) : '',
        'completed' => false,
        'source' => 'web_form',
        'created' => date('c')
    ];
}, $tasks);

$allTasks = array_merge($existingData['tasks'], $newTasks);

$taskData = [
    'page' => $pageFormatted,
    'riwayah' => $riwayah,
    'juz' => $juz,
    'tasks' => $allTasks,
    'updated' => date('c')
];

$jsonContent = json_encode($taskData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($jsonContent === false) {
    sendError('Failed to encode JSON data', 500);
}

if (file_put_contents($filePath, $jsonContent) === false) {
    sendError('Failed to save task file', 500);
}

chmod($filePath, 0644);

sendSuccess([
    'message' => 'Tasks submitted successfully',
    'page' => $pageFormatted,
    'juz' => $juz,
    'riwayah' => $riwayah,
    'tasksCount' => count($allTasks),
    'newTasksCount' => count($newTasks)
]);
