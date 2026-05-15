<?php
/**
 * Cleanup Completed Tasks - Multi-Riwayah Support
 * Only deletes page-tasks if they are OLDER than the completed file
 * New Structure: mushaftasks/{folder}/{riwayah}/
 */

$config = [
    'tasksBasePath' => __DIR__ . '/../mushaftasks/',
    'dryRun' => false,
    'logFile' => __DIR__ . '/../mushaftasks/cleanup.log',
    'timeFormat' => 'Y-m-d H:i:s'
];

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>
<html>
<head>
    <title>Cleanup Completed Tasks</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #1a1a1a; color: #f5f5f5; padding: 20px; }
        h2 { color: #22c55e; }
        .riwayah-section { background: #2d2d2d; border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid #404040; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .info { color: #a3a3a3; }
        .warning { color: #f59e0b; }
        .deleted { background: rgba(34, 197, 94, 0.1); padding: 4px 8px; border-radius: 4px; }
        .skipped { background: rgba(245, 158, 11, 0.1); padding: 4px 8px; border-radius: 4px; color: #f59e0b; }
        .archived { background: rgba(59, 130, 246, 0.1); padding: 4px 8px; border-radius: 4px; color: #3b82f6; }
        pre { background: #1a1a1a; padding: 10px; border-radius: 8px; overflow-x: auto; }
        .stats { display: flex; gap: 20px; margin: 10px 0; flex-wrap: wrap; }
        .stat-box { background: #3d3d3d; padding: 10px 20px; border-radius: 8px; }
        .dry-run { background: #f59e0b; color: #000; padding: 4px 12px; border-radius: 20px; font-weight: bold; display: inline-block; margin-bottom: 10px; }
        .time-diff { font-size: 0.85rem; color: #737373; }
    </style>
</head>
<body>";

echo "<h2>🧹 Cleanup Completed Tasks (Age-Aware)</h2>";
echo "<div class='info'>New Structure: mushaftasks/{folder}/{riwayah}/<br>";
echo "Archives completed page-tasks to pt-done/ before deletion</div><br>";

if ($config['dryRun']) {
    echo "<div class='dry-run'>⚠️ DRY RUN MODE - No files will be deleted</div>";
}

if (!file_exists($config['tasksBasePath'])) {
    die("<div class='error'>❌ Tasks base path not found: {$config['tasksBasePath']}</div>");
}

$riwayahs = [];
$ptCompletedBase = $config['tasksBasePath'] . 'pt-completed';

if (file_exists($ptCompletedBase) && is_dir($ptCompletedBase)) {
    $items = scandir($ptCompletedBase);
    foreach ($items as $item) {
        if ($item[0] === '.' || !is_dir($ptCompletedBase . '/' . $item)) continue;
        $riwayahs[] = $item;
    }
}

$pageTasksBase = $config['tasksBasePath'] . 'page-tasks';
if (file_exists($pageTasksBase) && is_dir($pageTasksBase)) {
    $items = scandir($pageTasksBase);
    foreach ($items as $item) {
        if ($item[0] === '.' || !is_dir($pageTasksBase . '/' . $item)) continue;
        if (!in_array($item, $riwayahs)) {
            $riwayahs[] = $item;
        }
    }
}

if (empty($riwayahs)) {
    echo "<div class='error'>❌ No riwayahs found in mushaftasks/</div>";
    exit;
}

sort($riwayahs);

echo "<div class='stats'>";
echo "<div class='stat-box'>Found <strong>" . count($riwayahs) . "</strong> riwayah(s)</div>";
echo "</div>";

$totalArchived = 0;
$totalDeleted = 0;
$totalSkipped = 0;
$totalErrors = 0;
$logEntries = [];

foreach ($riwayahs as $riwayah) {
    echo "<div class='riwayah-section'>";
    echo "<h3>📁 $riwayah</h3>";
    
    $ptCompletedDir = $config['tasksBasePath'] . 'pt-completed/' . $riwayah;
    $pageTasksDir = $config['tasksBasePath'] . 'page-tasks/' . $riwayah;
    $ptDoneDir = $config['tasksBasePath'] . 'pt-done/' . $riwayah;
    
    if (!file_exists($ptDoneDir) && !$config['dryRun']) {
        mkdir($ptDoneDir, 0755, true);
    }
    
    if (!file_exists($ptCompletedDir)) {
        echo "<div class='info'>ℹ️ No pt-completed folder for $riwayah</div>";
        echo "</div>";
        continue;
    }
    
    $completedFiles = glob($ptCompletedDir . '/*-completed.json');
    $archivedCount = 0;
    $deletedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;
    
    echo "<div class='info'>Found " . count($completedFiles) . " completed file(s)</div>";
    echo "<ul>";
    
    foreach ($completedFiles as $completedFile) {
        $filename = basename($completedFile);
        $completedTime = filemtime($completedFile);
        $completedDate = date($config['timeFormat'], $completedTime);
        
        if (!preg_match('/^(\d+)(-[^.]*)?-completed\.json$/', $filename, $matches)) {
            if (!preg_match('/^(\d+)(-completed)?\.json$/', $filename, $matches)) {
                echo "<li>⚠️  Skipping invalid format: $filename</li>";
                continue;
            }
        }
        
        $page = $matches[1];
        $taskFile = $pageTasksDir . '/' . $page . '-tasks.json';
        
        if (file_exists($taskFile)) {
            $taskFilename = basename($taskFile);
            $taskTime = filemtime($taskFile);
            $taskDate = date($config['timeFormat'], $taskTime);
            
            echo "<li>";
            echo "<strong>Page $page:</strong><br>";
            echo "<span class='time-diff'>Completed: $completedDate | Task: $taskDate</span><br>";
            
            if ($taskTime < $completedTime) {
                $ageDiff = $completedTime - $taskTime;
                $hoursOld = round($ageDiff / 3600, 1);
                
                if ($config['dryRun']) {
                    echo "<span class='archived'>[WOULD ARCHIVE]</span> Task is $hoursOld hours older";
                    $archivedCount++;
                    $deletedCount++;
                } else {
                    $ptDoneFile = $ptDoneDir . '/' . $page . '-tasks.json';
                    $archived = false;
                    
                    if (copy($taskFile, $ptDoneFile)) {
                        touch($ptDoneFile, $taskTime);
                        $archived = true;
                        $archivedCount++;
                    }
                    
                    if ($archived && unlink($taskFile)) {
                        echo "<span class='success'>✅ Archived & Deleted</span> (Task was $hoursOld hours older)";
                        $deletedCount++;
                        $logEntries[] = date('Y-m-d H:i:s') . " | $riwayah | Archived & Deleted: $taskFilename";
                    } else {
                        echo "<span class='error'>❌ Failed</span>";
                        $errorCount++;
                    }
                }
            } else {
                $ageDiff = $taskTime - $completedTime;
                $hoursNew = round($ageDiff / 3600, 1);
                echo "<span class='skipped'>⏭️ SKIPPED</span> Task is $hoursNew hours NEWER than completed (keeping it)";
                $skippedCount++;
            }
            echo "</li>";
        } else {
            echo "<li class='info'>Page $page: ✓ Already clean (no page-tasks file)</li>";
        }
        
        if (!$config['dryRun']) {
            unlink($completedFile);
        }
    }
    
    echo "</ul>";
    
    echo "<div class='stats'>";
    if ($archivedCount > 0) echo "<div class='stat-box' style='color:#3b82f6'>Archived: $archivedCount</div>";
    if ($deletedCount > 0) echo "<div class='stat-box success'>Deleted: $deletedCount</div>";
    if ($skippedCount > 0) echo "<div class='stat-box warning'>Skipped (newer): $skippedCount</div>";
    if ($errorCount > 0) echo "<div class='stat-box error'>Errors: $errorCount</div>";
    echo "</div>";
    
    echo "</div>";
    
    $totalArchived += $archivedCount;
    $totalDeleted += $deletedCount;
    $totalSkipped += $skippedCount;
    $totalErrors += $errorCount;
}

echo "<div class='riwayah-section' style='background: #1a1a1a; border: 2px solid #22c55e;'>";
echo "<h3>📊 Total Summary</h3>";
echo "<div class='stats'>";
if ($totalArchived > 0) echo "<div class='stat-box' style='color:#3b82f6'>Archived: $totalArchived</div>";
if ($totalDeleted > 0) echo "<div class='stat-box success'>Deleted: $totalDeleted</div>";
if ($totalSkipped > 0) echo "<div class='stat-box warning'>Skipped (newer): $totalSkipped</div>";
if ($totalErrors > 0) echo "<div class='stat-box error'>Errors: $totalErrors</div>";
echo "<div class='stat-box'>Riwayahs: " . count($riwayahs) . "</div>";
echo "</div>";

if (!$config['dryRun'] && !empty($logEntries)) {
    $logDir = dirname($config['logFile']);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($config['logFile'], implode("\n", $logEntries) . "\n", FILE_APPEND | LOCK_EX);
    echo "<p class='info'>📝 Log saved to: {$config['logFile']}</p>";
    
    if (file_exists($config['logFile']) && filesize($config['logFile']) > 5242880) {
        rename($config['logFile'], $config['logFile'] . '.old');
    }
}

if ($config['dryRun'] && $totalDeleted > 0) {
    echo "<p class='error'>⚠️ This was a dry run. Set \$config['dryRun'] = false to actually process.</p>";
}

echo "</div>";

echo "<div class='info' style='margin-top: 20px;'>Scanned: " . implode(', ', $riwayahs) . "</div>";
echo "</body></html>";
?>
