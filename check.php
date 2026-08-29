<?php
$dbHost = 'localhost'; $dbName = 'yadaki_db'; $dbUser = 'yadaki_dbuser'; $dbPass = 'R4shAd3AbJnQBJCmfWAq';
$pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "<div style='font-family:Tahoma;color:#fff;background:#111;padding:1rem;'>";

$hasColumn = $pdo->query("SHOW COLUMNS FROM products LIKE 'part_category_id'")->rowCount();
echo "Column exists: " . ($hasColumn ? "YES" : "NO") . "<br>";

if ($hasColumn) {
    $assigned = $pdo->query("SELECT COUNT(*) FROM products WHERE part_category_id IS NOT NULL")->fetchColumn();
    $total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "Assigned: $assigned / $total<br>";
}

echo "<h3>Part Categories:</h3>";
$cats = $pdo->query("
    SELECT p.id, p.name, p.parent_id, 
           (SELECT COUNT(*) FROM products WHERE part_category_id = p.id) as cnt
    FROM part_categories p ORDER BY COALESCE(p.parent_id, p.id), p.sort_order
")->fetchAll();

$parents = [];
foreach ($cats as $c) {
    if (!$c['parent_id']) {
        $parents[$c['id']] = $c;
    }
}
foreach ($cats as $c) {
    if ($c['parent_id'] && isset($parents[$c['parent_id']])) {
        echo $c['name'] . " → " . $c['cnt'] . " products<br>";
    }
}
echo "<br><a href='/' style='color:red;'>Back</a>";
echo "</div>";
@unlink(__FILE__);