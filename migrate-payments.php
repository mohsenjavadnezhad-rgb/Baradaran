<?php
/* مهاجرت درگاه پرداخت — یک‌بار اجرا و سپس خودحذف.
   ستون‌های پرداخت روی orders + جدول لاگ تلاش‌های پرداخت + کلیدهای پیش‌فرض تنظیمات. */
$dbHost = 'localhost'; $dbName = 'yadaki_db'; $dbUser = 'yadaki_dbuser'; $dbPass = 'R4shAd3AbJnQBJCmfWAq';
$pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$stmts = [
    /* روش پرداختی که مشتری انتخاب کرده: cod | card | zarinpal | zibal | idpay | sim */
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) NOT NULL DEFAULT 'cod'",
    /* وضعیت پرداخت مستقل از وضعیت سفارش نگه داشته می‌شود */
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid'",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(15,0) NOT NULL DEFAULT 0",
    /* شمارهٔ پیگیری بانک (RefID / refNumber / track_id) */
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_ref VARCHAR(120) NULL",
    /* توکن درگاه (Authority / trackId / id) — برای پیدا‌کردن سفارش در بازگشت */
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_authority VARCHAR(150) NULL",
    /* شمارهٔ کارت ماسک‌شده‌ای که درگاه برمی‌گرداند */
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_card VARCHAR(40) NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_note VARCHAR(255) NULL",

    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        gateway VARCHAR(30) NOT NULL,
        amount DECIMAL(15,0) NOT NULL DEFAULT 0,
        authority VARCHAR(150) NULL,
        ref_id VARCHAR(120) NULL,
        card_pan VARCHAR(40) NULL,
        status ENUM('created','redirected','paid','failed','canceled') NOT NULL DEFAULT 'created',
        message VARCHAR(255) NULL,
        raw TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX idx_pay_order (order_id),
        INDEX idx_pay_authority (authority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$ok = 0; $fail = 0;
foreach ($stmts as $s) {
    try { $pdo->exec($s); $ok++; } catch (Exception $e) { echo "ERR: " . htmlspecialchars($e->getMessage()) . "<br>"; $fail++; }
}

/* سفارش‌های قبلی همه «پرداخت در محل» بوده‌اند */
try { $pdo->exec("UPDATE orders SET payment_method='cod' WHERE payment_method IS NULL OR payment_method=''"); $ok++; }
catch (Exception $e) { $fail++; }

/* کلیدهای پیش‌فرض تنظیمات — INSERT IGNORE تا مقدارهای موجود دست‌نخورده بمانند.
   حالت آزمایشی روشن است تا کل چرخهٔ پرداخت بدون هیچ اطلاعات درگاهی قابل تست باشد. */
$defaults = [
    ['pay_test_mode', '1'],
    ['pay_enable_cod', '1'],
    ['pay_enable_card', '0'],
    ['pay_enable_online', '1'],
    ['pay_gateway', 'sim'],
    ['pay_unit', 'rial'],
    ['pay_desc', 'پرداخت سفارش {order} در {site}'],
    ['pay_min_amount', '1000'],
    ['pay_cod_note', 'مبلغ سفارش هنگام تحویل کالا دریافت می‌شود.'],
    ['pay_card_number', ''],
    ['pay_card_holder', ''],
    ['pay_card_bank', ''],
    ['pay_card_note', 'پس از واریز، شمارهٔ پیگیری را از طریق تلفن یا پیام‌رسان‌ها برای ما ارسال کنید.'],
    ['pay_zarinpal_merchant', ''],
    ['pay_zibal_merchant', ''],
    ['pay_idpay_key', ''],
];
$stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach ($defaults as $d) { try { $stmt->execute($d); $ok++; } catch (Exception $e) { $fail++; } }

echo "Payment migration done. OK: $ok — Failed: $fail";
@unlink(__FILE__);
