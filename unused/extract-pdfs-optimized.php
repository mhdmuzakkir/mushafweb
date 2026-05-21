<?php
// extract-pdfs-optimized.php - Smallest + Web Optimized PDFs

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

$secret = $_GET['key'] ?? $argv[1] ?? '';
if ($secret !== 'your-secret-key-123') {
    http_response_code(403);
    die('Access denied');
}

ini_set('memory_limit', '512M');
set_time_limit(3600);

$gsPath = null;
$possiblePaths = [
    __DIR__ . '/../gs',
    __DIR__ . '/../bin/gs',
    '/home/bin/gs',
    '/home/mdmunazir/bin/gs',
];

foreach ($possiblePaths as $path) {
    if (file_exists($path) && is_executable($path)) {
        $gsPath = $path;
        break;
    }
}

if (!$gsPath) {
    $found = shell_exec('find /home -name "gs" -type f -executable 2>/dev/null | head -1');
    if ($found) {
        $gsPath = trim($found);
    }
}

if (!$gsPath || !file_exists($gsPath)) {
    die("ERROR: Ghostscript not found.<br>Checked: " . implode(', ', $possiblePaths));
}

$version = shell_exec(escapeshellarg($gsPath) . ' --version 2>&1');
if (!$version || strpos($version, '.') === false) {
    die("ERROR: Ghostscript found but not working at: $gsPath<br>Output: " . ($version ?: 'none'));
}

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <title>PDF Web Optimization</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #0f0; padding: 20px; line-height: 1.6; }
        .error { color: #f00; }
        .success { color: #0f0; }
        .info { color: #ff0; }
        .stat { color: #0ff; }
        .box { border: 1px solid #0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h2>🚀 PDF Web Optimization Started</h2>
    <div class="box">
';
    ob_flush();
    flush();
}

$log = [];
$startTime = time();
$totalBytesOriginal = 0;
$totalBytesNew = 0;
$totalWebOptimized = 0;

function logMsg($msg) {
    global $log;
    $timestamp = date('H:i:s');
    $log[] = "$timestamp - $msg";
    
    $color = 'success';
    if (stripos($msg, 'error') !== false) $color = 'error';
    if (stripos($msg, 'skip') !== false) $color = 'info';
    if (stripos($msg, 'web') !== false) $color = 'stat';
    
    if (php_sapi_name() === 'cli') {
        echo "[$timestamp] $msg\n";
    } else {
        echo "<span class=\"$color\">[$timestamp] " . htmlspecialchars($msg) . "</span><br>\n";
        ob_flush();
        flush();
    }
}

function getJuzFromPage($page) {
    $map = [1=>21,2=>41,3=>61,4=>81,5=>101,6=>121,7=>141,8=>161,9=>181,10=>201,11=>221,12=>241,13=>261,14=>281,15=>301,16=>321,17=>341,18=>361,19=>381,20=>401,21=>421,22=>441,23=>461,24=>481,25=>501,26=>521,27=>541,28=>561,29=>581,30=>604];
    foreach ($map as $j => $max) if ($page <= $max) return $j;
    return 1;
}

function webOptimizeWithGs($inputFile, $outputFile, $gsPath) {
    $tempFile = dirname($outputFile) . '/temp-' . uniqid() . '.pdf';
    
    $command = sprintf(
        '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 ' .
        '-dPDFSETTINGS=/screen ' .
        '-dFastWebView=true ' .
        '-dNOPAUSE -dQUIET -dBATCH ' .
        '-dColorImageResolution=72 ' .
        '-dGrayImageResolution=72 ' .
        '-dMonoImageResolution=72 ' .
        '-dDownsampleColorImages=true ' .
        '-dDownsampleGrayImages=true ' .
        '-dDownsampleMonoImages=true ' .
        '-dCompressFonts=true ' .
        '-dSubsetFonts=true ' .
        '-sOutputFile=%s %s 2>&1',
        escapeshellarg($gsPath),
        escapeshellarg($tempFile),
        escapeshellarg($inputFile)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0 || !file_exists($tempFile) || filesize($tempFile) < 1000) {
        if (file_exists($tempFile)) unlink($tempFile);
        error_log("Ghostscript failed: " . implode("\n", $output));
        return false;
    }
    
    rename($tempFile, $outputFile);
    return true;
}

function extractAndWebOptimize($aiPath, $outputFile, $gsPath) {
    $data = file_get_contents($aiPath);
    $pdfStart = strpos($data, '%PDF');
    $pdfEnd = strrpos($data, '%%EOF', $pdfStart);
    
    if ($pdfStart === false || $pdfEnd === false) {
        return ['success' => false, 'error' => 'No PDF found in AI'];
    }
    
    $pdfEnd += strlen('%%EOF');
    $pdfData = substr($data, $pdfStart, $pdfEnd - $pdfStart);
    $originalSize = strlen($pdfData);
    
    if ($originalSize < 1000) {
        return ['success' => false, 'error' => 'PDF too small'];
    }
    
    $tempFile = sys_get_temp_dir() . '/extract-' . uniqid() . '.pdf';
    file_put_contents($tempFile, $pdfData);
    
    $success = webOptimizeWithGs($tempFile, $outputFile, $gsPath);
    unlink($tempFile);
    
    if (!$success) {
        return ['success' => false, 'error' => 'Ghostscript optimization failed'];
    }
    
    $newSize = filesize($outputFile);
    
    return [
        'success' => true,
        'original' => $originalSize,
        'new' => $newSize,
        'webOptimized' => true
    ];
}

logMsg("Ghostscript: $gsPath (version " . trim($version) . ")");

// Updated path
$aiFilesDir = __DIR__ . '/../ai-files';
$riwayahs = [];

if (file_exists($aiFilesDir)) {
    foreach (scandir($aiFilesDir) as $item) {
        if ($item[0] !== '.' && is_dir($aiFilesDir . '/' . $item)) {
            $riwayahs[] = $item;
        }
    }
}

if (empty($riwayahs)) {
    die('No riwayahs found');
}

logMsg("Found: " . implode(', ', $riwayahs));

$totalExtracted = 0;
$totalSkipped = 0;
$totalFailed = 0;

foreach ($riwayahs as $riwayah) {
    logMsg("Processing: $riwayah");
    
    // Updated paths
    $base = __DIR__ . '/../ai-files/' . $riwayah;
    $cache = __DIR__ . '/../cache/' . $riwayah;
    
    if (!file_exists($cache)) {
        mkdir($cache, 0755, true);
    }
    
    $statuses = [
        'ajza' => ['folder' => 'Ajza', 'hasJuz' => true],
        'review' => ['folder' => 'Review Task', 'hasJuz' => false],
        'completed' => ['folder' => 'Completed', 'hasJuz' => true, 'subFolder' => 'Ajza']
    ];
    
    foreach ($statuses as $status => $config) {
        $folderName = $config['folder'];
        $path = $base . '/' . $folderName;
        
        if (!file_exists($path)) continue;
        
        if ($config['hasJuz']) {
            if (isset($config['subFolder'])) {
                $path = $path . '/' . $config['subFolder'];
                if (!file_exists($path)) continue;
            }
            
            for ($juz = 1; $juz <= 30; $juz++) {
                $juzFolder = $path . '/' . str_pad($juz, 2, '0', STR_PAD_LEFT);
                if (!file_exists($juzFolder)) continue;
                
                processFolder($juzFolder, $cache, $status, $riwayah, $gsPath, $totalExtracted, $totalSkipped, $totalFailed);
                
                if (time() - $startTime > 1800) break 3;
            }
        } else {
            processFolder($path, $cache, $status, $riwayah, $gsPath, $totalExtracted, $totalSkipped, $totalFailed);
        }
    }
}

function processFolder($folderPath, $cacheDir, $status, $riwayah, $gsPath, &$totalExtracted, &$totalSkipped, &$totalFailed) {
    global $totalBytesOriginal, $totalBytesNew, $totalWebOptimized, $startTime;
    
    $files = scandir($folderPath);
    $aiFiles = [];
    
    foreach ($files as $file) {
        if ($file[0] !== '.' && preg_match('/\.ai$/i', $file)) {
            $aiFiles[] = $file;
        }
    }
    
    if (empty($aiFiles)) return;
    
    logMsg("Found " . count($aiFiles) . " files in " . basename($folderPath));
    
    foreach ($aiFiles as $file) {
        $aiPath = $folderPath . '/' . $file;
        $aiTime = filemtime($aiPath);
        
        preg_match('/^(\d+)/', $file, $matches);
        if (!$matches) continue;
        
        $pageNum = intval($matches[1]);
        $pageFormatted = sprintf('%03d', $pageNum);
        $cacheFile = "$cacheDir/$pageFormatted-$status.pdf";
        
        if (file_exists($cacheFile)) {
            $cacheTime = filemtime($cacheFile);
            if ($cacheTime >= $aiTime && (time() - $cacheTime) < 86400) {
                $totalSkipped++;
                continue;
            }
        }
        
        $result = extractAndWebOptimize($aiPath, $cacheFile, $gsPath);
        
        if ($result['success']) {
            touch($cacheFile, $aiTime);
            $totalExtracted++;
            $totalBytesOriginal += $result['original'];
            $totalBytesNew += $result['new'];
            $totalWebOptimized++;
            
            if ($totalExtracted % 10 == 0) {
                $saved = $totalBytesOriginal - $totalBytesNew;
                $percent = $totalBytesOriginal > 0 ? round(($saved / $totalBytesOriginal) * 100, 1) : 0;
                logMsg("Progress: $totalExtracted files, Saved: $percent%, WebOptimized: $totalWebOptimized");
            }
        } else {
            $totalFailed++;
            logMsg("Failed: $file - " . $result['error']);
        }
        
        if (time() - $startTime > 1850) break;
    }
}

logMsg("Cleaning up...");

foreach ($riwayahs as $riwayah) {
    // Updated path
    $cacheDir = __DIR__ . '/../cache/' . $riwayah;
    if (!file_exists($cacheDir)) continue;
    
    foreach (glob($cacheDir . '/*.pdf') as $cacheFile) {
        $filename = basename($cacheFile);
        if (!preg_match('/^(\d+)-(ajza|review|completed)\.pdf$/', $filename, $m)) continue;
        
        $page = $m[1];
        $status = $m[2];
        $juz = getJuzFromPage(intval($page));
        $juzPadded = str_pad($juz, 2, '0', STR_PAD_LEFT);
        
        // Updated path
        $base = __DIR__ . '/../ai-files/' . $riwayah;
        $found = false;
        
        if ($status === 'ajza') {
            if (glob("$base/Ajza/$juzPadded/$page-*.ai")) $found = true;
        } elseif ($status === 'completed') {
            if (glob("$base/Completed/Ajza/$juzPadded/$page-*.ai") || glob("$base/Completed/$juzPadded/$page-*.ai")) {
                $found = true;
            }
        } elseif ($status === 'review') {
            if (glob("$base/Review Task/$page-*.ai")) $found = true;
        }
        
        if (!$found) {
            unlink($cacheFile);
        }
    }
}

$saved = $totalBytesOriginal - $totalBytesNew;
$percent = $totalBytesOriginal > 0 ? round(($saved / $totalBytesOriginal) * 100, 1) : 0;

logMsg(" ");
logMsg("========== WEB OPTIMIZATION COMPLETE ==========");
logMsg("Extracted: $totalExtracted");
logMsg("Web Optimized: $totalWebOptimized ✓");
logMsg("Skipped: $totalSkipped");
logMsg("Failed: $totalFailed");
logMsg(" ");
logMsg("Size: " . round($totalBytesOriginal/1024/1024, 2) . " MB → " . round($totalBytesNew/1024/1024, 2) . " MB");
logMsg("Saved: " . round($saved/1024/1024, 2) . " MB ($percent%)");
logMsg("Time: " . (time() - $startTime) . " seconds");

// Updated path
$logFile = __DIR__ . '/../cache/webopt-log-' . date('Y-m-d-His') . '.txt';
file_put_contents($logFile, implode("\n", $log));
logMsg("Log: $logFile");

if (php_sapi_name() !== 'cli') {
    echo '</div></body></html>';
}
?>
