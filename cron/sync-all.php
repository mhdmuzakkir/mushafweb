<?php

$config = [
    'rclone' => '/home/bin/rclone',
    'local_base' => '/home/www/mushaf.linuxproguru.com/mushaftasks/',
    'ftp_remote' => 'myftp:/mushaftasks/',
    'drive_remote' => 'gdrive:/mushaftasks/',
    'log_file' => '/home/www/mushaf.linuxproguru.com/logs/sync.log'
];

// Ensure directories exist
foreach (['pt-completed', 'page-tasks', 'pt-done'] as $dir) {
    if (!is_dir($config['local_base'] . $dir)) {
        mkdir($config['local_base'] . $dir, 0755, true);
    }
}

function log_msg($msg) {
    global $config;
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($config['log_file'], $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

log_msg("=== Starting Sync ===");

// STEP 1: Download new completed markers from Drive to FTP/Local
log_msg("Downloading pt-completed from Drive...");
exec("{$config['rclone']} copy {$config['drive_remote']}pt-completed {$config['ftp_remote']}pt-completed 2>&1", $out, $ret);
if ($ret !== 0) log_msg("Warning: Download issues: " . implode("\n", $out));

// STEP 2: Process local files immediately
$processed = 0;
$ptCompletedDir = $config['local_base'] . 'pt-completed';

if (is_dir($ptCompletedDir)) {
    $riwayahs = array_diff(scandir($ptCompletedDir), ['.', '..']);
    
    foreach ($riwayahs as $riwayah) {
        $riwayahPath = $ptCompletedDir . '/' . $riwayah;
        if (!is_dir($riwayahPath)) continue;
        
        $files = glob($riwayahPath . '/*-pt-completed.json');
        
        foreach ($files as $completedFile) {
            if (!preg_match('/(\d+)-pt-completed\.json$/', basename($completedFile), $m)) continue;
            
            $page = $m[1];
            $tasksFile = $config['local_base'] . "page-tasks/$riwayah/$page-tasks.json";
            $doneDir = $config['local_base'] . "pt-done/$riwayah";
            $doneFile = "$doneDir/$page-tasks.json";
            
            // Skip if tasks file doesn't exist (orphan)
            if (!file_exists($tasksFile)) {
                unlink($completedFile);
                log_msg("Cleaned orphan: $riwayah/$page");
                continue;
            }
            
            // Skip if completed is not newer
            if (filemtime($completedFile) <= filemtime($tasksFile)) {
                log_msg("Skip (not newer): $riwayah/$page");
                continue;
            }
            
            // Process merge
            try {
                $tasksData = json_decode(file_get_contents($tasksFile), true) ?: [];
                $completedData = json_decode(file_get_contents($completedFile), true) ?: [];
                
                // Normalize arrays
                $tasks = isset($tasksData['tasks']) ? $tasksData['tasks'] : (array)$tasksData;
                $completed = isset($completedData['tasks']) ? $completedData['tasks'] : (array)$completedData;
                
                // Load existing done data
                $existing = [];
                if (file_exists($doneFile)) {
                    $d = json_decode(file_get_contents($doneFile), true) ?: [];
                    $existing = isset($d['tasks']) ? $d['tasks'] : (array)$d;
                }
                
                // Merge
                $merged = array_merge((array)$existing, (array)$tasks, (array)$completed);
                
                // Save
                if (!is_dir($doneDir)) mkdir($doneDir, 0755, true);
                file_put_contents($doneFile, json_encode(['tasks' => $merged], JSON_PRETTY_PRINT));
                
                // Delete local sources
                unlink($tasksFile);
                unlink($completedFile);
                
                // STEP 3: Move files in Drive (instead of delete) to archive folders
                log_msg("Moving to archive: $riwayah/$page");
                
                // Create archive folders if needed
                exec("{$config['rclone']} mkdir {$config['drive_remote']}ptc-done/$riwayah 2>&1");
                exec("{$config['rclone']} mkdir {$config['drive_remote']}pt-tasks-archive/$riwayah 2>&1");
                
                // Move pt-completed to ptc-done (archive)
                exec("{$config['rclone']} move {$config['drive_remote']}pt-completed/$riwayah/$page-pt-completed.json {$config['drive_remote']}ptc-done/$riwayah/ 2>&1", $out1, $ret1);
                if ($ret1 !== 0) {
                    log_msg("Warning: Could not move pt-completed $riwayah/$page: " . implode("\n", $out1));
                }
                
                // Move page-tasks to archive (or use deletefile if you prefer)
                exec("{$config['rclone']} move {$config['drive_remote']}page-tasks/$riwayah/$page-tasks.json {$config['drive_remote']}pt-tasks-archive/$riwayah/ 2>&1", $out2, $ret2);
                if ($ret2 !== 0) {
                    log_msg("Warning: Could not move page-tasks $riwayah/$page");
                }
                
                $processed++;
                
            } catch (Exception $e) {
                log_msg("ERROR $riwayah/$page: " . $e->getMessage());
            }
        }
    }
}

log_msg("Processed: $processed files");

// STEP 4: Sync updated page-tasks back to Drive (reflects local deletions)
if ($processed > 0) {
    log_msg("Syncing page-tasks to Drive...");
    exec("{$config['rclone']} sync {$config['ftp_remote']}page-tasks {$config['drive_remote']}page-tasks 2>&1", $out, $ret);
    if ($ret !== 0) log_msg("Warning: Upload issues");
}

log_msg("=== Done ===");