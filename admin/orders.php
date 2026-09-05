<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$payOn   = paymentReady();
$shipOn  = shippingReady();
$trackOn = trackingReady();
$trackDefs  = $trackOn ? orderTrackSteps() : [];
$trackPost  = $trackOn && trackingPostReady();
$trackError = '';

/* کدام ردیف/کدام کادر باید بعد از این درخواست باز بماند — همهٔ اقدام‌های
   زیر (بدون ریدایرکت، همان درخواست دوباره رندر می‌شود) این دو متغیر را
   پر می‌کنند تا رندر پایین فقط همان یک کادر را باز نگه دارد، بقیه بسته
   بمانند (هم‌الگوی «برند تازه‌ذخیره‌شده باز بماند» در admin/categories.php). */
$openRow = 0;
$openPanel = '';

/* ---------- تغییر وضعیت سفارش ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $allowedStatuses = ['pending', 'confirmed', 'shipped', 'cancelled'];
    $newStatus = $_POST['new_status'];
    if (in_array($newStatus, $allowedStatuses, true)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, (int)$_POST['order_id']]);
        $openRow = (int)$_POST['order_id'];
        $openPanel = 'status';
    }
}

/* ---------- تغییر دستی وضعیت پرداخت (کارت‌به‌کارت و پرداخت در محل) ---------- */
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
                $openRow = $pid;
                $openPanel = 'pay';
            }
        } catch (Throwable $e) { $payMsg = 'خطا در تغییر وضعیت پرداخت.'; }
    }
}

/* ---------- تأیید واریز کارت به کارت ---------- */
$c2cOn = $payOn && paymentC2cReady();
$c2cMsg = '';
if ($c2cOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['c2c_verify_id'])) {
    $vid = (int)$_POST['c2c_verify_id'];
    if ($vid > 0) {
        $c2cMsg = paymentC2cVerify($vid)
                ? 'واریز سفارش #' . $vid . ' تأیید و پرداخت ثبت شد.'
                : 'تأیید واریز سفارش #' . $vid . ' انجام نشد.';
        $openRow = $vid;
        $openPanel = 'pay';
    }
}

/* ---------- «دریافت چک» ---------- */
$chqOn = $payOn && paymentChequeReady();
$chqMsg = '';
if ($chqOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cheque_receive_id'])) {
    $rid = (int)$_POST['cheque_receive_id'];
    if ($rid > 0) {
        $chqMsg = paymentChequeReceive($rid)
                ? 'دریافت چک سفارش #' . $rid . ' ثبت شد.'
                : 'ثبت دریافت چک سفارش #' . $rid . ' انجام نشد.';
        $openRow = $rid;
        $openPanel = 'pay';
    }
}

/* ---------- تغییر سریع روش ارسال، مستقیم توی سطر (نوار کشویی) ----------
   خواستهٔ کاربر: «روش ارسال رو کلا از جزئیات حذف کن چون توی سطر مشخصه اما
   توی همون سطر امکان تغییرش رو بذار» — فقط خود روش عوض می‌شود، هزینه و
   مبلغ کل دست‌نخورده می‌مانند (اصلاح هزینه، در صورت لزوم، از admin/order-detail.php
   هنوز در دسترس است). */
$shipMsg = '';
if ($shipOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ship_order_id'])) {
    $sid = (int)$_POST['ship_order_id'];
    $newMethod = (string)($_POST['ship_method'] ?? '');
    if ($newMethod !== '' && shippingMethodDef($newMethod) === null) $newMethod = '';
    if ($sid > 0) {
        $pdo->prepare("UPDATE orders SET shipping_method = ? WHERE id = ?")->execute([$newMethod, $sid]);
        $openRow = $sid;
        $openPanel = '';
    }
}

/* ---------- ثبت روند ارسال (تیک‌های مرحله‌به‌مرحله) ----------
   همان منطق admin/order-detail.php، فقط حالا شناسهٔ سفارش از یک فیلد مخفی
   («track_order_id») می‌آید چون چند فرم مستقل (یکی برای هر سطر) روی همین
   یک صفحه‌اند، نه یک سفارش با شناسهٔ ثابت در URL. */
if ($trackOn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_save'], $_POST['track_order_id'])) {
    $tid = (int)$_POST['track_order_id'];
    $tOrd = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $tOrd->execute([$tid]);
    $tOrd = $tOrd->fetch();
    if ($tOrd) {
        $checked = (array)($_POST['track'] ?? []);
        $sets = [];
        $params = [];
        $justDone = [];
        foreach ($trackDefs as $col => $def) {
            $on = isset($checked[$col]);
            if ($on) {
                if (empty($tOrd[$col])) { $sets[] = "`$col` = NOW()"; $justDone[] = $col; }
            } else {
                $sets[] = "`$col` = NULL";
            }
        }
        $hasCourier = isset($checked['track_courier_at']);
        $sets[] = "courier_name = ?";
        $params[] = $hasCourier ? trim($_POST['courier_name'] ?? '') : '';
        $sets[] = "courier_phone = ?";
        $params[] = $hasCourier ? trim(faToLatinDigits($_POST['courier_phone'] ?? '')) : '';

        $postCode = '';
        if ($trackPost) {
            $postCode = trim(faToLatinDigits((string)($_POST['post_tracking_code'] ?? '')));
            if (!isset($checked['track_post_at'])) $postCode = '';
            $sets[] = "post_tracking_code = ?";
            $params[] = $postCode;
        }

        $cur = (string)$tOrd['status'];
        if ($cur !== 'cancelled') {
            if (isset($checked['track_shipped_at']) && $cur !== 'shipped') {
                $sets[] = "status = 'shipped'";
            } elseif (isset($checked['track_confirmed_at']) && $cur === 'pending') {
                $sets[] = "status = 'confirmed'";
            }
        }

        try {
            $params[] = $tid;
            $pdo->prepare("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

            if ($justDone && smsTrackEnabled()) {
                $fresh = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $fresh->execute([$tid]);
                $fresh = $fresh->fetch();
                if ($fresh) {
                    $vis = orderTrackStepsVisible($fresh);
                    foreach ($justDone as $col) {
                        if (!isset($vis[$col])) continue;
                        smsNotifyTrackStep($fresh, $col);
                    }
                }
            }
            $openRow = $tid;
            $openPanel = 'track';
        } catch (Throwable $e) {
            $trackError = $e->getMessage();
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

/* فیلتر وضعیت پرداخت */
$payFilter = (string)($_GET['pay'] ?? '');

/* ---------- دسته‌بندی سفارش بر اساس نوع مشتری صاحبش ----------
   سه دستهٔ مستقل، از روی داده‌های موجود (بدون ستون تازه‌ای روی orders):
   همکار / بدون‌ثبت‌نام (حساب «مهمان») / مشتری (بقیه). دیگر نمای «همه
   سفارشات» نداریم (خواستهٔ کاربر ۲۰۲۶-۰۹-۰۵: «سفارشات دسته‌بندی شده دیگه
   همه سفارشات نیاز نیست ... شلوغ میشه») — بدون ?ctype معتبر، به «مشتریان»
   می‌رویم؛ سایدبار ادمین هم دیگر لینکی به نمای ترکیبی نمی‌دهد. */
$ctypeCase = "CASE WHEN c.customer_type = 'partner' THEN 'partner'
                   WHEN c.mobile IS NULL OR c.mobile NOT REGEXP '^09[0-9]{9}$' THEN 'guest'
                   ELSE 'retail' END";
$ctypeFilter = (string)($_GET['ctype'] ?? '');
if (!in_array($ctypeFilter, ['partner', 'retail', 'guest'], true)) {
    $qs = $_GET;
    $qs['ctype'] = 'retail';
    header('Location: orders.php?' . http_build_query($qs));
    exit;
}
$ctypeLabels = ['retail' => 'مشتریان', 'partner' => 'همکاران', 'guest' => 'بدون ثبت‌نام'];

$whereParts = ["$ctypeCase = ?"];
$params = [$ctypeFilter];
if ($payOn && in_array($payFilter, ['unpaid', 'pending', 'paid', 'failed', 'refunded'], true)) {
    $whereParts[] = "o.payment_status = ?";
    $params[] = $payFilter;
}
$where = " WHERE " . implode(' AND ', $whereParts);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON c.id = o.customer_id" . $where);
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$totalPages = ceil($total / ITEMS_PER_PAGE);

$q = $pdo->prepare("SELECT o.*, $ctypeCase AS order_ctype FROM orders o LEFT JOIN customers c ON c.id = o.customer_id" . $where . " ORDER BY o.created_at DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset");
$q->execute($params);
$orders = $q->fetchAll();

/* اقلام هر سفارش این صفحه، یک‌جا (نه یکی‌یکی داخل حلقه) */
$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $itq = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($in)");
    $itq->execute($ids);
    foreach ($itq->fetchAll() as $it) {
        $itemsByOrder[(int)$it['order_id']][] = $it;
    }
}

/* شمارش‌های خلاصهٔ پرداخت، فقط داخل همین دسته (نه کل orders) */
$payCounts = ['all' => 0, 'paid' => 0, 'unpaid' => 0, 'pending' => 0, 'failed' => 0];
$paidSum = 0;
if ($payOn) {
    try {
        $ctQ = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE $ctypeCase = ?");
        $ctQ->execute([$ctypeFilter]);
        $payCounts['all'] = (int)$ctQ->fetchColumn();
        $psQ = $pdo->prepare("SELECT o.payment_status, COUNT(*) n FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE $ctypeCase = ? GROUP BY o.payment_status");
        $psQ->execute([$ctypeFilter]);
        foreach ($psQ as $r) { $payCounts[$r['payment_status']] = (int)$r['n']; }
        $sumQ = $pdo->prepare("SELECT COALESCE(SUM(o.paid_amount),0) FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE $ctypeCase = ? AND o.payment_status='paid'");
        $sumQ->execute([$ctypeFilter]);
        $paidSum = (float)$sumQ->fetchColumn();
    } catch (Throwable $e) {}
}

$statusLabels = orderStatusLabels();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت سفارشات - <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=64">
    <?php /* این صفحه از layout-top.php استفاده نمی‌کند (نمای مستقل قدیمی‌تر)، پس
            کلاس‌های اینجا خودشان تعریف می‌شوند. */ ?>
    <style>
    /* ---------- ردیف‌های بازشو ----------
       هر سفارش چند کلید کوچک روی سطرش دارد؛ هر کلید یک ردیف زیرش را
       باز/بسته می‌کند. سفارشی که پرداختش «پرداخت‌شده» باشد، کل سطرش سبز
       می‌شود — دقیقا همان رنگ سطر تسویه‌شدهٔ admin/partner-settlements.php.
       شمارهٔ سفارش تازه (۱۴ کاراکتر: ۵رقمی-تاریخ‌شمسی۸رقمی) از فرمت قبلی
       بلندتر است؛ ستون شماره پهن‌تر شد تا کامل و بدون سرریز دیده شود. */
    .orders-table .ot-no{width:158px !important;letter-spacing:0.02em;}
    .ord-row.is-paid{background:rgba(34,197,94,0.14) !important;}
    /* شبکهٔ سه‌ستونه (خواستهٔ کاربر: «سه‌تا زیر هم، سه‌تای دیگه هم زیر اون سه‌تای
       بالایی، دقیقا هم‌تراز») — با ۶ کلید، خودکار دو ردیف سه‌تایی می‌سازد و چون
       هر ستون ۱fr است، «فاکتور» هم دقیقا هم‌عرض سه کلید ردیف بالاست. */
    .ord-actions-row{display:grid;grid-template-columns:repeat(3, 1fr);gap:0.4rem;}
    .ord-actions-row .btn{width:100%;min-width:0;justify-content:center;text-align:center;}
    .ord-tgl.is-active{background:var(--red-primary);color:#fff;border-color:var(--red-primary);}
    /* «فاکتور» یک اقدام متفاوت است (باز کردن/چاپ فاکتور، نه باز/بسته‌کردن یک
       کادر) — رنگ آبی کم‌رنگ همین را نشان می‌دهد، جدا از خاکستری بقیهٔ کلیدها. */
    .ord-invoice-btn{background:rgba(59,130,246,0.12) !important;color:#3B82F6 !important;border-color:rgba(59,130,246,0.35) !important;}
    .ord-invoice-btn:hover{background:rgba(59,130,246,0.2) !important;}
    .ord-panel td{background:rgba(255,255,255,0.02);padding:1rem 1.25rem;border-top:1px dashed var(--border-color);}
    .ord-panel .od-row{display:flex;gap:0.75rem;padding:0.4rem 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;line-height:1.75;}
    .ord-panel .od-row:last-child{border-bottom:none;}
    .ord-panel .od-row .od-l{flex:0 0 100px;color:var(--text-muted);font-size:0.76rem;padding-top:0.15rem;}
    .ord-panel .od-row .od-v{flex:1;color:var(--text-secondary);min-width:0;}
    .ord-panel .od-row .od-v b{color:var(--text-primary);font-weight:600;}
    .ord-panel .od-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;}
    @media(max-width:860px){.ord-panel .od-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body style="background:var(--bg-primary);min-height:100vh;">
    <div class="admin-layout admin-layout--wide">
        <div class="admin-header">
            <h2 style="color:var(--text-primary);">
                مدیریت سفارشات
                <span style="color:var(--text-muted);font-size:0.85rem;font-weight:400;">— <?= h($ctypeLabels[$ctypeFilter]) ?></span>
            </h2>
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
        <?php if ($trackError !== ''): ?>
        <div class="flash flash-error" style="margin-bottom:1rem;">خطا در ثبت روند ارسال: <?= h($trackError) ?></div>
        <?php endif; ?>

        <?php if (!$payOn): ?>
        <div class="flash flash-error" style="margin-bottom:1rem;">
            ستون‌های پرداخت ساخته نشده‌اند. فایل <code dir="ltr">migrate-payments.php</code> را یک‌بار در مرورگر باز کنید.
        </div>
        <?php else: ?>
        <?php $ctypeQs = 'ctype=' . urlencode($ctypeFilter) . '&'; ?>
        <div class="cust-tabs" style="margin-bottom:1rem;">
            <a href="?<?= $ctypeQs ?>" class="cust-tab <?= $payFilter === '' ? 'active' : '' ?>">همه <span class="cust-tab-n"><?= $payCounts['all'] ?></span></a>
            <a href="?<?= $ctypeQs ?>pay=paid" class="cust-tab <?= $payFilter === 'paid' ? 'active' : '' ?>">پرداخت‌شده <span class="cust-tab-n"><?= (int)($payCounts['paid'] ?? 0) ?></span></a>
            <a href="?<?= $ctypeQs ?>pay=unpaid" class="cust-tab <?= $payFilter === 'unpaid' ? 'active' : '' ?>">پرداخت‌نشده <span class="cust-tab-n"><?= (int)($payCounts['unpaid'] ?? 0) ?></span></a>
            <a href="?<?= $ctypeQs ?>pay=pending" class="cust-tab <?= $payFilter === 'pending' ? 'active' : '' ?>">در انتظار پرداخت <span class="cust-tab-n"><?= (int)($payCounts['pending'] ?? 0) ?></span></a>
            <a href="?<?= $ctypeQs ?>pay=failed" class="cust-tab <?= $payFilter === 'failed' ? 'active' : '' ?>">ناموفق <span class="cust-tab-n"><?= (int)($payCounts['failed'] ?? 0) ?></span></a>
            <span style="margin-right:auto;color:var(--text-muted);font-size:0.8rem;">جمع دریافتی: <b style="color:#4ADE80;"><?= formatPrice($paidSum) ?></b></span>
        </div>
        <?php endif; ?>

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
                <?php $colspan = 7 + ($payOn ? 1 : 0) + ($shipOn ? 1 : 0); ?>
                <?php foreach ($orders as $o): $oid = (int)$o['id']; ?>
                <tr id="ord-row-<?= $oid ?>" class="ord-row<?= ($payOn && ($o['payment_status'] ?? '') === 'paid') ? ' is-paid' : '' ?>">
                    <td class="ot-no" dir="ltr" title="شناسهٔ داخلی: #<?= $oid ?>"><?= h(orderNumber($o)) ?></td>
                    <?php /* نشان نوع مشتری (همکار/مشتری/بدون‌ثبت‌نام) از خود سطر برداشته شد —
                            خواستهٔ کاربر: چون دیگر نمای «همه سفارشات» نداریم، عنوان بالای صفحه
                            از قبل همین دسته را می‌گوید، تکرارش روی هر سطر زائد است. */ ?>
                    <td class="ot-cust"><?= h($o['customer_name']) ?></td>
                    <td class="ot-mobile" dir="ltr"><?= h($o['customer_mobile']) ?></td>
                    <td class="ot-amount"><?= formatPrice($o['total_amount']) ?></td>
                    <td class="ot-status">
                        <span class="status-badge status-<?= $o['status'] ?>"><?= $statusLabels[$o['status']] ?></span>
                    </td>
                    <?php if ($payOn): ?>
                    <td class="ot-pay">
                        <?= paymentStatusBadgeFor((string)($o['payment_status'] ?? 'unpaid'), (string)($o['payment_method'] ?? 'cod')) ?>
                        <div style="color:var(--text-muted);font-size:0.7rem;margin-top:2px;"><?= h(paymentLabel((string)($o['payment_method'] ?? 'cod'))) ?></div>
                        <?php if ($c2cOn && paymentC2cAwaiting($o)): ?>
                        <div style="color:#FBBF24;font-size:0.7rem;margin-top:2px;"><?= icon('receipt', 'ic-sm') ?> واریز اعلام شد</div>
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
                        <form method="POST">
                            <input type="hidden" name="ship_order_id" value="<?= $oid ?>">
                            <select name="ship_method" class="form-control" style="font-size:0.76rem;padding:0.25rem 0.35rem;" onchange="this.form.submit()">
                                <option value="">— ثبت نشده —</option>
                                <?php foreach (shippingMethods() as $mk => $md): ?>
                                <option value="<?= h($mk) ?>" <?= $sm === $mk ? 'selected' : '' ?>><?= h($md['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php if ($sm !== ''): ?>
                        <div style="color:var(--text-muted);font-size:0.68rem;margin-top:2px;"><?= h(shippingCostText((int)($o['shipping_cost'] ?? 0), $sm)) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="ot-date" dir="ltr"><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?></td>
                    <td class="ot-actions">
                        <div class="ord-actions-row">
                            <button type="button" class="btn btn-secondary btn-sm ord-tgl" data-row="<?= $oid ?>" data-target="ord-p-info-<?= $oid ?>"><?= icon('info', 'ic-sm') ?> اطلاعات</button>
                            <button type="button" class="btn btn-secondary btn-sm ord-tgl" data-row="<?= $oid ?>" data-target="ord-p-status-<?= $oid ?>"><?= icon('clipboard-list', 'ic-sm') ?> وضعیت سفارش</button>
                            <?php if ($trackOn): ?>
                            <button type="button" class="btn btn-secondary btn-sm ord-tgl" data-row="<?= $oid ?>" data-target="ord-p-track-<?= $oid ?>"><?= icon('truck', 'ic-sm') ?> روند ارسال</button>
                            <?php endif; ?>
                            <?php if ($payOn): ?>
                            <button type="button" class="btn btn-secondary btn-sm ord-tgl" data-row="<?= $oid ?>" data-target="ord-p-pay-<?= $oid ?>"><?= icon('credit-card', 'ic-sm') ?> پرداخت</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary btn-sm ord-tgl" data-row="<?= $oid ?>" data-target="ord-p-items-<?= $oid ?>"><?= icon('package', 'ic-sm') ?> اقلام سفارش</button>
                            <a href="invoice.php?id=<?= $oid ?>" class="btn btn-secondary btn-sm ord-invoice-btn" target="_blank" title="مشاهده و چاپ فاکتور این سفارش"><?= icon('receipt', 'ic-sm') ?> فاکتور</a>
                        </div>
                    </td>
                </tr>

                <?php /* ---------- کادر «اطلاعات» ---------- */ ?>
                <tr class="ord-panel" data-row="<?= $oid ?>" id="ord-p-info-<?= $oid ?>" <?= ($openRow === $oid && $openPanel === 'info') ? '' : 'hidden' ?>>
                <td colspan="<?= $colspan ?>">
                    <div class="od-row"><span class="od-l">نام</span><span class="od-v"><b><?= h($o['customer_name']) ?></b></span></div>
                    <div class="od-row"><span class="od-l">موبایل</span><span class="od-v" dir="ltr" style="text-align:right;"><?= h($o['customer_mobile']) ?></span></div>
                    <div class="od-row"><span class="od-l">آدرس</span><span class="od-v"><?= nl2br(h($o['customer_address'])) ?></span></div>
                    <?php if ($o['notes']): ?>
                    <div class="od-row"><span class="od-l">توضیحات</span><span class="od-v"><?= nl2br(h($o['notes'])) ?></span></div>
                    <?php endif; ?>
                    <div class="od-row"><span class="od-l">تاریخ ثبت</span><span class="od-v"><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?> (<?= h(jDate($o['created_at'], true)) ?>)</span></div>
                    <?php $pchk = partCheckForOrder($oid); ?>
                    <?php if ($pchk): $pcSs = partCheckStockStatus($pchk); ?>
                    <div class="od-row"><span class="od-l">بررسی عکس/موجودی</span><span class="od-v">
                        مطابقت قطعه: <b><?= h(partCheckStatusLabel((string)$pchk['status'])) ?></b> —
                        موجودی: <b><?= h(partCheckStatusLabel($pcSs)) ?></b>
                        <a href="order-detail.php?id=<?= $oid ?>" style="margin-right:0.5rem;font-size:0.76rem;">جزئیات کامل بررسی</a>
                    </span></div>
                    <?php endif; ?>
                </td>
                </tr>

                <?php /* ---------- کادر «وضعیت سفارش» ---------- */ ?>
                <tr class="ord-panel" data-row="<?= $oid ?>" id="ord-p-status-<?= $oid ?>" <?= ($openRow === $oid && $openPanel === 'status') ? '' : 'hidden' ?>>
                <td colspan="<?= $colspan ?>">
                    <form method="POST" style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                        <label for="new_status-<?= $oid ?>" style="font-size:0.82rem;color:var(--text-muted);">تغییر وضعیت:</label>
                        <select name="new_status" id="new_status-<?= $oid ?>" class="form-control" style="max-width:220px;">
                            <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $o['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">ذخیرهٔ وضعیت</button>
                    </form>
                </td>
                </tr>

                <?php /* ---------- کادر «روند ارسال» ---------- */ ?>
                <?php if ($trackOn):
                    $trackVisible = orderTrackStepsVisible($o);
                ?>
                <tr class="ord-panel" data-row="<?= $oid ?>" id="ord-p-track-<?= $oid ?>" <?= ($openRow === $oid && $openPanel === 'track') ? '' : 'hidden' ?>>
                <td colspan="<?= $colspan ?>">
                    <div class="od-grid">
                        <form method="POST">
                            <input type="hidden" name="track_save" value="1">
                            <input type="hidden" name="track_order_id" value="<?= $oid ?>">
                            <div class="track-admin-list">
                                <?php foreach ($trackDefs as $col => $def):
                                    $at = $o[$col] ?? null;
                                    $adminOnly = !isset($trackVisible[$col]);
                                    $revealId = '';
                                    if (!empty($def['code'])) $revealId = 'trk-code-' . $oid;
                                    elseif ($col === 'track_courier_at') $revealId = 'trk-courier-' . $oid;
                                ?>
                                <label class="track-admin-row<?= !empty($at) ? ' is-done' : '' ?>">
                                    <input type="checkbox" name="track[<?= h($col) ?>]" value="1" <?= !empty($at) ? 'checked' : '' ?><?= $revealId !== '' ? ' data-reveal="' . $revealId . '"' : '' ?>>
                                    <span class="track-admin-ic"><?= icon($def['icon'], 'ic-sm') ?></span>
                                    <span class="track-admin-label"><?= h($def['label']) ?></span>
                                    <?php if ($adminOnly): ?>
                                    <span style="flex:0 0 auto;font-size:0.62rem;padding:0.1rem 0.32rem;border-radius:var(--radius-sm);background:rgba(234,179,8,0.14);color:#FBBF24;border:1px solid rgba(234,179,8,0.35);white-space:nowrap;">فقط ادمین</span>
                                    <?php endif; ?>
                                    <span class="track-admin-time"><?= !empty($at) ? h(jDate($at, true)) : '—' ?></span>
                                </label>

                                <?php if (!empty($def['code'])): ?>
                                <div class="track-reveal" id="trk-code-<?= $oid ?>"<?= empty($at) ? ' hidden' : '' ?>>
                                    <label for="post_tracking_code-<?= $oid ?>"><?= icon('receipt', 'ic-sm') ?> کد رهگیری مرسولهٔ پست</label>
                                    <input type="text" name="post_tracking_code" id="post_tracking_code-<?= $oid ?>" class="form-control" dir="ltr" inputmode="numeric" autocomplete="off"
                                           value="<?= h($o[$def['code']] ?? '') ?>" placeholder="مثلا 123456789012345678">
                                </div>
                                <?php endif; ?>

                                <?php if ($col === 'track_courier_at'): ?>
                                <div class="track-reveal" id="trk-courier-<?= $oid ?>"<?= empty($at) ? ' hidden' : '' ?>>
                                    <label><?= icon('user', 'ic-sm') ?> مشخصات پیک</label>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
                                        <div>
                                            <label style="display:block;font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem;">نام و نام خانوادگی پیک</label>
                                            <input type="text" name="courier_name" class="form-control" style="font-family:inherit;" value="<?= h($o['courier_name'] ?? '') ?>" placeholder="مثلا علی رضایی">
                                        </div>
                                        <div>
                                            <label style="display:block;font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem;">شمارهٔ تماس پیک</label>
                                            <input type="text" name="courier_phone" class="form-control" dir="ltr" inputmode="numeric" value="<?= h($o['courier_phone'] ?? '') ?>" placeholder="09xxxxxxxxx">
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="margin-top:0.9rem;">ذخیرهٔ روند ارسال</button>
                        </form>
                        <div>
                            <div class="od-sub-hd" style="font-size:0.82rem;color:var(--text-muted);margin-bottom:0.6rem;"><?= icon('external', 'ic-sm') ?> پیش‌نمایش آنچه مشتری می‌بیند</div>
                            <?= renderOrderTimeline($o) ?>
                        </div>
                    </div>
                </td>
                </tr>
                <?php endif; ?>

                <?php /* ---------- کادر «پرداخت» ---------- */ ?>
                <?php if ($payOn):
                    $payMethod = (string)($o['payment_method'] ?? 'cod');
                    $payStatus = (string)($o['payment_status'] ?? 'unpaid');
                ?>
                <tr class="ord-panel" data-row="<?= $oid ?>" id="ord-p-pay-<?= $oid ?>" <?= ($openRow === $oid && $openPanel === 'pay') ? '' : 'hidden' ?>>
                <td colspan="<?= $colspan ?>">
                    <div class="od-grid">
                        <div>
                            <div class="od-row"><span class="od-l">روش پرداخت</span><span class="od-v"><?= icon(paymentIcon($payMethod), 'ic-sm') ?> <?= h(paymentLabel($payMethod)) ?></span></div>
                            <div class="od-row"><span class="od-l">وضعیت پرداخت</span><span class="od-v"><?= paymentStatusBadgeForOrder($o) ?></span></div>
                            <?php if ((int)($o['paid_amount'] ?? 0) > 0): ?>
                            <div class="od-row"><span class="od-l">مبلغ پرداخت‌شده</span><span class="od-v"><?= formatPrice($o['paid_amount']) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($o['payment_ref'])): ?>
                            <div class="od-row"><span class="od-l">شمارهٔ پیگیری</span><span class="od-v" dir="ltr" style="text-align:right;"><?= h($o['payment_ref']) ?></span></div>
                            <?php endif; ?>

                            <?php if ($c2cOn && $payMethod === 'card' && trim((string)($o['c2c_ref'] ?? '')) !== ''): ?>
                            <div class="od-row"><span class="od-l">واریز مشتری</span><span class="od-v">
                                شناسه: <span dir="ltr"><?= h((string)$o['c2c_ref']) ?></span> —
                                مبلغ: <?= formatPrice((int)($o['c2c_amount'] ?? 0)) ?> —
                                ۴ رقم آخر کارت: <span dir="ltr"><?= h((string)($o['c2c_last4'] ?? '')) ?></span>
                            </span></div>
                            <?php if (paymentC2cAwaiting($o)): ?>
                            <form method="POST" style="margin-top:0.5rem;">
                                <input type="hidden" name="c2c_verify_id" value="<?= $oid ?>">
                                <button type="submit" class="btn btn-primary btn-sm" data-confirm="واریز سفارش #<?= $oid ?> تأیید شود؟ پیش از تأیید، واریز را در حساب بانکی بررسی کنید." data-confirm-icon="check" data-confirm-label="تأیید شود" data-confirm-tone="primary">تأیید واریز و ثبت پرداخت</button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($chqOn && $payMethod === 'cheque' && trim((string)($o['cheque_number'] ?? '')) !== ''): ?>
                            <div class="od-row"><span class="od-l">چک</span><span class="od-v">
                                بانک: <?= h((string)$o['cheque_bank']) ?> — سریال: <span dir="ltr"><?= h((string)$o['cheque_number']) ?></span> —
                                مبلغ: <?= formatPrice((int)($o['cheque_amount'] ?? 0)) ?>
                            </span></div>
                            <?php if (!empty($o['cheque_received_at'])): ?>
                            <p style="margin:0;color:var(--green);font-size:0.82rem;"><?= icon('check-circle', 'ic-sm') ?> چک دریافت شد — <?= date('Y/m/d H:i', strtotime($o['cheque_received_at'])) ?></p>
                            <?php else: ?>
                            <form method="POST" style="margin-top:0.5rem;">
                                <input type="hidden" name="cheque_receive_id" value="<?= $oid ?>">
                                <button type="submit" class="btn btn-primary btn-sm" data-confirm="دریافت چک سفارش #<?= $oid ?> ثبت شود؟" data-confirm-icon="check" data-confirm-label="ثبت شود" data-confirm-tone="primary">ثبت دریافت چک</button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <form method="POST">
                                <input type="hidden" name="pay_order_id" value="<?= $oid ?>">
                                <div class="form-group">
                                    <label>تغییر دستی وضعیت پرداخت:</label>
                                    <select name="pay_new_status" class="form-control">
                                        <?php foreach (['unpaid', 'pending', 'paid', 'failed', 'refunded'] as $ps): ?>
                                        <option value="<?= $ps ?>" <?= $payStatus === $ps ? 'selected' : '' ?>><?= h(paymentStatusLabel($ps)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">ثبت وضعیت پرداخت</button>
                            </form>
                        </div>
                    </div>
                </td>
                </tr>
                <?php endif; ?>

                <?php /* ---------- کادر «اقلام سفارش» ---------- */ ?>
                <tr class="ord-panel" data-row="<?= $oid ?>" id="ord-p-items-<?= $oid ?>" <?= ($openRow === $oid && $openPanel === 'items') ? '' : 'hidden' ?>>
                <td colspan="<?= $colspan ?>">
                    <table class="admin-table" style="margin:0;">
                        <thead><tr><th>محصول</th><th>قیمت واحد</th><th>نوع قیمت</th><th>تعداد</th><th>جمع</th></tr></thead>
                        <tbody>
                            <?php foreach (($itemsByOrder[$oid] ?? []) as $item): ?>
                            <tr>
                                <td><?= h($item['product_name']) ?></td>
                                <td><?= formatPrice($item['price']) ?></td>
                                <td><?= $item['price_type'] === 'wholesale' ? 'کلی' : 'جزئی' ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= formatPrice($item['subtotal']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php /* مبلغ کل، به‌جای یک سطر جدول جدا، همین‌جا در انتهای کادر —
                            خواستهٔ کاربر: «سطر کمتر بشه». */ ?>
                    <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.6rem;font-weight:bold;">
                        <span>مبلغ کل:</span>
                        <span style="color:var(--red-light);"><?= formatPrice($o['total_amount']) ?></span>
                    </div>
                </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                <tr><td colspan="<?= $colspan ?>" style="text-align:center;padding:2rem;color:var(--text-muted);">سفارشی ثبت نشده است.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php $qs = 'ctype=' . urlencode($ctypeFilter) . '&' . ($payFilter !== '' ? ('pay=' . urlencode($payFilter) . '&') : ''); ?>
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

    <?php /* باز/بسته‌کردن کادرهای هر سطر + بازکردن کادر مرحلهٔ کد رهگیری/مشخصات
            پیک با تیک‌خوردن مرحلهٔ خودشان — بدون این اسکریپت هم صفحه کار
            می‌کند (کادر باز‌شده از سرور، باز رندر شده است). */ ?>
    <script>
    (function () {
        /* هر ردیف یک کادر باز دارد، نه بیشتر — خواستهٔ کاربر: «روی هر کلیدی
           که کلیک می‌کنم، کلید قبل از کلیک را ببند». کلیک روی همان کلید
           باز، فقط می‌بندد (بدون باز کردن دوباره). */
        document.querySelectorAll('.ord-tgl').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var t = document.getElementById(btn.getAttribute('data-target'));
                if (!t) return;
                var row = btn.getAttribute('data-row');
                var wasOpen = !t.hidden;
                document.querySelectorAll('.ord-panel[data-row="' + row + '"]').forEach(function (p) {
                    p.hidden = true;
                });
                document.querySelectorAll('.ord-tgl[data-row="' + row + '"]').forEach(function (b) {
                    b.classList.remove('is-active');
                });
                if (!wasOpen) {
                    t.hidden = false;
                    btn.classList.add('is-active');
                }
            });
        });
        document.querySelectorAll('.ord-panel').forEach(function (p) {
            if (!p.hidden) {
                var btn = document.querySelector('.ord-tgl[data-target="' + p.id + '"]');
                if (btn) btn.classList.add('is-active');
            }
        });
        var openRow = document.getElementById('ord-row-<?= (int)$openRow ?>');
        <?php if ($openRow > 0): ?>
        if (openRow) openRow.scrollIntoView({ block: 'center' });
        <?php endif; ?>

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
</body>
</html>
