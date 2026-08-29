<?php
$dbHost='localhost';$dbName='yadaki_db';$dbUser='yadaki_dbuser';$dbPass='R4shAd3AbJnQBJCmfWAq';
$pdo=new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// 1. Delete duplicate "موتور و قطعات موتوری" - keep the one with MORE children
$dupes = $pdo->query("SELECT id, (SELECT COUNT(*) FROM part_categories c2 WHERE c2.parent_id = c1.id) as cnt FROM part_categories c1 WHERE name = 'موتور و قطعات موتوری' AND parent_id IS NULL ORDER BY cnt DESC")->fetchAll();

if (count($dupes) > 1) {
    $keep = $dupes[0]['id'];
    foreach (array_slice($dupes, 1) as $d) {
        $del = $d['id'];
        echo "Removing duplicate engine-parts id=$del (children: {$d['cnt']}), keeping id=$keep (children: {$dupes[0]['cnt']})<br>";
        $pdo->exec("UPDATE products SET part_category_id=NULL WHERE part_category_id IN (SELECT id FROM part_categories WHERE parent_id=$del)");
        $pdo->exec("DELETE FROM part_categories WHERE parent_id=$del");
        $pdo->exec("DELETE FROM part_categories WHERE id=$del");
    }
}

// Check ALL duplicates
$all = $pdo->query("SELECT name, COUNT(*) as cnt FROM part_categories WHERE parent_id IS NULL GROUP BY name HAVING cnt > 1")->fetchAll();
foreach ($all as $a) {
    $dupes = $pdo->query("SELECT id, (SELECT COUNT(*) FROM part_categories WHERE parent_id = c.id) as cc FROM part_categories c WHERE name = ? AND parent_id IS NULL ORDER BY cc DESC", [$a['name']])->fetchAll();
    $keep = $dupes[0]['id'];
    for ($i=1; $i<count($dupes); $i++) {
        $del = $dupes[$i]['id'];
        echo "Dedup: $a[name] - removing id=$del, keeping id=$keep<br>";
        $pdo->exec("DELETE FROM part_categories WHERE parent_id=$del");
        $pdo->exec("DELETE FROM part_categories WHERE id=$del");
    }
}

$total = $pdo->query("SELECT COUNT(*) FROM part_categories WHERE parent_id IS NULL")->fetchColumn();
$totalChildren = $pdo->query("SELECT COUNT(*) FROM part_categories WHERE parent_id IS NOT NULL")->fetchColumn();
echo "<br>Parents: $total, Children: $totalChildren<br>";
echo '<a href="/" style="color:red">بازگشت</a>';
@unlink(__FILE__);