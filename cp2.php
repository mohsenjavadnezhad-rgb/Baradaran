<?php
$tasks = [
    ['from' => __DIR__.'/ix.php', 'to' => __DIR__.'/index.php'],
    ['from' => __DIR__.'/mj.js',  'to' => __DIR__.'/assets/js/main.js'],
];
foreach ($tasks as $t) {
    if (!file_exists($t['from'])) { echo "MISS: {$t['from']}<br>"; continue; }
    if (file_exists($t['to'])) unlink($t['to']);
    copy($t['from'], $t['to']);
    echo "OK: {$t['to']}<br>";
    unlink($t['from']);
}

/* CSS direct write */
$css = 'REPLACE_CSS_HERE';
file_put_contents(__DIR__.'/assets/css/style.css', $css);
echo "CSS written<br>";

@unlink(__FILE__);
echo "Done";