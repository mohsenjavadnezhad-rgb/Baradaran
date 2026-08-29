<?php
$dbHost='localhost';$dbName='yadaki_db';$dbUser='yadaki_dbuser';$dbPass='R4shAd3AbJnQBJCmfWAq';
$pdo=new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

try { $pdo->exec("CREATE TABLE product_variants (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, country VARCHAR(100) DEFAULT NULL, manufacturer VARCHAR(200) DEFAULT NULL, retail_price DECIMAL(15,0) DEFAULT NULL, wholesale_price DECIMAL(15,0) DEFAULT NULL, stock INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); echo "Table created<br>"; } catch(Exception $e) { echo "Table: ".$e->getMessage()."<br>"; }

// Create 2-3 variants for ~60% of products
$countries = ['آلمان','ژاپن','کره جنوبی','چین','ایران','فرانسه','ایتالیا','آمریکا','انگلستان','تایوان','هند','ترکیه','اسپانیا','برزیل'];
$manufacturers = ['بوش (Bosch)','دنسو (Denso)','ساخت ایران','هیوندای موبیس','مان (Mann)','نگین (Negin)','ساچم (SACHS)','والئو (Valeo)','دلفی (Delphi)','فدرال موگول','زیمنس','کونتیننتال','TRW','ماهله (Mahle)','گیتس (Gates)','ایساکو','برمبو (Brembo)','KYB','لوكاس (Lucas)','SKF'];

$products = $pdo->query("SELECT id, retail_price, wholesale_price, stock FROM products ORDER BY RAND() LIMIT 350")->fetchAll();
$insert = $pdo->prepare("INSERT INTO product_variants (product_id, country, manufacturer, retail_price, wholesale_price, stock) VALUES (?,?,?,?,?,?)");
$count = 0;
foreach ($products as $p) {
    $num = rand(2,3);
    $used = [];
    for ($i=0; $i<$num; $i++) {
        do { $c = $countries[array_rand($countries)]; $m = $manufacturers[array_rand($manufacturers)]; $key = $c.'|'.$m; }
        while (in_array($key, $used));
        $used[] = $key;
        $rp = (int)($p['retail_price'] * (0.85 + mt_rand(0,30)/100));
        $wp = (int)($rp * 0.88);
        $st = (int)($p['stock'] / $num);
        $insert->execute([$p['id'], $c, $m, $rp, $wp, $st]);
        $count++;
    }
}
echo "Created $count variants<br>";

// Convert single country/manufacturer to variant for remaining products
$remaining = $pdo->query("SELECT id, country, manufacturer, retail_price, wholesale_price, stock FROM products WHERE id NOT IN (SELECT DISTINCT product_id FROM product_variants) AND country IS NOT NULL")->fetchAll();
$insert2 = $pdo->prepare("INSERT INTO product_variants (product_id, country, manufacturer, retail_price, wholesale_price, stock) VALUES (?,?,?,?,?,?)");
foreach ($remaining as $r) {
    $insert2->execute([$r['id'], $r['country'], $r['manufacturer'], $r['retail_price'], $r['wholesale_price'], $r['stock']]);
    $count++;
}
echo "Converted " . count($remaining) . " single products to variants<br>";
echo "Total variants: $count<br>Done";
@unlink(__FILE__);