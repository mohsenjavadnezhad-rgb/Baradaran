<?php
/* شروع پرداخت آنلاین — سفارش را می‌گیرد، درگاه را صدا می‌زند و کاربر را منتقل می‌کند.
   header.php لود نمی‌شود تا هیچ POST سبد خرید دوباره پردازش نشود و انتقال بدون رندر صفحه انجام شود. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireCustomerLogin('cart.php');

$orderId = (int)($_GET['order'] ?? 0);
$c       = currentCustomer();
$fail    = '';
$order   = null;

if (!paymentReady()) {
    $fail = 'زیرساخت پرداخت آنلاین هنوز روی این سایت راه‌اندازی نشده است.';
} elseif (!$orderId) {
    $fail = 'شمارهٔ سفارش نامعتبر است.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
        $stmt->execute([$orderId, (int)$c['id']]);
        $order = $stmt->fetch() ?: null;
    } catch (Throwable $e) { $order = null; }

    if (!$order) {
        $fail = 'سفارش یافت نشد.';
    } elseif (($order['payment_status'] ?? '') === 'paid') {
        /* قبلا پرداخت شده — دوباره به درگاه نمی‌رویم */
        redirect('order-success.php?id=' . $orderId);
    } elseif ($order['status'] === 'cancelled') {
        $fail = 'این سفارش لغو شده و قابل پرداخت نیست.';
    } elseif (!orderPaymentUnlocked($order)) {
        /* گیت «بررسی موجودی» — حتی اگر مشتری این آدرس را مستقیم بزند (مثلا
           با دکمهٔ برگشت مرورگر)، تا مدیر موجودی را تأیید نکند به درگاه
           نمی‌رود. */
        $fail = 'سفارش شما هنوز از نظر موجودی بررسی نشده است. پس از تأیید موجودی توسط فروشگاه، امکان پرداخت برایتان فعال می‌شود.';
    } else {
        $gw = (string)($order['payment_method'] ?? '');
        /* اگر روش ثبت‌شده آنلاین نیست (مثلا پرداخت در محل)، درگاه فعال فعلی استفاده می‌شود.
           «پرداخت اعتباری» جدا سنجیده می‌شود: تنظیم‌شده‌بودنش کافی نیست و تیک
           pay_enable_credit هم باید روشن باشد، وگرنه سفارش به درگاه بانکی عادی می‌رود. */
        $gwOk = paymentIsOnline($gw) && paymentIsConfigured($gw)
                && ($gw !== 'credit' || paymentCreditEnabled());
        if (!$gwOk) $gw = paymentActiveGateway();

        if ($gw === '') {
            $fail = 'در حال حاضر هیچ درگاه پرداخت آنلاینی فعال نیست. لطفا با ما تماس بگیرید.';
        } else {
            $amount  = paymentAmountFor($gw, (int)$order['total_amount']);
            $attempt = paymentAttemptCreate($orderId, $gw, $amount);
            $res     = paymentCreate($gw, $order);

            if (!empty($res['ok'])) {
                paymentSetAuthority($orderId, $gw, $res['authority']);
                paymentAttemptUpdate($attempt, [
                    'authority' => $res['authority'],
                    'status'    => 'redirected',
                    'raw'       => $res['raw'] ?? '',
                ]);
                paymentLog("START ok | order=$orderId | gw=$gw | amount=$amount | authority=" . $res['authority']);
                header('Location: ' . $res['url']);
                exit;
            }

            $fail = $res['error'] ?? 'ارتباط با درگاه پرداخت برقرار نشد.';
            paymentAttemptUpdate($attempt, ['status' => 'failed', 'message' => $fail, 'raw' => $res['raw'] ?? '']);
            paymentMarkFailed($orderId, $fail);
            paymentLog("START fail | order=$orderId | gw=$gw | $fail");
        }
    }
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پرداخت — <?= h(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=61">
<style>
@font-face{font-family:Peyda;src:url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff2') format('woff2'),url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff') format('woff')}
<?= iconBaseCss() ?>
.pay-page{max-width:520px;margin:3rem auto;padding:0 1rem;text-align:center}
.pay-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;padding:2rem 1.5rem}
.pay-card .ic{color:var(--red-light)}
.pay-card h1{font-size:1.15rem;margin:0.75rem 0 0.5rem;color:var(--text-primary)}
.pay-card p{color:var(--text-secondary);font-size:0.9rem;line-height:1.9;margin-bottom:1.25rem}
.pay-card .btn{margin:0.25rem}
</style>
</head>
<body>
<div class="pay-page">
  <div class="pay-card">
    <?= icon('alert', 'ic-xxl') ?>
    <h1>پرداخت آغاز نشد</h1>
    <p><?= h($fail) ?></p>
    <?php if ($orderId && $order): ?>
    <a href="payment-start.php?order=<?= (int)$orderId ?>" class="btn btn-primary btn-sm"><?= icon('refresh', 'ic-sm') ?> تلاش دوباره</a>
    <a href="order-success.php?id=<?= (int)$orderId ?>" class="btn btn-secondary btn-sm">جزئیات سفارش</a>
    <?php else: ?>
    <a href="cart.php" class="btn btn-primary btn-sm">بازگشت به سبد خرید</a>
    <?php endif; ?>
    <a href="shop.php" class="btn btn-secondary btn-sm">فروشگاه</a>
  </div>
</div>
</body>
</html>
