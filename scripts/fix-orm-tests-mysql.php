<?php

/**
 * Script to convert ORM test files from SQLite to MySQL syntax
 * - Converts CREATE TABLE syntax from SQLite to MySQL
 * - Adds ModelQueryBuilder import where needed
 */

$testDir = __DIR__ . '/../tests/Unit/Database/ORM';
$files = glob($testDir . '/*Test.php');

$conversions = [
    // SQLite to MySQL type conversions
    '/INTEGER PRIMARY KEY AUTOINCREMENT/' => 'INT AUTO_INCREMENT PRIMARY KEY',
    '/INTEGER NOT NULL/' => 'INT NOT NULL',
    '/INTEGER/' => 'INT',

    // Add ENGINE and CHARSET to CREATE TABLE
    '/(CREATE TABLE `?\w+`? \([^)]+\))\s*$/m' => '$1 ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
];

$addedImport = false;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;

    // Add ModelQueryBuilder import if query() method exists
    if (strpos($content, 'public static function query()') !== false) {
        if (strpos($content, 'use Toporia\\Framework\\Database\\ORM\\ModelQueryBuilder;') === false) {
            // Add import after other use statements
            $usePattern = '/(use [^;]+;)/';
            preg_match_all($usePattern, $content, $matches);
            if (!empty($matches[0])) {
                $lastUse = end($matches[0]);
                $content = str_replace($lastUse, $lastUse . "\nuse Toporia\\Framework\\Database\\ORM\\ModelQueryBuilder;", $content);
                $addedImport = true;
            }
        }
    }

    // Convert CREATE TABLE syntax
    foreach ($conversions as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: " . basename($file) . "\n";
    }
}

echo "Done!\n";


