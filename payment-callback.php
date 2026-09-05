<?php
/* بازگشت از درگاه پرداخت.
   ---------------------------------------------------------------
   نکات امنیتی:
   • این صفحه هرگز پارامترهای بازگشتی مرورگر را ملاک «پرداخت‌شده» نمی‌گیرد؛
     همیشه یک درخواست تأیید سرور-به-سرور به درگاه زده می‌شود (payment.php).
   • ورود کاربر الزامی نیست، چون بعضی درگاه‌ها با POST سرور-به-سرور برمی‌گردند
     و ممکن است کوکی نشست همراه نباشد. شناسایی سفارش با «توکن درگاه» انجام می‌شود.
   • اگر سفارش قبلا پرداخت‌شده باشد، دوباره تأیید نمی‌شود (idempotent).
   header.php لود نمی‌شود تا POST بازگشتی درگاه با پردازش سبد خرید تلاقی نکند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$req = array_merge($_GET, $_POST);

$gw = (string)($req['gw'] ?? '');
if (!paymentIsOnline($gw)) $gw = '';

/* --- پیدا کردن سفارش --- */
/* نام پارامتر «توکن» در هر درگاه متفاوت است */
$authKeys = ['zarinpal' => 'Authority', 'zibal' => 'trackId', 'idpay' => 'id', 'sim' => 'a'];
$authority = '';
if ($gw !== '' && isset($authKeys[$gw]) && isset($req[$authKeys[$gw]])) {
    $authority = trim((string)$req[$authKeys[$gw]]);
}
$orderIdHint = (int)($req['order'] ?? $req['order_id'] ?? $req['orderId'] ?? 0);

$state   = 'fail';
$message = '';
$order   = null;

if (!paymentReady()) {
    $message = 'زیرساخت پرداخت روی این سایت راه‌اندازی نشده است.';
} elseif ($gw === '') {
    $message = 'درگاه بازگشتی مشخص نیست.';
} else {
    if ($authority !== '') $order = paymentOrderByAuthority($gw, $authority);
    if (!$order && $orderIdHint > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderIdHint]);
            $order = $stmt->fetch() ?: null;
        } catch (Throwable $e) { $order = null; }
    }

    if (!$order) {
        $message = 'سفارش مربوط به این پرداخت پیدا نشد.';
        paymentLog("CALLBACK orphan | gw=$gw | authority=$authority | order=$orderIdHint");
    } elseif (($order['payment_status'] ?? '') === 'paid') {
        /* قبلا تأیید شده — دوباره تأیید نمی‌کنیم */
        $state   = 'ok';
        $message = 'این سفارش قبلا پرداخت شده است.';
    } else {
        $orderId = (int)$order['id'];

        /* آخرین تلاش ثبت‌شدهٔ همین درگاه را به‌روزرسانی می‌کنیم */
        $attempt = 0;
        foreach (paymentAttempts($orderId) as $a) {
            if ($a['gateway'] === $gw) { $attempt = (int)$a['id']; break; }
        }
        if (!$attempt) $attempt = paymentAttemptCreate($orderId, $gw, paymentAmountFor($gw, (int)$order['total_amount']));

        $res = paymentVerify($gw, $order, $req);

        if (!empty($res['ok'])) {
            $paid  = (int)($res['amount'] ?? $order['total_amount']);
            $note  = '';
            if ($paid !== (int)$order['total_amount']) {
                /* مبلغ برگشتی با مبلغ سفارش نمی‌خواند — پرداخت ثبت می‌شود ولی برای بررسی علامت می‌خورد */
                $note = 'اختلاف مبلغ: پرداخت‌شده ' . number_format($paid) . ' / سفارش ' . number_format((int)$order['total_amount']);
                paymentLog("CALLBACK amount-mismatch | order=$orderId | gw=$gw | $note");
            }
            paymentMarkPaid($orderId, $paid, $res['ref'] ?? '', $res['card'] ?? '');
            if ($note !== '') {
                try { $pdo->prepare("UPDATE orders SET payment_note=? WHERE id=?")->execute([$note, $orderId]); } catch (Throwable $e) {}
            }
            paymentAttemptUpdate($attempt, [
                'status'   => 'paid',
                'ref_id'   => $res['ref'] ?? '',
                'card_pan' => $res['card'] ?? '',
                'message'  => $note,
                'raw'      => $res['raw'] ?? '',
            ]);
            paymentLog("CALLBACK paid | order=$orderId | gw=$gw | ref=" . ($res['ref'] ?? '') . " | amount=$paid");
            $state   = 'ok';
            $message = 'پرداخت شما با موفقیت انجام شد.';
            $order   = array_merge($order, [
                'payment_status' => 'paid',
                'payment_ref'    => $res['ref'] ?? '',
                'paid_amount'    => $paid,
            ]);
        } else {
            $err = $res['error'] ?? 'پرداخت تأیید نشد.';
            paymentMarkFailed($orderId, $err);
            paymentAttemptUpdate($attempt, [
                'status'  => !empty($res['canceled']) ? 'canceled' : 'failed',
                'message' => $err,
                'raw'     => $res['raw'] ?? '',
            ]);
            paymentLog("CALLBACK failed | order=$orderId | gw=$gw | $err");
            $state   = !empty($res['canceled']) ? 'cancel' : 'fail';
            $message = $err;
        }
    }
}

/* اگر مشتری وارد شده و سفارش خودش است، صفحهٔ نتیجهٔ استاندارد سفارش را نشان می‌دهیم */
if ($order && isCustomerLoggedIn()) {
    $cu = currentCustomer();
    if ($cu && (int)($order['customer_id'] ?? 0) === (int)$cu['id']) {
        redirect('order-success.php?id=' . (int)$order['id'] . '&pay=' . $state);
    }
}

$titles = ['ok' => 'پرداخت موفق', 'cancel' => 'پرداخت لغو شد', 'fail' => 'پرداخت ناموفق'];
$icons  = ['ok' => 'check-circle', 'cancel' => 'x-circle', 'fail' => 'alert'];
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($titles[$state] ?? 'نتیجهٔ پرداخت') ?> — <?= h(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=66">
<style>
@font-face{font-family:Peyda;src:url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff2') format('woff2'),url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff') format('woff')}
<?= iconBaseCss() ?>
.pay-page{max-width:520px;margin:3rem auto;padding:0 1rem;text-align:center}
.pay-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;padding:2rem 1.5rem}
.pay-card h1{font-size:1.15rem;margin:0.75rem 0 0.5rem;color:var(--text-primary)}
.pay-card p{color:var(--text-secondary);font-size:0.9rem;line-height:1.9;margin-bottom:1rem}
.pay-card.ok .ic{color:#4ADE80}
.pay-card.fail .ic,.pay-card.cancel .ic{color:var(--red-light)}
.pay-rows{text-align:right;border-top:1px solid var(--border-color);margin-top:1rem;padding-top:1rem;font-size:0.85rem}
.pay-rows div{display:flex;justify-content:space-between;gap:1rem;padding:0.3rem 0}
.pay-rows span{color:var(--text-muted)}
.pay-card .btn{margin:0.25rem}
</style>
</head>
<body>
<div class="pay-page">
  <div class="pay-card <?= h($state) ?>">
    <?= icon($icons[$state] ?? 'alert', 'ic-xxl') ?>
    <h1><?= h($titles[$state] ?? 'نتیجهٔ پرداخت') ?></h1>
    <p><?= h($message) ?></p>
    <?php if ($order): ?>
    <div class="pay-rows">
      <div><span>شمارهٔ سفارش</span><b>#<?= (int)$order['id'] ?></b></div>
      <div><span>مبلغ سفارش</span><b><?= formatPrice($order['total_amount']) ?></b></div>
      <?php if (!empty($order['payment_ref'])): ?>
      <div><span>شمارهٔ پیگیری</span><b dir="ltr"><?= h($order['payment_ref']) ?></b></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:1rem;">
      <?php if ($state !== 'ok' && $order): ?>
      <a href="payment-start.php?order=<?= (int)$order['id'] ?>" class="btn btn-primary btn-sm"><?= icon('refresh', 'ic-sm') ?> پرداخت مجدد</a>
      <?php endif; ?>
      <a href="account.php" class="btn btn-secondary btn-sm">سفارش‌های من</a>
      <a href="shop.php" class="btn btn-secondary btn-sm">فروشگاه</a>
    </div>
  </div>
</div>
</body>
</html>
