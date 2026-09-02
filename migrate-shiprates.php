<?php
/* مهاجرت «نرخ‌نامهٔ ارسال» — یک‌بار اجرا و سپس خودحذف.
   ---------------------------------------------------------------
   سه چیز ساخته می‌شود:
   1) جدول `shipping_methods` — فهرست روش‌های ارسال از حالت «ثابت در کد» به
      دیتابیس منتقل می‌شود تا مدیر بتواند روش اضافه/حذف کند. بذر اولیه
      همان هشت روش فعلی است و «فعال بودن/هزینه/توضیح»شان از همان کلیدهای
      settings (ship_enable_* / ship_cost_* / ship_note_*) خوانده می‌شود،
      پس هیچ تنظیمی از دست نمی‌رود.
   2) جدول `shipping_rates` — برای هر روش چند ردیف «شهر مقصد + وزن تا +
      هزینه». اگر شهر مشتری با ردیفی بخواند، همان هزینه جای هزینهٔ ثابت
      می‌نشیند.
   3) ستون اختیاری `products.weight` (کیلوگرم) — پرکردنش تدریجی است؛ تا
      وقتی خالی باشد، نرخ‌نامه از «ردیف پایه» استفاده می‌کند.
   اطلاعات اتصال از includes/config.php خوانده می‌شود (هیچ رمزی در این فایل نیست). */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

$stmts = [
    /* روش‌های ارسال. is_deleted = حذف نرم: اگر سفارشی به این روش اشاره کند
       نباید ردیفش نابود شود، وگرنه نام روش آن سفارش گم می‌شود. */
    "CREATE TABLE IF NOT EXISTS shipping_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        method_key VARCHAR(40) NOT NULL UNIQUE,
        label VARCHAR(120) NOT NULL,
        icon VARCHAR(40) NOT NULL DEFAULT 'truck',
        hint VARCHAR(255) NOT NULL DEFAULT '',
        badge VARCHAR(120) NOT NULL DEFAULT '',
        badge_short VARCHAR(40) NOT NULL DEFAULT '',
        cod_only TINYINT(1) NOT NULL DEFAULT 0,
        freight_collect TINYINT(1) NOT NULL DEFAULT 0,
        cost DECIMAL(15,0) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    /* نرخ‌نامه. city_norm نسخهٔ یکدست‌شدهٔ نام شهر است (نیم‌فاصله و ی/ک عربی
       حذف) تا «مشهد» با «مشهد مقدس» بخواند — تولیدش در PHP است. */
    "CREATE TABLE IF NOT EXISTS shipping_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        method_key VARCHAR(40) NOT NULL,
        city VARCHAR(80) NOT NULL,
        city_norm VARCHAR(80) NOT NULL,
        weight_to DECIMAL(10,2) NOT NULL DEFAULT 0,
        cost DECIMAL(15,0) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_rate_method (method_key),
        INDEX idx_rate_city (city_norm)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    /* وزن محصول (کیلوگرم) — اختیاری و تدریجی */
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS weight DECIMAL(10,2) NULL",
];

$ok = 0; $fail = 0;
foreach ($stmts as $s) {
    try { $pdo->exec($s); $ok++; }
    catch (Exception $e) { echo "ERR: " . htmlspecialchars($e->getMessage()) . "<br>"; $fail++; }
}

/* ---- بذر روش‌ها: تعریف از کد + وضعیت فعلی از settings ---- */
$seeded = 0;
try {
    $ins = $pdo->prepare("INSERT IGNORE INTO shipping_methods
        (method_key, label, icon, hint, badge, badge_short, cod_only, freight_collect, cost, sort_order, is_active, is_deleted)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,0)");
    $i = 0;
    foreach (shippingDefaultMethods() as $k => $d) {
        $cost = preg_replace('/\D+/', '', faToLatinDigits((string)getSettingRaw('ship_cost_' . $k, '0')));
        $ins->execute([
            $k,
            $d['label'],
            $d['icon'],
            (string)($d['hint'] ?? ''),
            (string)($d['badge'] ?? ''),
            (string)($d['badge_short'] ?? ''),
            !empty($d['cod_only']) ? 1 : 0,
            !empty($d['freight_collect']) ? 1 : 0,
            $cost === '' ? 0 : (int)$cost,
            $i * 10,
            getSettingRaw('ship_enable_' . $k, '1') === '1' ? 1 : 0,
        ]);
        $seeded += $ins->rowCount();
        $i++;
    }
    $ok++;
} catch (Exception $e) { echo "ERR seed: " . htmlspecialchars($e->getMessage()) . "<br>"; $fail++; }

/* ---- کلیدهای پیش‌فرض تنظیمات جدید ---- */
$stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach ([
    ['ship_barbari_cost', '0'],   // هزینهٔ پایهٔ باربری
    ['ship_barbari_desc', ''],    // توضیح زیر گزینهٔ باربری در صفحهٔ تسویه
    ['ship_rate_note',    ''],    // توضیح اختیاری بالای جدول نرخ‌نامه
] as $d) {
    try { $stmt->execute($d); $ok++; } catch (Exception $e) { $fail++; }
}

echo "Shipping rates migration done. OK: $ok — Failed: $fail<br>";
echo "روش‌های بذرشده: " . (int)$seeded . "<br>";
echo "جدول روش‌ها: " . (dbHasTable('shipping_methods') ? 'ساخته شد' : 'ساخته نشد') . " — ";
echo "نرخ‌نامه: "     . (dbHasTable('shipping_rates')   ? 'ساخته شد' : 'ساخته نشد') . " — ";
echo "وزن محصول: "   . (dbHasColumn('products', 'weight') ? 'ساخته شد' : 'ساخته نشد');

@unlink(__FILE__);
