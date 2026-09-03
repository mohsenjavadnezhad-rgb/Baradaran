<?php
/* درگاه پرداخت آزمایشی (شبیه‌ساز داخلی).
   ---------------------------------------------------------------
   هدف: تست کامل چرخهٔ پرداخت (تسویه ← درگاه ← بازگشت ← تأیید ← ثبت وضعیت)
   بدون داشتن هیچ اطلاعات درگاه واقعی. فقط وقتی «حالت آزمایشی» در تنظیمات
   ادمین روشن باشد کار می‌کند و هیچ پول واقعی جابه‌جا نمی‌کند.
   لینک ورود با امضای HMAC ساخته می‌شود تا کسی نتواند مستقیما
   payment-callback.php را با پارامترهای دلخواه صدا بزند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$auth   = (string)($_GET['a'] ?? '');
$order  = (int)($_GET['order'] ?? 0);
$amount = (int)($_GET['amount'] ?? 0);
$sig    = (string)($_GET['sig'] ?? '');

$err = '';
if (!paymentTestMode()) {
    $err = 'درگاه آزمایشی خاموش است. (حالت آزمایشی در پنل ادمین ← تنظیمات ← درگاه پرداخت)';
} elseif ($auth === '' || $order <= 0) {
    $err = 'پارامترهای ورود به درگاه آزمایشی ناقص است.';
} elseif (!hash_equals(paymentSimSign(['start', $auth, $order, $amount]), $sig)) {
    $err = 'امضای درخواست معتبر نیست.';
}

$okUrl = $nokUrl = '';
if ($err === '') {
    $base = 'payment-callback.php?gw=sim&a=' . urlencode($auth) . '&order=' . $order;
    $okUrl  = $base . '&Status=OK&sig='  . paymentSimSign(['done', $auth, $order, 'OK']);
    $nokUrl = $base . '&Status=NOK&sig=' . paymentSimSign(['done', $auth, $order, 'NOK']);
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>درگاه پرداخت آزمایشی</title>
<link rel="stylesheet" href="assets/css/style.css?v=59">
<style>
@font-face{font-family:Peyda;src:url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff2') format('woff2'),url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff') format('woff')}
<?= iconBaseCss() ?>
.sim-wrap{max-width:460px;margin:2.5rem auto;padding:0 1rem}
.sim-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;overflow:hidden}
.sim-hd{background:linear-gradient(135deg,rgba(37,99,235,0.18),rgba(37,99,235,0.05));border-bottom:1px solid var(--border-color);padding:1rem;display:flex;align-items:center;gap:0.6rem}
.sim-hd .ic{color:#60A5FA;width:1.7rem;height:1.7rem}
.sim-hd b{font-size:0.95rem;color:var(--text-primary);display:block}
.sim-hd small{color:var(--text-muted);font-size:0.72rem}
.sim-bd{padding:1.25rem}
.sim-warn{background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.35);color:#FBBF24;border-radius:8px;padding:0.6rem 0.8rem;font-size:0.78rem;line-height:1.8;margin-bottom:1rem}
.sim-rows{font-size:0.88rem;margin-bottom:1.25rem}
.sim-rows div{display:flex;justify-content:space-between;gap:1rem;padding:0.45rem 0;border-bottom:1px dashed var(--border-color)}
.sim-rows div:last-child{border-bottom:none}
.sim-rows span{color:var(--text-muted)}
.sim-rows b{color:var(--text-primary)}
.sim-rows b.amt{color:#4ADE80;font-size:1.05rem}
.sim-fake{display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-bottom:1.25rem}
.sim-fake input{width:100%;padding:0.5rem 0.6rem;background:var(--bg-primary);border:1px solid var(--border-color);border-radius:8px;color:var(--text-secondary);font-family:inherit;font-size:0.82rem;text-align:center}
.sim-fake input:first-child{grid-column:1/-1}
.sim-acts{display:flex;gap:0.6rem}
.sim-acts a{flex:1;text-align:center}
.sim-err{padding:2rem 1.5rem;text-align:center}
.sim-err .ic{color:var(--red-light)}
.sim-err p{color:var(--text-secondary);font-size:0.9rem;line-height:1.9;margin:0.75rem 0 1rem}
</style>
</head>
<body>
<div class="sim-wrap">
  <div class="sim-card">
    <div class="sim-hd">
      <?= icon('shield-check') ?>
      <div><b>درگاه پرداخت آزمایشی</b><small>شبیه‌ساز داخلی — بدون تراکنش بانکی واقعی</small></div>
    </div>

    <?php if ($err !== ''): ?>
    <div class="sim-err">
      <?= icon('alert', 'ic-xxl') ?>
      <p><?= h($err) ?></p>
      <a href="shop.php" class="btn btn-secondary btn-sm">بازگشت به فروشگاه</a>
    </div>
    <?php else: ?>
    <div class="sim-bd">
      <div class="sim-warn">
        <?= icon('info', 'ic-sm') ?>
        این صفحه <b>درگاه واقعی نیست</b>. برای آزمایش فرآیند پرداخت ساخته شده و هیچ مبلغی از حساب شما کم نمی‌شود.
        پس از اتصال درگاه واقعی، «حالت آزمایشی» را در پنل ادمین خاموش کنید تا این صفحه دیگر نمایش داده نشود.
      </div>
      <div class="sim-rows">
        <div><span>پذیرنده</span><b><?= h(SITE_NAME) ?></b></div>
        <div><span>شمارهٔ سفارش</span><b>#<?= $order ?></b></div>
        <div><span>شناسهٔ تراکنش</span><b dir="ltr" style="font-size:0.75rem;"><?= h($auth) ?></b></div>
        <div><span>مبلغ قابل پرداخت</span><b class="amt"><?= formatPrice($amount) ?></b></div>
      </div>
      <div class="sim-fake">
        <input type="text" value="6037-9911-0000-0000" dir="ltr" readonly>
        <input type="text" value="12/09" dir="ltr" readonly>
        <input type="text" value="CVV2: 123" dir="ltr" readonly>
      </div>
      <div class="sim-acts">
        <a href="<?= h($okUrl) ?>" class="btn btn-primary btn-sm"><?= icon('check', 'ic-sm') ?> پرداخت موفق</a>
        <a href="<?= h($nokUrl) ?>" class="btn btn-secondary btn-sm"><?= icon('x', 'ic-sm') ?> انصراف / ناموفق</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
