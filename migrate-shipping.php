<?php
/* مهاجرت «روش‌های ارسال» — یک‌بار اجرا و سپس خودحذف.
   ---------------------------------------------------------------
   • دو ستون روی orders: روشی که مشتری انتخاب کرده + هزینهٔ ارسالِ ثبت‌شده.
     هزینه در خودِ سفارش ذخیره می‌شود تا تغییرِ بعدیِ قیمت‌ها در تنظیمات،
     مبلغ سفارش‌های قدیمی را عوض نکند.
   • کلیدهای پیش‌فرض تنظیمات با INSERT IGNORE: همهٔ روش‌ها فعال و همه با
     هزینهٔ صفر (= پس‌کرایه / توافقی)، پس تا وقتی مدیر قیمتی وارد نکند
     هیچ رقمی به سفارش‌ها اضافه نمی‌شود.
   اطلاعات اتصال از includes/config.php خوانده می‌شود (هیچ رمزی در این فایل نیست). */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
/* فهرست روش‌ها از includes/shipping.php می‌آید (که functions.php لودش می‌کند)
   تا اگر روزی روشی اضافه شد، همین اسکریپت هم بی‌ویرایش درست باشد. */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

$stmts = [
    /* کلیدِ روشِ انتخابی: peyk_mashhad | post_sefareshi | post_pishtaz | barbari
       | chapar | tipax | digi_express | post_havaei  (خالی = ثبت‌شده پیش از این قابلیت) */
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_method VARCHAR(40) NULL",
    /* هزینهٔ ارسالِ همین سفارش؛ صفر = پس‌کرایه / توافقی */
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(15,0) NOT NULL DEFAULT 0",
];

$ok = 0; $fail = 0;
foreach ($stmts as $s) {
    try { $pdo->exec($s); $ok++; }
    catch (Exception $e) { echo "ERR: " . htmlspecialchars($e->getMessage()) . "<br>"; $fail++; }
}

/* روش‌های ارسال + کلیدهای پیش‌فرض تنظیمات */
$defaults = [];
foreach (array_keys(shippingMethods()) as $k) {
    $defaults[] = ['ship_enable_' . $k, '1'];   // همه فعال
    $defaults[] = ['ship_cost_' . $k, '0'];     // صفر = پس‌کرایه / توافقی
    $defaults[] = ['ship_note_' . $k, ''];      // توضیح دلخواه مدیر (خالی = متن پیش‌فرض)
}

$stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach ($defaults as $d) {
    try { $stmt->execute($d); $ok++; } catch (Exception $e) { $fail++; }
}
/* توضیح اختیاریِ بالای انتخابگر ارسال در صفحهٔ تسویه */
try { $stmt->execute(['ship_desc', '']); $ok++; } catch (Exception $e) { $fail++; }

echo "Shipping migration done. OK: $ok — Failed: $fail<br>";
echo "روش‌ها: " . htmlspecialchars(implode(' / ', array_column(shippingMethods(), 'label')));

@unlink(__FILE__);
