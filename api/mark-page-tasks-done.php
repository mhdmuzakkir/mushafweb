<?php
/**
 * Mushaf Task Manager - Auto Cleanup Script with Merging
 * Merges page-tasks + pt-completed into pt-done (appends if exists)
 */
 $isCli = (php_sapi_name() === 'cli');

// Only set HTTP headers if running via web, not CLI
if (!$isCli) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

$config = [
    'tasksBasePath' => '/home/www/mushaf.linuxproguru.com/mushaftasks/',
    // ... rest of your config
];

// CORS only for web
if (!$isCli && $config['enableCors']) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
// Allow cron trigger via GET with secret
if (isset($_GET['secret']) && $_GET['secret'] === 'your-random-secret-here' && isset($_GET['checkall'])) {
    // Scan all files in pt-completed/ and process them
    // ... existing logic but looping through all files found
}
$config = [
    'tasksBasePath' => __DIR__ . '/../mushaftasks/',
    'enableCors' => true
];

if ($config['enableCors']) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$results = [
    'success' => true,
    'processed' => [],
    'skipped' => [],
    'errors' => [],
    'summary' => [
        'total_checked' => 0,
        'merged' => 0,
        'cleaned_markers' => 0,
        'skipped_time' => 0,
        'errors_count' => 0
    ]
];

$ptCompletedDir = $config['tasksBasePath'] . 'pt-completed';

if (!file_exists($ptCompletedDir)) {
    echo json_encode([
        'success' => false,
        'message' => 'pt-completed directory not found',
        'path_checked' => $ptCompletedDir
    ]);
    exit;
}

$riwayahFolders = array_filter(glob($ptCompletedDir . '/*'), 'is_dir');

if (empty($riwayahFolders)) {
    echo json_encode([
        'success' => true,
        'message' => 'No riwayah folders found',
        'action' => 'nothing_to_do'
    ]);
    exit;
}

foreach ($riwayahFolders as $riwayahPath) {
    $riwayah = basename($riwayahPath);
    
    // FIXED: Look for *-pt-completed.json instead of *-completed.json
    $completedFiles = glob($riwayahPath . '/*-pt-completed.json');
    
    foreach ($completedFiles as $completedFile) {
        $results['summary']['total_checked']++;
        
        $filename = basename($completedFile);
        
        // FIXED: Regex to match XXX-pt-completed.json
        if (!preg_match('/^(\d+)-pt-completed\.json$/i', $filename, $matches)) {
            continue;
        }
        
        $page = $matches[1]; // Keeps leading zeros (003)
        
        // Define paths
        $pageTasksFile = $config['tasksBasePath'] . 'page-tasks/' . $riwayah . '/' . $page . '-tasks.json';
        $ptDoneDir = $config['tasksBasePath'] . 'pt-done/' . $riwayah;
        $ptDoneFile = $ptDoneDir . '/' . $page . '-tasks.json';
        
        $completedTime = filemtime($completedFile);
        
        // Case 1: No page-tasks file exists (orphan marker)
        if (!file_exists($pageTasksFile)) {
            if (unlink($completedFile)) {
                $results['processed'][] = [
                    'riwayah' => $riwayah,
                    'page' => $page,
                    'action' => 'removed_orphan_marker'
                ];
                $results['summary']['cleaned_markers']++;
            } else {
                $results['errors'][] = [
                    'riwayah' => $riwayah,
                    'page' => $page,
                    'error' => 'Failed to delete orphan marker'
                ];
                $results['summary']['errors_count']++;
            }
            continue;
        }
        
        $pageTasksTime = filemtime($pageTasksFile);
        
        // Case 2: Check timestamps
        if ($completedTime <= $pageTasksTime) {
            $results['skipped'][] = [
                'riwayah' => $riwayah,
                'page' => $page,
                'reason' => 'completed_not_newer',
                'completed_time' => date('Y-m-d H:i:s', $completedTime),
                'tasks_time' => date('Y-m-d H:i:s', $pageTasksTime)
            ];
            $results['summary']['skipped_time']++;
            continue;
        }
        
        // Case 3: Merge and archive
        try {
            // Read source files
            $pageTasksJson = file_get_contents($pageTasksFile);
            $completedJson = file_get_contents($completedFile);
            
            if ($pageTasksJson === false || $completedJson === false) {
                throw new Exception('Failed to read source files');
            }
            
            $pageTasksData = json_decode($pageTasksJson, true);
            $completedData = json_decode($completedJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in source files: ' . json_last_error_msg());
            }
            
            // Normalize to arrays (handle both flat arrays and objects with 'tasks' key)
            if (!is_array($pageTasksData)) $pageTasksData = [];
            if (!is_array($completedData)) $completedData = [];
            
            // If data has 'tasks' key, use that, otherwise assume flat array
            $pageTasksArray = isset($pageTasksData['tasks']) ? $pageTasksData['tasks'] : $pageTasksData;
            $completedArray = isset($completedData['tasks']) ? $completedData['tasks'] : $completedData;
            
            // Ensure arrays are indexed (not associative)
            $pageTasksArray = array_values($pageTasksArray);
            $completedArray = array_values($completedArray);
            
            // Load existing pt-done data if present
            $existingArray = [];
            $existedPreviously = false;
            
            if (file_exists($ptDoneFile)) {
                $existingJson = file_get_contents($ptDoneFile);
                if ($existingJson !== false) {
                    $existingData = json_decode($existingJson, true);
                    if (is_array($existingData)) {
                        $existingArray = isset($existingData['tasks']) ? $existingData['tasks'] : $existingData;
                        $existingArray = array_values($existingArray);
                        $existedPreviously = true;
                    }
                }
            }
            
            // Merge: existing + page-tasks + pt-completed
            $mergedArray = array_merge($existingArray, $pageTasksArray, $completedArray);
            
            // Optional: Remove duplicates based on 'id' or 'taskId' if your tasks have unique IDs
            // Uncomment below if tasks have unique 'id' fields to prevent duplicates
            /*
            $seenIds = [];
            $dedupedArray = [];
            foreach ($mergedArray as $task) {
                $id = $task['id'] ?? $task['taskId'] ?? null;
                if ($id === null || !isset($seenIds[$id])) {
                    if ($id !== null) $seenIds[$id] = true;
                    $dedupedArray[] = $task;
                }
            }
            $mergedArray = $dedupedArray;
            */
            
            // Reconstruct final data structure (preserve 'tasks' wrapper if original used it)
            if (isset($pageTasksData['tasks']) || isset($completedData['tasks'])) {
                $finalData = ['tasks' => $mergedArray];
            } else {
                $finalData = $mergedArray;
            }
            
            // Ensure directory exists
            if (!file_exists($ptDoneDir)) {
                if (!mkdir($ptDoneDir, 0755, true)) {
                    throw new Exception('Failed to create pt-done directory');
                }
            }
            
            // Write merged file
            $writeResult = file_put_contents(
                $ptDoneFile, 
                json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            
            if ($writeResult === false) {
                throw new Exception('Failed to write merged pt-done file');
            }
            
            // Preserve timestamp from newest source
            touch($ptDoneFile, max($pageTasksTime, $completedTime));
            
            // Delete both source files only after successful write
            $deletedPageTasks = unlink($pageTasksFile);
            $deletedCompleted = unlink($completedFile);
            
            if (!$deletedPageTasks || !$deletedCompleted) {
                // Log warning but don't fail - file is already merged successfully
                $results['warnings'][] = [
                    'riwayah' => $riwayah,
                    'page' => $page,
                    'warning' => 'Merged successfully but failed to delete source(s): ' . 
                                 (!$deletedPageTasks ? 'page-tasks ' : '') . 
                                 (!$deletedCompleted ? 'pt-completed' : '')
                ];
            }
            
            $results['processed'][] = [
                'riwayah' => $riwayah,
                'page' => $page,
                'action' => 'merged_and_archived',
                'mode' => $existedPreviously ? 'appended_to_existing' : 'created_new',
                'counts' => [
                    'existing' => count($existingArray),
                    'page_tasks' => count($pageTasksArray),
                    'pt_completed' => count($completedArray),
                    'total_merged' => count($mergedArray)
                ],
                'saved_to' => str_replace(__DIR__, '.', $ptDoneFile),
                'time_diff_hours' => round(($completedTime - $pageTasksTime) / 3600, 2)
            ];
            $results['summary']['merged']++;
            
        } catch (Exception $e) {
            $results['errors'][] = [
                'riwayah' => $riwayah,
                'page' => $page,
                'error' => $e->getMessage()
            ];
            $results['summary']['errors_count']++;
        }
    }
}

// Build summary message
if ($results['summary']['merged'] > 0) {
    $results['message'] = "Successfully merged and archived {$results['summary']['merged']} task file(s)";
} elseif ($results['summary']['cleaned_markers'] > 0) {
    $results['message'] = "Cleaned {$results['summary']['cleaned_markers']} orphan marker(s)";
} elseif ($results['summary']['skipped_time'] > 0) {
    $results['message'] = "Found {$results['summary']['skipped_time']} task(s) not ready for archiving";
} else {
    $results['message'] = "Nothing to process";
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>