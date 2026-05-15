<?php
/**
 * Mushaf WebP Delete API
 * Deletes a WebP file by relative path
 */

$API_KEY = 'mushaf-webp-secret-2026'; // CHANGE THIS in production!
$BASE_DIR = __DIR__ . '/../webp/';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Auth check
$headers = getallheaders();
$apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';
if (empty($apiKey) && isset($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'];
}

if ($apiKey !== $API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['relative_path']) || empty($input['relative_path'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing relative_path']);
    exit;
}

$relativePath = $input['relative_path'];
$relativePath = str_replace('\\', '/', $relativePath);
$relativePath = ltrim($relativePath, '/');

if (strpos($relativePath, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid path']);
    exit;
}

$targetPath = $BASE_DIR . $relativePath;

if (!file_exists($targetPath)) {
    echo json_encode(['success' => true, 'message' => 'File already gone']);
    exit;
}

if (unlink($targetPath)) {
    // Try to clean up empty parent directories
    $dir = dirname($targetPath);
    while ($dir !== $BASE_DIR && $dir !== dirname($dir)) {
        $contents = array_diff(scandir($dir), ['.', '..']);
        if (empty($contents)) {
            rmdir($dir);
            $dir = dirname($dir);
        } else {
            break;
        }
    }
    echo json_encode(['success' => true, 'path' => $relativePath]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete file']);
}
