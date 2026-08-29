<?php
/* ---------- سفارش‌های گذشتهٔ مشتری ----------
   سفارش‌های ارسال‌شده و لغوشده از فهرستِ «سفارش‌های در جریان» در account.php جدا
   شده‌اند و اینجا نشان داده می‌شوند تا آن صفحه شلوغ نشود. کارتِ هر سفارش با همان
   renderCustomerOrderCard() ساخته می‌شود، پس نمایش دو صفحه هرگز واگرا نمی‌شود. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireCustomerLogin('orders-past.php');

$c = currentCustomer();

$orders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([(int)$c['id']]);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {}

list($openOrders, $pastOrders) = splitCustomerOrders($orders);

/* جمع‌بندیِ همین صفحه: سفارش‌های لغوشده در مبلغ حساب نمی‌شوند */
$pastCount = 0; $pastSum = 0;
foreach ($pastOrders as $o) {
    if (($o['status'] ?? '') === 'cancelled') continue;
    $pastCount++;
    $pastSum += (float)$o['total_amount'];
}

$rNext       = (($_GET['r'] ?? '') === '1') ? '2' : '1';
$rDone       = isset($_GET['r']);
$refreshedAt = jDate(date('Y-m-d H:i:s'), true);

$statsHtml = 'سفارش‌های تحویل‌شده: <b>' . $pastCount . '</b> &nbsp;|&nbsp; مجموع: <b>'
           . formatPriceUnit($pastSum) . '</b> &nbsp;|&nbsp; آخرین بروزرسانی: <b>'
           . h($refreshedAt) . '</b>';

$emptyHtml = '<p style="color:var(--text-muted);">هنوز سفارشِ تحویل‌شده یا لغوشده‌ای ندارید.</p>'
           . '<a href="account.php#orders" class="btn btn-secondary btn-sm" style="margin-top:0.5rem;">'
           . icon('arrow-right', 'ic-sm') . ' بازگشت به سفارش‌های در جریان</a>';

/* پاسخِ بروزرسانیِ درجا — مثل account.php، فقط قطعهٔ HTML با کامنتِ نشانه */
if (($_GET['frag'] ?? '') === 'orders') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!--ordersfrag-->' . renderCustomerOrdersLive($pastOrders, $statsHtml, $emptyHtml);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="account-head">
    <h1 class="page-title">سفارش‌های گذشته</h1>
    <a href="account.php#orders" class="btn btn-secondary btn-sm"><?= icon('arrow-right', 'ic-sm') ?> حساب کاربری</a>
  </div>

  <div class="account-box" id="orders">
    <h3 class="account-box-title" style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
      <span>سفارش‌های ارسال‌شده و لغوشده (<?= count($pastOrders) ?>)</span>
      <span style="display:flex;align-items:center;gap:0.35rem;flex-wrap:wrap;">
        <a href="orders-past.php?r=<?= h($rNext) ?>#orders" id="ord-refresh" class="btn btn-secondary btn-sm"
           title="گرفتن تازه‌ترین وضعیت سفارش‌ها از فروشگاه"><?= icon('refresh', 'ic-sm') ?> بروزرسانی</a>
        <a href="account.php#orders" class="btn btn-secondary btn-sm"
           title="سفارش‌هایی که در حال آماده‌سازی یا ارسال هستند"><?= icon('truck', 'ic-sm') ?> سفارش‌های در جریان (<?= count($openOrders) ?>)</a>
      </span>
    </h3>
    <div class="flash flash-success" id="ord-flash" style="margin-bottom:0.75rem;<?= $rDone ? '' : 'display:none;' ?>"><?= icon('check-circle', 'ic-sm') ?> وضعیت سفارش‌ها بروزرسانی شد.</div>
    <div id="orders-live"><?= renderCustomerOrdersLive($pastOrders, $statsHtml, $emptyHtml) ?></div>
  </div>
</div>

<script>
/* همان بروزرسانیِ درجای account.php (بدون پریدن صفحه) برای این فهرست */
(function () {
    var btn = document.getElementById('ord-refresh');
    var box = document.getElementById('orders-live');
    var fl  = document.getElementById('ord-flash');
    if (!btn || !box || !window.fetch) return;
    var busy = false, timer = null;
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (busy) return;
        busy = true;
        var old = btn.innerHTML;
        btn.innerHTML = 'در حال بروزرسانی…';
        btn.style.opacity = '0.65';
        var done = function () { busy = false; btn.innerHTML = old; btn.style.opacity = ''; };
        fetch('orders-past.php?frag=orders', { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (t) {
                if (t.indexOf('<!--ordersfrag-->') !== 0) { window.location.href = btn.href; return; }
                box.innerHTML = t.slice(17);
                if (fl) {
                    fl.style.display = '';
                    if (timer) clearTimeout(timer);
                    timer = setTimeout(function () { fl.style.display = 'none'; }, 5000);
                }
                done();
            })
            .catch(function () { window.location.href = btn.href; done(); });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php';
