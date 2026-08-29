<?php
$dbHost='localhost';$dbName='yadaki_db';$dbUser='yadaki_dbuser';$dbPass='R4shAd3AbJnQBJCmfWAq';
$pdo=new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

try { $pdo->exec("ALTER TABLE products ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER description"); echo "column country added<br>"; } catch(Exception $e) { echo "country: ".$e->getMessage()."<br>"; }
try { $pdo->exec("ALTER TABLE products ADD COLUMN manufacturer VARCHAR(200) DEFAULT NULL AFTER country"); echo "column manufacturer added<br>"; } catch(Exception $e) { echo "manufacturer: ".$e->getMessage()."<br>"; }

// Update existing products with random realistic values
$countries = ['آلمان','ژاپن','کره جنوبی','چین','ایران','فرانسه','ایتالیا','آمریکا','انگلستان','تایوان','هند','ترکیه','اسپانیا','برزیل'];
$manufacturers = ['بوش (Bosch)','دنسو (Denso)','ساخت ایران','هیوندای موبیس','مان (Mann)','نگین (Negin)','ساچم (SACHS)','والئو (Valeo)','دلفی (Delphi)','فدرال موگول','زیمنس','کونتیننتال','TRW','ماهله (Mahle)','گیتس (Gates)','ایساکو','آمیکو','کروز (Cruze)','برمبو (Brembo)','KYB'];

$stmt = $pdo->prepare("UPDATE products SET country = ?, manufacturer = ? WHERE (country IS NULL OR manufacturer IS NULL) LIMIT 1");
$updated = 0;
$products = $pdo->query("SELECT id FROM products WHERE country IS NULL OR manufacturer IS NULL")->fetchAll();
foreach ($products as $p) {
    $c = $countries[array_rand($countries)];
    $m = $manufacturers[array_rand($manufacturers)];
    $pdo->exec("UPDATE products SET country = '".addslashes($c)."', manufacturer = '".addslashes($m)."' WHERE id = ".$p['id']);
    $updated++;
}
echo "Updated $updated products<br>Done";
@unlink(__FILE__);