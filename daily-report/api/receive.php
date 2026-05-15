<?php
/**
 * Mushaf Report Receiver
 * Receives HTML reports from Google Apps Script and saves to FTP server
 * 
 * Endpoint: https://mushaf.linuxproguru.com/daily-report/api/receive.php
 * Method: POST
 * Content-Type: application/json
 */

// Configuration
define('API_KEY', 'a7f3c9e2d8b4510f6e8a2b9c4d7e1f5a'); // Must match GAS config
define('REPORTS_DIR', '../../reports');  // Relative to api/ folder

define('FTP_HOST', 'mushaf.linuxproguru.com');
define('FTP_USER', 'mushaf_linuxproguru.com');
define('FTP_PASS', 'Vikhara@548');
define('FTP_PORT', 21);

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate API key
if (!isset($data['api_key']) || $data['api_key'] !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Validate required fields
if (!isset($data['date']) || !isset($data['html']) || !isset($data['filename'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: date, html, filename']);
    exit;
}

$date = $data['date'];
$html = $data['html'];
$filename = $data['filename'];

// Validate filename format (YYYY-MM-DD.html)
if (!preg_match('/^\d{4}-\d{2}-\d{2}\.html$/', $filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid filename format. Expected: YYYY-MM-DD.html']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid date format. Expected: YYYY-MM-DD']);
    exit;
}

/**
 * Save file via FTP
 */
function saveViaFTP($filename, $content) {
    $remotePath = REPORTS_DIR . '/' . $filename;
    
    // Connect to FTP
    $ftpConn = ftp_connect(FTP_HOST, FTP_PORT, 30);
    if (!$ftpConn) {
        return ['success' => false, 'error' => 'Could not connect to FTP server'];
    }
    
    // Login
    $login = ftp_login($ftpConn, FTP_USER, FTP_PASS);
    if (!$login) {
        ftp_close($ftpConn);
        return ['success' => false, 'error' => 'FTP login failed'];
    }
    
    // Enable passive mode
    ftp_pasv($ftpConn, true);
    
    // Check if reports directory exists, create if not
    $dirExists = ftp_nlist($ftpConn, REPORTS_DIR);
    if ($dirExists === false) {
        ftp_mkdir($ftpConn, REPORTS_DIR);
    }
    
    // Create temp file
    $tempFile = tempnam(sys_get_temp_dir(), 'mushaf_');
    file_put_contents($tempFile, $content);
    
    // Upload file
    $upload = ftp_put($ftpConn, $remotePath, $tempFile, FTP_BINARY);
    
    // Clean up temp file
    unlink($tempFile);
    
    // Close connection
    ftp_close($ftpConn);
    
    if ($upload) {
        return ['success' => true, 'path' => $remotePath];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file via FTP'];
    }
}

/**
 * Alternative: Save to local file system (if same server)
 */
function saveLocal($filename, $content) {
    $dir = __DIR__ . '/' . REPORTS_DIR;
    $filepath = $dir . '/' . $filename;
    
    // Create reports directory if it doesn't exist
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create reports directory'];
        }
    }
    
    // Save file
    $result = file_put_contents($filepath, $content, LOCK_EX);
    
    if ($result !== false) {
        return ['success' => true, 'path' => $filepath];
    } else {
        return ['success' => false, 'error' => 'Failed to write file'];
    }
}

// Try local save first (works if PHP is on same server as FTP)
$result = saveLocal($filename, $html);

// If local save fails, try FTP
if (!$result['success']) {
    $result = saveViaFTP($filename, $html);
}

// Return response
if ($result['success']) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Report saved successfully',
        'date' => $date,
        'filename' => $filename,
        'path' => $result['path'],
        'viewer_url' => 'https://mushaf.linuxproguru.com/daily-report/?date=' . $date
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $result['error'],
        'date' => $date,
        'filename' => $filename
    ]);
}
