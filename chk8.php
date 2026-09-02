<?php
/* ------------------------------------------------------------------
   بررسی زمان‌اجرای «روند پیگیری سفارش» (گروه ب) پشت گیت‌های ورود.
   چون هنوز هیچ سفارشی در دیتابیس نیست، یک مشتری و سفارش آزمایشی
   داخل یک تراکنش می‌سازد، صفحه‌ها را رندر می‌کند و بعد ROLLBACK می‌کند
   (هیچ ردی در دیتابیس نمی‌ماند). یک‌بارمصرف — سپس به ۴۰۴ خنثی شود.
   اجرا: http://yadakii.ir/chk8.php?key=b8chk9427
   ------------------------------------------------------------------ */
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== 'b8chk9427') { http_response_code(404); exit; }

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$out = [];
function say($k, $v) { global $out; $out[] = $k . ': ' . $v; }
function yn($b) { return $b ? 'YES' : 'NO'; }
function dump() { global $out; echo implode("\n", $out), "\n"; }

/* ---------- ۱) گاردها، آیکن، تایم‌لاین با دادهٔ ساختگی (بی‌نیاز از دیتابیس) ---------- */
say('trackingReady', yn(trackingReady()));
say('orderTrackSteps count', count(orderTrackSteps()));
say('icon truck present', yn(icon('truck') !== ''));

$fake = [
    'status'                   => 'confirmed',
    'track_confirmed_at'       => '2026-08-18 09:00:00',
    'track_collecting_at'      => '2026-08-18 10:30:00',
    'track_finding_courier_at' => null,
    'track_courier_at'         => '2026-08-18 12:15:00',
    'track_shipped_at'         => null,
    'courier_name'             => 'AAA BBB',
    'courier_phone'            => '09120000000',
];
$html = renderOrderTimeline($fake);
say('timeline renders', yn($html !== ''));
say('timeline done steps', substr_count($html, 'is-done'));
say('timeline todo steps', substr_count($html, 'is-todo'));
say('timeline courier box', yn(strpos($html, 'track-courier') !== false));
say('timeline tel link', yn(strpos($html, 'tel:09120000000') !== false));
say('timeline jalali stamp', yn(strpos($html, '1405/05/27') !== false));

$blank = $fake;
foreach (orderTrackSteps() as $c => $d) { $blank[$c] = null; }
say('empty timeline is note', yn(strpos(renderOrderTimeline($blank), 'track-empty') !== false));

/* ---------- ۲) ساخت ردیف آزمایشی داخل تراکنش ---------- */
/* برای هر جدول، ستون‌های NOT NULL بدون مقدار پیش‌فرض را خودکار پر می‌کنیم
   تا به شمای دقیق وابسته نباشیم. */
function autoRow(PDO $pdo, $table, array $given) {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    $row = $given;
    foreach ($stmt->fetchAll() as $c) {
        $name = $c['COLUMN_NAME'];
        if (array_key_exists($name, $row)) continue;
        if (strpos((string)$c['EXTRA'], 'auto_increment') !== false) continue;
        if ($c['IS_NULLABLE'] === 'YES' || $c['COLUMN_DEFAULT'] !== null) continue;
        $t = strtolower($c['DATA_TYPE']);
        if (in_array($t, ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double'], true)) {
            $row[$name] = 0;
        } elseif (in_array($t, ['datetime', 'timestamp'], true)) {
            $row[$name] = date('Y-m-d H:i:s');
        } elseif ($t === 'date') {
            $row[$name] = date('Y-m-d');
        } else {
            $row[$name] = 'TEST';
        }
    }
    return $row;
}

function insertRow(PDO $pdo, $table, array $row) {
    $cols = array_keys($row);
    $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES ("
         . implode(',', array_fill(0, count($cols), '?')) . ")";
    $pdo->prepare($sql)->execute(array_values($row));
    return (int)$pdo->lastInsertId();
}

$cid = 0; $oid = 0;
try {
    $pdo->beginTransaction();

    $cid = insertRow($pdo, 'customers', autoRow($pdo, 'customers', [
        'mobile'    => '09990000000',
        'full_name' => 'TEST PROBE',
    ]));
    say('temp customer created', yn($cid > 0));

    $oid = insertRow($pdo, 'orders', autoRow($pdo, 'orders', [
        'customer_id'       => $cid,
        'customer_name'     => 'TEST PROBE',
        'customer_mobile'   => '09990000000',
        'customer_address'  => 'TEST ADDRESS',
        'total_amount'      => 1000000,
        'status'            => 'confirmed',
        'created_at'        => date('Y-m-d H:i:s'),
        /* روند پیگیری: سه مرحلهٔ اول ثبت‌شده */
        'track_confirmed_at'       => date('Y-m-d H:i:s'),
        'track_collecting_at'      => date('Y-m-d H:i:s'),
        'track_finding_courier_at' => date('Y-m-d H:i:s'),
        'courier_name'             => 'AAA BBB',
        'courier_phone'            => '09120000000',
    ]));
    say('temp order created', yn($oid > 0));

    insertRow($pdo, 'order_items', autoRow($pdo, 'order_items', [
        'order_id'     => $oid,
        'product_id'   => (int)$pdo->query("SELECT id FROM products ORDER BY id LIMIT 1")->fetchColumn(),
        'product_name' => 'TEST ITEM',
        'price'        => 500000,
        'quantity'     => 2,
        'subtotal'     => 1000000,
        'price_type'   => 'retail',
    ]));

    /* --- ۲الف) SQL همان UPDATE هندلر ادمین --- */
    $sets = []; $params = [];
    foreach (orderTrackSteps() as $col => $d) { $sets[] = "`$col` = NOW()"; }
    $sets[] = "courier_name = ?";  $params[] = 'ZZZ';
    $sets[] = "courier_phone = ?"; $params[] = '09120000001';
    $sets[] = "status = 'shipped'";
    $params[] = $oid;
    $pdo->prepare("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    $chk = $pdo->prepare("SELECT courier_name, track_shipped_at, status FROM orders WHERE id = ?");
    $chk->execute([$oid]);
    $r = $chk->fetch();
    say('handler UPDATE sql valid', 'YES');
    say('  courier written', yn(($r['courier_name'] ?? '') === 'ZZZ'));
    say('  shipped stamp set', yn(!empty($r['track_shipped_at'])));
    say('  status synced to shipped', yn(($r['status'] ?? '') === 'shipped'));

    /* برگرداندن به حالت «در جریان» تا رندر هم مرحلهٔ done و هم todo داشته باشد */
    $pdo->prepare("UPDATE orders SET status='confirmed', track_courier_at=NOW(),
        track_shipped_at=NULL, courier_name='AAA BBB', courier_phone='09120000000'
        WHERE id = ?")->execute([$oid]);

    /* --- ۲ب) رندر واقعی صفحهٔ ادمین پشت گیت ورود ---
       order-detail.php از require نسبی '../includes/...' استفاده می‌کند، پس باید
       دایرکتوری جاری روی admin/ باشد (مثل یک درخواست واقعی به همان صفحه). */
    $_SESSION['admin_id'] = 1;
    $_GET['id'] = $oid;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $cwd = getcwd();
    chdir(__DIR__ . '/admin');
    ob_start();
    include 'order-detail.php';
    $page = ob_get_clean();
    chdir($cwd);
    unset($_SESSION['admin_id']);
    say('admin page length', strlen($page));
    say('admin tracking card', yn(strpos($page, 'track-admin-list') !== false));
    say('admin checkbox count', substr_count($page, 'name="track['));
    say('admin checked count', substr_count($page, 'value="1" checked'));
    say('admin courier fields', yn(strpos($page, 'name="courier_name"') !== false
        && strpos($page, 'name="courier_phone"') !== false));
    say('admin preview timeline', yn(strpos($page, 'track-timeline') !== false));
    say('admin css v13', yn(strpos($page, 'style.css?v=13') !== false));
    say('admin clean (no error)', yn(stripos($page, 'Fatal error') === false
        && stripos($page, 'Warning:') === false && stripos($page, 'Notice:') === false));

    /* --- ۲ج) رندر واقعی صفحهٔ حساب کاربری --- */
    $_SESSION['customer_id'] = $cid;
    unset($GLOBALS['__customer_cache']);
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    include __DIR__ . '/account.php';
    $acc = ob_get_clean();
    unset($_SESSION['customer_id'], $GLOBALS['__customer_cache']);
    say('account page length', strlen($acc));
    say('account order-track block', yn(strpos($acc, 'order-track') !== false));
    say('account timeline', yn(strpos($acc, 'track-timeline') !== false));
    say('account done steps', substr_count($acc, 'is-done'));
    say('account todo steps', substr_count($acc, 'is-todo'));
    say('account courier box', yn(strpos($acc, 'track-courier') !== false));
    say('account css v13', yn(strpos($acc, 'style.css?v=13') !== false));
    say('account clean (no error)', yn(stripos($acc, 'Fatal error') === false
        && stripos($acc, 'Warning:') === false && stripos($acc, 'Notice:') === false));

    $pdo->rollBack();
    say('ROLLED BACK', yn(!$pdo->inTransaction()));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    say('EXCEPTION', $e->getMessage());
}

/* ---------- ۳) تأیید اینکه هیچ ردی نمانده ---------- */
try {
    $left = (int)$pdo->prepare("SELECT COUNT(*) FROM orders WHERE id = ?")->execute([$oid]);
    $s = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id = ?"); $s->execute([$oid]);
    say('orders rows left', (int)$s->fetchColumn());
    $s = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE id = ?"); $s->execute([$cid]);
    say('customers rows left', (int)$s->fetchColumn());
    say('total orders in db', (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn());
} catch (Throwable $e) { say('cleanup check', $e->getMessage()); }

dump();
