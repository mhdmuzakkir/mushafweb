<?php
/**
 * Save user's last location
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
header('Content-Type: application/json');

$headers = getallheaders();
$token = isset($headers['X-Auth-Token']) ? $headers['X-Auth-Token'] : '';
$user = null;

if ($token) {
    $decoded = base64_decode($token);
    if ($decoded && strpos($decoded, ':') !== false) {
        list($username, $timestamp) = explode(':', $decoded);
        if (time() - $timestamp < 30 * 24 * 60 * 60) {
            $user = $username;
        }
    }
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$saveData = [
    'page' => $data['page'] ?? '',
    'riwayah' => $data['riwayah'] ?? '',
    'juz' => $data['juz'] ?? '',
    'timestamp' => date('c')
];

// Updated path: users is in parent directory
$userDir = __DIR__ . '/../users';
if (!file_exists($userDir)) {
    mkdir($userDir, 0755, true);
}

$file = $userDir . '/' . $user . '_last.json';
file_put_contents($file, json_encode($saveData, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
