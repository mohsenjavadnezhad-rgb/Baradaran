<?php
$dbHost='localhost';$dbName='yadaki_db';$dbUser='yadaki_dbuser';$dbPass='R4shAd3AbJnQBJCmfWAq';
$pdo=new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

$dupes = $pdo->query("SELECT name, GROUP_CONCAT(id ORDER BY id) as ids, GROUP_CONCAT((SELECT COUNT(*) FROM part_categories c2 WHERE c2.parent_id = c1.id) ORDER BY id) as cnts FROM part_categories c1 WHERE parent_id IS NULL GROUP BY name HAVING COUNT(*) > 1")->fetchAll();

foreach ($dupes as $d) {
    $ids = explode(',', $d['ids']);
    $cnts = explode(',', $d['cnts']);
    $keep = $ids[0]; $keepIdx = 0;
    foreach ($cnts as $k => $c) { if ((int)$c > (int)$cnts[$keepIdx]) { $keep = $ids[$k]; $keepIdx = $k; } }
    foreach ($ids as $i => $id) {
        if ($id == $keep) continue;
        $pdo->exec("UPDATE products SET part_category_id=$keep WHERE part_category_id=$id");
        $pdo->exec("UPDATE part_categories SET parent_id=$keep WHERE parent_id=$id");
        $pdo->exec("DELETE FROM part_categories WHERE id=$id");
        echo "Merged $d[name] (ids: ".implode(',',$ids).") -> kept $keep<br>";
    }
}
echo "DONE";
@unlink(__FILE__);