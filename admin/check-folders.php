<?php
header('Content-Type: text/plain');

// Updated path: ai-files is in parent directory
$base = __DIR__ . '/../ai-files';
echo "Base directory: $base\n";
echo "Exists: " . (file_exists($base) ? 'YES' : 'NO') . "\n\n";

if (file_exists($base)) {
    echo "Contents of ai-files/:\n";
    $items = scandir($base);
    foreach ($items as $item) {
        if ($item[0] === '.') continue;
        echo "  - $item " . (is_dir($base . '/' . $item) ? '[DIR]' : '[FILE]') . "\n";
        
        if (strtolower($item) === 'warsh') {
            echo "    Contents of $item/:\n";
            $sub = scandir($base . '/' . $item);
            foreach ($sub as $s) {
                if ($s[0] === '.') continue;
                echo "      - $s " . (is_dir($base . '/' . $item . '/' . $s) ? '[DIR]' : '[FILE]') . "\n";
            }
        }
    }
}

echo "\n\nTrying to find any .ai files recursively...\n";
function findAI($dir, $level = 0) {
    if (!file_exists($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item[0] === '.') continue;
        $path = $dir . '/' . $item;
        echo str_repeat("  ", $level) . "- $item\n";
        if (is_dir($path) && $level < 3) {
            findAI($path, $level + 1);
        }
    }
}
findAI($base);
?>
