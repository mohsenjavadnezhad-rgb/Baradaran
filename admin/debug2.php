<?php
$file = __DIR__ . '/product-edit.php';
echo phpversion() . "<br>";
echo "File exists: " . (file_exists($file) ? "YES" : "NO") . "<br>";
echo "Size: " . filesize($file) . "<br>";

include $file;