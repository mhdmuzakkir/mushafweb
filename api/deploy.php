<?php
/**
 * Deploy webhook — runs `git pull origin master` on the server.
 *
 * Usage:
 *   1. Set $DEPLOY_SECRET below to a random string.
 *   2. Call this endpoint after pushing:
 *      https://mushaf.linuxproguru.com/api/deploy.php?token=YOUR_SECRET
 *   3. (Optional) Configure your Git provider's webhook to call that URL
 *      automatically on every push.
 */

// --- Configuration ---------------------------------------------------------

// Change this to a strong random secret. Keep it private.
$DEPLOY_SECRET = 'webupload1312';

// Absolute path to the Git repository on the server.
// Adjust if your project is checked out somewhere else.
$REPO_DIR = realpath(__DIR__ . '/..');

// Branch to pull.
$BRANCH = 'master';

// Log file for deploy attempts.
$LOG_FILE = __DIR__ . '/../logs/deploy.log';

// --- Helper functions ------------------------------------------------------

function jsonResponse($success, $message, $details = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'time' => date('Y-m-d H:i:s')
    ], $details), JSON_PRETTY_PRINT);
    exit;
}

function writeLog($line) {
    global $LOG_FILE;
    $dir = dirname($LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function runCommand($cmd, $cwd) {
    $descriptors = [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w']  // stderr
    ];

    $env = array_merge($_ENV, [
        'HOME' => getenv('HOME') ?: '/tmp',
        'GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null'
    ]);

    $process = proc_open($cmd, $descriptors, $pipes, $cwd, $env);
    if (!is_resource($process)) {
        return ['success' => false, 'output' => 'Failed to start process', 'exitCode' => -1];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'success' => $exitCode === 0,
        'output' => trim($stdout . "\n" . $stderr),
        'exitCode' => $exitCode
    ];
}

// --- Security check --------------------------------------------------------

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$headerToken = '';

// Support Bearer token in Authorization header (used by GitHub/GitLab webhooks)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $headerToken = trim($m[1]);
}

$providedToken = $headerToken ?: $token;

if (empty($providedToken)) {
    http_response_code(401);
    writeLog('Rejected: missing token from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    jsonResponse(false, 'Missing deploy token');
}

if ($providedToken !== $DEPLOY_SECRET) {
    http_response_code(403);
    writeLog('Rejected: invalid token from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    jsonResponse(false, 'Invalid deploy token');
}

// --- Run git pull ----------------------------------------------------------

if (!is_dir($REPO_DIR . '/.git')) {
    http_response_code(500);
    writeLog('Error: ' . $REPO_DIR . ' is not a Git repository');
    jsonResponse(false, 'Repository not found at ' . $REPO_DIR);
}

writeLog('Started deploy from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Optionally stash local changes before pulling (uncomment if needed)
// runCommand('git stash', $REPO_DIR);

$result = runCommand('git pull origin ' . escapeshellarg($BRANCH), $REPO_DIR);
writeLog('Result: exit=' . $result['exitCode'] . ' output=' . str_replace("\n", ' | ', $result['output']));

if ($result['success']) {
    jsonResponse(true, 'Deploy successful', [
        'branch' => $BRANCH,
        'repository' => $REPO_DIR,
        'output' => $result['output']
    ]);
} else {
    http_response_code(500);
    jsonResponse(false, 'Deploy failed', [
        'branch' => $BRANCH,
        'repository' => $REPO_DIR,
        'output' => $result['output'],
        'exitCode' => $result['exitCode']
    ]);
}
