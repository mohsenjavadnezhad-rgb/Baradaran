<?php
$dbHost = 'localhost'; $dbName = 'yadaki_db'; $dbUser = 'yadaki_dbuser'; $dbPass = 'R4shAd3AbJnQBJCmfWAq';
$pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$stmts = [
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS part_category_id INT NULL AFTER stock",
    "CREATE TABLE IF NOT EXISTS part_categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL UNIQUE, parent_id INT NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (parent_id) REFERENCES part_categories(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$ok = 0; $fail = 0;
foreach ($stmts as $s) {
    try { $pdo->exec($s); $ok++; } catch (Exception $e) { echo "ERR: " . $e->getMessage() . "<br>"; $fail++; }
}

// Insert parents
$parents = [
    ['موتور و قطعات موتوری', 'engine-parts', 1],
    ['جلوبندی و تعلیق', 'suspension', 2],
    ['ترمز', 'brakes', 3],
    ['بدنه و شاسی', 'body-chassis', 4],
    ['برق و الکترونیک', 'electrical', 5],
    ['گیربکس و انتقال قدرت', 'transmission', 6],
    ['اگزوز', 'exhaust', 7],
    ['سیستم سوخت‌رسانی', 'fuel-system', 8],
    ['سیستم خنک‌کننده', 'cooling', 9],
    ['لوازم داخلی', 'interior', 10],
    ['چراغ و روشنایی', 'lighting', 11],
    ['شیشه و آینه', 'glass-mirrors', 12],
    ['لاستیک و چرخ', 'tires-wheels', 13],
    ['روغن و مایعات', 'fluids', 14],
];

$stmt = $pdo->prepare("INSERT IGNORE INTO part_categories (name, slug, parent_id, sort_order) VALUES (?, ?, ?, ?)");
foreach ($parents as $p) { try { $stmt->execute($p); $ok++; } catch (Exception $e) { $fail++; } }

$children = [
    ['پیستون و رینگ', 'piston-rings', 1, 1], ['سرسیلندر و واشر', 'cylinder-head', 1, 2],
    ['تسمه تایم', 'timing-belt', 1, 3], ['شمع و کوئل', 'spark-coil', 1, 4],
    ['فیلتر روغن و هوا', 'filters', 1, 5], ['کمک فنر', 'shock-absorber', 2, 1],
    ['سیبک و بوش', 'bushings', 2, 2], ['فنر و تعلیق', 'springs', 2, 3],
    ['لنت ترمز', 'brake-pads', 3, 1], ['دیسک ترمز', 'brake-discs', 3, 2],
    ['پمپ ترمز و بوستر', 'brake-pump', 3, 3], ['کالیپر ترمز', 'brake-caliper', 3, 4],
    ['سپر و جلوپنجره', 'bumpers-grille', 4, 1], ['گلگیر و کاپوت', 'fenders-hood', 4, 2],
    ['درب و قطعات بدنه', 'doors-body', 4, 3], ['استارت و دینام', 'starter-alternator', 5, 1],
    ['باتری و کابل', 'battery-cables', 5, 2], ['سنسورها', 'sensors', 5, 3],
    ['کلاچ', 'clutch', 6, 1], ['گیربکس اتوماتیک', 'auto-transmission', 6, 2],
    ['پلوس و دیفرانسیل', 'axles-differential', 6, 3], ['منبع اگزوز', 'muffler', 7, 1],
    ['پمپ بنزین', 'fuel-pump', 8, 1], ['انژکتور', 'injector', 8, 2],
    ['رادیاتور', 'radiator', 9, 1], ['واتر پمپ', 'water-pump', 9, 2],
    ['فن خنک‌کننده', 'cooling-fan', 9, 3], ['داشبورد و رودری', 'dashboard', 10, 1],
    ['صندلی و کمربند', 'seats-belts', 10, 2], ['چراغ جلو و عقب', 'headlights-taillights', 11, 1],
    ['شیشه جلو و عقب', 'windshield', 12, 1], ['آینه بغل', 'side-mirrors', 12, 2],
    ['لاستیک', 'tires', 13, 1], ['رینگ و قالپاق', 'rims-hubcaps', 13, 2],
    ['روغن موتور', 'engine-oil', 14, 1], ['ضدیخ و آب رادیاتور', 'antifreeze', 14, 2],
];

$stmt2 = $pdo->prepare("INSERT IGNORE INTO part_categories (name, slug, parent_id, sort_order) VALUES (?, ?, ?, ?)");
foreach ($children as $c) { try { $stmt2->execute($c); $ok++; } catch (Exception $e) { $fail++; } }

echo "Created $ok part categories. Failed: $fail";
@unlink(__FILE__);