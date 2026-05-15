<?php

/**
 * Generate Riwayah Variant Tasks from JSON
 * Reads {riwayah}_variants.json and creates/merges page-tasks.json files
 * Usage: ?riwayah=warsh or ?riwayah=qaloon
 */

// Configuration
$riwayah = isset($_GET['riwayah']) ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_GET['riwayah'])) : 'warsh';
$wordField = $riwayah . '_word'; // warsh_word, qaloon_word, etc.

$config = [
    'variantsFile' => __DIR__ . "/../data/{$riwayah}_variants.json",
    'qcfMappingFile' => __DIR__ . '/../data/qcf_v2_mapping.json', // Your 604 pages mapping
    'tasksBasePath' => __DIR__ . '/../mushaftasks/',
    'riwayah' => $riwayah,
    'maxTasksPerPage' => 50
];

header('Content-Type: application/json');

// ==================== LOAD FILES ====================

// Load variants file (e.g., warsh_variants.json, qaloon_variants.json)
if (!file_exists($config['variantsFile'])) {
    echo json_encode(['success' => false, 'message' => 'Variants file not found: ' . $config['variantsFile']]);
    exit;
}

$variantsData = json_decode(file_get_contents($config['variantsFile']), true);
if (!$variantsData) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON in variants file']);
    exit;
}

// Load QCF V2 page mapping (you need to provide this)
if (!file_exists($config['qcfMappingFile'])) {
    echo json_encode(['success' => false, 'message' => 'QCF mapping file not found. Please provide the 604 pages mapping.']);
    exit;
}

$qcfMapping = json_decode(file_get_contents($config['qcfMappingFile']), true);
if (!$qcfMapping || !isset($qcfMapping['pages'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid QCF mapping file']);
    exit;
}

// Build Surah:Ayah to Page mapping
$ayahToPage = [];
foreach ($qcfMapping['pages'] as $pageData) {
    $pageNum = $pageData['page_number'];
    $fromSurah = $pageData['from_surah'];
    $fromAyah = $pageData['from_ayah'];
    $toSurah = $pageData['to_surah'];
    $toAyah = $pageData['to_ayah'];
    
    // Handle same surah
    if ($fromSurah === $toSurah) {
        for ($ayah = $fromAyah; $ayah <= $toAyah; $ayah++) {
            $ayahToPage["{$fromSurah}:{$ayah}"] = $pageNum;
        }
    } else {
        // Cross-surah page (rare, but handle it)
        // From start to end of first surah
        $ayahToPage["{$fromSurah}:{$fromAyah}"] = $pageNum;
        // From start of last surah to end
        $ayahToPage["{$toSurah}:{$toAyah}"] = $pageNum;
    }
}

// ==================== PAGE TO JUZ MAPPING ====================

function getJuzFromPage($page)
{
    $pageToJuz = [
        1 => [1, 21], 2 => [22, 41], 3 => [42, 61], 4 => [62, 81], 5 => [82, 101],
        6 => [102, 121], 7 => [122, 141], 8 => [142, 161], 9 => [162, 181], 10 => [182, 201],
        11 => [202, 221], 12 => [222, 241], 13 => [242, 261], 14 => [262, 281], 15 => [282, 301],
        16 => [302, 321], 17 => [322, 341], 18 => [342, 361], 19 => [362, 381], 20 => [382, 401],
        21 => [402, 421], 22 => [422, 441], 23 => [442, 461], 24 => [462, 481], 25 => [482, 501],
        26 => [502, 521], 27 => [522, 541], 28 => [542, 561], 29 => [562, 581], 30 => [582, 604]
    ];

    foreach ($pageToJuz as $juz => $range) {
        if ($page >= $range[0] && $page <= $range[1]) {
            return $juz;
        }
    }
    return null;
}

// ==================== FILE OPERATIONS ====================

function ensureDirectory($path)
{
    if (!file_exists($path)) {
        if (!mkdir($path, 0755, true)) {
            return false;
        }
    }
    return is_dir($path) && is_writable($path);
}

function sanitizeFilename($filename)
{
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    return $filename;
}

// ==================== PROCESS VARIANTS ====================

// NEW PATH STRUCTURE: mushaftasks/page-tasks/{riwayah}/ (matches get-page-tasks.php)
$pageTasksPath = $config['tasksBasePath'] . 'page-tasks' . DIRECTORY_SEPARATOR . ucfirst($config['riwayah']);

// Create directories
if (!ensureDirectory($pageTasksPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to create page tasks directory']);
    exit;
}

$stats = [
    'total_variants' => count($variantsData),
    'pages_affected' => 0,
    'tasks_created' => 0,
    'tasks_merged' => 0,
    'errors' => []
];

// Group variants by page
$variantsByPage = [];

foreach ($variantsData as $key => $variant) {
    // Validate variant data using dynamic field name (warsh_word, qaloon_word, etc.)
    if (empty($variant[$wordField]) || empty($variant['description'])) {
        $stats['errors'][] = "Invalid data for {$key}";
        continue;
    }

    // Extract location: remove _1, _2, _3 suffix from keys like "5:107_2" → "5:107"
    if (isset($variant['location']) && !empty($variant['location'])) {
        $location = $variant['location'];
    } else {
        $location = preg_replace('/_\d+$/', '', $key); // Removes trailing _N
    }

    // Find page for this ayah
    if (!isset($ayahToPage[$location])) {
        $stats['errors'][] = "Page not found for {$location} (key: {$key})";
        continue;
    }
    
    $pageNum = $ayahToPage[$location];
    
    if (!isset($variantsByPage[$pageNum])) {
        $variantsByPage[$pageNum] = [];
    }
    
    $variantsByPage[$pageNum][] = [
        'location' => $location,
        'riwayah_word' => $variant[$wordField],  // Dynamic: warsh_word, qaloon_word, etc.
        'standard_word' => $variant['standard_word'] ?? '',
        'description' => $variant['description']
    ];
}

// Process each page
foreach ($variantsByPage as $pageNum => $variants) {
    $pageFormatted = str_pad($pageNum, 3, '0', STR_PAD_LEFT);
    $filename = sanitizeFilename("{$pageFormatted}-tasks.json");
    $filePath = $pageTasksPath . DIRECTORY_SEPARATOR . $filename;
    
    // Load existing tasks
    $existingData = [
        'page' => $pageFormatted,
        'riwayah' => $config['riwayah'],
        'juz' => getJuzFromPage($pageNum),
        'tasks' => [],
        'updated' => date('c')
    ];
    
    if (file_exists($filePath)) {
        $existing = json_decode(file_get_contents($filePath), true);
        if ($existing && isset($existing['tasks'])) {
            $existingData['tasks'] = $existing['tasks'];
        }
    }
    
    // Track existing task IDs to avoid duplicates
    $existingIds = [];
    foreach ($existingData['tasks'] as $task) {
        if (isset($task['id'])) {
            $existingIds[$task['id']] = true;
        }
    }
    
    // Add variant tasks
    $newTasksCount = 0;
    foreach ($variants as $variant) {
        // Create unique ID based on location and word (using riwayah as prefix)
        $taskId = $config['riwayah'] . '_' . md5($variant['location'] . $variant['riwayah_word']);
        
        // Skip if already exists
        if (isset($existingIds[$taskId])) {
            $stats['tasks_merged']++;
            continue;
        }
        
        // Build description
        $description = $variant['description'];
        if (!empty($variant['standard_word'])) {
            $description .= " (instead of: {$variant['standard_word']})";
        }
        
        $newTask = [
            'id' => $taskId,
            'title' => $variant['riwayah_word'],
            'description' => $description,
            'location' => $variant['location'],
            'variant_type' => $config['riwayah'],        // Dynamic: warsh, qaloon, etc.
            'completed' => false,
            'source' => $config['riwayah'] . '_variant',  // Dynamic: warsh_variant, qaloon_variant, etc.
            'created' => date('c')
        ];
        
        $existingData['tasks'][] = $newTask;
        $newTasksCount++;
        $stats['tasks_created']++;
    }
    
    // Check max tasks limit
    if (count($existingData['tasks']) > $config['maxTasksPerPage']) {
        $stats['errors'][] = "Page {$pageNum} exceeds max tasks limit";
    }
    
    // Save file
    $existingData['updated'] = date('c');
    $jsonContent = json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($filePath, $jsonContent) === false) {
        $stats['errors'][] = "Failed to save page {$pageNum}";
        continue;
    }
    
    chmod($filePath, 0644);
    $stats['pages_affected']++;
}

// ==================== RESULT ====================

echo json_encode([
    'success' => true,
    'message' => ucfirst($config['riwayah']) . ' variant tasks generated successfully',
    'riwayah' => $config['riwayah'],
    'stats' => $stats,
    'pages_processed' => array_keys($variantsByPage)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);