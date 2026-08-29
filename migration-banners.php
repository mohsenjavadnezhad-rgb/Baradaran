<?php
/* migration-banners.php — ساخت جدول بنرها و مقداردهی اولیه. یک‌بار در مرورگر اجرا شود. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
echo '<div style="font-family:Tahoma;direction:rtl;padding:20px;">';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        position ENUM('main','small') NOT NULL DEFAULT 'small',
        title VARCHAR(300) DEFAULT NULL,
        subtitle VARCHAR(300) DEFAULT NULL,
        description VARCHAR(500) DEFAULT NULL,
        image VARCHAR(500) DEFAULT NULL,
        link_url VARCHAR(500) DEFAULT NULL,
        btn_text VARCHAR(100) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "جدول banners ساخته شد.<br>";
} catch (Exception $e) {
    echo "خطا در ساخت جدول: " . h($e->getMessage()) . "<br>";
}

/* مقداردهی اولیه فقط اگر جدول خالی است */
$count = (int)$pdo->query("SELECT COUNT(*) FROM banners")->fetchColumn();
if ($count === 0) {
    // بنر اصلی پیش‌فرض (مطابق متن فعلی صفحه)
    $pdo->prepare("INSERT INTO banners (position, subtitle, title, description, btn_text, link_url, sort_order, is_active)
                   VALUES ('main', ?, ?, ?, ?, ?, 0, 1)")
        ->execute([
            'فروشگاه تخصصی لوازم یدکی',
            'قطعات اصلی خودرو با قیمت کلی و جزئی',
            'هزاران قطعه یدکی برای خودروهای ایرانی و خارجی؛ ضمانت اصالت کالا و ارسال سریع به سراسر کشور.',
            'مشاهده فروشگاه',
            'index.php',
        ]);

    // بنرهای کوچک: از دسته‌بندی قطعات، وگرنه لیست پیش‌فرض
    $small = [];
    foreach (getPartCategoriesTree() as $g) {
        $small[] = ['title' => $g['parent']['name'], 'q' => $g['parent']['name']];
    }
    if (!$small) {
        $small = [
            ['title' => 'لنت و دیسک ترمز', 'q' => 'لنت ترمز'],
            ['title' => 'فیلتر روغن و هوا',  'q' => 'فیلتر'],
            ['title' => 'شمع و وایر',        'q' => 'شمع'],
            ['title' => 'تسمه تایم',         'q' => 'تسمه تایم'],
            ['title' => 'روغن و مایعات',     'q' => 'روغن موتور'],
            ['title' => 'باتری و برق',       'q' => 'باتری'],
        ];
    }
    $small = array_slice($small, 0, 6);
    $ins = $pdo->prepare("INSERT INTO banners (position, title, link_url, sort_order, is_active) VALUES ('small', ?, ?, ?, 1)");
    foreach ($small as $i => $b) {
        $ins->execute([$b['title'], 'search.php?q=' . urlencode($b['q']), $i]);
    }
    echo "بنر اصلی و " . count($small) . " بنر کوچک اولیه ساخته شد.<br>";
} else {
    echo "جدول از قبل داده دارد ($count ردیف) — مقداردهی اولیه رد شد.<br>";
}

echo "<b>انجام شد.</b> <a href='admin/banners.php'>رفتن به مدیریت بنرها</a>";
echo '</div>';

@unlink(__FILE__);
