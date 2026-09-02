<?php
/* اجرای یک‌بارهٔ ساختِ جدولِ منوهای قابل‌مدیریتِ سایت + بذرِ چیدمانِ فعلی.
   بعد از اجرا روی هاست با up.php به 404 خنثی می‌شود (طبق قرارداد پروژه) —
   نگه نمی‌ماند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/menu.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_menus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_group VARCHAR(20) NOT NULL,
        item_key VARCHAR(30) NULL,
        label VARCHAR(120) NOT NULL,
        url VARCHAR(255) NULL,
        icon VARCHAR(30) NOT NULL DEFAULT 'menu',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK: table site_menus ready\n";
} catch (Throwable $e) {
    echo "FAIL: create table -- " . $e->getMessage() . "\n";
    exit;
}

/* بذرِ اولیه فقط اگر جدول کاملاً خالی است — اجرای دوباره چیزی تکراری نمی‌سازد */
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM site_menus")->fetchColumn();
if ($cnt === 0) {
    $ins = $pdo->prepare("INSERT INTO site_menus (menu_group, item_key, label, url, icon, sort_order, is_active)
                           VALUES (?, ?, ?, ?, ?, ?, 1)");
    foreach (['main', 'footer'] as $grp) {
        $order = 10;
        foreach (menuDefaults($grp) as $it) {
            $ins->execute([$grp, $it['item_key'], $it['label'], $it['url'], $it['icon'], $order]);
            $order += 10;
        }
    }
    echo "OK: seeded default menu items\n";
} else {
    echo "SKIP seed: table already has $cnt row(s)\n";
}
echo "DONE\n";
