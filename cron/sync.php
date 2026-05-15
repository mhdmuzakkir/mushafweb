<?php
$logFile = "/home/logs/rclone_sync.log";
$date = date("Y-m-d H:i:s");
$start = microtime(true);

// Step 1: Download
$cmd1 = "/home/bin/rclone copy gdrive:/mushaftasks/pt-completed myftp:/mushaftasks/pt-completed 2>&1";
$output1 = shell_exec($cmd1);
file_put_contents($logFile, "$date - DL: " . trim($output1) . "\n", FILE_APPEND);

// Step 2: Process (FIXED PATH)
$phpCmd = "/usr/local/php7.3/bin/php /home/www/mushaf.linuxproguru.com/api/mark-page-tasks-done.php 2>&1";
$phpOutput = shell_exec($phpCmd);
file_put_contents($logFile, "$date - PROC: " . substr(trim($phpOutput), 0, 100) . "\n", FILE_APPEND);

// Step 3: Upload
$cmd2 = "/home/bin/rclone sync myftp:/mushaftasks/page-tasks gdrive:/mushaftasks/page-tasks 2>&1";
$output2 = shell_exec($cmd2);
file_put_contents($logFile, "$date - UP: " . trim($output2) . " [" . round(microtime(true)-$start,1) . "s]\n", FILE_APPEND);