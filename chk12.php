<?php
/* بررسی زندهٔ گروه «و» — استپر تعداد در سبد خرید.
   سبد در session است، پس هیچ نوشتنی در دیتابیس انجام نمی‌شود. */
if (($_GET['key'] ?? '') !== 'c12cart4471') { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';

function say($k, $v) { echo str_pad($k, 42, '.') . ' ' . $v . "\n"; }
function yn($b)      { return $b ? 'YES' : '*** NO ***'; }
function has($s, $n) { return strpos($s, $n) !== false; }
function dirt($s) {
    foreach (['Warning:', 'Notice:', 'Deprecated:', 'Fatal error:', 'Undefined'] as $w) {
        if (strpos($s, $w) !== false) return $w;
    }
    return '';
}
/* شبیه‌سازی POST بدون هدر AJAX تا handleCartAction() به‌جای exit، مقدار برگرداند */
function post($fields) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    $_POST = $fields;
    $r = handleCartAction();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    return $r;
}

echo "=== 1) توابع و کالای آزمایشی ===\n";
say('fn cartAjaxState', yn(function_exists('cartAjaxState')));
say('fn handleCartAction', yn(function_exists('handleCartAction')));

$zzP = $pdo->query("SELECT id, name, stock, wholesale_min_qty, retail_price, wholesale_price,
                           retail_discount, wholesale_discount
                    FROM products
                    WHERE is_active = 1 AND wholesale_min_qty > 1 AND stock >= wholesale_min_qty + 2
                    ORDER BY wholesale_min_qty ASC, id DESC LIMIT 1")->fetch();
if (!$zzP) { echo "*** کالای مناسب (آستانهٔ کلی>1 و موجودی کافی) یافت نشد\n"; exit; }
$zzId = (int)$zzP['id'];
$zzMin = (int)$zzP['wholesale_min_qty'];
$zzStock = (int)$zzP['stock'];
say('product', $zzId . ' — ' . $zzP['name']);
say('آستانهٔ کلی / موجودی', $zzMin . ' / ' . $zzStock);

echo "\n=== 2) رندر cart.php با ۱ عدد ===\n";
$_SESSION['cart'] = [$zzId => 1];
$_GET = [];
ob_start(); include __DIR__ . '/cart.php'; $zzC1 = ob_get_clean();
say('ردیف cart-row + data-id', yn(has($zzC1, 'class="cart-row" data-id="' . $zzId . '"')));
say('فرم cart-update-form', yn(has($zzC1, 'class="cart-update-form"')));
say('data-min-whole', yn(has($zzC1, 'data-min-whole="' . $zzMin . '"')));
say('استپر qty-stepper cart-stepper', yn(has($zzC1, 'class="qty-stepper cart-stepper"')));
say('دکمهٔ منها step=-1', yn(has($zzC1, 'name="step" value="-1"')));
say('دکمهٔ بعلاوه step=1', yn(has($zzC1, 'name="step" value="1"')));
say('فیلد text + inputmode', yn(has($zzC1, 'class="qty-field cart-qty-field"') && has($zzC1, 'inputmode="numeric"')));
say('type=number حذف شده', yn(!has($zzC1, 'cart-qty-input') && !has($zzC1, 'onchange="this.form.submit()"')));
say('data-max = موجودی', yn(has($zzC1, 'data-max="' . $zzStock . '"')));
say('مقدار فیلد = 1', yn(has($zzC1, 'name="quantity" value="1"')));
say('دکمهٔ پیش‌فرض Enter', yn(has($zzC1, 'class="cart-enter-submit"')));
say('fallback بدون JS (noscript)', yn(has($zzC1, '<noscript>') && has($zzC1, 'cart-apply')));
say('قیمت واحد cart-unit-price', yn(has($zzC1, 'class="cart-unit-price"')));
say('نشان جزئی', yn(has($zzC1, 'cart-price-type retail')));
say('جمع کل cart-total-val', yn(has($zzC1, 'class="cart-total-val"')));
say('فرم حذف دست‌نخورده', yn(has($zzC1, 'value="remove"') && has($zzC1, 'حذف</button>')));
say('style.css?v=17', yn(has($zzC1, 'style.css?v=17')));
say('cart.js?v=17', yn(has($zzC1, 'cart.js?v=17')));
$d = dirt($zzC1); say('خروجی پاک', $d === '' ? 'YES' : "*** $d ***");

echo "\n=== 3) cartAjaxState — قیمت جزئی ===\n";
$zzS = cartAjaxState($zzId);
say('count = 1', yn((int)$zzS['count'] === 1));
say('empty = false', yn($zzS['empty'] === false));
say('item.removed = false', yn(isset($zzS['item']) && $zzS['item']['removed'] === false));
say('item.quantity = 1', yn($zzS['item']['quantity'] === 1));
say('item.price_type = retail', yn($zzS['item']['price_type'] === 'retail'));
say('item.type_label = جزئی', yn($zzS['item']['type_label'] === 'جزئی'));
say('item.max = موجودی', yn($zzS['item']['max'] === $zzStock));
say('total_html با pnum/tmn', yn(has($zzS['total_html'], 'pnum') && has($zzS['total_html'], 'tmn')));
say('sub_html با pnum', yn(has($zzS['item']['sub_html'], 'pnum')));
$zzRetail = discountedPrice($zzP['retail_price'], $zzP['retail_discount']);
say('total = قیمت جزئی × 1', yn((float)$zzS['total'] === (float)$zzRetail) . ' (' . $zzS['total'] . ')');

echo "\n=== 4) عبور از آستانه → قیمت کلی ===\n";
$_SESSION['cart'] = [$zzId => $zzMin];
$zzS2 = cartAjaxState($zzId);
$zzWhole = discountedPrice($zzP['wholesale_price'], $zzP['wholesale_discount']);
say('count = آستانه', yn((int)$zzS2['count'] === $zzMin));
say('price_type = wholesale', yn($zzS2['item']['price_type'] === 'wholesale'));
say('type_label = کلی', yn($zzS2['item']['type_label'] === 'کلی'));
say('total = قیمت کلی × آستانه', yn((float)$zzS2['total'] === (float)$zzWhole * $zzMin) . ' (' . $zzS2['total'] . ')');
say('یک عدد کمتر → جزئی', yn(
    ($_SESSION['cart'] = [$zzId => $zzMin - 1]) && cartAjaxState($zzId)['item']['price_type'] === 'retail'));

echo "\n=== 5) رندر cart.php با تعداد کلی ===\n";
$_SESSION['cart'] = [$zzId => $zzMin];
$_GET = [];
ob_start(); include __DIR__ . '/cart.php'; $zzC2 = ob_get_clean();
say('نشان کلی در جدول', yn(has($zzC2, 'cart-price-type wholesale') && has($zzC2, 'کلی')));
say('مقدار فیلد = آستانه', yn(has($zzC2, 'name="quantity" value="' . $zzMin . '"')));
$d = dirt($zzC2); say('خروجی پاک', $d === '' ? 'YES' : "*** $d ***");

echo "\n=== 6) دکمه‌های +/− بدون جاوااسکریپت (name=step) ===\n";
$_SESSION['cart'] = [$zzId => 2];
$r = post(['action' => 'update', 'product_id' => $zzId, 'step' => '1', 'quantity' => '2']);
say('step=+1 → 3', yn((int)$_SESSION['cart'][$zzId] === 3) . ' (' . $_SESSION['cart'][$zzId] . ')');
say('پیام موفق', yn(!empty($r['success'])));
post(['action' => 'update', 'product_id' => $zzId, 'step' => '-1', 'quantity' => '3']);
say('step=-1 → 2', yn((int)$_SESSION['cart'][$zzId] === 2) . ' (' . $_SESSION['cart'][$zzId] . ')');
/* step باید بر مقدار فیلد اولویت داشته باشد، نه جمع آن */
$_SESSION['cart'] = [$zzId => 5];
post(['action' => 'update', 'product_id' => $zzId, 'step' => '1', 'quantity' => '99']);
say('step بر فیلد اولویت دارد → 6', yn((int)$_SESSION['cart'][$zzId] === 6) . ' (' . $_SESSION['cart'][$zzId] . ')');

echo "\n=== 7) تعداد دستی + ارقام فارسی + سقف موجودی ===\n";
$_SESSION['cart'] = [$zzId => 1];
post(['action' => 'update', 'product_id' => $zzId, 'quantity' => '۴']);
say('ارقام فارسی «۴» → 4', yn((int)$_SESSION['cart'][$zzId] === 4) . ' (' . $_SESSION['cart'][$zzId] . ')');
post(['action' => 'update', 'product_id' => $zzId, 'quantity' => (string)($zzStock + 50)]);
say('بیش از موجودی → سقف', yn((int)$_SESSION['cart'][$zzId] === $zzStock) . ' (' . $_SESSION['cart'][$zzId] . ')');
say('state.item.quantity = سقف', yn(cartAjaxState($zzId)['item']['quantity'] === $zzStock));

echo "\n=== 8) رسیدن به صفر → حذف ردیف ===\n";
$_SESSION['cart'] = [$zzId => 1];
post(['action' => 'update', 'product_id' => $zzId, 'step' => '-1', 'quantity' => '1']);
say('کالا از سبد رفت', yn(!isset($_SESSION['cart'][$zzId])));
$zzS3 = cartAjaxState($zzId);
say('item.removed = true', yn(!empty($zzS3['item']['removed'])));
say('empty = true', yn($zzS3['empty'] === true));
say('count = 0', yn((int)$zzS3['count'] === 0));

echo "\n=== 9) رندر سبد خالی ===\n";
$_SESSION['cart'] = [];
$_GET = [];
ob_start(); include __DIR__ . '/cart.php'; $zzC3 = ob_get_clean();
say('بلوک «سبد خالی است»', yn(has($zzC3, 'cart-empty') && has($zzC3, 'سبد خرید شما خالی است')));
say('جدولی رندر نشده', yn(!has($zzC3, 'cart-update-form')));
$d = dirt($zzC3); say('خروجی پاک', $d === '' ? 'YES' : "*** $d ***");

echo "\n=== 10) فایل جاوااسکریپت مستقرشده ===\n";
$zzJs = (string)@file_get_contents(__DIR__ . '/assets/js/cart.js');
say('طول فایل', (string)strlen($zzJs));
foreach (['applyCartState', 'pushQty', 'clampQty', 'toLatinDigits', 'cart-qty-field',
          'cart-total-val', 'cart-price-type', 'is-busy', '__seq', 'cart-update-form'] as $tk) {
    say("js دارد: $tk", yn(has($zzJs, $tk)));
}
say('badge با عدد دقیق سرور', yn(has($zzJs, 'updateCartBadge(cart.count)')));

echo "\n=== 11) پاک‌سازی ===\n";
$_SESSION['cart'] = [];
say('سبد نشست خالی شد', yn(getCartCount() === 0));
$zzCss = (string)@file_get_contents(__DIR__ . '/assets/css/style.css');
say('css: cart-stepper', yn(has($zzCss, '.cart-stepper')));
say('css: cart-enter-submit', yn(has($zzCss, '.cart-enter-submit')));
say('css: cart-qty-input حذف شد', yn(!has($zzCss, '.cart-qty-input')));
echo "\nDONE\n";
