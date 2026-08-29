<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $allowedStatuses = ['pending', 'confirmed', 'shipped', 'cancelled'];
    $newStatus = $_POST['new_status'];
    if (in_array($newStatus, $allowedStatuses)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, (int)$_POST['order_id']]);
    }
}

$payOn = paymentReady();
$shipOn = shippingReady();

/* تغییر دستی وضعیت پرداخت (برای کارت‌به‌کارت و پرداخت در محل) */
$payMsg = '';
if ($payOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_order_id'], $_POST['pay_new_status'])) {
    $allowedPay = ['unpaid', 'pending', 'paid', 'failed', 'refunded'];
    $pid = (int)$_POST['pay_order_id'];
    $pst = (string)$_POST['pay_new_status'];
    $pref = trim(faToLatinDigits((string)($_POST['pay_ref'] ?? '')));
    if (in_array($pst, $allowedPay, true) && $pid > 0) {
        try {
            $ord = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $ord->execute([$pid]);
            $ord = $ord->fetch();
            if ($ord) {
                if ($pst === 'paid') {
                    paymentMarkPaid($pid, (int)$ord['total_amount'], $pref !== '' ? $pref : 'دستی-ادمین', '', false);
                    $pdo->prepare("UPDATE orders SET payment_note=? WHERE id=?")
                        ->execute(['تأیید دستی توسط مدیر', $pid]);
                } else {
                    $pdo->prepare("UPDATE orders SET payment_status=?, payment_ref=? WHERE id=?")
                        ->execute([$pst, $pref, $pid]);
                }
                paymentLog("ADMIN set payment_status=$pst | order=$pid | ref=$pref | by=" . ($_SESSION['admin_username'] ?? 'admin'));
                $payMsg = 'وضعیت پرداخت سفارش #' . $pid . ' به «' . paymentStatusLabel($pst) . '» تغییر کرد.';
            }
        } catch (Throwable $e) { $payMsg = 'خطا در تغییر وضعیت پرداخت.'; }
    }
}

/* تأیید واریزِ کارت به کارت از همین فهرست (صفِ بررسی).
   مشتری چهار مورد را ثبت کرده و سفارش «در انتظار تأیید واریز» است؛ با تأیید
   مدیر پرداخت «پرداخت‌شده» و سفارش «تأیید شده» می‌شود. */
$c2cOn = $payOn && paymentC2cReady();
$c2cMsg = '';
if ($c2cOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['c2c_verify_id'])) {
    $vid = (int)$_POST['c2c_verify_id'];
    if ($vid > 0) {
        $c2cMsg = paymentC2cVerify($vid)
                ? 'واریز سفارش #' . $vid . ' تأیید و پرداخت ثبت شد.'
                : 'تأیید واریز سفارش #' . $vid . ' انجام نشد.';
    }
}

/* «دریافت چک» از همین فهرست (صفِ بررسی) — همان الگوی بالا، با این تفاوت که
   دریافتِ چک را «پرداخت‌شده» نمی‌کند، فقط زمانِ رسیدنِ چک را ثبت می‌کند. */
$chqOn = $payOn && paymentChequeReady();
$chqMsg = '';
if ($chqOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cheque_receive_id'])) {
    $rid = (int)$_POST['cheque_receive_id'];
    if ($rid > 0) {
        $chqMsg = paymentChequeReceive($rid)
                ? 'دریافتِ چکِ سفارش #' . $rid . ' ثبت شد.'
                : 'ثبتِ دریافتِ چکِ سفارش #' . $rid . ' انجام نشد.';
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

/* فیلتر وضعیت پرداخت */
$payFilter = (string)($_GET['pay'] ?? '');
$where = '';
$params = [];
if ($payOn && in_array($payFilter, ['unpaid', 'pending', 'paid', 'failed', 'refunded'], true)) {
    $where = " WHERE payment_status = ?";
    $params[] = $payFilter;
}

$cnt = $pdo->prepare("SELECT COUNT(*) FROM orders" . $where);
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$totalPages = ceil($total / ITEMS_PER_PAGE);

$q = $pdo->prepare("SELECT * FROM orders" . $where . " ORDER BY created_at DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset");
$q->execute($params);
$orders = $q->fetchAll();

/* شمارش‌های خلاصهٔ پرداخت */
$payCounts = ['all' => 0, 'paid' => 0, 'unpaid' => 0, 'pending' => 0, 'failed' => 0];
$paidSum = 0;
if ($payOn) {
    try {
        $payCounts['all'] = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        foreach ($pdo->query("SELECT payment_status, COUNT(*) n FROM orders GROUP BY payment_status") as $r) {
            $payCounts[$r['payment_status']] = (int)$r['n'];
        }
        $paidSum = (float)$pdo->query("SELECT COALESCE(SUM(paid_amount),0) FROM orders WHERE payment_status='paid'")->fetchColumn();
    } catch (Throwable $e) {}
}

$statusLabels = [
    'pending' => 'در انتظار',
    'confirmed' => 'تأیید شده',
    'shipped' => 'ارسال شده',
    'cancelled' => 'لغو شده',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت سفارشات - <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=52">
</head>
<body style="background:var(--bg-primary);min-height:100vh;">
    <div class="admin-layout admin-layout--wide">
        <div class="admin-header">
            <h2 style="color:var(--text-primary);">مدیریت سفارشات</h2>
            <a href="index.php" class="btn btn-secondary btn-sm">بازگشت</a>
        </div>

        <div class="admin-menu">
            <a href="index.php">داشبورد</a>
            <a href="products.php">محصولات</a>
            <a href="categories.php">دسته‌بندی‌ها</a>
            <a href="orders.php" class="active">سفارشات</a>
            <a href="settings.php">تنظیمات</a>
            <a href="../shop.php" target="_blank">مشاهده فروشگاه</a>
        </div>

        <?php if ($payMsg !== ''): ?>
        <div class="flash flash-success" style="margin-bottom:1rem;"><?= h($payMsg) ?></div>
        <?php endif; ?>

        <?php if ($c2cMsg !== ''): ?>
        <div class="flash flash-success" style="margin-bottom:1rem;"><?= h($c2cMsg) ?></div>
        <?php endif; ?>

        <?php if ($chqMsg !== ''): ?>
        <div class="flash flash-success" style="margin-bottom:1rem;"><?= h($chqMsg) ?></div>
        <?php endif; ?>

        <?php if (!$payOn): ?>
        <div class="flash flash-error" style="margin-bottom:1rem;">
            ستون‌های پرداخت ساخته نشده‌اند. فایل <code dir="ltr">migrate-payments.php</code> را یک‌بار در مرورگر باز کنید.
        </div>
        <?php else: ?>
        <div class="cust-tabs" style="margin-bottom:1rem;">
            <a href="orders.php" class="cust-tab <?= $payFilter === '' ? 'active' : '' ?>">همه <span class="cust-tab-n"><?= $payCounts['all'] ?></span></a>
            <a href="?pay=paid" class="cust-tab <?= $payFilter === 'paid' ? 'active' : '' ?>">پرداخت‌شده <span class="cust-tab-n"><?= (int)($payCounts['paid'] ?? 0) ?></span></a>
            <a href="?pay=unpaid" class="cust-tab <?= $payFilter === 'unpaid' ? 'active' : '' ?>">پرداخت‌نشده <span class="cust-tab-n"><?= (int)($payCounts['unpaid'] ?? 0) ?></span></a>
            <a href="?pay=pending" class="cust-tab <?= $payFilter === 'pending' ? 'active' : '' ?>">در انتظار پرداخت <span class="cust-tab-n"><?= (int)($payCounts['pending'] ?? 0) ?></span></a>
            <a href="?pay=failed" class="cust-tab <?= $payFilter === 'failed' ? 'active' : '' ?>">ناموفق <span class="cust-tab-n"><?= (int)($payCounts['failed'] ?? 0) ?></span></a>
            <span style="margin-right:auto;color:var(--text-muted);font-size:0.8rem;">جمع دریافتی: <b style="color:#4ADE80;"><?= formatPrice($paidSum) ?></b></span>
        </div>
        <?php endif; ?>

        <?php /* جدول با این‌همه ستون (شماره/مشتری/موبایل/مبلغ/وضعیت/پرداخت/ارسال/
                تاریخ/عملیات) و چند خط توضیح داخل سلولِ پرداخت/ارسال، در قابِ
                تنگِ صفحه فشرده و روی‌هم می‌افتاد. حالا مثلِ جدولِ نرخ‌نامه در
                تنظیمات، خودِ جدول در قابِ اسکرول‌شوندهٔ خودش عرضِ لازم را
                می‌گیرد و به‌جای فشرده‌شدن، فقط قاب اسکرول افقی می‌خورد. */ ?>
        <div class="tbl-scroll">
        <table class="admin-table orders-table">
            <thead>
                <tr>
                    <th class="ot-no">شماره</th>
                    <th class="ot-cust">مشتری</th>
                    <th class="ot-mobile">موبایل</th>
                    <th class="ot-amount">مبلغ</th>
                    <th class="ot-status">وضعیت</th>
                    <?php if ($payOn): ?><th class="ot-pay">پرداخت</th><?php endif; ?>
                    <?php if ($shipOn): ?><th class="ot-ship">ارسال</th><?php endif; ?>
                    <th class="ot-date">تاریخ</th>
                    <th class="ot-actions">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="ot-no" dir="ltr" title="شناسهٔ داخلی: #<?= (int)$o['id'] ?>"><?= h(orderNumber($o)) ?></td>
                    <td class="ot-cust"><?= h($o['customer_name']) ?></td>
                    <td class="ot-mobile" dir="ltr"><?= h($o['customer_mobile']) ?></td>
                    <td class="ot-amount"><?= formatPrice($o['total_amount']) ?></td>
                    <td class="ot-status">
                        <span class="status-badge status-<?= $o['status'] ?>">
                            <?= $statusLabels[$o['status']] ?>
                        </span>
                    </td>
                    <?php if ($payOn): ?>
                    <td class="ot-pay">
                        <?= paymentStatusBadgeFor((string)($o['payment_status'] ?? 'unpaid'), (string)($o['payment_method'] ?? 'cod')) ?>
                        <div style="color:var(--text-muted);font-size:0.7rem;margin-top:2px;"><?= h(paymentLabel((string)($o['payment_method'] ?? 'cod'))) ?></div>
                        <?php if ($c2cOn && paymentC2cAwaiting($o)): ?>
                        <?php /* صفِ تأیید: مشتری واریز کارت‌به‌کارت را اعلام کرده و منتظر بررسی است */ ?>
                        <div style="color:#FBBF24;font-size:0.7rem;margin-top:2px;"><?= icon('receipt', 'ic-sm') ?> واریز اعلام شد — بررسی کنید</div>
                        <?php endif; ?>
                        <?php if ($chqOn && paymentChequeAwaiting($o)): ?>
                        <?php if (empty($o['cheque_received_at'])): ?>
                        <div style="color:#FBBF24;font-size:0.7rem;margin-top:2px;"><?= icon('receipt', 'ic-sm') ?> در انتظار دریافت چک</div>
                        <?php else: ?>
                        <div style="color:#4ADE80;font-size:0.7rem;margin-top:2px;"><?= icon('check-circle', 'ic-sm') ?> چک دریافت شد</div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($shipOn): $sm = (string)($o['shipping_method'] ?? ''); ?>
                    <td class="ot-ship">
                        <?php if ($sm !== ''): ?>
                        <span style="font-size:0.78rem;"><?= icon(shippingIcon($sm), 'ic-sm') ?> <?= h(shippingLabel($sm)) ?></span>
                        <div style="color:var(--text-muted);font-size:0.7rem;margin-top:2px;"><?= h(shippingCostText((int)($o['shipping_cost'] ?? 0), $sm)) ?></div>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:0.78rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="ot-date" dir="ltr"><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?></td>
                    <td class="ot-actions">
                        <div class="ot-actions-row">
                        <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn btn-secondary btn-sm">جزئیات</a>
                        <a href="invoice.php?id=<?= $o['id'] ?>" class="btn btn-secondary btn-sm" target="_blank" title="مشاهده و چاپ فاکتور این سفارش"><?= icon('receipt', 'ic-sm') ?> فاکتور</a>
                        <?php if ($c2cOn && paymentC2cAwaiting($o)): ?>
                        <form method="POST" onsubmit="return confirm('واریز سفارش #<?= (int)$o['id'] ?> تأیید شود؟ پیش از تأیید، واریز را در حساب بانکی بررسی کنید.');">
                            <input type="hidden" name="c2c_verify_id" value="<?= (int)$o['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm">تأیید واریز</button>
                        </form>
                        <?php elseif ($chqOn && paymentChequeAwaiting($o) && empty($o['cheque_received_at'])): ?>
                        <form method="POST" onsubmit="return confirm('دریافتِ چکِ سفارش #<?= (int)$o['id'] ?> ثبت شود؟');">
                            <input type="hidden" name="cheque_receive_id" value="<?= (int)$o['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm">دریافت چک</button>
                        </form>
                        <?php elseif ($payOn && ($o['payment_status'] ?? '') !== 'paid'
                                   && !in_array((string)($o['payment_method'] ?? ''), ['cheque', 'partner_month'], true)): ?>
                        <?php /* چک و پرداختِ اول‌ماه عمداً از این دکمه بیرون‌اند: تسویهٔ واقعی‌شان
                                (وصول‌شدنِ چک، حساب‌کتابِ ماهانه) در admin/partner-settlements.php
                                پیگیری می‌شود، نه با یک کلیکِ «پرداخت شد» روی تک‌تک سفارش‌ها اینجا —
                                خواستهٔ کاربر: خریدِ همکار نباید منتظرِ این تأییدِ اضافه بماند و باید
                                جلو برود؛ اگر روزی واقعاً لازم شد، «تغییرِ دستیِ وضعیتِ پرداخت» در
                                admin/order-detail.php هنوز در دسترس است. */ ?>
                        <form method="POST" onsubmit="return confirm('سفارش #<?= (int)$o['id'] ?> به‌عنوان پرداخت‌شده ثبت شود؟');">
                            <input type="hidden" name="pay_order_id" value="<?= (int)$o['id'] ?>">
                            <input type="hidden" name="pay_new_status" value="paid">
                            <button type="submit" class="btn btn-primary btn-sm">پرداخت شد</button>
                        </form>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                <tr><td colspan="<?= 7 + ($payOn ? 1 : 0) + ($shipOn ? 1 : 0) ?>" style="text-align:center;padding:2rem;color:var(--text-muted);">سفارشی ثبت نشده است.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php $qs = $payFilter !== '' ? ('pay=' . urlencode($payFilter) . '&') : ''; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="current"><?= $i ?></span>
                <?php else: ?>
                <a href="?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>