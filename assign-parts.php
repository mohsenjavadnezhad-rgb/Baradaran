<?php
$dbHost = 'localhost'; $dbName = 'yadaki_db'; $dbUser = 'yadaki_dbuser'; $dbPass = 'R4shAd3AbJnQBJCmfWAq';
$pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$partChildren = $pdo->query("SELECT id, name, slug FROM part_categories WHERE parent_id IS NOT NULL")->fetchAll();

$keywordMap = [];
foreach ($partChildren as $pc) {
    switch ($pc['slug']) {
        case 'piston-rings': $keywords = ['پیستون','رینگ','شاتون']; break;
        case 'cylinder-head': $keywords = ['سرسیلندر','واشر']; break;
        case 'timing-belt': $keywords = ['تسمه تایم','تایمینگ']; break;
        case 'spark-coil': $keywords = ['شمع','کوئل','کویل']; break;
        case 'filters': $keywords = ['فیلتر']; break;
        case 'shock-absorber': $keywords = ['کمک فنر']; break;
        case 'bushings': $keywords = ['سیبک','بوش']; break;
        case 'springs': $keywords = ['فنر']; break;
        case 'brake-pads': $keywords = ['لنت']; break;
        case 'brake-discs': $keywords = ['دیسک ترمز','دیسک چرخ']; break;
        case 'brake-pump': $keywords = ['پمپ ترمز','بوستر']; break;
        case 'brake-caliper': $keywords = ['کالیپر']; break;
        case 'bumpers-grille': $keywords = ['سپر','جلوپنجره']; break;
        case 'fenders-hood': $keywords = ['گلگیر','کاپوت']; break;
        case 'doors-body': $keywords = ['درب','در خودرو']; break;
        case 'starter-alternator': $keywords = ['استارت','دینام']; break;
        case 'battery-cables': $keywords = ['باتری','کابل']; break;
        case 'sensors': $keywords = ['سنسور']; break;
        case 'clutch': $keywords = ['کلاچ','دیسک و صفحه']; break;
        case 'auto-transmission': $keywords = ['گیربکس اتوماتیک','گیربکس']; break;
        case 'axles-differential': $keywords = ['پلوس','دیفرانسیل']; break;
        case 'muffler': $keywords = ['اگزوز','منبع اگزوز']; break;
        case 'fuel-pump': $keywords = ['پمپ بنزین','سوخت']; break;
        case 'injector': $keywords = ['انژکتور','افشانک']; break;
        case 'radiator': $keywords = ['رادیاتور']; break;
        case 'water-pump': $keywords = ['واتر پمپ']; break;
        case 'cooling-fan': $keywords = ['فن خنک کننده','فن رادیاتور']; break;
        case 'dashboard': $keywords = ['داشبورد','رودری']; break;
        case 'seats-belts': $keywords = ['صندلی','کمربند']; break;
        case 'headlights-taillights': $keywords = ['چراغ','چراغ جلو','چراغ عقب','طبق']; break;
        case 'windshield': $keywords = ['شیشه','شیشه جلو','شیشه عقب']; break;
        case 'side-mirrors': $keywords = ['آینه']; break;
        case 'tires': $keywords = ['لاستیک','تایر']; break;
        case 'rims-hubcaps': $keywords = ['رینگ','قالپاق']; break;
        case 'engine-oil': $keywords = ['روغن موتور','روغن']; break;
        case 'antifreeze': $keywords = ['ضدیخ','آب رادیاتور']; break;
        default: $keywords = []; break;
    }
    $keywordMap[$pc['id']] = $keywords;
}

$products = $pdo->query("SELECT id, name FROM products WHERE part_category_id IS NULL")->fetchAll();
$updated = 0;

$update = $pdo->prepare("UPDATE products SET part_category_id = ? WHERE id = ?");

foreach ($products as $prod) {
    $name = $prod['name'];
    foreach ($keywordMap as $catId => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_stripos($name, $kw) !== false) {
                $update->execute([$catId, $prod['id']]);
                $updated++;
                continue 3;
            }
        }
    }
}

echo "Updated $updated products with part categories. " . (count($products) - $updated) . " left unassigned.";
@unlink(__FILE__);