<?php
$dir = new RecursiveDirectoryIterator('.');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$totalLines = 0;
$vulns = [];

foreach($files as $file) {
    $path = $file[0];
    if (strpos($path, 'vendor') !== false || strpos($path, '.gemini') !== false) continue;
    $content = file_get_contents($path);
    $lines = substr_count($content, "\n");
    $totalLines += $lines;
    
    // Look for unsafe queries (e.g. query("SELECT * FROM table WHERE id = $id"))
    if (preg_match_all('/->query\(\s*".*\$.*"\s*\)/', $content, $matches)) {
        foreach($matches[0] as $match) {
            $vulns[] = "File: $path | Match: " . trim($match);
        }
    }
}
echo "Total PHP Lines: $totalLines\n";
echo "Potential Unsafe Queries (SQLi risks):\n";
print_r($vulns);
