<?php
/* =====================================================================
   لایهٔ درگاه پرداخت — انتزاعی و قابل اتصال به هر درگاه ایرانی
   ---------------------------------------------------------------------
   طراحی:
   • هر درگاه یک «راننده» است با دو عمل: paymentCreate() و paymentVerify().
   • هیچ اطلاعات محرمانه‌ای در کد نیست؛ همه از جدول settings خوانده می‌شود
     (پنل ادمین ← تنظیمات ← درگاه پرداخت).
   • تا وقتی هیچ درگاهی تنظیم نشده، «درگاه آزمایشی» داخلی فعال است تا کل
     چرخهٔ پرداخت (شروع ← بازگشت ← تأیید ← ثبت وضعیت) قابل تست باشد.
   • تصمیم «پرداخت‌شده» هرگز از پارامترهای بازگشتی مرورگر گرفته نمی‌شود؛
     همیشه با یک درخواست سرور-به-سرور به درگاه تأیید می‌شود.
   ===================================================================== */
require_once __DIR__ . '/db.php';

/* ---------- تعریف درگاه‌ها ---------- */
/* kind: offline = بدون انتقال به بانک | online = انتقال به درگاه
   needs: کلیدهای تنظیماتی که بدون آن‌ها درگاه قابل استفاده نیست
   unit : واحد مبلغی که درگاه می‌پذیرد (قیمت‌های سایت به تومان است) */
function paymentGateways() {
    return [
        'cod' => [
            'label' => 'پرداخت در محل / هنگام تحویل',
            'kind'  => 'offline',
            'icon'  => 'package',
            'needs' => [],
            'unit'  => 'toman',
        ],
        'card' => [
            'label' => 'کارت به کارت',
            'kind'  => 'offline',
            'icon'  => 'credit-card',
            'needs' => ['pay_card_number'],
            'unit'  => 'toman',
        ],
        'sim' => [
            'label'     => 'درگاه آزمایشی (بدون بانک واقعی)',
            'kind'      => 'online',
            'icon'      => 'tools',
            'needs'     => [],
            'unit'      => 'toman',
            'test_only' => true,
        ],
        'zarinpal' => [
            'label' => 'زرین‌پال',
            'kind'  => 'online',
            'icon'  => 'credit-card',
            'needs' => ['pay_zarinpal_merchant'],
            'unit'  => 'rial',
        ],
        'zibal' => [
            'label' => 'زیبال',
            'kind'  => 'online',
            'icon'  => 'credit-card',
            'needs' => ['pay_zibal_merchant'],
            'unit'  => 'rial',
        ],
        'idpay' => [
            'label' => 'آیدی‌پی',
            'kind'  => 'online',
            'icon'  => 'credit-card',
            'needs' => ['pay_idpay_key'],
            'unit'  => 'rial',
        ],
        /* پرداخت اعتباری/اقساطی. برچسبش از تنظیمات خوانده می‌شود چون نام
           ارائه‌دهنده متفاوت است. پیش‌فرض خاموش (pay_enable_credit=0) و تا
           پرنشدنِ آدرس‌ها و شناسهٔ پذیرنده در فهرست تسویه ظاهر نمی‌شود.
           credit_only یعنی این درگاه، «درگاه بانکیِ اصلی» نیست و کلید خودش
           آن را روشن می‌کند (پس در فهرست انتخابِ درگاه بانکی نمی‌آید). */
        'credit' => [
            'label'       => getSetting('pay_credit_label', 'پرداخت اعتباری (اقساطی)'),
            'kind'        => 'online',
            'icon'        => 'receipt',
            'needs'       => ['pay_credit_merchant', 'pay_credit_create_url'],
            'unit'        => 'rial',
            'credit_only' => true,
        ],
        /* دو روشِ زیر فقط برای همکارانِ تأییدشده نمایش داده می‌شوند
           (paymentAvailableMethods($toman, $isPartner) — نه اینجا، آنجا فیلتر
           می‌شود) و بدون درگاه‌اند: سفارش کامل می‌شود، پرداخت بعداً و بیرون از
           سایت انجام/بررسی می‌شود. */
        'partner_month' => [
            'label' => 'پرداخت اول ماه',
            'kind'  => 'offline',
            'icon'  => 'calendar',
            'needs' => [],
            'unit'  => 'toman',
        ],
        'cheque' => [
            'label' => 'چک',
            'kind'  => 'offline',
            'icon'  => 'receipt',
            'needs' => [],
            'unit'  => 'toman',
        ],
    ];
}

/* آیا پرداخت اعتباری روشن و پیکربندی‌شده است؟ */
function paymentCreditEnabled() {
    return getSettingRaw('pay_enable_credit', '0') === '1' && paymentIsConfigured('credit');
}

/* حدِ پایینِ مبلغ برای پرداخت اعتباری (تومان). ۰ = بدون حد. */
function paymentCreditMin() {
    $v = preg_replace('/\D+/', '', faToLatinDigits((string)getSettingRaw('pay_credit_min', '0')));
    return $v === '' ? 0 : (int)$v;
}

function paymentGatewayDef($key) {
    $all = paymentGateways();
    return $all[$key] ?? null;
}

function paymentLabel($key) {
    $d = paymentGatewayDef($key);
    return $d ? $d['label'] : ($key === '' ? 'نامشخص' : $key);
}

function paymentIcon($key) {
    $d = paymentGatewayDef($key);
    return $d ? $d['icon'] : 'credit-card';
}

function paymentIsOnline($key) {
    $d = paymentGatewayDef($key);
    return $d && $d['kind'] === 'online';
}

function paymentTestMode() {
    return getSettingRaw('pay_test_mode', '1') === '1';
}

/* آیا ستون‌های پرداخت روی جدول orders ساخته شده‌اند؟
   (تا قبل از اجرای migrate-payments.php صفحات نباید خطا بدهند) */
function paymentReady() {
    if (isset($GLOBALS['__pay_ready'])) return $GLOBALS['__pay_ready'];
    global $pdo;
    $ok = false;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = ?");
        $stmt->execute(['payment_status']);
        $ok = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) { $ok = false; }
    $GLOBALS['__pay_ready'] = $ok;
    return $ok;
}

/* آیا ستون‌های «کارت به کارت» ساخته شده‌اند؟ (migrate-cityrates.php)
   پیش از مهاجرت، فرمِ ثبت واریز نمایش داده نمی‌شود و بقیهٔ صفحه سالم می‌ماند. */
function paymentC2cReady() {
    if (isset($GLOBALS['__pay_c2c'])) return $GLOBALS['__pay_c2c'];
    $GLOBALS['__pay_c2c'] = paymentReady() && dbHasColumn('orders', 'c2c_ref');
    return $GLOBALS['__pay_c2c'];
}

/* آیا کلیدهای لازم این درگاه پر شده‌اند؟ درگاه آزمایشی همیشه (در حالت تست) آماده است. */
function paymentIsConfigured($key) {
    $d = paymentGatewayDef($key);
    if (!$d) return false;
    if (!empty($d['test_only'])) return paymentTestMode();
    foreach ($d['needs'] as $need) {
        if (trim((string)getSettingRaw($need, '')) === '') return false;
    }
    return true;
}

/* درگاه آنلاین انتخاب‌شده در تنظیمات؛ اگر تنظیم نشده و حالت تست روشن است ← درگاه آزمایشی
   پرداخت اعتباری اینجا نمی‌آید: کلید مستقلِ خودش (pay_enable_credit) آن را روشن
   می‌کند و در کنارِ درگاه بانکی نمایش داده می‌شود، نه به‌جای آن. */
function paymentActiveGateway() {
    $key = trim((string)getSetting('pay_gateway', ''));
    $d   = paymentGatewayDef($key);
    if ($key !== '' && $d && empty($d['credit_only']) && paymentIsOnline($key) && paymentIsConfigured($key)) return $key;
    if (paymentTestMode()) return 'sim';
    return '';
}

/* روش‌های پرداختِ قابل نمایش در صفحهٔ تسویه، به‌ترتیب نمایش.
   $isPartner: آیا مشتریِ همین سفارش، همکارِ تأییدشده است؟ («پرداخت اول ماه»
   و «چک» فقط برای همکارانِ تأییدشده نمایش داده می‌شوند — خواستهٔ مدیر.) */
function paymentAvailableMethods($toman = null, $isPartner = false) {
    $out = [];
    if (getSettingRaw('pay_enable_cod', '1') === '1') {
        $out['cod'] = paymentGatewayDef('cod');
    }
    if (getSettingRaw('pay_enable_online', '1') === '1') {
        $gw = paymentActiveGateway();
        if ($gw !== '') $out[$gw] = paymentGatewayDef($gw);
    }
    /* کارت‌به‌کارت عمداً بعد از درگاهِ آنلاین/آزمایشی می‌آید (خواستهٔ مدیر) —
       ترتیبِ نمایش در تسویه دقیقاً همین ترتیبِ افزودن به $out است. */
    if (getSettingRaw('pay_enable_card', '0') === '1' && paymentIsConfigured('card')) {
        $out['card'] = paymentGatewayDef('card');
    }
    /* پرداخت اعتباری: مستقل از درگاه بانکی. اگر مبلغ سفارش داده شده باشد و از
       حدِ پایینِ ارائه‌دهنده کمتر باشد، نشان داده نمی‌شود تا مشتری بی‌دلیل
       به درگاهی نرود که مبلغش را نمی‌پذیرد. */
    if (paymentCreditEnabled()) {
        $min = paymentCreditMin();
        if ($toman === null || $min <= 0 || (int)$toman >= $min) {
            $out['credit'] = paymentGatewayDef('credit');
        }
    }
    /* دو روشِ ویژهٔ همکاران، آخرِ فهرست — یک مشتریِ جزئی اصلاً این دو تیک را
       نمی‌بیند، پس نیازی نیست بالای فهرستِ عمومی جا بگیرند. */
    if ($isPartner && paymentChequeReady()) {
        if (getSettingRaw('pay_enable_partner_month', '1') === '1') $out['partner_month'] = paymentGatewayDef('partner_month');
        if (getSettingRaw('pay_enable_cheque', '1') === '1')        $out['cheque']         = paymentGatewayDef('cheque');
    }
    /* اگر هیچ روشی فعال نبود، پرداخت در محل تنها راه باقی‌مانده است */
    if (!$out) $out['cod'] = paymentGatewayDef('cod');
    return $out;
}

/* مبلغ بر حسب واحد مورد انتظار درگاه (قیمت‌های سایت تومان است) */
function paymentAmountFor($key, $toman) {
    $d = paymentGatewayDef($key);
    $unit = $d['unit'] ?? 'toman';
    if ($unit === 'toman') return (int)$toman;
    /* واحد ریال؛ اگر مدیر تصریح کند که درگاه تومانی است، ضریب حذف می‌شود */
    if (getSettingRaw('pay_unit', 'rial') === 'toman') return (int)$toman;
    return (int)$toman * 10;
}

function paymentCallbackUrl($key) {
    return rtrim(SITE_URL, '/') . '/payment-callback.php?gw=' . urlencode($key);
}

function paymentDescription($order) {
    $tpl = getSetting('pay_desc', 'پرداخت سفارش {order} در {site}');
    return str_replace(['{order}', '{site}'], [(int)$order['id'], SITE_NAME], $tpl);
}

function paymentLog($line) {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    @file_put_contents($dir . '/payment.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

/* ---------- امضای درگاه آزمایشی ---------- */
/* بدون امضا، هر کسی می‌توانست payment-callback.php را با پارامترهای دلخواه
   صدا بزند و سفارش را «پرداخت‌شده» کند. کلید یک‌بار خودکار ساخته می‌شود. */
function paymentSimSecret() {
    $s = getSettingRaw('pay_sim_secret', '');
    if (trim((string)$s) === '') {
        try { $s = bin2hex(random_bytes(16)); } catch (Throwable $e) { $s = md5(uniqid('sim', true)); }
        setSetting('pay_sim_secret', $s);
    }
    return $s;
}

function paymentSimSign(array $parts) {
    return hash_hmac('sha256', implode('|', $parts), paymentSimSecret());
}

/* ---------- لاگ تلاش‌های پرداخت (جدول payments) ---------- */
function paymentAttemptCreate($orderId, $gw, $amount) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO payments (order_id, gateway, amount, status) VALUES (?, ?, ?, 'created')");
        $stmt->execute([(int)$orderId, $gw, (int)$amount]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) { paymentLog('attempt-create failed: ' . $e->getMessage()); return 0; }
}

function paymentAttemptUpdate($id, array $fields) {
    global $pdo;
    if (!$id || !$fields) return;
    $allowed = ['authority', 'ref_id', 'card_pan', 'status', 'message', 'raw'];
    $set = []; $vals = [];
    foreach ($fields as $k => $v) {
        if (!in_array($k, $allowed, true)) continue;
        $set[] = "$k = ?";
        $vals[] = ($k === 'raw' || $k === 'message') ? mb_substr((string)$v, 0, ($k === 'message' ? 250 : 4000)) : $v;
    }
    if (!$set) return;
    $set[] = "updated_at = NOW()";
    $vals[] = (int)$id;
    try { $pdo->prepare("UPDATE payments SET " . implode(', ', $set) . " WHERE id = ?")->execute($vals); }
    catch (Throwable $e) { paymentLog('attempt-update failed: ' . $e->getMessage()); }
}

/* آخرین تلاش پرداخت یک سفارش (برای نمایش در ادمین) */
function paymentAttempts($orderId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC");
        $stmt->execute([(int)$orderId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* ---------- ثبت نتیجه روی سفارش ---------- */
function paymentMarkPaid($orderId, $amountToman, $ref, $card = '', $confirmOrder = true) {
    global $pdo;
    try {
        $pdo->prepare("UPDATE orders SET payment_status='paid', paid_amount=?, payment_ref=?, payment_card=?, paid_at=NOW() WHERE id=?")
            ->execute([(int)$amountToman, (string)$ref, (string)$card, (int)$orderId]);
        if ($confirmOrder) {
            /* سفارش پرداخت‌شده خودکار «تأیید شده» می‌شود، مگر لغو شده باشد */
            $pdo->prepare("UPDATE orders SET status='confirmed' WHERE id=? AND status='pending'")->execute([(int)$orderId]);
        }
        return true;
    } catch (Throwable $e) { paymentLog("mark-paid failed order=$orderId: " . $e->getMessage()); return false; }
}

function paymentMarkFailed($orderId, $note = '') {
    global $pdo;
    try {
        $pdo->prepare("UPDATE orders SET payment_status='failed', payment_note=? WHERE id=? AND payment_status <> 'paid'")
            ->execute([mb_substr((string)$note, 0, 250), (int)$orderId]);
        return true;
    } catch (Throwable $e) { return false; }
}

function paymentSetAuthority($orderId, $gw, $authority) {
    global $pdo;
    try {
        $pdo->prepare("UPDATE orders SET payment_method=?, payment_status='pending', payment_authority=? WHERE id=?")
            ->execute([$gw, (string)$authority, (int)$orderId]);
    } catch (Throwable $e) { paymentLog("set-authority failed order=$orderId: " . $e->getMessage()); }
}

function paymentOrderByAuthority($gw, $authority) {
    global $pdo;
    if (trim((string)$authority) === '') return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE payment_authority = ? AND payment_method = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([(string)$authority, $gw]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

/* ---------- برچسب و رنگ وضعیت ---------- */
function paymentStatusLabel($status) {
    $m = [
        'unpaid'   => 'پرداخت‌نشده',
        'pending'  => 'در انتظار پرداخت',
        'paid'     => 'پرداخت‌شده',
        'failed'   => 'پرداخت ناموفق',
        'refunded' => 'بازگشت وجه',
    ];
    return $m[$status] ?? 'نامشخص';
}

function paymentStatusBadge($status) {
    $ic = ['paid' => 'check-circle', 'failed' => 'x-circle', 'pending' => 'clock', 'refunded' => 'refresh'];
    $name = $ic[$status] ?? 'alert';
    return '<span class="pay-badge pay-' . h($status) . '">' . icon($name, 'ic-sm') . ' ' . h(paymentStatusLabel($status)) . '</span>';
}

/* همان برچسب، ولی با توجه به روش پرداخت: در «کارت به کارت» وضعیت pending یعنی
   مشتری واریز را ثبت کرده و منتظر تأیید مدیر است — نه «در انتظار پرداخت».
   همهٔ نمایش‌های وضعیت (سایت و ادمین) از این دو تابع می‌خوانند تا یک جمله بگویند. */
function paymentStatusLabelFor($status, $method = '') {
    if ($method === 'card' && $status === 'pending') return 'در انتظار تأیید واریز';
    if ($method === 'cheque') {
        if ($status === 'unpaid')  return 'در انتظار ثبت چک';
        if ($status === 'pending') return 'در انتظار بررسی چک';
    }
    if ($method === 'partner_month' && $status === 'pending') return 'پرداخت اول ماه — در انتظار';
    return paymentStatusLabel($status);
}

function paymentStatusBadgeFor($status, $method = '') {
    $ic = ['paid' => 'check-circle', 'failed' => 'x-circle', 'pending' => 'clock', 'refunded' => 'refresh'];
    $name = $ic[$status] ?? 'alert';
    return '<span class="pay-badge pay-' . h($status) . '">' . icon($name, 'ic-sm') . ' '
         . h(paymentStatusLabelFor($status, $method)) . '</span>';
}

/* ---------- کارت به کارت: ثبتِ واریز توسط مشتری و تأیید توسط مدیر ----------
   مشتری چهار چیز را می‌گوید: شناسهٔ واریز، مبلغ، چهار رقم آخر کارت مبدأ و زمان
   واریز. سفارش با وضعیت «در انتظار تأیید واریز» (pending) می‌ماند تا مدیر در
   جزئیات سفارش تأیید کند — تصمیم مدیر: پرداخت خودکار تأیید نمی‌شود. */
/* پاک‌سازی و اعتبارسنجیِ ورودیِ واریز، جدا از ذخیره‌سازی. صفحهٔ تسویه اطلاعات
   واریز را پیش از ثبتِ سفارش می‌گیرد، پس باید بتواند پیش از INSERT اعتبار را
   بسنجد؛ وگرنه سفارشی ثبت می‌شد که اطلاعات واریزش ناقص است. */
function paymentC2cClean(array $in) {
    $out = [
        'ref'       => trim((string)($in['ref'] ?? '')),
        'amount'    => (int)preg_replace('/\D+/', '', faToLatinDigits((string)($in['amount'] ?? ''))),
        'last4'     => preg_replace('/\D+/', '', faToLatinDigits((string)($in['last4'] ?? ''))),
        'paid_text' => trim((string)($in['paid_text'] ?? '')),
        'error'     => '',
    ];
    if ($out['ref'] === '')                   $out['error'] = 'شناسهٔ واریز (شمارهٔ پیگیری) را وارد کنید.';
    elseif ($out['amount'] <= 0)              $out['error'] = 'مبلغ واریزی را وارد کنید.';
    elseif (strlen($out['last4']) !== 4)      $out['error'] = 'چهار رقم آخر کارت خود را وارد کنید.';
    elseif ($out['paid_text'] === '')         $out['error'] = 'تاریخ و زمان واریز را وارد کنید.';

    if (mb_strlen($out['ref']) > 60)       $out['ref']       = mb_substr($out['ref'], 0, 60);
    if (mb_strlen($out['paid_text']) > 60) $out['paid_text'] = mb_substr($out['paid_text'], 0, 60);
    return $out;
}

function paymentC2cSave($orderId, array $in) {
    global $pdo;
    if (!paymentC2cReady()) return 'ثبت واریز روی این نصب فعال نیست.';

    $v = paymentC2cClean($in);
    if ($v['error'] !== '') return $v['error'];

    try {
        $pdo->prepare("UPDATE orders
                       SET c2c_ref=?, c2c_amount=?, c2c_last4=?, c2c_paid_text=?, c2c_reported_at=NOW(),
                           payment_status='pending'
                       WHERE id=? AND payment_method='card' AND payment_status <> 'paid'")
            ->execute([$v['ref'], $v['amount'], $v['last4'], $v['paid_text'], (int)$orderId]);
        return '';
    } catch (Throwable $e) {
        paymentLog("c2c-save failed order=$orderId: " . $e->getMessage());
        return 'ثبت واریز انجام نشد. لطفاً دوباره تلاش کنید.';
    }
}

/* تأیید مدیر: همان paymentMarkPaid() با ارقامی که مشتری گفته + مهر زمان تأیید */
function paymentC2cVerify($orderId, array $order = []) {
    global $pdo;
    $orderId = (int)$orderId;
    if ($orderId <= 0) return false;
    if (!$order) {
        try {
            $st = $pdo->prepare("SELECT * FROM orders WHERE id=?");
            $st->execute([$orderId]);
            $order = $st->fetch() ?: [];
        } catch (Throwable $e) { $order = []; }
    }
    if (!$order) return false;
    /* فقط سفارشِ کارت‌به‌کارتی که مشتری واریزش را اعلام کرده و پرداخت‌نشده است.
       این گارد باعث می‌شود این تابع هیچ‌وقت جای تأییدِ دستیِ عمومی را نگیرد. */
    if (!paymentC2cAwaiting($order)) return false;

    $amount = (int)($order['c2c_amount'] ?? 0);
    if ($amount <= 0) $amount = (int)($order['total_amount'] ?? 0);
    $ref    = trim((string)($order['c2c_ref'] ?? ''));
    $last4  = trim((string)($order['c2c_last4'] ?? ''));

    if (!paymentMarkPaid($orderId, $amount, $ref, $last4, true)) return false;
    if (paymentC2cReady()) {
        try { $pdo->prepare("UPDATE orders SET c2c_verified_at=NOW() WHERE id=?")->execute([$orderId]); }
        catch (Throwable $e) {}
    }
    paymentLog("ADMIN verified c2c | order=$orderId | amount=$amount | by=" . ($_SESSION['admin_username'] ?? 'admin'));
    return true;
}

/* آیا این سفارش منتظر تأیید واریزِ کارت‌به‌کارت است؟ (نشانِ صف در فهرست ادمین) */
function paymentC2cAwaiting(array $order) {
    return (string)($order['payment_method'] ?? '') === 'card'
        && (string)($order['payment_status'] ?? '') !== 'paid'
        && trim((string)($order['c2c_ref'] ?? '')) !== '';
}

/* ---------- چک: ثبتِ اطلاعات توسط همکار و «دریافت چک» توسط مدیر ----------
   دقیقاً هم‌الگوی کارت‌به‌کارت بالا: مشتری (همکار) پس از ثبت سفارش، در همان
   صفحهٔ order-success.php اطلاعات چک را می‌گوید؛ سفارش «در انتظار بررسی چک»
   می‌ماند. تیکِ «دریافت چک» مدیر با «پرداخت شد» یکی نیست — چک رسیده‌بودن به
   معنیِ وصول‌شدنش نیست؛ برای «پرداخت شد» همان دکمهٔ عمومیِ paymentMarkPaid
   جداگانه در ادمین می‌ماند. */
function paymentChequeReady() {
    if (isset($GLOBALS['__pay_cheque'])) return $GLOBALS['__pay_cheque'];
    $GLOBALS['__pay_cheque'] = paymentReady() && dbHasColumn('orders', 'cheque_number');
    return $GLOBALS['__pay_cheque'];
}

function paymentChequeClean(array $in) {
    $out = [
        'bank'      => trim((string)($in['bank'] ?? '')),
        'number'    => trim((string)($in['number'] ?? '')),
        'date'      => trim((string)($in['date'] ?? '')),
        'amount'    => (int)preg_replace('/\D+/', '', faToLatinDigits((string)($in['amount'] ?? ''))),
        'sayad'     => trim((string)($in['sayad'] ?? '')),
        'error'     => '',
    ];
    if ($out['bank'] === '')          $out['error'] = 'نام بانک را وارد کنید.';
    elseif ($out['number'] === '')    $out['error'] = 'شمارهٔ چک را وارد کنید.';
    elseif ($out['date'] === '')      $out['error'] = 'تاریخ چک را وارد کنید.';
    elseif ($out['amount'] <= 0)      $out['error'] = 'مبلغ چک را وارد کنید.';

    if (mb_strlen($out['bank'])   > 120) $out['bank']   = mb_substr($out['bank'], 0, 120);
    if (mb_strlen($out['number']) > 60)  $out['number'] = mb_substr($out['number'], 0, 60);
    if (mb_strlen($out['date'])   > 40)  $out['date']   = mb_substr($out['date'], 0, 40);
    if (mb_strlen($out['sayad'])  > 60)  $out['sayad']  = mb_substr($out['sayad'], 0, 60);
    return $out;
}

function paymentChequeSave($orderId, array $in) {
    global $pdo;
    if (!paymentChequeReady()) return 'ثبت چک روی این نصب فعال نیست.';

    $v = paymentChequeClean($in);
    if ($v['error'] !== '') return $v['error'];

    try {
        $pdo->prepare("UPDATE orders
                       SET cheque_bank=?, cheque_number=?, cheque_date=?, cheque_amount=?, cheque_sayad=?,
                           cheque_reported_at=NOW(), payment_status='pending'
                       WHERE id=? AND payment_method='cheque' AND payment_status <> 'paid'")
            ->execute([$v['bank'], $v['number'], $v['date'], $v['amount'], $v['sayad'], (int)$orderId]);
        return '';
    } catch (Throwable $e) {
        paymentLog("cheque-save failed order=$orderId: " . $e->getMessage());
        return 'ثبت اطلاعات چک انجام نشد. لطفاً دوباره تلاش کنید.';
    }
}

/* آیا این سفارش منتظر بررسیِ چک است؟ (نشانِ صف در فهرست ادمین) */
function paymentChequeAwaiting(array $order) {
    return (string)($order['payment_method'] ?? '') === 'cheque'
        && (string)($order['payment_status'] ?? '') !== 'paid'
        && trim((string)($order['cheque_number'] ?? '')) !== '';
}

/* تیکِ «دریافت چک» مدیر — فقط زمان را ثبت می‌کند، وضعیتِ پرداخت را عوض نمی‌کند */
function paymentChequeReceive($orderId) {
    global $pdo;
    if (!paymentChequeReady()) return false;
    try {
        $pdo->prepare("UPDATE orders SET cheque_received_at=NOW() WHERE id=? AND payment_method='cheque'")
            ->execute([(int)$orderId]);
        paymentLog("ADMIN received cheque | order=$orderId | by=" . ($_SESSION['admin_username'] ?? 'admin'));
        return true;
    } catch (Throwable $e) { return false; }
}

/* =====================================================================
   ساخت پرداخت — بازگشت: ['ok'=>bool,'url'=>string,'authority'=>string,'error'=>string,'raw'=>string]
   ===================================================================== */
function paymentCreate($gw, array $order) {
    $toman  = (int)$order['total_amount'];
    $amount = paymentAmountFor($gw, $toman);
    $cb     = paymentCallbackUrl($gw);
    $desc   = paymentDescription($order);
    $mobile = preg_replace('/\D+/', '', faToLatinDigits($order['customer_mobile'] ?? ''));

    $min = (int)getSetting('pay_min_amount', '1000');
    if ($toman < $min) {
        return ['ok' => false, 'error' => 'مبلغ سفارش کمتر از حداقل مبلغ قابل پرداخت آنلاین است.'];
    }

    switch ($gw) {
        case 'sim':      return paymentCreateSim($order, $toman);
        case 'zarinpal': return paymentCreateZarinpal($order, $amount, $cb, $desc, $mobile);
        case 'zibal':    return paymentCreateZibal($order, $amount, $cb, $desc, $mobile);
        case 'idpay':    return paymentCreateIdpay($order, $amount, $cb, $desc, $mobile);
        case 'credit':   return paymentCreateCredit($order, $amount, $cb, $desc, $mobile);
    }
    return ['ok' => false, 'error' => 'این روش پرداخت آنلاین نیست.'];
}

/* ---------- پرداخت اعتباری/اقساطی ----------
   ارائه‌دهنده‌های اعتباری (اسنپ‌پی، تارا، دیجی‌پی و…) همگی یک الگو دارند:
   POST جِیسون به «آدرس ایجاد» با مبلغ و آدرس بازگشت → پاسخی که یک نشانهٔ
   یکتا (token/trackId/…) و یک آدرس پرداخت دارد. چون هنوز قراردادی بسته
   نشده، آدرس‌ها و نامِ فیلدها از تنظیمات خوانده می‌شوند تا بعداً بدون
   تغییرِ کد قابل استفاده باشند؛ پاسخ هم با چند نامِ رایج خوانده می‌شود. */
function paymentCreditHeaders() {
    $h = ['Content-Type: application/json', 'Accept: application/json'];
    $key = trim((string)getSettingRaw('pay_credit_merchant', ''));
    if ($key !== '') $h[] = 'Authorization: Bearer ' . $key;
    return $h;
}

/* اولین کلیدِ موجود از میان چند نامِ رایج را برمی‌گرداند (پاسخ‌ها یکسان نیستند) */
function paymentCreditPick($j, array $names) {
    if (!is_array($j)) return '';
    foreach ($names as $n) {
        if (isset($j[$n]) && $j[$n] !== '' && !is_array($j[$n])) return (string)$j[$n];
        if (isset($j['data'][$n]) && $j['data'][$n] !== '' && !is_array($j['data'][$n])) return (string)$j['data'][$n];
        if (isset($j['result'][$n]) && $j['result'][$n] !== '' && !is_array($j['result'][$n])) return (string)$j['result'][$n];
    }
    return '';
}

function paymentCreateCredit(array $order, $amount, $cb, $desc, $mobile) {
    /* اگر مدیر پرداخت اعتباری را خاموش کرده، هیچ مشتری تازه‌ای به آن درگاه نمی‌رود.
       (تأیید همچنان کار می‌کند تا پرداختِ نیمه‌تمامِ قبلی بی‌سرانجام نماند.) */
    if (getSettingRaw('pay_enable_credit', '0') !== '1') {
        return ['ok' => false, 'error' => 'پرداخت اعتباری در حال حاضر فعال نیست.'];
    }

    $merchant = trim((string)getSettingRaw('pay_credit_merchant', ''));
    $url      = trim((string)getSettingRaw('pay_credit_create_url', ''));
    if ($merchant === '' || $url === '') {
        return ['ok' => false, 'error' => 'پرداخت اعتباری هنوز پیکربندی نشده است.'];
    }

    $min = paymentCreditMin();
    if ($min > 0 && (int)$order['total_amount'] < $min) {
        return ['ok' => false, 'error' => 'مبلغ سفارش کمتر از حداقل مبلغ پرداخت اعتباری است.'];
    }

    $body = [
        'merchant'    => $merchant,
        'amount'      => (int)$amount,
        'callbackUrl' => $cb,
        'description' => $desc,
        'orderId'     => (string)(int)$order['id'],
    ];
    if ($mobile !== '') $body['mobile'] = $mobile;

    $res = httpPostJson($url, json_encode($body, JSON_UNESCAPED_UNICODE), paymentCreditHeaders());
    $raw = $res['body'] ?? ($res['error'] ?? '');
    $j   = json_decode((string)$raw, true);

    $auth = paymentCreditPick($j, ['token', 'trackId', 'authority', 'paymentToken', 'id', 'transactionId']);
    $pay  = paymentCreditPick($j, ['paymentUrl', 'redirectUrl', 'url', 'link', 'startUrl']);

    if (!empty($res['ok']) && $auth !== '' && $pay !== '') {
        return ['ok' => true, 'url' => $pay, 'authority' => $auth, 'raw' => $raw];
    }
    $msg = paymentCreditPick($j, ['message', 'error_message', 'errorMessage', 'error']);
    return ['ok' => false,
            'error' => 'پرداخت اعتباری: ' . ($msg !== '' ? $msg : ('خطای ' . ($res['status'] ?? '?'))),
            'raw' => $raw];
}

function paymentCreateSim(array $order, $toman) {
    try { $rnd = bin2hex(random_bytes(5)); } catch (Throwable $e) { $rnd = substr(md5(uniqid('', true)), 0, 10); }
    $auth = 'SIM' . (int)$order['id'] . '-' . $rnd;
    $sig  = paymentSimSign(['start', $auth, (int)$order['id'], (int)$toman]);
    $url  = rtrim(SITE_URL, '/') . '/payment-gateway-sim.php?a=' . urlencode($auth)
          . '&order=' . (int)$order['id'] . '&amount=' . (int)$toman . '&sig=' . $sig;
    return ['ok' => true, 'url' => $url, 'authority' => $auth, 'raw' => 'simulator'];
}

function paymentZarinpalBase() {
    return paymentTestMode() ? 'https://sandbox.zarinpal.com/pg/' : 'https://payment.zarinpal.com/pg/';
}

function paymentCreateZarinpal(array $order, $amount, $cb, $desc, $mobile) {
    $merchant = trim((string)getSettingRaw('pay_zarinpal_merchant', ''));
    if ($merchant === '') return ['ok' => false, 'error' => 'شناسهٔ پذیرندهٔ زرین‌پال تنظیم نشده است.'];

    $body = ['merchant_id' => $merchant, 'amount' => (int)$amount, 'callback_url' => $cb, 'description' => $desc];
    if ($mobile !== '') $body['metadata'] = ['mobile' => $mobile];

    $res = httpPostJson(paymentZarinpalBase() . 'v4/payment/request.json',
        json_encode($body, JSON_UNESCAPED_UNICODE),
        ['Content-Type: application/json', 'Accept: application/json']);

    $raw = $res['body'] ?? ($res['error'] ?? '');
    $j = json_decode((string)$raw, true);
    $code = isset($j['data']['code']) ? (int)$j['data']['code'] : 0;
    $auth = $j['data']['authority'] ?? '';

    if ($code === 100 && $auth !== '') {
        return ['ok' => true, 'url' => paymentZarinpalBase() . 'StartPay/' . $auth, 'authority' => $auth, 'raw' => $raw];
    }
    $msg = $j['errors']['message'] ?? ($j['errors'][0]['message'] ?? '');
    return ['ok' => false, 'error' => 'زرین‌پال: ' . ($msg !== '' ? $msg : ('خطای ' . ($code ?: ($res['status'] ?? '?')))), 'raw' => $raw];
}

function paymentCreateZibal(array $order, $amount, $cb, $desc, $mobile) {
    $merchant = trim((string)getSettingRaw('pay_zibal_merchant', ''));
    if ($merchant === '' && paymentTestMode()) $merchant = 'zibal'; // پذیرندهٔ نمونهٔ زیبال
    if ($merchant === '') return ['ok' => false, 'error' => 'شناسهٔ پذیرندهٔ زیبال تنظیم نشده است.'];

    $body = ['merchant' => $merchant, 'amount' => (int)$amount, 'callbackUrl' => $cb,
             'description' => $desc, 'orderId' => (string)(int)$order['id']];
    if ($mobile !== '') $body['mobile'] = $mobile;

    $res = httpPostJson('https://gateway.zibal.ir/v1/request',
        json_encode($body, JSON_UNESCAPED_UNICODE),
        ['Content-Type: application/json']);

    $raw = $res['body'] ?? ($res['error'] ?? '');
    $j = json_decode((string)$raw, true);
    $result  = isset($j['result']) ? (int)$j['result'] : 0;
    $trackId = isset($j['trackId']) ? (string)$j['trackId'] : '';

    if ($result === 100 && $trackId !== '') {
        return ['ok' => true, 'url' => 'https://gateway.zibal.ir/start/' . $trackId, 'authority' => $trackId, 'raw' => $raw];
    }
    $msg = $j['message'] ?? '';
    return ['ok' => false, 'error' => 'زیبال: ' . ($msg !== '' ? $msg : ('خطای ' . ($result ?: ($res['status'] ?? '?')))), 'raw' => $raw];
}

function paymentIdpayHeaders() {
    return [
        'Content-Type: application/json',
        'X-API-KEY: ' . trim((string)getSettingRaw('pay_idpay_key', '')),
        'X-SANDBOX: ' . (paymentTestMode() ? '1' : '0'),
    ];
}

function paymentCreateIdpay(array $order, $amount, $cb, $desc, $mobile) {
    $key = trim((string)getSettingRaw('pay_idpay_key', ''));
    if ($key === '') return ['ok' => false, 'error' => 'کلید API آیدی‌پی تنظیم نشده است.'];

    $body = [
        'order_id' => (string)(int)$order['id'],
        'amount'   => (int)$amount,
        'name'     => (string)($order['customer_name'] ?? ''),
        'desc'     => $desc,
        'callback' => $cb,
    ];
    if ($mobile !== '') $body['phone'] = $mobile;

    $res = httpPostJson('https://api.idpay.ir/v1.1/payment',
        json_encode($body, JSON_UNESCAPED_UNICODE), paymentIdpayHeaders());

    $raw = $res['body'] ?? ($res['error'] ?? '');
    $j = json_decode((string)$raw, true);
    $id   = $j['id']   ?? '';
    $link = $j['link'] ?? '';

    if ($id !== '' && $link !== '') {
        return ['ok' => true, 'url' => $link, 'authority' => $id, 'raw' => $raw];
    }
    $msg = $j['error_message'] ?? '';
    return ['ok' => false, 'error' => 'آیدی‌پی: ' . ($msg !== '' ? $msg : ('خطای ' . ($res['status'] ?? '?'))), 'raw' => $raw];
}

/* =====================================================================
   تأیید پرداخت — بازگشت:
   ['ok'=>bool,'ref'=>string,'card'=>string,'amount'=>int(تومان),'canceled'=>bool,'error'=>string,'raw'=>string]
   $req = آرایهٔ پارامترهای بازگشتی (GET + POST)
   ===================================================================== */
function paymentVerify($gw, array $order, array $req) {
    switch ($gw) {
        case 'sim':      return paymentVerifySim($order, $req);
        case 'zarinpal': return paymentVerifyZarinpal($order, $req);
        case 'zibal':    return paymentVerifyZibal($order, $req);
        case 'idpay':    return paymentVerifyIdpay($order, $req);
        case 'credit':   return paymentVerifyCredit($order, $req);
    }
    return ['ok' => false, 'error' => 'درگاه ناشناس.'];
}

/* تأیید پرداخت اعتباری. اگر «آدرس تأیید» تنظیم نشده باشد هیچ‌چیز خودکار
   پرداخت‌شده نمی‌شود؛ سفارش «در انتظار» می‌ماند تا مدیر دستی بررسی کند.
   (قاعدهٔ ثابتِ این لایه: بدونِ تأییدِ سمت سرور، هیچ سفارشی paid نمی‌شود.) */
function paymentVerifyCredit(array $order, array $req) {
    $url = trim((string)getSettingRaw('pay_credit_verify_url', ''));
    $auth = trim((string)($order['payment_authority'] ?? ''));
    if ($auth === '') {
        $auth = (string)(paymentCreditPick($req, ['token', 'trackId', 'authority', 'paymentToken', 'id']) ?: '');
    }

    /* لغو از سمت مشتری: ارائه‌دهنده‌ها معمولاً وضعیت را در پارامتر بازگشت می‌آورند */
    $st = strtolower((string)paymentCreditPick($req, ['status', 'state', 'result']));
    if ($st !== '' && in_array($st, ['cancel', 'canceled', 'cancelled', 'nok', 'failed', '0', '-1'], true)) {
        return ['ok' => false, 'canceled' => true, 'error' => 'پرداخت اعتباری لغو شد.'];
    }

    if ($url === '') {
        return ['ok' => false,
                'error' => 'آدرس تأییدِ پرداخت اعتباری تنظیم نشده است؛ این پرداخت باید دستی بررسی شود.'];
    }

    $res = httpPostJson($url, json_encode([
        'merchant' => trim((string)getSettingRaw('pay_credit_merchant', '')),
        'token'    => $auth,
        'orderId'  => (string)(int)$order['id'],
        'amount'   => (int)paymentAmountFor('credit', (int)$order['total_amount']),
    ], JSON_UNESCAPED_UNICODE), paymentCreditHeaders());

    $raw = $res['body'] ?? ($res['error'] ?? '');
    $j   = json_decode((string)$raw, true);

    $ref  = paymentCreditPick($j, ['refNumber', 'refId', 'referenceId', 'transactionId', 'trackId', 'token']);
    $card = paymentCreditPick($j, ['cardNumber', 'maskedCard', 'card_no', 'pan']);
    $amt  = (int)paymentCreditPick($j, ['amount', 'paidAmount']);
    $okSt = strtolower((string)paymentCreditPick($j, ['status', 'state', 'result']));
    $paid = !empty($res['ok']) && ($okSt === '' || in_array($okSt, ['1', '100', 'ok', 'success', 'paid', 'verified', 'true'], true));

    if ($paid && $ref !== '') {
        return ['ok' => true, 'ref' => $ref, 'card' => $card,
                'amount' => paymentTomanFrom('credit', $amt ?: paymentAmountFor('credit', (int)$order['total_amount'])),
                'raw' => $raw];
    }
    $msg = paymentCreditPick($j, ['message', 'error_message', 'errorMessage', 'error']);
    return ['ok' => false,
            'error' => 'پرداخت اعتباری: ' . ($msg !== '' ? $msg : ('تأیید نشد — خطای ' . ($res['status'] ?? '?'))),
            'raw' => $raw];
}

/* تبدیل مبلغ برگشتی درگاه به تومان */
function paymentTomanFrom($gw, $amount) {
    $d = paymentGatewayDef($gw);
    $unit = $d['unit'] ?? 'toman';
    if ($unit === 'toman' || getSettingRaw('pay_unit', 'rial') === 'toman') return (int)$amount;
    return (int)round((int)$amount / 10);
}

function paymentVerifySim(array $order, array $req) {
    $auth   = (string)($req['a'] ?? $req['Authority'] ?? '');
    $status = strtoupper((string)($req['Status'] ?? ''));
    $sig    = (string)($req['sig'] ?? '');
    $expect = paymentSimSign(['done', $auth, (int)$order['id'], $status]);
    if (!hash_equals($expect, $sig)) {
        return ['ok' => false, 'error' => 'امضای بازگشت از درگاه آزمایشی معتبر نیست.', 'raw' => 'bad-signature'];
    }
    if ($status !== 'OK') {
        return ['ok' => false, 'canceled' => true, 'error' => 'پرداخت آزمایشی توسط کاربر لغو شد.', 'raw' => 'canceled'];
    }
    return ['ok' => true, 'ref' => 'SIM' . str_pad((string)(int)$order['id'], 6, '0', STR_PAD_LEFT) . substr(sha1($auth), 0, 6),
            'card' => '6037-****-****-0000', 'amount' => (int)$order['total_amount'], 'raw' => 'simulated-ok'];
}

function paymentVerifyZarinpal(array $order, array $req) {
    $merchant = trim((string)getSettingRaw('pay_zarinpal_merchant', ''));
    $auth     = (string)($req['Authority'] ?? '');
    $status   = strtoupper((string)($req['Status'] ?? ''));
    if ($status !== 'OK') return ['ok' => false, 'canceled' => true, 'error' => 'پرداخت توسط کاربر لغو شد یا ناموفق بود.'];

    $amount = paymentAmountFor('zarinpal', (int)$order['total_amount']);
    $res = httpPostJson(paymentZarinpalBase() . 'v4/payment/verify.json',
        json_encode(['merchant_id' => $merchant, 'amount' => (int)$amount, 'authority' => $auth], JSON_UNESCAPED_UNICODE),
        ['Content-Type: application/json', 'Accept: application/json']);

    $raw = $res['body'] ?? ($res['error'] ?? '');
    $j = json_decode((string)$raw, true);
    $code = isset($j['data']['code']) ? (int)$j['data']['code'] : 0;

    /* ۱۰۰ = تأیید موفق، ۱۰۱ = قبلاً تأیید شده (هر دو یعنی پول دریافت شده) */
    if ($code === 100 || $code === 101) {
        return ['ok' => true,
                'ref'    => (string)($j['data']['ref_id'] ?? ''),
                'card'   => (string)($j['data']['card_pan'] ?? ''),
                'amount' => paymentTomanFrom('zarinpal', $amount),
                'raw'    => $raw];
    }
    $msg = $j['errors']['message'] ?? '';
    return ['ok' => false, 'error' => 'زرین‌پال: ' . ($msg !== '' ? $msg : ('کد ' . $code)), 'raw' => $raw];
}

function paymentVerifyZibal(array $order, array $req) {
    $merchant = trim((string)getSettingRaw('pay_zibal_merchant', ''));
    if ($merchant === '' && paymentTestMode()) $merchant = 'zibal';
    $trackId = (string)($req['trackId'] ?? '');
    $success = (string)($req['success'] ?? '');
    if ($success === '0') return ['ok' => false, 'canceled' => true, 'error' => 'پرداخت توسط کاربر لغو شد یا ناموفق بود.'];

    $res = httpPostJson('https://gateway.zibal.ir/v1/verify',
        json_encode(['merchant' => $merchant, 'trackId' => $trackId], JSON_UNESCAPED_UNICODE),
        ['Content-Type: application/json']);

    $raw = $res['body'] ?? ($res['error'] ?? '');
    $j = json_decode((string)$raw, true);
    $result = isset($j['result']) ? (int)$j['result'] : 0;

    /* ۱۰۰ = تأیید موفق، ۲۰۱ = قبلاً تأیید شده */
    if ($result === 100 || $result === 201) {
        return ['ok' => true,
                'ref'    => (string)($j['refNumber'] ?? $trackId),
                'card'   => (string)($j['cardNumber'] ?? ''),
                'amount' => paymentTomanFrom('zibal', (int)($j['amount'] ?? paymentAmountFor('zibal', (int)$order['total_amount']))),
                'raw'    => $raw];
    }
    $msg = $j['message'] ?? '';
    return ['ok' => false, 'error' => 'زیبال: ' . ($msg !== '' ? $msg : ('کد ' . $result)), 'raw' => $raw];
}

function paymentVerifyIdpay(array $order, array $req) {
    $id      = (string)($req['id'] ?? '');
    $orderId = (string)($req['order_id'] ?? (int)$order['id']);
    $st      = isset($req['status']) ? (int)$req['status'] : 0;
    /* وضعیت‌های کمتر از ۱۰۰ در آیدی‌پی یعنی ناموفق/لغو */
    if ($st > 0 && $st < 100) {
        return ['ok' => false, 'canceled' => true, 'error' => 'آیدی‌پی: پرداخت انجام نشد (کد ' . $st . ').'];
    }

    $res = httpPostJson('https://api.idpay.ir/v1.1/payment/verify',
        json_encode(['id' => $id, 'order_id' => $orderId], JSON_UNESCAPED_UNICODE), paymentIdpayHeaders());

    $raw = $res['body'] ?? ($res['error'] ?? '');
    $j = json_decode((string)$raw, true);
    $status = isset($j['status']) ? (int)$j['status'] : 0;

    /* ۱۰۰ = تأییدشده، ۱۰۱ = قبلاً تأیید شده، ۲۰۰ = به حساب پذیرنده واریز شده */
    if ($status === 100 || $status === 101 || $status === 200) {
        $amount = (int)($j['amount'] ?? ($j['payment']['amount'] ?? paymentAmountFor('idpay', (int)$order['total_amount'])));
        return ['ok' => true,
                'ref'    => (string)($j['track_id'] ?? ($j['payment']['track_id'] ?? $id)),
                'card'   => (string)($j['payment']['card_no'] ?? ''),
                'amount' => paymentTomanFrom('idpay', $amount),
                'raw'    => $raw];
    }
    $msg = $j['error_message'] ?? '';
    return ['ok' => false, 'error' => 'آیدی‌پی: ' . ($msg !== '' ? $msg : ('کد ' . $status)), 'raw' => $raw];
}
