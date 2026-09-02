<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$order = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$order->execute([$id]);
$order = $order->fetch();

if (!$order) {
    echo '<div style="text-align:center;padding:3rem;color:var(--text-muted);">سفارش یافت نشد.</div>';
    exit;
}

$items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

$trackOn   = trackingReady();
$trackDefs = orderTrackSteps();
$trackPost = trackingPostReady();
$trackError = '';

/* ---- ثبت روند ارسال (PRG: self-post به همین صفحه) ----
   تیک‌خورده و خالی → مهر NOW()؛ تیک‌خورده و مهردار → دست‌نخورده (زمان اصلی حفظ می‌شود)؛
   بدون تیک → NULL. سپس هم‌گام‌سازی ملایم با ENUM وضعیت (بدون دست‌زدن به «لغو شده»).
   مرحله‌هایی که همین حالا از خالی به مهردار رفتند در $justDone جمع می‌شوند تا
   بعد از UPDATE برایشان پیامک برود (فقط یک‌بار — تیک قبلا زده‌شده دوباره پیامک نمی‌کند). */
if ($trackOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_save'])) {
    $checked = (array)($_POST['track'] ?? []);
    $sets = [];
    $params = [];
    $justDone = [];
    foreach ($trackDefs as $col => $def) {
        $on = isset($checked[$col]);
        if ($on) {
            if (empty($order[$col])) { $sets[] = "`$col` = NOW()"; $justDone[] = $col; }
        } else {
            $sets[] = "`$col` = NULL";
        }
    }
    /* نام و شمارهٔ پیک، دقیقا مثل کد رهگیری پست، به مرحلهٔ خودش گره خورده است:
       با برداشتن تیک «تحویل به پیک» پاک می‌شوند تا مشخصات پیک بی‌صاحب در
       روند سفارش مشتری نماند. */
    $hasCourier = isset($checked['track_courier_at']);
    $sets[] = "courier_name = ?";
    $params[] = $hasCourier ? trim($_POST['courier_name'] ?? '') : '';
    $sets[] = "courier_phone = ?";
    $params[] = $hasCourier ? trim(faToLatinDigits($_POST['courier_phone'] ?? '')) : '';

    /* کد رهگیری پست: فقط وقتی ستونش ساخته شده. با برداشتن تیک «تحویل به پست»
       کد هم پاک می‌شود تا کد بی‌صاحب در تایم‌لاین نماند. */
    $postCode = '';
    if ($trackPost) {
        $postCode = trim(faToLatinDigits((string)($_POST['post_tracking_code'] ?? '')));
        if (!isset($checked['track_post_at'])) $postCode = '';
        $sets[] = "post_tracking_code = ?";
        $params[] = $postCode;
    }

    /* هم‌گام‌سازی وضعیت: فقط رو به جلو */
    $cur = (string)$order['status'];
    if ($cur !== 'cancelled') {
        if (isset($checked['track_shipped_at']) && $cur !== 'shipped') {
            $sets[] = "status = 'shipped'";
        } elseif (isset($checked['track_confirmed_at']) && $cur === 'pending') {
            $sets[] = "status = 'confirmed'";
        }
    }

    try {
        $params[] = $id;
        $pdo->prepare("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

        /* پیامک مراحل تازه‌ثبت‌شده. با ردیف به‌روزشده فرستاده می‌شود تا
           {code} و {courier} مقدار همین ذخیره را داشته باشند. شکست پیامک
           هرگز ثبت مرحله را برنمی‌گرداند — فقط در storage/sms.log می‌ماند. */
        $smsSent = 0;
        if ($justDone && smsTrackEnabled()) {
            $fresh = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $fresh->execute([$id]);
            $fresh = $fresh->fetch();
            if ($fresh) {
                $vis = orderTrackStepsVisible($fresh);
                foreach ($justDone as $col) {
                    /* مرحله‌ای که برای این سفارش به مشتری نشان داده نمی‌شود،
                       پیامک هم ندارد؛ وگرنه از پیکی خبر می‌داد که در روند
                       سفارش خودش وجود ندارد. */
                    if (!isset($vis[$col])) continue;
                    $r = smsNotifyTrackStep($fresh, $col);
                    if (!empty($r['ok'])) $smsSent++;
                }
            }
        }

        header('Location: order-detail.php?id=' . $id . '&tsaved=1' . ($smsSent > 0 ? '&sms=' . $smsSent : ''));
        exit;
    } catch (Throwable $e) {
        $trackError = $e->getMessage();
    }
}

/* ---- کدام مرحله‌ها را مشتری همین سفارش می‌بیند؟ ----
   تصمیم از روی روش ارسال خود سفارش گرفته می‌شود (orderDeliveryMode()):
   سفارش پستی/باربری دو مرحلهٔ پیک را نمی‌بیند و سفارش پیک درون‌شهری مرحلهٔ
   «تحویل به پست» را. همهٔ مرحله‌ها سر جای خودشان در همین فهرست تیک می‌مانند —
   فیلتر فقط برای چشم مشتری است. */
$trackVisible  = $trackOn ? orderTrackStepsVisible($order) : [];
$trackMode     = $trackOn ? orderDeliveryMode($order) : '';
$trackCity     = $trackOn ? orderDestCity($order) : '';
$trackModeNote = '';
if ($trackMode === 'ship') {
    $sm = trim((string)($order['shipping_method'] ?? ''));
    $trackModeNote = 'این سفارش'
        . ($sm !== '' ? ' با «' . shippingLabel($sm) . '»' : '')
        . ($trackCity !== '' ? ' به «' . $trackCity . '»' : '')
        . ' ارسال می‌شود و پیک درون‌شهری ندارد، پس دو مرحلهٔ «در حال جستجوی پیک» و «تحویل به پیک»'
        . ' فقط برای شما دیده می‌شود و پیامکی هم برای آن‌ها نمی‌رود.';
} elseif ($trackMode === 'courier') {
    $trackModeNote = 'این سفارش با پیک درون‌شهری'
        . ($trackCity !== '' ? ' («' . $trackCity . '»)' : '')
        . ' تحویل می‌شود، پس مرحلهٔ «تحویل به پست» فقط برای شما دیده می‌شود.';
}

$payOn     = paymentReady();
$payMethod = $payOn ? (string)($order['payment_method'] ?? 'cod') : '';
$payStatus = $payOn ? (string)($order['payment_status'] ?? 'unpaid') : '';
$attempts  = $payOn ? paymentAttempts($id) : [];

/* ---- تأیید واریز کارت به کارت ----
   مشتری چهار مورد را ثبت کرده (شناسهٔ واریز، مبلغ، چهار رقم آخر کارت و زمان)
   و سفارش «در انتظار تأیید واریز» مانده است. هیچ پرداختی خودکار تأیید نمی‌شود؛
   فقط با همین دکمه پرداخت «پرداخت‌شده» و سفارش «تأیید شده» می‌شود. */
$c2cOn    = $payOn && paymentC2cReady();
$c2cWait  = $c2cOn && paymentC2cAwaiting($order);
$c2cError = '';

if ($c2cOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['c2c_verify'])) {
    if (paymentC2cVerify($id, $order)) {
        header('Location: order-detail.php?id=' . $id . '&c2csaved=1');
        exit;
    }
    $c2cError = 'تأیید واریز انجام نشد. اطلاعات واریز را بررسی کنید.';
}

/* ---- «دریافت چک» ----
   همکار بانک/شماره/تاریخ/مبلغ چک را ثبت کرده؛ این دکمه فقط زمان دریافت
   فیزیکی چک را ثبت می‌کند — با «پرداخت شد» (که خودش جدا و پایین‌تر است) یکی
   نیست، چون رسیدن چک به معنی وصول‌شدنش نیست. */
$chqOn   = $payOn && paymentChequeReady();
$chqWait = $chqOn && paymentChequeAwaiting($order);

if ($chqOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cheque_receive'])) {
    paymentChequeReceive($id);
    header('Location: order-detail.php?id=' . $id . '&chqsaved=1');
    exit;
}

/* ---- روش ارسال ----
   مدیر می‌تواند روش انتخابی مشتری و هزینهٔ آن را اصلاح کند (مثلا وقتی
   مشتری «پیک مشهد» را برای شهری دیگر انتخاب کرده، یا هزینهٔ «پس‌کرایه»
   بعدا مشخص شده). مبلغ کل سفارش = جمع کالاها + هزینهٔ ارسال، پس با تغییر
   هزینه، total_amount هم بازمحاسبه می‌شود.
   استثناء: سفارش پرداخت‌شده مبلغش عوض نمی‌شود (فقط روشش قابل اصلاح است). */
$shipOn     = shippingReady();
$shipError  = '';
$shipLocked = $payOn && $payStatus === 'paid';

if ($shipOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ship_save'])) {
    $newMethod = (string)($_POST['shipping_method'] ?? '');
    if ($newMethod !== '' && shippingMethodDef($newMethod) === null) $newMethod = '';

    $oldCost = (int)($order['shipping_cost'] ?? 0);
    $newCost = $oldCost;
    if (!$shipLocked) {
        $raw = preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['shipping_cost'] ?? '')));
        $newCost = $raw === '' ? 0 : (int)$raw;
    }
    /* جمع کالاها ثابت است؛ فقط سهم ارسال از مبلغ کل جابه‌جا می‌شود */
    $goods    = max(0, (int)$order['total_amount'] - $oldCost);
    $newTotal = $goods + $newCost;

    try {
        $pdo->prepare("UPDATE orders SET shipping_method = ?, shipping_cost = ?, total_amount = ? WHERE id = ?")
            ->execute([$newMethod, $newCost, $newTotal, $id]);
        header('Location: order-detail.php?id=' . $id . '&ssaved=1');
        exit;
    } catch (Throwable $e) {
        $shipError = $e->getMessage();
    }
}

$shipMethod = $shipOn ? (string)($order['shipping_method'] ?? '') : '';
$shipCost   = $shipOn ? (int)($order['shipping_cost'] ?? 0) : 0;
$orderTax   = (int)($order['tax_total'] ?? 0);
$goodsTotal = max(0, (int)$order['total_amount'] - $shipCost - $orderTax);

$statusLabels = [
    'pending' => 'در انتظار',
    'confirmed' => 'تأیید شده',
    'shipped' => 'ارسال شده',
    'cancelled' => 'لغو شده',
];

/* «بررسی عکس نمونهٔ قطعه» این سفارش (اگر مشتری پیش از خرید عکس فرستاده باشد).
   خواستهٔ مدیر: «سمت ادمین / هم اینو به جزئیات سفارش اضافه کن». */
$pchk     = partCheckForOrder((int)$order['id']);
$pchkImgs = $pchk ? partCheckImages((int)$pchk['id']) : [];
$pchkProd = null;
if ($pchk && !empty($pchk['product_id'])) {
    try {
        $stp = $pdo->prepare("SELECT id, name, technical_number FROM products WHERE id = ?");
        $stp->execute([(int)$pchk['product_id']]);
        $pchkProd = $stp->fetch() ?: null;
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات سفارش <?= h(orderNumber($order)) ?> - <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=54">
</head>
<body style="background:var(--bg-primary);min-height:100vh;">
    <div class="admin-layout">
        <div class="admin-header">
            <h2 style="color:var(--text-primary);">جزئیات سفارش <span dir="ltr" title="شناسهٔ داخلی: #<?= (int)$order['id'] ?>"><?= h(orderNumber($order)) ?></span></h2>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <a href="invoice.php?id=<?= (int)$order['id'] ?>" class="btn btn-primary btn-sm" target="_blank" title="فاکتور چاپی این سفارش"><?= icon('receipt', 'ic-sm') ?> مشاهده فاکتور</a>
                <a href="orders.php" class="btn btn-secondary btn-sm">بازگشت</a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
            <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:1.5rem;">
                <h3 style="margin-bottom:1rem;border-bottom:2px solid var(--red-primary);padding-bottom:0.5rem;">اطلاعات مشتری</h3>
                <p style="margin-bottom:0.5rem;"><strong>نام:</strong> <?= h($order['customer_name']) ?></p>
                <p style="margin-bottom:0.5rem;"><strong>موبایل:</strong> <?= h($order['customer_mobile']) ?></p>
                <p style="margin-bottom:0.5rem;"><strong>آدرس:</strong> <?= nl2br(h($order['customer_address'])) ?></p>
                <?php if ($order['notes']): ?>
                <p><strong>توضیحات:</strong> <?= nl2br(h($order['notes'])) ?></p>
                <?php endif; ?>
                <?php if ($shipMethod !== ''): ?>
                <p style="margin-bottom:0.5rem;"><strong>روش ارسال:</strong>
                    <?= icon(shippingIcon($shipMethod), 'ic-sm') ?> <?= h(shippingLabel($shipMethod)) ?>
                    <span style="color:var(--text-muted);font-size:0.85rem;">(<?= h(shippingCostText($shipCost, $shipMethod)) ?>)</span>
                </p>
                <?php endif; ?>
                <p style="margin-top:1rem;"><strong>تاریخ ثبت:</strong> <?= date('Y/m/d H:i', strtotime($order['created_at'])) ?></p>
            </div>
            <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:1.5rem;">
                <h3 style="margin-bottom:1rem;border-bottom:2px solid var(--red-primary);padding-bottom:0.5rem;">وضعیت سفارش</h3>
                <p style="margin-bottom:1rem;">
                    وضعیت فعلی:
                    <span class="status-badge status-<?= $order['status'] ?>">
                        <?= $statusLabels[$order['status']] ?>
                    </span>
                </p>
                <form method="POST" action="orders.php">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <div class="form-group">
                        <label for="new_status">تغییر وضعیت:</label>
                        <select name="new_status" id="new_status" class="form-control">
                            <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                </form>
            </div>
        </div>

        <?php /* ---------- عکس نمونهٔ قطعه که مشتری پیش از خرید فرستاده ---------- */ ?>
        <?php if ($pchk): $pcSt = (string)$pchk['status']; ?>
        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem;">
            <h3 style="margin-bottom:1rem;border-bottom:2px solid var(--red-primary);padding-bottom:0.5rem;"><?= icon('camera') ?> عکس نمونهٔ قطعه (ارسال مشتری پیش از خرید)</h3>

            <div class="pchk-panel-head">
                <span class="pchk-badge <?= ['pending' => 'is-wait', 'approved' => 'is-ok', 'rejected' => 'is-no'][$pcSt] ?? 'is-wait' ?>">
                    <?= icon(['pending' => 'clock', 'approved' => 'check-circle', 'rejected' => 'x-circle'][$pcSt] ?? 'clock', 'ic-sm') ?>
                    <?= h(partCheckStatusLabel($pcSt)) ?>
                </span>
                <a href="part-checks.php?tab=<?= h($pcSt) ?>#pc<?= (int)$pchk['id'] ?>" class="btn btn-secondary btn-sm">
                    <?= icon('external', 'ic-sm') ?> در صفحهٔ بررسی عکس قطعه
                </a>
                <span class="pchk-when"><?= icon('calendar', 'ic-sm') ?> <?= h(jDate($pchk['created_at'], true)) ?></span>
            </div>

            <div class="pchk-confirm">
                <div class="pchk-confirm-row <?= $pcSt === 'approved' ? 'is-ok' : ($pcSt === 'rejected' ? '' : 'is-soft') ?>">
                    <?= icon($pcSt === 'approved' ? 'check-circle' : ($pcSt === 'rejected' ? 'x-circle' : 'clock'), 'ic-sm') ?>
                    <b>مطابقت قطعه:</b> <?= h(partCheckStatusLabel($pcSt)) ?>
                    <?php if (!empty($pchk['reviewed_at'])): ?>
                    <span class="pchk-confirm-note">— بررسی در <?= h(jDate($pchk['reviewed_at'], true)) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($pcSt === 'approved'): ?>
                <div class="pchk-confirm-row <?= !empty($pchk['stock_ok']) ? 'is-ok' : 'is-soft' ?>">
                    <?= icon(!empty($pchk['stock_ok']) ? 'package' : 'clock', 'ic-sm') ?>
                    <b>موجودی:</b> <?= !empty($pchk['stock_ok']) ? 'تأیید شد' : 'تأیید نشد' ?>
                    <?php if (trim((string)$pchk['stock_note']) !== ''): ?>
                    <span class="pchk-confirm-note">— <?= h((string)$pchk['stock_note']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($pchkProd): ?>
            <p class="pchk-sent-for"><?= icon('package', 'ic-sm') ?> برای کالای:
                <a href="product-edit.php?id=<?= (int)$pchkProd['id'] ?>" target="_blank"><b><?= h((string)$pchkProd['name']) ?></b></a>
                <?php if (trim((string)$pchkProd['technical_number']) !== ''): ?>
                <span class="pchk-tech">شماره فنی: <?= h((string)$pchkProd['technical_number']) ?></span>
                <?php endif; ?>
            </p>
            <?php endif; ?>
            <?php if (trim((string)$pchk['car_info']) !== ''): ?>
            <p class="pchk-sent-line"><?= icon('truck', 'ic-sm') ?> <b>خودروی مشتری:</b> <?= h((string)$pchk['car_info']) ?></p>
            <?php endif; ?>
            <?php if (trim((string)$pchk['note']) !== ''): ?>
            <p class="pchk-sent-line"><?= icon('clipboard-list', 'ic-sm') ?> <b>توضیح مشتری:</b> <?= nl2br(h((string)$pchk['note'])) ?></p>
            <?php endif; ?>
            <?php if (trim((string)$pchk['admin_note']) !== ''): ?>
            <p class="pchk-adminnote"><?= icon('message', 'ic-sm') ?> <b>یادداشت کارشناس:</b> <?= nl2br(h((string)$pchk['admin_note'])) ?></p>
            <?php endif; ?>

            <?php if ($pchkImgs): ?>
            <div class="pchk-sent-head"><?= icon('camera', 'ic-sm') ?> <?= count($pchkImgs) ?> عکس ارسالی — برای اندازهٔ کامل روی هر عکس بزنید</div>
            <div class="pchk-thumbs">
                <?php foreach ($pchkImgs as $pi => $pim): $pSrc = '../uploads/partchecks/' . basename((string)$pim['image']); ?>
                <a href="<?= h($pSrc) ?>" target="_blank" rel="noopener" class="pchk-thumb" title="عکس <?= (int)$pi + 1 ?>">
                    <img src="<?= h($pSrc) ?>" alt="عکس <?= (int)$pi + 1 ?>" loading="lazy">
                    <span class="pchk-thumb-n"><?= (int)$pi + 1 ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="pchk-sent-line"><?= icon('alert', 'ic-sm') ?> عکسی برای این درخواست ثبت نشده است.</p>
            <?php endif; ?>
        </div>
        <?php elseif (partCheckOn()): ?>
        <div style="background:var(--bg-card);border:1px dashed var(--border-color);border-radius:var(--radius);padding:1rem 1.25rem;margin-bottom:1.5rem;color:var(--text-muted);font-size:0.85rem;">
            <?= icon('camera', 'ic-sm') ?> این مشتری پیش از خرید عکس نمونهٔ قطعه نفرستاد (مرحله را رد کرد).
        </div>
        <?php endif; ?>

        <?php if ($shipOn): ?>
        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem;">
            <h3 style="margin-bottom:1rem;border-bottom:2px solid var(--red-primary);padding-bottom:0.5rem;"><?= icon('truck') ?> روش ارسال</h3>

            <?php if (isset($_GET['ssaved'])): ?>
            <div class="flash flash-success" style="margin-bottom:1rem;"><?= icon('check-circle', 'ic-sm') ?> روش ارسال ذخیره شد.</div>
            <?php endif; ?>
            <?php if ($shipError !== ''): ?>
            <div class="flash flash-error" style="margin-bottom:1rem;"><?= icon('alert', 'ic-sm') ?> ذخیره نشد: <?= h($shipError) ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">
                <div>
                    <p style="margin-bottom:0.5rem;"><strong>انتخاب مشتری:</strong>
                        <?php if ($shipMethod !== ''): ?>
                        <?= icon(shippingIcon($shipMethod), 'ic-sm') ?> <?= h(shippingLabel($shipMethod)) ?>
                        <?php else: ?>
                        <span style="color:var(--text-muted);">— ثبت نشده</span>
                        <?php endif; ?>
                    </p>
                    <p style="margin-bottom:0.5rem;"><strong>هزینهٔ ارسال:</strong> <?= h(shippingCostText($shipCost, $shipMethod)) ?></p>
                    <p style="margin-bottom:0.5rem;"><strong>جمع کالاها:</strong> <?= formatPrice($goodsTotal) ?></p>
                    <?php if ($orderTax > 0): ?>
                    <p style="margin-bottom:0.5rem;"><strong>مالیات:</strong> <?= formatPrice($orderTax) ?></p>
                    <?php endif; ?>
                    <?php if ($shipMethod !== '' && ($sb = shippingBadge($shipMethod)) !== ''): ?>
                    <p style="color:#FBBF24;font-size:0.85rem;"><?= icon('alert', 'ic-sm') ?> این روش <?= h($sb) ?> است — آدرس سفارش را بررسی کنید.</p>
                    <?php endif; ?>
                </div>

                <form method="POST" action="order-detail.php?id=<?= $id ?>">
                    <input type="hidden" name="ship_save" value="1">
                    <div class="form-group">
                        <label for="shipping_method">اصلاح روش ارسال</label>
                        <select name="shipping_method" id="shipping_method" class="form-control">
                            <option value="">— ثبت نشده —</option>
                            <?php foreach (shippingMethods() as $mk => $md): ?>
                            <option value="<?= h($mk) ?>" <?= $shipMethod === $mk ? 'selected' : '' ?>><?= h($md['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="shipping_cost">هزینهٔ ارسال (تومان)</label>
                        <input type="text" name="shipping_cost" id="shipping_cost" class="form-control" dir="ltr"
                               inputmode="numeric" value="<?= (int)$shipCost ?>" <?= $shipLocked ? 'disabled' : '' ?>>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">
                            <?php if ($shipLocked): ?>
                            سفارش پرداخت‌شده است؛ مبلغ تغییر نمی‌کند. فقط روش ارسال قابل اصلاح است.
                            <?php else: ?>
                            صفر = پس‌کرایه / توافقی. با تغییر این رقم، «مبلغ کل» سفارش هم بازمحاسبه می‌شود.
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">ذخیرهٔ روش ارسال</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($trackOn): ?>
        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem;">
            <h3 style="margin-bottom:1rem;border-bottom:2px solid var(--red-primary);padding-bottom:0.5rem;"><?= icon('truck') ?> روند ارسال سفارش</h3>

            <?php if (isset($_GET['tsaved'])): ?>
            <div class="flash flash-success" style="margin-bottom:1rem;"><?= icon('check-circle', 'ic-sm') ?> روند ارسال ذخیره شد و برای مشتری قابل مشاهده است.<?php
                $smsN = (int)($_GET['sms'] ?? 0);
                if ($smsN > 0) echo ' — ' . $smsN . ' پیامک برای مشتری ارسال شد' . (getSettingRaw('sms_test_mode', '1') === '1' ? ' (حالت آزمایشی: فقط در گزارش ثبت شد)' : '') . '.';
                elseif (!smsTrackEnabled()) echo ' — اطلاع‌رسانی پیامکی خاموش است.';
            ?></div>
            <?php endif; ?>
            <?php if ($trackError !== ''): ?>
            <div class="flash flash-error" style="margin-bottom:1rem;"><?= icon('alert', 'ic-sm') ?> ذخیره نشد: <?= h($trackError) ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">
                <form method="POST" action="order-detail.php?id=<?= $id ?>">
                    <input type="hidden" name="track_save" value="1">
                    <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.75rem;">
                        هر مرحله را که انجام شد تیک بزنید؛ زمان تیک به‌صورت خودکار ثبت می‌شود.
                        با برداشتن تیک، آن مرحله پاک می‌شود.
                    </p>
                    <?php if ($trackModeNote !== ''): ?>
                    <p style="font-size:0.74rem;color:#FBBF24;background:rgba(234,179,8,0.08);border:1px solid rgba(234,179,8,0.3);border-radius:var(--radius-sm);padding:0.5rem 0.6rem;margin-bottom:0.75rem;line-height:1.8;">
                        <?= icon('info', 'ic-sm') ?> <?= h($trackModeNote) ?>
                    </p>
                    <?php endif; ?>
                    <div class="track-admin-list">
                        <?php foreach ($trackDefs as $col => $def):
                            $at = $order[$col] ?? null;
                            $adminOnly = !isset($trackVisible[$col]);
                            /* هر مرحله‌ای که فیلد زیر خودش دارد، کادرش را با data-reveal
                               باز/بسته می‌کند: کد رهگیری برای «تحویل به پست» و مشخصات پیک
                               برای «تحویل به پیک». */
                            $reveal = !empty($def['code']) ? 'trk-code' : ($col === 'track_courier_at' ? 'trk-courier' : '');
                        ?>
                        <label class="track-admin-row<?= !empty($at) ? ' is-done' : '' ?>">
                            <input type="checkbox" name="track[<?= h($col) ?>]" value="1" <?= !empty($at) ? 'checked' : '' ?><?= $reveal !== '' ? ' data-reveal="' . $reveal . '"' : '' ?>>
                            <span class="track-admin-ic"><?= icon($def['icon'], 'ic-sm') ?></span>
                            <span class="track-admin-label"><?= h($def['label']) ?></span>
                            <?php if ($adminOnly): ?>
                            <span title="این مرحله برای این سفارش به مشتری نشان داده نمی‌شود و پیامکی هم برایش ارسال نمی‌شود."
                                  style="flex:0 0 auto;font-size:0.62rem;padding:0.1rem 0.32rem;border-radius:var(--radius-sm);background:rgba(234,179,8,0.14);color:#FBBF24;border:1px solid rgba(234,179,8,0.35);white-space:nowrap;">فقط ادمین</span>
                            <?php endif; ?>
                            <span class="track-admin-time"><?= !empty($at) ? h(jDate($at, true)) : '—' ?></span>
                        </label>

                        <?php /* کادر کد رهگیری، دقیقا زیر همان مرحله. با تیک‌خوردن باز می‌شود؛
                                 بدون جاوااسکریپت هم اگر مرحله از قبل تیک‌دار باشد باز است. */ ?>
                        <?php if (!empty($def['code'])): ?>
                        <div class="track-reveal" id="trk-code"<?= empty($at) ? ' hidden' : '' ?>>
                            <label for="post_tracking_code"><?= icon('receipt', 'ic-sm') ?> کد رهگیری مرسولهٔ پست</label>
                            <input type="text" name="post_tracking_code" id="post_tracking_code" class="form-control" dir="ltr" inputmode="numeric" autocomplete="off"
                                   value="<?= h($order[$def['code']] ?? '') ?>" placeholder="مثلا 123456789012345678">
                            <i>این کد در روند سفارش مشتری نمایش داده می‌شود و اگر رقمی باشد به رهگیری سایت پست لینک می‌شود.<?= smsTrackEnabled() ? ' متن پیامک این مرحله هم می‌تواند با {code} همین کد را بفرستد.' : '' ?></i>
                        </div>
                        <?php endif; ?>

                        <?php /* مشخصات پیک، زیر تیک «تحویل به پیک» — همان الگوی کادر کد رهگیری.
                                 با برداشتن تیک، هنگام ذخیره پاک می‌شوند. */ ?>
                        <?php if ($col === 'track_courier_at'): ?>
                        <div class="track-reveal" id="trk-courier"<?= empty($at) ? ' hidden' : '' ?>>
                            <label><?= icon('user', 'ic-sm') ?> مشخصات پیک</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
                                <div>
                                    <label for="courier_name" style="display:block;font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem;">نام و نام خانوادگی پیک</label>
                                    <input type="text" name="courier_name" id="courier_name" class="form-control" style="font-family:inherit;"
                                           value="<?= h($order['courier_name'] ?? '') ?>" placeholder="مثلا علی رضایی">
                                </div>
                                <div>
                                    <label for="courier_phone" style="display:block;font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem;">شمارهٔ تماس پیک</label>
                                    <input type="text" name="courier_phone" id="courier_phone" class="form-control" dir="ltr" inputmode="numeric"
                                           value="<?= h($order['courier_phone'] ?? '') ?>" placeholder="09xxxxxxxxx">
                                </div>
                            </div>
                            <i>این نام و شماره در روند سفارش مشتری، زیر همین مرحله نمایش داده می‌شود.<?= smsTrackEnabled() ? ' متن پیامک این مرحله هم می‌تواند با {courier} همین‌ها را بفرستد.' : '' ?></i>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:1rem;">ذخیرهٔ روند ارسال</button>

                    <?php /* وضعیت اطلاع‌رسانی پیامکی — همان‌جا که تیک زده می‌شود دیده شود
                             تا مدیر بداند با ذخیره‌کردن، پیامکی می‌رود یا نه. */ ?>
                    <p class="track-sms-note<?= smsTrackEnabled() ? ' is-on' : '' ?>">
                        <?= icon(smsTrackEnabled() ? 'mobile' : 'x-circle', 'ic-sm') ?>
                        <?php if (smsTrackEnabled()): ?>
                            اطلاع‌رسانی پیامکی <b>روشن</b> است؛ با ذخیره، برای هر مرحلهٔ تازه‌تیک‌خورده یک پیامک به مشتری می‌رود.
                            <?php if (getSettingRaw('sms_test_mode', '1') === '1'): ?>
                            <b style="color:#FBBF24;">حالت آزمایشی پیامک روشن است</b> — پیامک واقعی ارسال نمی‌شود.
                            <?php endif; ?>
                        <?php else: ?>
                            اطلاع‌رسانی پیامکی <b>خاموش</b> است؛ مراحل ثبت می‌شوند ولی پیامکی ارسال نمی‌شود.
                        <?php endif; ?>
                        <a href="settings.php?sec=sms">تنظیمات پیامک</a>
                    </p>
                </form>

                <div>
                    <h4 style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.75rem;">پیش‌نمایش آنچه مشتری می‌بیند</h4>
                    <?= renderOrderTimeline($order) ?>
                </div>
            </div>
        </div>

        <?php /* باز/بسته‌شدن کادر زیر هر مرحله با تیک همان مرحله (کد رهگیری پست و
                 مشخصات پیک). data-reveal روی چک‌باکس، id همان مقدار روی کادر. بدون این
                 اسکریپت هم صفحه کار می‌کند: کادر مرحلهٔ تیک‌دار از سمت سرور باز رندر شده. */ ?>
        <script>
        (function () {
            var boxes = document.querySelectorAll('.track-admin-row input[data-reveal]');
            for (var i = 0; i < boxes.length; i++) {
                (function (cb) {
                    var box = document.getElementById(cb.getAttribute('data-reveal'));
                    if (!box) return;
                    cb.addEventListener('change', function () {
                        box.hidden = !cb.checked;
                        if (cb.checked) {
                            var f = box.querySelector('input');
                            if (f) f.focus();
                        }
                    });
                })(boxes[i]);
            }
        })();
        </script>
        <?php endif; ?>

        <?php if ($payOn): ?>
        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem;">
            <h3 style="margin-bottom:1rem;border-bottom:2px solid var(--red-primary);padding-bottom:0.5rem;"><?= icon('credit-card') ?> پرداخت</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div>
                    <p style="margin-bottom:0.5rem;"><strong>روش پرداخت:</strong> <?= icon(paymentIcon($payMethod), 'ic-sm') ?> <?= h(paymentLabel($payMethod)) ?></p>
                    <p style="margin-bottom:0.5rem;"><strong>وضعیت پرداخت:</strong> <?= paymentStatusBadgeFor($payStatus, $payMethod) ?></p>
                    <?php if ((int)($order['paid_amount'] ?? 0) > 0): ?>
                    <p style="margin-bottom:0.5rem;"><strong>مبلغ پرداخت‌شده:</strong> <?= formatPrice($order['paid_amount']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($order['payment_ref'])): ?>
                    <p style="margin-bottom:0.5rem;"><strong>شمارهٔ پیگیری:</strong> <span dir="ltr"><?= h($order['payment_ref']) ?></span></p>
                    <?php endif; ?>
                    <?php if (!empty($order['payment_card'])): ?>
                    <p style="margin-bottom:0.5rem;"><strong>کارت پرداخت‌کننده:</strong> <span dir="ltr"><?= h($order['payment_card']) ?></span></p>
                    <?php endif; ?>
                    <?php if (!empty($order['paid_at'])): ?>
                    <p style="margin-bottom:0.5rem;"><strong>زمان پرداخت:</strong> <?= date('Y/m/d H:i', strtotime($order['paid_at'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($order['payment_note'])): ?>
                    <p style="color:#FBBF24;font-size:0.85rem;"><?= icon('alert', 'ic-sm') ?> <?= h($order['payment_note']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <form method="POST" action="orders.php">
                        <input type="hidden" name="pay_order_id" value="<?= (int)$order['id'] ?>">
                        <div class="form-group">
                            <label for="pay_new_status">تغییر دستی وضعیت پرداخت:</label>
                            <select name="pay_new_status" id="pay_new_status" class="form-control">
                                <?php foreach (['unpaid', 'pending', 'paid', 'failed', 'refunded'] as $ps): ?>
                                <option value="<?= $ps ?>" <?= $payStatus === $ps ? 'selected' : '' ?>><?= h(paymentStatusLabel($ps)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="pay_ref">شمارهٔ پیگیری / رسید (اختیاری)</label>
                            <input type="text" name="pay_ref" id="pay_ref" class="form-control" dir="ltr" value="<?= h($order['payment_ref'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">ثبت وضعیت پرداخت</button>
                    </form>
                    <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.5rem;">
                        برای پرداخت در محل، پس از دریافت مبلغ اینجا «پرداخت‌شده» را ثبت کنید.
                        <?php if ($c2cOn && $payMethod === 'card'): ?>واریز کارت‌به‌کارت را از کادر پایین تأیید کنید.<?php endif; ?>
                        <?php if ($chqOn && $payMethod === 'cheque'): ?>اطلاعات چک را از کادر پایین ببینید؛ «دریافت چک» با «پرداخت‌شده» فرق دارد.<?php endif; ?>
                        پرداخت‌های آنلاین خودکار توسط درگاه ثبت می‌شوند.
                    </div>
                </div>
            </div>

            <?php if ($c2cOn && $payMethod === 'card'): ?>
            <?php /* اطلاعاتی که مشتری خودش پس از واریز ثبت کرده + دکمهٔ تأیید */ ?>
            <?php $hasC2c = trim((string)($order['c2c_ref'] ?? '')) !== ''; ?>
            <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px dashed var(--border-color);">
                <h4 style="font-size:0.9rem;margin-bottom:0.75rem;"><?= icon('receipt', 'ic-sm') ?> واریز اعلام‌شدهٔ مشتری</h4>

                <?php if (isset($_GET['c2csaved'])): ?>
                <div style="color:var(--green);font-size:0.85rem;margin-bottom:0.75rem;"><?= icon('check-circle', 'ic-sm') ?> واریز تأیید شد؛ پرداخت «پرداخت‌شده» و سفارش «تأیید شده» ثبت شد.</div>
                <?php endif; ?>
                <?php if ($c2cError !== ''): ?>
                <div style="color:var(--red-light);font-size:0.85rem;margin-bottom:0.75rem;"><?= icon('alert', 'ic-sm') ?> <?= h($c2cError) ?></div>
                <?php endif; ?>

                <?php if (!$hasC2c): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;"><?= icon('clock', 'ic-sm') ?> مشتری هنوز اطلاعات واریز را ثبت نکرده است.</p>
                <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0.75rem 1.5rem;">
                    <p style="margin:0;"><strong>شناسهٔ واریز:</strong> <span dir="ltr"><?= h((string)$order['c2c_ref']) ?></span></p>
                    <p style="margin:0;"><strong>مبلغ اعلامی:</strong> <?= formatPrice((int)($order['c2c_amount'] ?? 0)) ?>
                        <?php if ((int)($order['c2c_amount'] ?? 0) !== (int)$order['total_amount']): ?>
                        <span style="color:#FBBF24;font-size:0.78rem;">(مبلغ سفارش: <?= formatPrice((int)$order['total_amount']) ?>)</span>
                        <?php endif; ?>
                    </p>
                    <p style="margin:0;"><strong>چهار رقم آخر کارت مبدأ:</strong> <span dir="ltr"><?= h((string)($order['c2c_last4'] ?? '')) ?></span></p>
                    <p style="margin:0;"><strong>زمان واریز (به گفتهٔ مشتری):</strong> <?= h((string)($order['c2c_paid_text'] ?? '')) ?></p>
                    <?php if (!empty($order['c2c_reported_at'])): ?>
                    <p style="margin:0;"><strong>زمان ثبت در سایت:</strong> <?= date('Y/m/d H:i', strtotime($order['c2c_reported_at'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($order['c2c_verified_at'])): ?>
                    <p style="margin:0;color:var(--green);"><strong>تأیید شده در:</strong> <?= date('Y/m/d H:i', strtotime($order['c2c_verified_at'])) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($c2cWait): ?>
                <form method="POST" action="order-detail.php?id=<?= $id ?>" style="margin-top:0.9rem;"
                      onsubmit="return confirm('واریز این سفارش تأیید شود؟ پرداخت «پرداخت‌شده» و سفارش «تأیید شده» می‌شود.');">
                    <input type="hidden" name="c2c_verify" value="1">
                    <button type="submit" class="btn btn-primary"><?= icon('check-circle', 'ic-sm') ?> تأیید واریز و ثبت پرداخت</button>
                    <span style="color:var(--text-muted);font-size:0.72rem;margin-inline-start:0.5rem;">پیش از تأیید، واریز را در حساب بانکی بررسی کنید.</span>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($chqOn && $payMethod === 'cheque'): ?>
            <?php /* از ۲۰۲۶-۰۸-۲۹ همکار دوباره بانک/سریال/تاریخ/مبلغ/در وجه/شناسهٔ
                    صیاد چک را در تسویه‌حساب تایپ می‌کند (خواستهٔ تازهٔ کاربر). دکمهٔ
                    «دریافت چک» هم‌چنان همیشه در دسترس است، چون رسیدن فیزیکی چک
                    مستقل از پرشدن این فرم است (سفارش‌های قدیمی‌تر که این ستون‌ها
                    را ندارند هم بدون خطا کار می‌کنند). */ ?>
            <?php $hasChq = trim((string)($order['cheque_number'] ?? '')) !== ''; ?>
            <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px dashed var(--border-color);">
                <h4 style="font-size:0.9rem;margin-bottom:0.75rem;"><?= icon('receipt', 'ic-sm') ?> پرداخت با چک</h4>

                <?php if (isset($_GET['chqsaved'])): ?>
                <div style="color:var(--green);font-size:0.85rem;margin-bottom:0.75rem;"><?= icon('check-circle', 'ic-sm') ?> دریافت چک ثبت شد.</div>
                <?php endif; ?>

                <?php if ($hasChq): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0.75rem 1.5rem;margin-bottom:0.75rem;">
                    <p style="margin:0;"><strong>بانک:</strong> <?= h((string)$order['cheque_bank']) ?></p>
                    <p style="margin:0;"><strong>سریال چک:</strong> <span dir="ltr"><?= h((string)$order['cheque_number']) ?></span></p>
                    <p style="margin:0;"><strong>تاریخ چک:</strong> <?= h((string)$order['cheque_date']) ?></p>
                    <p style="margin:0;"><strong>مبلغ چک:</strong> <?= formatPrice((int)($order['cheque_amount'] ?? 0)) ?>
                        <?php if ((int)($order['cheque_amount'] ?? 0) !== (int)$order['total_amount']): ?>
                        <span style="color:#FBBF24;font-size:0.78rem;">(مبلغ سفارش: <?= formatPrice((int)$order['total_amount']) ?>)</span>
                        <?php endif; ?>
                    </p>
                    <?php if (trim((string)($order['cheque_payee'] ?? '')) !== ''): ?>
                    <p style="margin:0;"><strong>در وجه:</strong> <?= h((string)$order['cheque_payee']) ?></p>
                    <?php endif; ?>
                    <?php if (trim((string)($order['cheque_sayad'] ?? '')) !== ''): ?>
                    <p style="margin:0;"><strong>شناسهٔ صیاد:</strong> <span dir="ltr"><?= h((string)$order['cheque_sayad']) ?></span></p>
                    <?php endif; ?>
                    <?php if (!empty($order['cheque_reported_at'])): ?>
                    <p style="margin:0;"><strong>زمان ثبت در سایت:</strong> <?= date('Y/m/d H:i', strtotime($order['cheque_reported_at'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:0.75rem;">مهلت ارسال/تحویل اصل چک: <b><?= (int)paymentChequeDeadlineDays() ?> روز</b> از زمان ثبت سفارش.</p>
                <?php endif; ?>

                <?php if (!empty($order['cheque_received_at'])): ?>
                <p style="margin:0;color:var(--green);"><?= icon('check-circle', 'ic-sm') ?> <strong>چک دریافت شد</strong> — <?= date('Y/m/d H:i', strtotime($order['cheque_received_at'])) ?></p>
                <?php else: ?>
                <form method="POST" action="order-detail.php?id=<?= $id ?>"
                      onsubmit="return confirm('دریافت این چک ثبت شود؟ (این کار پرداخت را «پرداخت‌شده» نمی‌کند — فقط می‌گوید چک رسیده است.)');">
                    <input type="hidden" name="cheque_receive" value="1">
                    <button type="submit" class="btn btn-primary"><?= icon('check-circle', 'ic-sm') ?> ثبت دریافت چک</button>
                    <span style="color:var(--text-muted);font-size:0.72rem;margin-inline-start:0.5rem;">وقتی چک واقعا وصول شد، از فرم «ثبت وضعیت پرداخت» بالا «پرداخت‌شده» را بزنید.</span>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($attempts): ?>
            <h4 style="font-size:0.85rem;color:var(--text-muted);margin:1.25rem 0 0.5rem;">تلاش‌های پرداخت (<?= count($attempts) ?>)</h4>
            <table class="admin-table">
                <thead><tr><th>زمان</th><th>درگاه</th><th>مبلغ ارسالی</th><th>وضعیت</th><th>شمارهٔ پیگیری</th><th>پیام</th></tr></thead>
                <tbody>
                <?php
                $attLabels = ['created' => 'ساخته شد', 'redirected' => 'انتقال به درگاه', 'paid' => 'پرداخت شد', 'failed' => 'ناموفق', 'canceled' => 'لغو شد'];
                foreach ($attempts as $a): ?>
                <tr>
                    <td style="white-space:nowrap;font-size:0.78rem;"><?= date('Y/m/d H:i', strtotime($a['created_at'])) ?></td>
                    <td><?= h(paymentLabel($a['gateway'])) ?></td>
                    <td dir="ltr"><?= number_format((int)$a['amount']) ?></td>
                    <td><?= h($attLabels[$a['status']] ?? $a['status']) ?></td>
                    <td dir="ltr" style="font-size:0.78rem;"><?= h($a['ref_id'] ?? '') ?></td>
                    <td style="font-size:0.75rem;color:var(--text-muted);"><?= h($a['message'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <h3 style="margin-bottom:1rem;">اقلام سفارش</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>محصول</th>
                    <th>قیمت واحد</th>
                    <th>نوع قیمت</th>
                    <th>تعداد</th>
                    <th>جمع</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= formatPrice($item['price']) ?></td>
                    <td><?= $item['price_type'] === 'wholesale' ? 'کلی' : 'جزئی' ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= formatPrice($item['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($shipOn && $shipCost > 0): ?>
                <tr>
                    <td colspan="4" style="text-align:left;color:var(--text-secondary);">جمع کالاها:</td>
                    <td style="color:var(--text-secondary);"><?= formatPrice($goodsTotal) ?></td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align:left;color:var(--text-secondary);">هزینهٔ ارسال<?= $shipMethod !== '' ? ' (' . h(shippingLabel($shipMethod)) . ')' : '' ?>:</td>
                    <td style="color:var(--text-secondary);"><?= formatPrice($shipCost) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($orderTax > 0): ?>
                <tr>
                    <td colspan="4" style="text-align:left;color:var(--text-secondary);">مالیات:</td>
                    <td style="color:var(--text-secondary);"><?= formatPrice($orderTax) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="4" style="text-align:left;font-weight:bold;">مبلغ کل:</td>
                    <td style="font-weight:bold;color:var(--red-light);"><?= formatPrice($order['total_amount']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>