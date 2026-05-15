<?php
/**
 * Mushaf WebP Upload API
 * Receives WebP files and saves them with the same relative path structure
 */

// Configuration
$API_KEY = 'mushaf-webp-secret-2026'; // CHANGE THIS in production!
$BASE_DIR = __DIR__ . '/../webp/';
$MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB

// CORS headers (adjust origin as needed)
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

// Validate input
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $err = isset($_FILES['file']) ? $_FILES['file']['error'] : 'No file uploaded';
    echo json_encode(['success' => false, 'error' => 'Upload error: ' . $err]);
    exit;
}

if (!isset($_POST['relative_path']) || empty($_POST['relative_path'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing relative_path']);
    exit;
}

$file = $_FILES['file'];
$relativePath = $_POST['relative_path'];

// Security: sanitize relative path
$relativePath = str_replace('\\', '/', $relativePath);
$relativePath = ltrim($relativePath, '/');

// Prevent directory traversal
if (strpos($relativePath, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid path']);
    exit;
}

// Validate extension
$ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
if ($ext !== 'webp') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Only .webp files allowed']);
    exit;
}

// Check file size
if ($file['size'] > $MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit;
}

// Ensure base directory exists
if (!is_dir($BASE_DIR)) {
    mkdir($BASE_DIR, 0755, true);
}

$targetPath = $BASE_DIR . $relativePath;
$targetDir = dirname($targetPath);

// Create directory structure
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode([
        'success' => true,
        'path' => $relativePath,
        'size' => $file['size']
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}
