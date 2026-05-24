<?php
/**
 * Mushaf WebP to PDF Generator
 * Generates a downloadable PDF from all WebP files for a given riwayah.
 * Pages have cream background matching the viewer theme.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use TCPDF;

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$riwayah = preg_replace('/[^a-zA-Z0-9\s_\-\(\)]/', '', $_GET['riwayah'] ?? '');
if (empty($riwayah)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing riwayah parameter']);
    exit;
}

// Same scanning logic as get-files.php
$baseFolder = __DIR__ . '/../webp/' . $riwayah;
$files = [];

function scanJuzFolders($basePath, $status, $riwayah, &$files) {
    for ($i = 1; $i <= 30; $i++) {
        $juzFolder = $basePath . '/' . str_pad($i, 2, '0', STR_PAD_LEFT);
        if (!file_exists($juzFolder)) continue;
        $items = scandir($juzFolder);
        foreach ($items as $item) {
            if ($item[0] === '.' || !preg_match('/\.webp$/i', $item)) continue;
            $fullPath = $juzFolder . '/' . $item;
            if (!is_file($fullPath)) continue;
            if (preg_match('/^(\d+)(-[^.]*)?\.webp$/i', $item, $matches)) {
                $page = intval($matches[1]);
                $files[] = [
                    'page' => $page,
                    'path' => $fullPath,
                    'status' => $status
                ];
            }
        }
    }
}

function scanFlatFolder($path, $status, $riwayah, &$files) {
    if (!file_exists($path)) return;
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item[0] === '.' || !preg_match('/\.webp$/i', $item)) continue;
        $fullPath = $path . '/' . $item;
        if (!is_file($fullPath)) continue;
        if (preg_match('/^(\d+)(-[^.]*)?\.webp$/i', $item, $matches)) {
            $page = intval($matches[1]);
            $files[] = [
                'page' => $page,
                'path' => $fullPath,
                'status' => $status
            ];
        }
    }
}

// Scan all statuses
scanJuzFolders($baseFolder . '/Ajza', 'ajza', $riwayah, $files);
scanFlatFolder($baseFolder . '/Review Task', 'review', $riwayah, $files);
if (file_exists($baseFolder . '/Completed/Ajza')) {
    scanJuzFolders($baseFolder . '/Completed/Ajza', 'completed', $riwayah, $files);
} else {
    scanJuzFolders($baseFolder . '/Completed', 'completed', $riwayah, $files);
}
if (file_exists($baseFolder . '/Recheck/Ajza')) {
    scanJuzFolders($baseFolder . '/Recheck/Ajza', 'recheck', $riwayah, $files);
} else {
    scanJuzFolders($baseFolder . '/Recheck', 'recheck', $riwayah, $files);
}

// Sort by page number
usort($files, function($a, $b) {
    return $a['page'] - $b['page'];
});

if (empty($files)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'No WebP files found for riwayah: ' . $riwayah]);
    exit;
}

// Build PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Mushaf WebP Exporter');
$pdf->SetAuthor('Mushaf Viewer');
$pdf->SetTitle('Mushaf - ' . $riwayah);
$pdf->SetSubject('Mushaf Pages - ' . $riwayah);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(false);

// Cream background color from viewer CSS --bg-deep: #f5f0e6
$creamR = 245;
$creamG = 240;
$creamB = 230;

$pageWidth = 210;  // A4 width in mm
$pageHeight = 297; // A4 height in mm

foreach ($files as $file) {
    $pdf->AddPage();

    // Fill entire page with cream background
    $pdf->SetFillColor($creamR, $creamG, $creamB);
    $pdf->Rect(0, 0, $pageWidth, $pageHeight, 'F');

    $imgPath = $file['path'];

    // Get image dimensions
    $imgInfo = @getimagesize($imgPath);
    if (!$imgInfo) {
        // Try converting webp to temporary jpg for TCPDF compatibility
        $tmpPath = sys_get_temp_dir() . '/mushaf_pdf_' . md5($imgPath) . '.jpg';
        if (!file_exists($tmpPath)) {
            $img = @imagecreatefromwebp($imgPath);
            if ($img) {
                imagejpeg($img, $tmpPath, 95);
                imagedestroy($img);
            }
        }
        if (file_exists($tmpPath)) {
            $imgPath = $tmpPath;
            $imgInfo = @getimagesize($imgPath);
        }
    }

    if (!$imgInfo) {
        // Skip if image can't be processed
        $pdf->SetTextColor(100, 80, 60);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Page ' . $file['page'] . ' - Image unavailable', 0, 1, 'C');
        continue;
    }

    $imgW = $imgInfo[0];
    $imgH = $imgInfo[1];
    $imgAspect = $imgW / $imgH;

    // Margins
    $margin = 10;
    $maxW = $pageWidth - ($margin * 2);
    $maxH = $pageHeight - ($margin * 2);
    $pageAspect = $maxW / $maxH;

    // Fit image proportionally within margins
    if ($imgAspect > $pageAspect) {
        $drawW = $maxW;
        $drawH = $drawW / $imgAspect;
    } else {
        $drawH = $maxH;
        $drawW = $drawH * $imgAspect;
    }

    $x = ($pageWidth - $drawW) / 2;
    $y = ($pageHeight - $drawH) / 2;

    $pdf->Image($imgPath, $x, $y, $drawW, $drawH, '', '', 'C', false, 300, '', false, false, 0);
}

// Output PDF for download
$pdfFileName = 'Mushaf-' . $riwayah . '-' . date('Y-m-d') . '.pdf';
$pdf->Output($pdfFileName, 'D');
