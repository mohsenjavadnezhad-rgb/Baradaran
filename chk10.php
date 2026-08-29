<?php
/* ------------------------------------------------------------------
   بررسیِ زمان‌اجرای «کاروسل تصاویر محصول» (گروه د).
   چند ردیف product_images آزمایشی داخل یک تراکنش می‌سازد، صفحه‌ها را
   رندر می‌کند و بعد ROLLBACK می‌کند (هیچ ردی نمی‌ماند).
   یک‌بارمصرف — سپس به ۴۰۴ خنثی شود.
   اجرا: http://yadakii.ir/chk10.php?key=c10gal7742
   ------------------------------------------------------------------ */
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== 'c10gal7742') { http_response_code(404); exit; }

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$out = [];
function say($k, $v) { global $out; $out[] = $k . ': ' . $v; }
function yn($b) { return $b ? 'YES' : 'NO'; }
function clean($s) {
    return stripos($s, 'Fatal error') === false && stripos($s, 'Warning:') === false
        && stripos($s, 'Notice:') === false && stripos($s, 'Deprecated:') === false
        && stripos($s, 'Parse error') === false;
}
function dump() { global $out; echo implode("\n", $out), "\n"; }

/* ---------- ۱) جدول و آیکن ---------- */
say('product_images table', yn(dbHasTable('product_images')));
foreach (['product_id', 'image', 'sort_order'] as $c) {
    say('  has ' . $c, yn(dbHasColumn('product_images', $c)));
}
$tr = icon('trash', 'ic-sm');
say('trash icon', yn($tr !== '' && strpos($tr, 'viewBox="0 0 24 24"') !== false));
say('chevrons', yn(icon('chevron-right') !== '' && icon('chevron-left') !== ''));

$ids = [];
$pid = 0;
try {
    $pdo->beginTransaction();

    /* ---------- ۲) محصولِ آزمایشی (باید تصویر اصلی داشته باشد) ---------- */
    $row = $pdo->query("SELECT id, name, image FROM products
        WHERE is_active = 1 AND image IS NOT NULL AND image <> ''
        ORDER BY id DESC LIMIT 1")->fetch();
    if (!$row) { say('FATAL', 'no active product with a main image'); dump(); exit; }
    $pid = (int)$row['id'];
    say('test product', $pid . ' — ' . $row['name']);
    say('main image', $row['image']);

    $before = count(getProductImages($pid));
    say('gallery rows before', $before);

    /* ---------- ۳) درج ۳ تصویر گالری (همان SQL پنل ادمین) ---------- */
    $gs = $pdo->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?,?,?)");
    foreach (['zzchk_a.jpg', 'zzchk_b.jpg', 'zzchk_c.jpg'] as $i => $f) {
        $gs->execute([$pid, $f, $before + $i]);
        $ids[] = (int)$pdo->lastInsertId();
    }
    $imgs = getProductImages($pid);
    say('gallery rows after', count($imgs));
    say('order = a,b,c', yn(
        ($imgs[$before]['image']     ?? '') === 'zzchk_a.jpg' &&
        ($imgs[$before + 1]['image'] ?? '') === 'zzchk_b.jpg' &&
        ($imgs[$before + 2]['image'] ?? '') === 'zzchk_c.jpg'
    ));

    /* ---------- ۴) رندر واقعی صفحهٔ محصول ---------- */
    $_GET = ['id' => $pid];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    include __DIR__ . '/product.php';
    $page = ob_get_clean();
    $expect = $before + 4;   // تصویر اصلی + گالری

    say('--- product.php ---', strlen($page) . ' bytes');
    say('gallery wrapper', yn(strpos($page, 'class="product-gallery"') !== false));
    say('main slot id', yn(strpos($page, 'id="pgMainImg"') !== false));
    say('main src = product image', yn(strpos($page, 'uploads/products/' . $row['image'] . '" alt') !== false));
    say('thumb count', substr_count($page, 'class="pg-thumb'));
    say('  expected', $expect);
    say('first thumb active', yn(strpos($page, 'class="pg-thumb is-active"') !== false));
    say('temp thumbs present', yn(strpos($page, 'zzchk_a.jpg') !== false
        && strpos($page, 'zzchk_b.jpg') !== false && strpos($page, 'zzchk_c.jpg') !== false));
    say('arrows', substr_count($page, 'class="pg-arrow'));
    say('counter', yn(strpos($page, '>1 / ' . $expect . '<') !== false));
    say('carousel script', yn(strpos($page, "id=\"pgThumbs\"") !== false
        && strpos($page, 'scrollIntoView') !== false));
    say('lazy thumbs', yn(strpos($page, 'loading="lazy"') !== false));
    say('old static gallery gone', yn(strpos($page, 'گالری تصاویر</h3>') === false));
    say('qty stepper intact', yn(strpos($page, 'id="qtyMinus"') !== false));
    say('css v15', yn(strpos($page, 'style.css?v=15') !== false));
    say('clean', yn(clean($page)));

    /* ---------- ۵) رندر واقعی پنل ادمین (فقط یک‌بار — توابع دوباره تعریف نشوند) ---------- */
    $_SESSION['admin_id'] = 1;
    $cwd = getcwd();
    chdir(__DIR__ . '/admin');
    $_GET = ['id' => $pid];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    include 'product-edit.php';
    $ae = ob_get_clean();
    chdir($cwd);
    unset($_SESSION['admin_id']);

    say('--- admin/product-edit.php ---', strlen($ae) . ' bytes');
    say('gallery section', yn(strpos($ae, 'id="gallery"') !== false));
    say('multi file input', yn(strpos($ae, 'name="gallery[]"') !== false
        && strpos($ae, 'multiple') !== false));
    say('gal items', substr_count($ae, 'class="gal-item"'));
    say('move fwd links', substr_count($ae, 'gdir=fwd'));
    say('move back links', substr_count($ae, 'gdir=back'));
    say('delete links', substr_count($ae, 'gdel='));
    say('delete confirm', yn(strpos($ae, 'این تصویر حذف شود؟') !== false));
    say('is_special still there', yn(strpos($ae, 'name="is_special"') !== false));
    say('max upload shown', yn(strpos($ae, '12 تصویر') !== false));
    say('clean', yn(clean($ae)));

    /* ---------- ۶) توابع مرتب‌سازی (پس از include تعریف شده‌اند) ---------- */
    say('helpers defined', yn(function_exists('galleryMove') && function_exists('galleryOrder')
        && function_exists('galleryApplyOrder') && function_exists('saveGalleryUploads')));

    $bId = $ids[1];                      // تصویر «b»
    galleryMove($pid, $bId, -1);         // یک پله جلوتر
    $o = array_column(getProductImages($pid), 'image');
    say('after move fwd', implode(',', array_slice($o, $before)));
    say('  b moved before a', yn(($o[$before] ?? '') === 'zzchk_b.jpg'));

    galleryMove($pid, $bId, 1);          // برگرد
    $o = array_column(getProductImages($pid), 'image');
    say('after move back', implode(',', array_slice($o, $before)));
    say('  order restored', yn(($o[$before] ?? '') === 'zzchk_a.jpg'));

    /* اولین تصویر جلوتر از خودش نرود (بدون خطا) */
    galleryMove($pid, (int)getProductImages($pid)[0]['id'], -1);
    say('move past start is safe', yn(count(getProductImages($pid)) === $before + 3));

    /* ---------- ۷) ترتیب پایدار وقتی sort_order تکراری است ---------- */
    $pdo->prepare("UPDATE product_images SET sort_order = 0 WHERE product_id = ?")->execute([$pid]);
    $o = array_column(getProductImages($pid), 'id');
    $sorted = $o; sort($sorted, SORT_NUMERIC);
    say('stable tie-break by id', yn($o === $sorted));

    /* شماره‌گذاری دوباره ترتیب را پیوسته می‌کند */
    galleryApplyOrder(galleryOrder($pid));
    $so = array_column(getProductImages($pid), 'sort_order');
    say('renumbered 0..n-1', yn($so === range(0, count($so) - 1)));

    /* ---------- ۸) حذف: ردیف می‌رود، فایلِ ناموجود مشکلی نمی‌سازد ---------- */
    $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$ids[2]]);
    galleryUnlinkIfUnused('zzchk_c.jpg');
    say('after delete count', count(getProductImages($pid)));
    say('main image kept', yn((string)$pdo->query("SELECT image FROM products WHERE id = " . $pid)->fetchColumn() === $row['image']));
    /* تصویر اصلی نباید حذف شود چون products به آن ارجاع دارد */
    galleryUnlinkIfUnused($row['image']);
    say('main file still on disk', yn(is_file(__DIR__ . '/uploads/products/' . $row['image'])));

    $pdo->rollBack();
    say('ROLLED BACK', yn(!$pdo->inTransaction()));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    say('EXCEPTION', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}

/* ---------- ۹) تأیید نبودِ هر ردّی ---------- */
try {
    $left = (int)$pdo->query("SELECT COUNT(*) FROM product_images WHERE image LIKE 'zzchk_%'")->fetchColumn();
    say('temp gallery rows left', $left);
    if ($pid) say('gallery rows now', count(getProductImages($pid)));
} catch (Throwable $e) { say('cleanup check', $e->getMessage()); }

dump();
