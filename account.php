<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requireCustomerLogin('account.php');

$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full   = trim($_POST['full_name'] ?? '');
    $prov   = trim($_POST['province'] ?? '');
    /* نام رسمی شهر از فهرست شهرها گرفته می‌شود تا نرخ‌نامهٔ ارسال بتواند
       بعدا همین شهر را بشناسد؛ اگر شهر در فهرست نبود، عینا همان چیزی که
       مشتری نوشته ذخیره می‌شود. */
    $city   = shippingCityCanonical(trim($_POST['city'] ?? ''));
    $addr   = trim($_POST['address'] ?? '');
    $postal = trim(faToLatinDigits($_POST['postal_code'] ?? ''));
    $comp   = trim($_POST['partner_company'] ?? '');

    if ($full === '') $errors[] = 'نام و نام خانوادگی الزامی است.';

    if (empty($errors)) {
        $pdo->prepare("UPDATE customers SET full_name=?, province=?, city=?, address=?, postal_code=? WHERE id=?")
            ->execute([$full, $prov, $city, $addr, $postal, (int)$_SESSION['customer_id']]);
        if (isset($_POST['partner_company'])) {
            try {
                $pdo->prepare("UPDATE customers SET partner_company=? WHERE id=?")
                    ->execute([$comp, (int)$_SESSION['customer_id']]);
            } catch (Throwable $e) {}
        }
        currentCustomer(true); // بازخوانی کش
        $saved = true;
    }
}

$c = currentCustomer();
$isPartner = (($c['customer_type'] ?? 'retail') === 'partner');
$pStatus   = $c['partner_status'] ?? 'none';

$orders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([(int)$c['id']]);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {}

/* سفارش‌های «در جریان» روی همین صفحه می‌مانند و «گذشته» (ارسال‌شده/لغوشده) به
   صفحهٔ جداگانهٔ orders-past.php می‌روند تا این صفحه شلوغ نشود. */
list($openOrders, $pastOrders) = splitCustomerOrders($orders);

/* آمار خرید (سفارش‌های لغوشده شمرده نمی‌شوند) */
$buyCount = 0; $buySum = 0;
foreach ($orders as $o) {
    if (($o['status'] ?? '') === 'cancelled') continue;
    $buyCount++;
    $buySum += (float)$o['total_amount'];
}

/* ---- کلید «بروزرسانی» فهرست سفارش‌ها ----
   با جاوااسکریپت فقط همین ناحیه (#orders-live) دوباره از سرور گرفته و جای‌گذاری
   می‌شود، پس صفحه دیگر به بالا نمی‌پرد. بدون جاوااسکریپت همان لینک عادی باز
   می‌شود؛ مقدار r بین ۱ و ۲ عوض می‌شود تا مرورگر کلیک را «همین صفحه + لنگر»
   نبیند و درخواست تازه بفرستد. GET است، پس فرم مشخصات دوباره POST نمی‌شود
   (الگوی PRG). */
$rNext       = (($_GET['r'] ?? '') === '1') ? '2' : '1';
$rDone       = isset($_GET['r']);
$refreshedAt = jDate(date('Y-m-d H:i:s'), true);

$statsHtml = 'تعداد خرید: <b>' . $buyCount . '</b> سفارش &nbsp;|&nbsp; مجموع خرید: <b>'
           . formatPriceUnit($buySum) . '</b> &nbsp;|&nbsp; آخرین بروزرسانی: <b>'
           . h($refreshedAt) . '</b>';

$emptyHtml = $pastOrders
    ? '<p style="color:var(--text-muted);">سفارش در جریانی ندارید؛ سفارش‌های پیشین شما در صفحهٔ «سفارش‌های گذشته» است.</p>'
      . '<a href="orders-past.php" class="btn btn-secondary btn-sm" style="margin-top:0.5rem;">'
      . icon('clock', 'ic-sm') . ' سفارش‌های گذشته</a>'
    : '<p style="color:var(--text-muted);">هنوز سفارشی ثبت نکرده‌اید.</p>'
      . '<a href="shop.php" class="btn btn-primary btn-sm" style="margin-top:0.5rem;">شروع خرید</a>';

/* پاسخ بروزرسانی درجا: فقط قطعهٔ HTML فهرست، بدون هدر و فوتر.
   کامنت نشانه در ابتدای خروجی است تا اگر نشست منقضی شده و پاسخ صفحهٔ ورود بود،
   جاوااسکریپت تشخیص بدهد و به‌جای تزریق صفحهٔ ورود، صفحه را عادی باز کند. */
if (($_GET['frag'] ?? '') === 'orders') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!--ordersfrag-->' . renderCustomerOrdersLive($openOrders, $statsHtml, $emptyHtml);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="account-head">
    <h1 class="page-title">
      حساب کاربری
      <?php if ($isPartner): ?>
        <?php if ($pStatus === 'approved'): ?>
        <span class="badge-partner" title="حساب همکار تأییدشده"><?= icon('check', 'ic-sm') ?> همکار تأییدشده</span>
        <?php else: ?>
        <span class="badge-pending" title="در انتظار بررسی پشتیبانی">همکار — در انتظار تأیید</span>
        <?php endif; ?>
      <?php else: ?>
        <span class="badge-retail">مشتری</span>
      <?php endif; ?>
    </h1>
    <a href="logout.php" class="btn btn-secondary btn-sm">خروج از حساب</a>
  </div>

  <?php if ($saved): ?><div class="flash flash-success">اطلاعات شما ذخیره شد.</div><?php endif; ?>
  <?php if ($errors): ?><div class="flash flash-error"><?php foreach ($errors as $e): ?><p><?= h($e) ?></p><?php endforeach; ?></div><?php endif; ?>
  <?php if (!customerProfileComplete($c)): ?><div class="flash auth-test">لطفا پروفایل خود را کامل کنید (نام، آدرس و کد پستی) تا هنگام خرید نیازی به وارد‌کردن دوباره نباشد.</div><?php endif; ?>
  <?php if ($isPartner && $pStatus !== 'approved'): ?>
  <div class="auth-note" style="margin-bottom:1rem;">
    درخواست حساب <b>همکار</b> شما ثبت شده و در انتظار بررسی است.
    توجه: <b>قیمت کلی</b> برای همهٔ مشتریان با رسیدن تعداد به حد کلی هر کالا خودکار اعمال می‌شود و به این تأیید وابسته نیست.
  </div>
  <?php endif; ?>

  <div class="account-grid">
    <div class="account-box">
      <h3 class="account-box-title">مشخصات و آدرس</h3>
      <form method="POST" class="auth-form">
        <div class="form-group">
          <label>شمارهٔ موبایل</label>
          <input type="text" class="form-control" value="<?= h($c['mobile']) ?>" dir="ltr" readonly style="opacity:0.7;">
        </div>
        <div class="form-group">
          <label>نام و نام خانوادگی *</label>
          <input type="text" name="full_name" class="form-control" value="<?= h($_POST['full_name'] ?? $c['full_name'] ?? '') ?>" required>
        </div>
        <?php if ($isPartner): ?>
        <div class="form-group">
          <label>نام فروشگاه / تعمیرگاه</label>
          <input type="text" name="partner_company" class="form-control" value="<?= h($_POST['partner_company'] ?? $c['partner_company'] ?? '') ?>" placeholder="مثلا یدکی‌فروشی برادران">
        </div>
        <?php endif; ?>
        <div class="form-row-2">
          <?php /* همان انتخابگر دوسطحی صفحهٔ تسویه: استان و شهر هر دو نوار
                   کشویی و به هم وابسته. شهر پروفایل مبنای محاسبهٔ خودکار هزینهٔ
                   ارسال در سبد خرید و تسویه است، پس انتخاب از فهرست جلوی
                   غلط‌های تایپی را می‌گیرد. */ ?>
          <?= shippingProvinceCityFields(
                  $_POST['province'] ?? $c['province'] ?? '',
                  $_POST['city'] ?? $c['city'] ?? '',
                  'شهرتان را از فهرست انتخاب کنید تا هزینهٔ ارسال خودکار حساب شود.'
              ) ?>
        </div>
        <div class="form-group">
          <label>آدرس کامل</label>
          <textarea name="address" class="form-control" rows="3"><?= h($_POST['address'] ?? $c['address'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>کد پستی</label>
          <input type="text" name="postal_code" class="form-control" value="<?= h($_POST['postal_code'] ?? $c['postal_code'] ?? '') ?>" dir="ltr" inputmode="numeric">
        </div>
        <button type="submit" class="btn btn-primary">ذخیرهٔ اطلاعات</button>
      </form>
    </div>

    <div class="account-box" id="orders">
      <h3 class="account-box-title" style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
        <span>سفارش‌های در جریان (<?= count($openOrders) ?>)</span>
        <span style="display:flex;align-items:center;gap:0.35rem;flex-wrap:wrap;">
          <?php /* بروزرسانی وضعیت: تازه‌ترین روند ارسالی که مدیر ثبت کرده را می‌آورد. */ ?>
          <a href="account.php?r=<?= h($rNext) ?>#orders" id="ord-refresh" class="btn btn-secondary btn-sm"
             title="گرفتن تازه‌ترین وضعیت سفارش‌ها از فروشگاه"><?= icon('refresh', 'ic-sm') ?> بروزرسانی</a>
          <a href="orders-past.php" class="btn btn-secondary btn-sm"
             title="سفارش‌های ارسال‌شده و لغوشده"><?= icon('clock', 'ic-sm') ?> سفارش‌های گذشته (<?= count($pastOrders) ?>)</a>
        </span>
      </h3>
      <div class="flash flash-success" id="ord-flash" style="margin-bottom:0.75rem;<?= $rDone ? '' : 'display:none;' ?>"><?= icon('check-circle', 'ic-sm') ?> وضعیت سفارش‌ها بروزرسانی شد.</div>
      <div id="orders-live"><?= renderCustomerOrdersLive($openOrders, $statsHtml, $emptyHtml) ?></div>
    </div>
  </div>
</div>

<script>
/* بروزرسانی درجای فهرست سفارش‌ها: فقط درون #orders-live از سرور گرفته و عوض
   می‌شود، پس نه صفحه بارگذاری می‌شود و نه به بالا می‌پرد. اگر مرورگر fetch
   نداشت یا پاسخ قطعهٔ موردنظر نبود (مثلا نشست منقضی شده)، همان لینک عادی
   باز می‌شود تا کلید هرگز بی‌کار نماند. */
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
        fetch('account.php?frag=orders', { credentials: 'same-origin' })
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
