<?php
$results = [];
$root = __DIR__;

$tasks = [
    ['from' => "$root/ix.php", 'to' => "$root/index.php"],
    ['from' => "$root/sc.css", 'to' => "$root/assets/css/style.css"],
    ['from' => "$root/mj.js",  'to' => "$root/assets/js/main.js"],
];

foreach ($tasks as $t) {
    if (!file_exists($t['from'])) { $results[] = "MISSING: {$t['from']}"; continue; }
    if (file_exists($t['to'])) unlink($t['to']);
    $ok = copy($t['from'], $t['to']);
    $results[] = ($ok ? 'OK' : 'FAIL') . ": {$t['to']}";
    if ($ok) unlink($t['from']);
}
echo implode('<br>', $results);
@unlink(__FILE__);
echo '<br>Done. Self-deleted.';