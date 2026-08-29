<?php
$dbHost = 'localhost';
$dbName = 'yadaki_db';
$dbUser = 'yadaki_dbuser';
$dbPass = 'R4shAd3AbJnQBJCmfWAq';

echo "<html dir='rtl'><head><meta charset='utf-8'><style>
body { font-family: Tahoma; background: #111827; color: #F9FAFB; padding: 2rem; max-width: 700px; margin: auto; }
.success { color: #10B981; } .error { color: #EF4444; } pre { background: #1F2937; padding: 1rem; border-radius: 8px; overflow-x: auto; }
h2 { color: #DC2626; }
</style></head><body>";

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "<p class='success'>✓ اتصال به دیتابیس <strong>$dbName</strong> با موفقیت انجام شد.</p>";

    $sql = file_get_contents(__DIR__ . '/database.sql');
    $pdo->exec($sql);

    echo "<p class='success'>✓ جداول و داده‌های نمونه با موفقیت در دیتابیس ایجاد شدند.</p>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>جداول ساخته شده (" . count($tables) . "):</h3><ul>";
    foreach ($tables as $t) {
        echo "<li class='success'>$t</li>";
    }
    echo "</ul>";

    echo "<h3>مراحل بعدی:</h3>";
    echo "<ol>";
    echo "<li>فایل <strong style='color:#EF4444;'>db-importer.php</strong> را از سرور حذف کنید.</li>";
    echo "<li>از طریق <a href='admin/login.php' style='color:#DC2626;'>admin/login.php</a> وارد پنل مدیریت شوید.</li>";
    echo "<li>نام کاربری: <strong>admin</strong> / رمز عبور: <strong>admin123</strong></li>";
    echo "<li>بعد از ورود، رمز عبور را تغییر دهید.</li>";
    echo "</ol>";

} catch (Exception $e) {
    echo "<p class='error'>✗ خطا: " . $e->getMessage() . "</p>";
    echo "<p>مطمئن شوید که:</p>";
    echo "<ul>";
    echo "<li>دیتابیس <strong>$dbName</strong> در phpMyAdmin وجود دارد.</li>";
    echo "<li>کاربر <strong>$dbUser</strong> به دیتابیس دسترسی دارد.</li>";
    echo "<li>فایل <strong>database.sql</strong> کنار این فایل قرار دارد.</li>";
    echo "</ul>";
}

echo "</body></html>";