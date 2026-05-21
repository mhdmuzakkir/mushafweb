<?php
// extract-pdfs.php - Place in web root
// Security: Only run with secret key
$secret = $_GET['key'] ?? '';
if ($secret !== 'your-secret-key-123') {
    http_response_code(403);
    exit('Access denied');
}

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
set_time_limit(300);

$log = [];
$startTime = time();

function logMsg($msg) {
    global $log;
    $log[] = date('H:i:s') . ' - ' . $msg;
    echo $msg . "<br>\n";
    flush();
    ob_flush();
}

function getJuzFromPage($page) {
    $map = [1=>21,2=>41,3=>61,4=>81,5=>101,6=>121,7=>141,8=>161,9=>181,10=>201,11=>221,12=>241,13=>261,14=>281,15=>301,16=>321,17=>341,18=>361,19=>381,20=>401,21=>421,22=>441,23=>461,24=>481,25=>501,26=>521,27=>541,28=>561,29=>581,30=>604];
    foreach ($map as $j => $max) if ($page <= $max) return $j;
    return 1;
}

// Updated path: ai-files is in parent directory
$aiFilesDir = __DIR__ . '/../ai-files';
$riwayahs = [];

if (file_exists($aiFilesDir)) {
    $items = scandir($aiFilesDir);
    foreach ($items as $item) {
        if ($item[0] !== '.' && is_dir($aiFilesDir . '/' . $item)) {
            $riwayahs[] = $item;
        }
    }
}

if (empty($riwayahs)) {
    logMsg('No riwayahs found in ai-files/');
    exit;
}

$totalExtracted = 0;
$totalSkipped = 0;
$totalCleaned = 0;

foreach ($riwayahs as $riwayah) {
    logMsg("Processing: $riwayah");
    
    // Updated paths
    $base = __DIR__ . '/../ai-files/' . $riwayah;
    $cache = __DIR__ . '/../cache/' . $riwayah;
    
    if (!file_exists($base)) {
        logMsg("  Directory not found: $base");
        continue;
    }
    
    if (!file_exists($cache)) {
        mkdir($cache, 0755, true);
        logMsg("  Created cache directory");
    }
    
    $statuses = [
        'ajza' => ['folder' => 'Ajza', 'hasJuz' => true],
        'review' => ['folder' => 'Review Task', 'hasJuz' => false],
        'completed' => ['folder' => 'Completed', 'hasJuz' => true, 'subFolder' => 'Ajza']
    ];
    
    foreach ($statuses as $status => $config) {
        $folderName = $config['folder'];
        $path = $base . '/' . $folderName;
        
        if (!file_exists($path)) {
            continue;
        }
        
        logMsg("  Scanning: $folderName");
        
        if ($config['hasJuz']) {
            if (isset($config['subFolder'])) {
                $path = $path . '/' . $config['subFolder'];
                if (!file_exists($path)) {
                    logMsg("    Subfolder not found: $path");
                    continue;
                }
            }
            
            for ($juz = 1; $juz <= 30; $juz++) {
                $juzFolder = $path . '/' . str_pad($juz, 2, '0', STR_PAD_LEFT);
                if (!file_exists($juzFolder)) continue;
                
                processFolder($juzFolder, $cache, $status, $riwayah, $totalExtracted, $totalSkipped, $totalCleaned, $startTime);
                
                if (time() - $startTime > 240) break 2;
            }
        } else {
            processFolder($path, $cache, $status, $riwayah, $totalExtracted, $totalSkipped, $totalCleaned, $startTime);
        }
    }
}

function processFolder($folderPath, $cacheDir, $status, $riwayah, &$totalExtracted, &$totalSkipped, &$totalCleaned, $startTime) {
    $files = scandir($folderPath);
    $filesFound = 0;
    
    foreach ($files as $file) {
        if ($file[0] === '.' || !preg_match('/\.ai$/i', $file)) continue;
        
        $filesFound++;
        $aiPath = $folderPath . '/' . $file;
        $aiTime = filemtime($aiPath);
        
        preg_match('/^(\d+)/', $file, $matches);
        if (!$matches) continue;
        
        $pageNum = intval($matches[1]);
        $pageFormatted = sprintf('%03d', $pageNum);
        $cacheFile = "$cacheDir/$pageFormatted-$status.pdf";
        
        $oldPatterns = [
            "$cacheDir/$pageNum-$status.pdf",
            "$cacheDir/" . sprintf('%02d', $pageNum) . "-$status.pdf",
        ];
        
        foreach ($oldPatterns as $oldFile) {
            if (file_exists($oldFile) && $oldFile !== $cacheFile) {
                unlink($oldFile);
                $totalCleaned++;
            }
        }
        
        if (file_exists($cacheFile)) {
            $cacheTime = filemtime($cacheFile);
            
            if ($aiTime > $cacheTime) {
                logMsg("    Page $pageFormatted: AI newer, updating...");
                unlink($cacheFile);
                $totalCleaned++;
            } else if ((time() - $cacheTime) < 86400) {
                $totalSkipped++;
                continue;
            }
        }
        
        $data = file_get_contents($aiPath);
        $pdfStart = strpos($data, '%PDF');
        $pdfEnd = strrpos($data, '%%EOF', $pdfStart);
        
        if ($pdfStart !== false && $pdfEnd !== false) {
            $pdfEnd += strlen('%%EOF');
            $pdfData = substr($data, $pdfStart, $pdfEnd - $pdfStart);
            
            if (strlen($pdfData) > 1000) {
                file_put_contents($cacheFile, $pdfData);
                touch($cacheFile, $aiTime);
                $totalExtracted++;
                
                if ($totalExtracted % 10 == 0) {
                    logMsg("    Progress: $totalExtracted extracted...");
                }
            }
        }
    }
    
    if ($filesFound > 0) {
        logMsg("    Found $filesFound AI files in " . basename($folderPath));
    }
}

logMsg(" ");
logMsg("Checking for orphaned cache files...");

foreach ($riwayahs as $riwayah) {
    // Updated path
    $cacheDir = __DIR__ . '/../cache/' . $riwayah;
    if (!file_exists($cacheDir)) continue;
    
    $cacheFiles = glob($cacheDir . '/*.pdf');
    
    foreach ($cacheFiles as $cacheFile) {
        $filename = basename($cacheFile);
        if (!preg_match('/^(\d+)-(ajza|review|completed)\.pdf$/', $filename, $m)) continue;
        
        $page = $m[1];
        $status = $m[2];
        $pageInt = intval($page);
        $juz = getJuzFromPage($pageInt);
        $juzPadded = str_pad($juz, 2, '0', STR_PAD_LEFT);
        
        // Updated path
        $base = __DIR__ . '/../ai-files/' . $riwayah;
        $found = false;
        
        if ($status === 'ajza') {
            $aiPath = "$base/Ajza/$juzPadded/$page-*.ai";
            if (glob($aiPath)) $found = true;
        } 
        elseif ($status === 'completed') {
            $aiPath1 = "$base/Completed/Ajza/$juzPadded/$page-*.ai";
            $aiPath2 = "$base/Completed/$juzPadded/$page-*.ai";
            if (glob($aiPath1) || glob($aiPath2)) $found = true;
        } 
        elseif ($status === 'review') {
            $aiPath = "$base/Review Task/$page-*.ai";
            if (glob($aiPath)) $found = true;
        }
        
        if (!$found) {
            unlink($cacheFile);
            $totalCleaned++;
            logMsg("  Deleted orphaned: $filename");
        }
    }
}

// Updated path
$logFile = __DIR__ . '/../cache/extraction-log-' . date('Y-m-d') . '.txt';
file_put_contents($logFile, implode("\n", $log));

logMsg(" ");
logMsg("=== SUMMARY ===");
logMsg("Total extracted/updated: $totalExtracted");
logMsg("Total skipped (up to date): $totalSkipped");
logMsg("Total cleaned (old/orphaned): $totalCleaned");
logMsg("Time elapsed: " . (time() - $startTime) . " seconds");
logMsg("Log saved to: $logFile");
?>
