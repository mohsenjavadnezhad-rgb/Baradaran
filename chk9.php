<?php
/* ------------------------------------------------------------------
   بررسیِ زمان‌اجرای «دو بنر کناری» (گروه ج).
   یک محصولِ «فروش ویژه» و یک آفر زمان‌دارِ آزمایشی داخل یک تراکنش
   می‌سازد، صفحه‌ها را رندر می‌کند و بعد ROLLBACK می‌کند (هیچ ردی نمی‌ماند).
   یک‌بارمصرف — سپس به ۴۰۴ خنثی شود.
   اجرا: http://yadakii.ir/chk9.php?key=c9chk5183
   ------------------------------------------------------------------ */
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== 'c9chk5183') { http_response_code(404); exit; }

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$out = [];
function say($k, $v) { global $out; $out[] = $k . ': ' . $v; }
function yn($b) { return $b ? 'YES' : 'NO'; }
function dump() { global $out; echo implode("\n", $out), "\n"; }

/* ---------- ۱) گاردها و شِمای جدول ---------- */
say('specialSaleReady', yn(specialSaleReady()));
say('timedOffersReady', yn(timedOffersReady()));

try {
    $cols = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'timed_offers'
        ORDER BY ORDINAL_POSITION")->fetchAll();
    $names = [];
    foreach ($cols as $c) { $names[] = $c['COLUMN_NAME'] . '(' . $c['COLUMN_TYPE'] . ($c['IS_NULLABLE'] === 'YES' ? ',null' : '') . ')'; }
    say('timed_offers columns', implode(' | ', $names));
    foreach (['product_id','image','title','subtitle','end_at','sort_order','is_active'] as $need) {
        say('  has ' . $need, yn(dbHasColumn('timed_offers', $need)));
    }
} catch (Throwable $e) { say('schema read', 'ERR ' . $e->getMessage()); }

say('existing offers', (int)$pdo->query("SELECT COUNT(*) FROM timed_offers")->fetchColumn());
say('existing specials', (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_special = 1")->fetchColumn());

/* ---------- ۲) تراکنش: ساخت دادهٔ آزمایشی و رندر واقعی ---------- */
$offerId = 0;
try {
    $pdo->beginTransaction();

    /* --- ۲الف) محصولِ فروش ویژه (با تخفیف، تا شاخهٔ تخفیف هم آزمایش شود) --- */
    $spId = (int)$pdo->query("SELECT id FROM products WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
    say('special test product id', $spId);
    $pdo->prepare("UPDATE products SET is_special = 1, retail_discount = 20 WHERE id = ?")->execute([$spId]);

    /* همان SQL توگلِ admin/products.php */
    $pdo->prepare("UPDATE products SET is_special = 1 - is_special WHERE id = ?")->execute([$spId]);
    $st = $pdo->prepare("SELECT is_special FROM products WHERE id = ?"); $st->execute([$spId]);
    say('toggle sql off', yn((int)$st->fetchColumn() === 0));
    $pdo->prepare("UPDATE products SET is_special = 1 - is_special WHERE id = ?")->execute([$spId]);
    $st->execute([$spId]);
    say('toggle sql on', yn((int)$st->fetchColumn() === 1));

    $sp = getSpecialSaleProduct();
    say('getSpecialSaleProduct', yn($sp && (int)$sp['id'] === $spId));
    say('  discount picked up', yn($sp && (int)$sp['retail_discount'] === 20));

    /* --- ۲ب) آفر زمان‌دار با همان INSERT پنل ادمین (سنجهٔ وجود ستون‌ها) --- */
    $endAt = date('Y-m-d H:i:s', time() + 3 * 86400 + 5 * 3600);
    $pdo->prepare("INSERT INTO timed_offers (product_id, image, title, subtitle, end_at, sort_order, is_active)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([$spId, null, 'TEST OFFER TITLE', 'TEST OFFER SUB', $endAt, -999, 1]);
    $offerId = (int)$pdo->lastInsertId();
    say('admin INSERT ok', yn($offerId > 0));

    $of = getActiveTimedOffer();
    say('getActiveTimedOffer', yn($of && (int)$of['id'] === $offerId));
    say('  title round-trip', yn($of && $of['title'] === 'TEST OFFER TITLE'));
    say('  subtitle round-trip', yn($of && ($of['subtitle'] ?? '') === 'TEST OFFER SUB'));
    say('  product joined', yn($of && !empty($of['product_name'])));
    say('  end_at kept', yn($of && $of['end_at'] === $endAt));

    /* آفر منقضی نباید انتخاب شود */
    $pdo->prepare("UPDATE timed_offers SET end_at = ? WHERE id = ?")
        ->execute([date('Y-m-d H:i:s', time() - 3600), $offerId]);
    $ofExp = getActiveTimedOffer();
    say('expired offer skipped', yn(!$ofExp || (int)$ofExp['id'] !== $offerId));
    /* غیرفعال نباید انتخاب شود */
    $pdo->prepare("UPDATE timed_offers SET end_at = ?, is_active = 0 WHERE id = ?")->execute([$endAt, $offerId]);
    $ofOff = getActiveTimedOffer();
    say('inactive offer skipped', yn(!$ofOff || (int)$ofOff['id'] !== $offerId));
    /* بازگرداندن به حالت فعال برای رندر */
    $pdo->prepare("UPDATE timed_offers SET is_active = 1 WHERE id = ?")->execute([$offerId]);

    /* --- ۲ج) رندر واقعی صفحهٔ اصلی (بنرها) --- */
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    include __DIR__ . '/banners.php';
    $page = ob_get_clean();
    say('--- banners.php ---', strlen($page) . ' bytes');
    say('hero row', yn(strpos($page, 'banner-hero-row') !== false));
    say('hero-main wrapper', yn(strpos($page, 'class="hero-main"') !== false));
    say('special banner', yn(strpos($page, 'sb-special') !== false));
    say('  links to product', yn(strpos($page, 'product.php?id=' . $spId . '" class="side-banner sb-special') !== false));
    say('  off badge', yn(strpos($page, '<span class="sb-off">20') !== false));
    say('  struck old price', yn(strpos($page, '<span class="sb-price"><s>') !== false));
    say('offer banner', yn(strpos($page, 'sb-offer') !== false));
    say('  offer title shown', yn(strpos($page, 'TEST OFFER TITLE') !== false));
    say('  offer subtitle shown', yn(strpos($page, 'TEST OFFER SUB') !== false));
    say('  countdown cells', substr_count($page, 'class="sb-cd"'));
    say('  data-end epoch', yn(strpos($page, 'data-end="' . strtotime($endAt) . '"') !== false));
    say('  3 days left', yn(strpos($page, '<b>03</b><i>روز</i>') !== false));
    say('  sb-over fallback', yn(strpos($page, 'sb-over') !== false));
    say('  countdown script', yn(strpos($page, ".sb-count[data-end]") !== false));
    say('  css v14', yn(strpos($page, 'style.css?v=14') !== false));
    say('  clean (no php error)', yn(stripos($page, 'Fatal error') === false
        && stripos($page, 'Warning:') === false && stripos($page, 'Notice:') === false
        && stripos($page, 'Deprecated:') === false));

    /* --- ۲د) رندر واقعی صفحه‌های ادمین (پشت گیت ورود) --- */
    $_SESSION['admin_id'] = 1;
    $cwd = getcwd();
    chdir(__DIR__ . '/admin');

    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start(); include 'products.php'; $ap = ob_get_clean();
    say('--- admin/products.php ---', strlen($ap) . ' bytes');
    say('special column header', yn(strpos($ap, '<th>فروش ویژه</th>') !== false));
    say('special toggle links', substr_count($ap, 'special='));
    say('special hint box', yn(strpos($ap, 'بنر عمودی «تخفیف ویژه»') !== false));
    say('active special marked', yn(strpos($ap, 'special=' . $spId . '"') !== false));
    say('clean', yn(stripos($ap, 'Fatal error') === false && stripos($ap, 'Warning:') === false
        && stripos($ap, 'Notice:') === false && stripos($ap, 'Deprecated:') === false));

    $_GET = [];
    ob_start(); include 'banners.php'; $ab = ob_get_clean();
    say('--- admin/banners.php ---', strlen($ab) . ' bytes');
    say('offer section', yn(strpos($ab, 'id="offers"') !== false));
    say('offer form fields', yn(strpos($ab, 'name="offer_days"') !== false
        && strpos($ab, 'name="offer_hours"') !== false
        && strpos($ab, 'name="offer_image"') !== false
        && strpos($ab, 'name="product_id"') !== false
        && strpos($ab, 'name="save_offer"') !== false));
    say('product dropdown options', substr_count($ab, '<option value="'));
    say('temp offer listed', yn(strpos($ab, 'TEST OFFER TITLE') !== false));
    say('remaining time shown', yn(strpos($ab, 'روز و') !== false));
    say('toggle/delete links', yn(strpos($ab, 'otoggle=' . $offerId) !== false
        && strpos($ab, 'odelete=' . $offerId) !== false));
    say('clean', yn(stripos($ab, 'Fatal error') === false && stripos($ab, 'Warning:') === false
        && stripos($ab, 'Notice:') === false && stripos($ab, 'Deprecated:') === false));

    $_GET = ['id' => $spId];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start(); include 'product-edit.php'; $ae = ob_get_clean();
    say('--- admin/product-edit.php ---', strlen($ae) . ' bytes');
    say('is_special checkbox', yn(strpos($ae, 'name="is_special"') !== false));
    say('checkbox is checked', yn(strpos($ae, 'name="is_special" value="1" checked') !== false));
    say('clean', yn(stripos($ae, 'Fatal error') === false && stripos($ae, 'Warning:') === false
        && stripos($ae, 'Notice:') === false && stripos($ae, 'Deprecated:') === false));

    chdir($cwd);
    unset($_SESSION['admin_id']);

    $pdo->rollBack();
    say('ROLLED BACK', yn(!$pdo->inTransaction()));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    say('EXCEPTION', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}

/* ---------- ۳) تأیید اینکه هیچ ردی نمانده ---------- */
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM timed_offers WHERE id = ?"); $s->execute([$offerId]);
    say('temp offer rows left', (int)$s->fetchColumn());
    say('specials in db now', (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_special = 1")->fetchColumn());
    say('offers in db now', (int)$pdo->query("SELECT COUNT(*) FROM timed_offers")->fetchColumn());
} catch (Throwable $e) { say('cleanup check', $e->getMessage()); }

dump();
