<?php
$dbHost='localhost';$dbName='yadaki_db';$dbUser='yadaki_dbuser';$dbPass='R4shAd3AbJnQBJCmfWAq';
$pdo=new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

$all = [
    ['موتور و قطعات موتوری' => [
        'پیستون و رینگ پیستون', 'شاتون و میل‌لنگ', 'سرسیلندر', 'واشر سرسیلندر',
        'تسمه تایم', 'کیت تایم', 'شمع موتور', 'کوئل (کویل)', 'فیلتر روغن',
        'فیلتر هوا', 'فیلتر بنزین', 'سوپاپ و اسبک', 'میل سوپاپ', 'یاتاقان',
        'منیفولد هوا', 'منیفولد دود', 'توربو شارژر', 'پولی سر میل‌لنگ'
    ]],
    ['جلوبندی و تعلیق' => [
        'کمک فنر جلو', 'کمک فنر عقب', 'سیبک فرمان', 'بوش طبق',
        'فنر لول', 'طبق بالا', 'طبق پایین', 'میل تعادل', 'ستون فرمان',
        'بلبرینگ چرخ', 'کاسه نمد', 'گردگیر پلوس'
    ]],
    ['ترمز' => [
        'لنت ترمز جلو', 'لنت ترمز عقب', 'دیسک ترمز جلو', 'دیسک ترمز عقب',
        'پمپ ترمز', 'بوستر ترمز', 'کالیپر ترمز', 'شیلنگ ترمز',
        'لوله ترمز', 'سنسور ABS', 'ترمز دستی'
    ]],
    ['بدنه و شاسی' => [
        'سپر جلو', 'سپر عقب', 'جلوپنجره', 'گلگیر جلو', 'گلگیر عقب',
        'کاپوت', 'صندوق عقب', 'درب جلو', 'درب عقب', 'سینی زیر موتور',
        'شاسی و ستون', 'رکاب'
    ]],
    ['برق و الکترونیک' => [
        'استارت', 'دینام', 'باتری', 'کابل باتری', 'سنسور اکسیژن',
        'سنسور دور موتور', 'سنسور دمای آب', 'سنسور knock', 'کامپیوتر ECU',
        'فیوز و رله', 'شمع و کوئل', 'دسته سیم'
    ]],
    ['گیربکس و انتقال قدرت' => [
        'صفحه کلاچ', 'دیسک کلاچ', 'فلایویل', 'گیربکس دستی', 'گیربکس اتوماتیک',
        'پلوس', 'دیفرانسیل', 'گردگیر پلوس', 'سه‌شاخه فرمان', 'روغن گیربکس'
    ]],
    ['اگزوز' => [
        'منبع اگزوز', 'کاتالیزور', 'لوله اگزوز', 'فیلتر دوده DPF', 'بست اگزوز'
    ]],
    ['سیستم سوخت‌رسانی' => [
        'پمپ بنزین', 'انژکتور', 'رگولاتور فشار', 'فیلتر بنزین',
        'مخزن بنزین', 'شیلنگ بنزین'
    ]],
    ['سیستم خنک‌کننده' => [
        'رادیاتور', 'واتر پمپ', 'فن خنک‌کننده', 'ترموستات',
        'مخزن رادیاتور', 'شیلنگ رادیاتور'
    ]],
    ['سیستم تهویه مطبوع' => [
        'کمپرسور کولر', 'کندانسور کولر', 'فیلتر کابین', 'شیلنگ کولر',
        'اواپراتور', 'گاز کولر'
    ]],
    ['لوازم داخلی' => [
        'داشبورد', 'رودری', 'صندلی جلو', 'صندلی عقب', 'کمربند ایمنی',
        'فرمان', 'کنسول وسط', 'پدال‌ها', 'آفتابگیر'
    ]],
    ['چراغ و روشنایی' => [
        'چراغ جلو', 'چراغ عقب', 'چراغ مه‌شکن', 'چراغ راهنما',
        'چراغ سقف', 'لامپ و LED'
    ]],
    ['شیشه و آینه' => [
        'شیشه جلو', 'شیشه عقب', 'شیشه درب جلو', 'شیشه درب عقب',
        'آینه بغل', 'بالابر شیشه', 'موتور شیشه‌بالابر'
    ]],
    ['لاستیک و چرخ' => [
        'لاستیک', 'رینگ', 'قالپاق', 'توپی چرخ', 'بلبرینگ چرخ'
    ]],
    ['روغن و مایعات' => [
        'روغن موتور', 'ضدیخ', 'آب رادیاتور', 'روغن ترمز',
        'روغن هیدرولیک', 'آب مقطر', 'اسپری و شوینده'
    ]],
    ['تاسیسات موتور' => [
        'تسمه دینام', 'تسمه کولر', 'هرزگرد', 'پولی',
        'نگهدارنده موتور (دسته موتور)', 'واشر درب سوپاپ'
    ]],
];

// Check how many exist
$existing = $pdo->query("SELECT COUNT(*) FROM part_categories")->fetchColumn();
echo "Existing: $existing<br>";

if ($existing == 0) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS part_categories (
        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL UNIQUE,
        parent_id INT NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES part_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $productsHasCol = $pdo->query("SHOW COLUMNS FROM products LIKE 'part_category_id'")->rowCount();
    if (!$productsHasCol) {
        $pdo->exec("ALTER TABLE products ADD COLUMN part_category_id INT NULL AFTER stock");
    }
}

$slugify = function($text) {
    $text = str_replace([' ','‌','.','،',',','(',')','?',':','/'],'-',$text);
    $text = preg_replace('/-+/','-',$text);
    return trim($text,'-');
};

$insertParent = $pdo->prepare("INSERT IGNORE INTO part_categories (name, slug, parent_id, sort_order) VALUES (?, ?, NULL, ?)");
$insertChild = $pdo->prepare("INSERT IGNORE INTO part_categories (name, slug, parent_id, sort_order) SELECT ?, ?, id, ? FROM part_categories WHERE slug=? AND parent_id IS NULL LIMIT 1");

$added = 0;
$parentSlugs = [];

foreach ($all as $group) {
    foreach ($group as $parentName => $children) {
        $pSlug = $slugify($parentName);
        $parentSlugs[] = $pSlug;
        $insertParent->execute([$parentName, $pSlug, count($parentSlugs)]);
        foreach ($children as $j => $childName) {
            $cSlug = $slugify($childName);
            $insertChild->execute([$childName, $cSlug, $j+1, $pSlug]);
            $added++;
        }
    }
}

// Re-assign products to parent part categories based on keywords
$pdo->exec("UPDATE products SET part_category_id=NULL");

$map = [];
$pSlugs = $pdo->query("SELECT id, slug FROM part_categories WHERE parent_id IS NULL")->fetchAll();
foreach ($pSlugs as $ps) {
    $map[$ps['slug']] = $ps['id'];
}

$keywords = [
    'موتور' => ['پیستون','شاتون','میل‌لنگ','سرسیلندر','واشر سر','واشر','تسمه تایم','کیت تایم','شمع','کوئل','کویل','سوپاپ','اسبک','میل سوپاپ','یاتاقان','منیفولد','توربو','پولی','فیلتر','فلایویل','تسمه دینام','تسمه کولر','هرزگرد','دسته موتور','نگهدارنده','درب سوپاپ'],
    'جلوبندی-و-تعلیق' => ['کمک فنر','سیبک','بوش طبق','بوش','فنر لول','فنر','طبق','میل تعادل','ستون فرمان','بلبرینگ','کاسه نمد','گردگیر پلوس','سه‌شاخه'],
    'ترمز' => ['لنت ترمز','لنت','دیسک ترمز','دیسک','پمپ ترمز','بوستر ترمز','بوستر','کالیپر','شیلنگ ترمز','لوله ترمز','سنسور ABS','ترمز دستی','ترمز','روغن ترمز'],
    'بدنه-و-شاسی' => ['سپر','جلوپنجره','گلگیر','کاپوت','صندوق عقب','درب','سینی','شاسی','رکاب'],
    'برق-و-الکترونیک' => ['استارت','دینام','باتری','کابل','سنسور','اکسیژن','ECU','کامپیوتر','فیوز','رله','دسته سیم'],
    'گیربکس-و-انتقال-قدرت' => ['کلاچ','صفحه کلاچ','دیسک کلاچ','گیربکس','پلوس','دیفرانسیل','روغن گیربکس'],
    'اگزوز' => ['اگزوز','منبع اگزوز','کاتالیزور','لوله اگزوز','DPF','بست اگزوز'],
    'سیستم-سوخت-رسانی' => ['پمپ بنزین','انژکتور','رگولاتور','فیلتر بنزین','مخزن بنزین','شیلنگ بنزین','افشانک'],
    'سیستم-خنک-کننده' => ['رادیاتور','واتر پمپ','فن خنک','ترموستات','مخزن رادیاتور','شیلنگ رادیاتور'],
    'سیستم-تهویه-مطبوع' => ['کمپرسور','کولر','کندانسور','فیلتر کابین','اواپراتور','گاز کولر'],
    'لوازم-داخلی' => ['داشبورد','رودری','صندلی','کمربند','فرمان','کنسول وسط','پدال','آفتابگیر'],
    'چراغ-و-روشنایی' => ['چراغ','مه‌شکن','راهنما','لامپ','LED'],
    'شیشه-و-آینه' => ['شیشه','آینه بغل','آینه','بالابر','شیشه‌بالابر'],
    'لاستیک-و-چرخ' => ['لاستیک','رینگ','قالپاق','توپی','بلبرینگ چرخ','بلبرینگ','تایر'],
    'روغن-و-مایعات' => ['روغن موتور','ضدیخ','آب رادیاتور','آب مقطر','اسپری','شوینده','روغن هیدرولیک'],
    'تاسیسات-موتور' => ['تسمه دینام','تسمه کولر','هرزگرد','پولی','دسته موتور','درب سوپاپ'],
];

$st = $pdo->prepare("UPDATE products SET part_category_id = ? WHERE name LIKE ? AND part_category_id IS NULL");
foreach ($map as $slug => $id) {
    if (isset($keywords[$slug])) {
        foreach ($keywords[$slug] as $kw) {
            $st->execute([$id, '%' . $kw . '%']);
        }
    }
}

$total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$done = $pdo->query("SELECT COUNT(*) FROM products WHERE part_category_id IS NOT NULL")->fetchColumn();
$catCount = $pdo->query("SELECT COUNT(*) FROM part_categories")->fetchColumn();

echo "Total part categories: $catCount<br>";
echo "Products categorized: $done / $total<br>";
echo "New subcategories added: $added<br>";
echo '<br><a href="/" style="color:red">بازگشت به فروشگاه</a>';
@unlink(__FILE__);