<?php
/**
 * Auto-generate 3 sample products for every car model (subcategory)
 * Run once, then delete from server.
 */

$dbHost = 'localhost';
$dbName = 'yadaki_db';
$dbUser = 'yadaki_dbuser';
$dbPass = 'R4shAd3AbJnQBJCmfWAq';

$parts = [
    ['name' => 'لنت ترمز جلو',      'tech' => 'LN', 'retail' => [450000, 1200000],  'whole' => [0.85, 0.90]],
    ['name' => 'دیسک ترمز جلو',     'tech' => 'DSK','retail' => [900000, 2800000],  'whole' => [0.85, 0.90]],
    ['name' => 'فیلتر روغن',        'tech' => 'FO', 'retail' => [180000, 550000],   'whole' => [0.80, 0.88]],
    ['name' => 'فیلتر هوا',         'tech' => 'FA', 'retail' => [150000, 480000],   'whole' => [0.80, 0.88]],
    ['name' => 'شمع موتور (۴ عدد)',  'tech' => 'SH', 'retail' => [220000, 650000],   'whole' => [0.82, 0.90]],
    ['name' => 'کمک فنر جلو',       'tech' => 'KF', 'retail' => [800000, 2200000],  'whole' => [0.85, 0.90]],
    ['name' => 'صفحه کلاچ',         'tech' => 'SK', 'retail' => [1200000, 3500000], 'whole' => [0.83, 0.90]],
    ['name' => 'تسمه دینام',        'tech' => 'TD', 'retail' => [350000, 950000],   'whole' => [0.83, 0.90]],
    ['name' => 'رادیاتور',          'tech' => 'RD', 'retail' => [1500000, 4200000], 'whole' => [0.85, 0.90]],
    ['name' => 'پمپ بنزین',         'tech' => 'PB', 'retail' => [1800000, 5000000], 'whole' => [0.85, 0.90]],
    ['name' => 'دینام',             'tech' => 'DN', 'retail' => [2200000, 6000000], 'whole' => [0.85, 0.92]],
    ['name' => 'استارت',            'tech' => 'ST', 'retail' => [1900000, 5500000], 'whole' => [0.85, 0.92]],
    ['name' => 'کاسه نمد گیربکس',   'tech' => 'KN', 'retail' => [250000, 750000],   'whole' => [0.80, 0.88]],
    ['name' => 'بلبرینگ چرخ جلو',   'tech' => 'BL', 'retail' => [650000, 1800000],  'whole' => [0.85, 0.90]],
    ['name' => 'سیلندر ترمز',       'tech' => 'SL', 'retail' => [750000, 2000000],  'whole' => [0.85, 0.90]],
    ['name' => 'کویل',              'tech' => 'KL', 'retail' => [450000, 1200000],  'whole' => [0.83, 0.90]],
    ['name' => 'سنسور اکسیژن',      'tech' => 'SN', 'retail' => [850000, 2400000],  'whole' => [0.85, 0.90]],
    ['name' => 'کاتالیزور',         'tech' => 'KT', 'retail' => [2800000, 8000000], 'whole' => [0.85, 0.92]],
];

echo "<html dir='rtl'><head><meta charset='utf-8'><style>
body { font-family: Tahoma; background: #111827; color: #F9FAFB; padding: 2rem; max-width: 900px; margin: auto; }
.success { color: #10B981; } .error { color: #EF4444; } h2 { color: #DC2626; }
table { width: 100%; border-collapse: collapse; margin: 1rem 0; background: #1F2937; border-radius: 8px; overflow: hidden; }
th { background: #374151; padding: 0.5rem; text-align: right; font-size: 0.85rem; }
td { padding: 0.5rem; border-top: 1px solid #374151; font-size: 0.8rem; }
.stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin: 1rem 0; }
.stat { background: #1F2937; padding: 1rem; border-radius: 8px; text-align: center; }
.stat-num { font-size: 2rem; font-weight: bold; color: #DC2626; }
.stat-label { font-size: 0.8rem; color: #9CA3AF; }
</style></head><body>";

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "<p class='success'>✓ اتصال به دیتابیس برقرار شد.</p>";

    $models = $pdo->query("SELECT id, name, slug FROM categories WHERE parent_id IS NOT NULL ORDER BY id")->fetchAll();

    $existingCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $countBefore8 = $existingCount - 8;
    $added = 0;
    $skipped = 0;

    $insertProduct = $pdo->prepare(
        "INSERT INTO products (name, technical_number, description, retail_price, wholesale_price, wholesale_min_qty, stock, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $insertLink = $pdo->prepare(
        "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)"
    );

    $pdo->beginTransaction();

    echo "<h2>در حال تولید محصول برای " . count($models) . " مدل خودرو...</h2>";

    foreach ($models as $model) {
        $addedForModel = $pdo->query(
            "SELECT COUNT(*) FROM product_categories pc JOIN products p ON pc.product_id = p.id WHERE pc.category_id = " . (int)$model['id']
        )->fetchColumn();

        if ($addedForModel >= 3) {
            $skipped++;
            continue;
        }

        $needed = 3 - $addedForModel;
        $picked = array_rand(array_flip(range(0, count($parts) - 1)), min($needed, count($parts)));
        if (!is_array($picked)) $picked = [$picked];

        foreach ($picked as $idx) {
            $p = $parts[$idx];
            $retail = rand($p['retail'][0], $p['retail'][1]);
            $wholePct = $p['whole'][0] + mt_rand(0, (int)(($p['whole'][1] - $p['whole'][0]) * 100)) / 100;
            $whole = (int)($retail * $wholePct);
            $wholeMin = rand(3, 10);

            $techNum = $p['tech'] . '-' . strtoupper(substr($model['slug'], 0, 4)) . '-' . rand(100, 999);
            $prodName = $p['name'] . ' ' . $model['name'];
            $desc = $p['name'] . ' با کیفیت بالا مناسب ' . $model['name'];
            $stock = rand(10, 100);

            $insertProduct->execute([$prodName, $techNum, $desc, $retail, $whole, $wholeMin, $stock]);
            $pid = $pdo->lastInsertId();
            $insertLink->execute([$pid, $model['id']]);
            $added++;
        }
    }

    $pdo->commit();

    echo "<div class='stats'>";
    echo "<div class='stat'><div class='stat-num'>" . count($models) . "</div><div class='stat-label'>مدل خودرو</div></div>";
    echo "<div class='stat'><div class='stat-num'>$added</div><div class='stat-label'>محصول جدید</div></div>";
    echo "<div class='stat'><div class='stat-num'>$skipped</div><div class='stat-label'>مدل‌های skip شده</div></div>";
    echo "<div class='stat'><div class='stat-num'>" . $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() . "</div><div class='stat-label'>کل محصولات</div></div>";
    echo "</div>";

    echo "<p class='success'>✓ عملیات با موفقیت انجام شد. حالا هر مدل خودرو حداقل ۳ محصول دارد.</p>";
    echo "<p style='color:#EF4444;'>⚠ این فایل (generate-samples.php) را از سرور حذف کنید.</p>";
    echo "<p><a href='index.php' style='color:#DC2626;'>← بازگشت به فروشگاه</a></p>";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "<p class='error'>✗ خطا: " . $e->getMessage() . "</p>";
}

echo "</body></html>";