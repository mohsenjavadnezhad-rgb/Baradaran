<?php
$file = __DIR__ . '/admin/product-edit.php';
if (file_exists($file)) {
    $lines = file($file);
    $line = 1;
    foreach ($lines as $l) {
        if (strlen(trim($l)) > 0 && $line >= 10 && $line <= 20) echo "$line: $l<br>";
        $line++;
    }
    echo "Total lines: " . count($lines) . "<br>";
}
echo "PHP version: " . phpversion() . "<br>";

// Quick syntax check
$output = shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
echo "Lint: $output<br>";