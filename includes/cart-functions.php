<?php
require_once __DIR__ . '/db.php';

function cartAdd($productId, $quantity = 1) {
    $stmt = $GLOBALS['pdo']->prepare("SELECT id, stock FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) return ['success' => false, 'message' => 'محصول یافت نشد.'];

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

    $currentQty = $_SESSION['cart'][$productId] ?? 0;
    $newQty = $currentQty + $quantity;

    if ($newQty > $product['stock']) {
        return ['success' => false, 'message' => 'موجودی کافی نیست.'];
    }

    $_SESSION['cart'][$productId] = $newQty;
    return ['success' => true, 'message' => 'محصول به سبد خرید اضافه شد.'];
}

function cartUpdate($productId, $quantity) {
    if ($quantity <= 0) {
        return cartRemove($productId);
    }

    $stmt = $GLOBALS['pdo']->prepare("SELECT id, stock FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) return ['success' => false, 'message' => 'محصول یافت نشد.'];

    if ($quantity > $product['stock']) {
        $quantity = $product['stock'];
    }

    $_SESSION['cart'][$productId] = $quantity;
    return ['success' => true, 'message' => 'سبد خرید به‌روز شد.'];
}

function cartRemove($productId) {
    unset($_SESSION['cart'][$productId]);
    return ['success' => true, 'message' => 'محصول از سبد خرید حذف شد.'];
}

function cartClear() {
    $_SESSION['cart'] = [];
    /* روش ارسالِ انتخاب‌شده در سبد هم با سبد پاک می‌شود، وگرنه سفارش بعدی با
       روشِ سفارش قبلی شروع می‌شد. */
    unset($_SESSION['ship_method']);
}

/* وضعیت تازهٔ سبد برای پاسخ AJAX — تا صفحهٔ سبد بدون رفرش هم‌گام بماند.
   قیمت واحد همیشه در سرور بازمحاسبه می‌شود (getCartItems)، پس عبور تعداد از
   آستانهٔ عمده همان‌جا نوع قیمت را عوض می‌کند و مرورگر فقط نتیجه را نشان می‌دهد. */
function cartAjaxState($productId = 0) {
    $state = [
        'count' => function_exists('getCartCount') ? getCartCount() : 0,
        'total' => 0,
        'total_html' => '',
        'empty' => true,
    ];
    if (!function_exists('getCartItems')) return $state;

    $money = function ($v) {
        return function_exists('formatPriceUnit') ? formatPriceUnit($v) : number_format((float)$v);
    };

    $items = getCartItems();
    $total = 0;
    foreach ($items as $it) $total += $it['subtotal'];
    $tax = function_exists('itemsTaxTotal') ? itemsTaxTotal($items) : 0;

    $state['total'] = $total;
    $state['total_html'] = $money($total);
    $state['tax'] = $tax;
    $state['tax_html'] = $tax > 0 ? $money($tax) : '';
    /* اگر بلوکِ ارسال روی صفحه نیست (shippingReady/AvailableMethods خاموش)،
       ردیفِ «مبلغ کل» سبد جداگانه و بدون کمکِ ship زنده می‌شود؛ همان‌جا هم
       مالیات باید لحاظ شود. وقتی بلوک ارسال هست، payable_html خودِ ship این
       کار را می‌کند (goodsTotal که پایین‌تر به آن داده می‌شود شاملِ مالیات است). */
    $state['alltotal_html'] = $money($total + $tax);
    $state['empty'] = empty($items);

    if ($productId) {
        /* پیدا نشدن ردیف = حذف‌شده (تعداد صفر یا دکمهٔ حذف) */
        $state['item'] = ['id' => (int)$productId, 'removed' => true];
        foreach ($items as $it) {
            if ((int)$it['product']['id'] !== (int)$productId) continue;
            $state['item'] = [
                'id'         => (int)$productId,
                'removed'    => false,
                'quantity'   => (int)$it['quantity'],
                'price_type' => $it['price_type'],
                'type_label' => $it['price_type'] === 'wholesale' ? 'کلی' : 'جزئی',
                'price_html' => $money($it['price']),
                'sub_html'   => $money($it['subtotal']),
                'max'        => (int)$it['product']['stock'],
            ];
            break;
        }
    }

    $state['ship'] = cartShipState($items, $total + $tax);
    return $state;
}

/* ---------- وضعیت تازهٔ بلوک ارسالِ صفحهٔ سبد ----------
   با هر تغییر تعداد، وزن سبد و در نتیجه نرخِ همهٔ روش‌ها و «کم‌ترین هزینه» عوض
   می‌شود (خواستهٔ مدیر: «وزن و هزینه بر اساس تعداد در سبد من تغییر کنه»). همان
   shippingCartSummary() سرور محاسبه می‌کند که رندرِ اولِ صفحه را هم ساخته، پس
   عددِ زنده با عددِ رندرشده و با عددی که در ثبت سفارش حساب می‌شود یکی است —
   هیچ نسخهٔ جاوااسکریپتیِ موازی از این محاسبه وجود ندارد.
   خروجی null یعنی این صفحه بلوک ارسال ندارد و مرورگر نباید چیزی را دست بزند. */
function cartShipState(array $items, $goodsTotal) {
    if (!function_exists('shippingCartSummary') || !function_exists('shippingReady')) return null;
    if (!shippingReady() || !shippingAvailableMethods() || !$items) return null;

    $city = '';
    if (function_exists('isCustomerLoggedIn') && isCustomerLoggedIn()) {
        $city = trim((string)(currentCustomer()['city'] ?? ''));
    }
    $s = shippingCartSummary($items, $city, $goodsTotal);

    $rows = [];
    foreach ($s['quotes'] as $q) {
        $rows[] = [
            'key'       => $q['key'],
            'price'     => shippingQuoteBadgeOnly($q) ? '' : (string)$q['text'],
            'soft'      => empty($q['known']),
            'badge_only' => shippingQuoteBadgeOnly($q),
            'best'      => !empty($s['best']) && $s['best']['key'] === $q['key'],
            /* روشِ محدود به شهرِ دیگر: با تغییر تعداد هم غیرفعال می‌ماند (شهر عوض
               نمی‌شود، ولی مرورگر نباید ردیف را از حالت غیرفعال دربیاورد). */
            'off'       => !empty($q['blocked']),
        ];
    }

    $bestHtml = '';
    if (!empty($s['best'])) {
        $bestHtml = ' — کم‌ترین هزینه: <b>' . h(formatPrice((int)$s['best']['cost']))
                  . '</b> با «' . h((string)$s['best']['label']) . '».';
    }

    return [
        'rows'         => $rows,
        'pick'         => $s['pick'],
        'weight_line'  => $s['weight_line'],
        'best_html'    => $bestHtml,
        'cost_text'    => $s['cost_text'],
        'cost_soft'    => ($s['pick'] === '' || $s['cost'] <= 0),
        'payable_html' => formatPriceUnit($s['payable']),
        'ready'        => $s['pick'] !== '',
    ];
}

function handleCartAction() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $action = $_POST['action'] ?? '';

    /* انتخاب روش ارسال در صفحهٔ سبد خرید — محصولی در کار نیست، پس پیش از
       بررسی product_id پاسخ داده می‌شود. بدون جاوااسکریپت، تغییر رادیو فرم را
       submit می‌کند و صفحه با انتخاب تازه دوباره رندر می‌شود. */
    if ($action === 'ship') {
        if (!function_exists('shippingSetSessionMethod')) return null;
        $picked = shippingSetSessionMethod($_POST['shipping_method'] ?? '');
        $result = [
            'success' => true,
            'message' => $picked === ''
                ? 'روش ارسال انتخاب نشد.'
                : 'روش ارسال: ' . shippingLabel($picked),
        ];
        return cartAjaxReply($result, 0);
    }

    $productId = (int)($_POST['product_id'] ?? 0);

    if (!$productId) return;

    /* تعداد ممکن است با ارقام فارسی/عربی ارسال شود (کیبورد فارسی یا copy/paste)؛
       بدون تبدیل، (int) نتیجه را صفر می‌کند و «قیمت کلی» هرگز اعمال نمی‌شود. */
    $rawQty = (string)($_POST['quantity'] ?? '');
    if (function_exists('faToLatinDigits')) $rawQty = faToLatinDigits($rawQty);
    $rawQty = preg_replace('/\D+/', '', $rawQty);

    /* دکمه‌های +/− سبد بدون جاوااسکریپت هم کار کنند: submit با name="step".
       در این حالت مقدارِ فیلد نادیده گرفته و روی تعدادِ فعلیِ سبد اعمال می‌شود. */
    $step = 0;
    if (isset($_POST['step'])) $step = (int)$_POST['step'] > 0 ? 1 : -1;

    $result = null;
    switch ($action) {
        case 'add':
            $qty = max(1, (int)($rawQty === '' ? 1 : $rawQty));
            $result = cartAdd($productId, $qty);
            break;
        case 'update':
            if ($step !== 0) {
                $qty = max(0, (int)($_SESSION['cart'][$productId] ?? 0) + $step);
            } else {
                $qty = max(0, (int)($rawQty === '' ? 0 : $rawQty));
            }
            $result = cartUpdate($productId, $qty);
            break;
        case 'remove':
            $result = cartRemove($productId);
            break;
    }

    return cartAjaxReply($result, $productId);
}

/* پاسخِ مشترکِ همهٔ عملیات سبد: در حالت AJAX خروجی JSON و پایانِ کار، وگرنه
   همان آرایه تا هدر پیغامِ «توست» را نشان بدهد.
   حتی وقتی عملیات ناموفق بود (موجودی کافی نیست) وضعیت تازه برگردانده می‌شود
   تا مرورگر فیلد را به مقدار واقعیِ سبد برگرداند. */
function cartAjaxReply($result, $productId = 0) {
    if (!$result) return $result;
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) return $result;

    $result['cart'] = cartAjaxState($productId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}