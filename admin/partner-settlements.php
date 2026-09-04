<?php
/* پیگیری تسویهٔ همکاران — یک گزارش مجزا: کدام همکار، چند فاکتور بی‌تسویه
   دارد، جمع بدهی‌اش چقدر است، و قرار است با چه روشی تسویه کند (چک، اول ماه،
   کارت‌به‌کارت در انتظار تأیید، پرداخت در محل، یا آنلاین ناموفق).
   ---------------------------------------------------------------------
   تب‌بندی (خواستهٔ کاربر): بالای صفحه یک ردیف تب — «همه روش‌ها» + یک تب به
   ازای هر روشی که واقعا بدهی‌ای با آن باقی مانده. دو تب «پرداخت اول ماه» و
   «چک» محتوای ویژهٔ خودشان را دارند:
     • اول ماه: نوار شمارش‌معکوس خودکار تا پایان ماه شمسی جاری + برچسب
       «عقب‌افتاده» روی فاکتورهایی که از ماه قبل مانده‌اند.
     • چک: سه دستهٔ جدا — «باید چک بفرستند»، «چک ارسال شده، منتظر دریافت»
       و «چک دریافت شد» — با جزئیات کامل چک (بانک/سریال/تاریخ/مبلغ/در
       وجه/شناسهٔ صیاد) و دکمهٔ سریع «ثبت دریافت چک» همین‌جا.
   بقیهٔ روش‌ها (پرداخت در محل، کارت‌به‌کارت، درگاه‌های آنلاین) همان فهرست
   تفکیکی‌شدهٔ اصلی را می‌بینند، فقط فیلترشده روی همان یک روش. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$payOn     = paymentReady();
$hasPnrCol = dbHasColumn('customers', 'customer_type');

/* وضعیت پیگیری هر روش تسویه (نقدی/اول ماه/چک) — جدا از payment_status خود
   سفارش‌ها؛ این یک یادداشت ساده برای خود ادمین است: «این روش را همین الان
   پیگیری کردم / تمام شد» یا «هنوز در حال پیگیری‌ام»، تا هر روش کلید سبز
   خودش را داشته باشد. در settings ذخیره می‌شود، کلیدهای pset_status_<method>؛
   مقدار 'done' یعنی سبز، هرچیز دیگر یعنی «در حال پیگیری». */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pset_toggle_method'])) {
    $pmKey = preg_replace('/[^a-z_]/', '', (string)$_POST['pset_toggle_method']);
    if ($pmKey !== '') {
        $cur = getSettingRaw('pset_status_' . $pmKey, 'progress');
        setSetting('pset_status_' . $pmKey, $cur === 'done' ? 'progress' : 'done');
    }
    $backPm = preg_replace('/[^a-z_]/', '', (string)($_POST['pset_back_pm'] ?? 'all'));
    header('Location: partner-settlements.php?pm=' . ($backPm !== '' ? $backPm : 'all') . '#tsviyeh');
    exit;
}

/* دکمهٔ سریع «ثبت دریافت چک» — دقیقا همان کاری که در جزئیات سفارش انجام
   می‌شود (paymentChequeReceive)، فقط بدون نیاز به بازکردن هر سفارش جداگانه.
   وضعیت پرداخت را عوض نمی‌کند — فقط می‌گوید اصل چک رسیده است. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pset_cheque_receive'])) {
    $rcvId = (int)$_POST['pset_cheque_receive'];
    if ($rcvId > 0 && function_exists('paymentChequeReceive')) paymentChequeReceive($rcvId);
    header('Location: partner-settlements.php?pm=cheque#tsviyeh');
    exit;
}

/* چهار کلید سریع «تسویه شد» روی هر سفارش، مستقیم توی همان سطر «همکاران
   بدهکار» (خواستهٔ کاربر ۲۰۲۶-۰۹-۰۳) — نقدی/کارت‌به‌کارت/چک/اول ماه، صرف‌نظر
   از اینکه هنگام ثبت سفارش کدام روش انتخاب شده بود؛ بدهی همکار معمولا با
   هر روشی که جور شود تسویه می‌شود. هم‌الگوی paymentMarkPaid() که
   admin/orders.php برای «پرداخت شد» می‌زند، فقط یادداشتش می‌گوید با کدام
   روش. اگر با AJAX (fetch) صدا زده شود (pset_ajax=1) یک JSON کوچک برمی‌گرداند
   تا سطر بی‌نیاز از رفرش سبز شود؛ بدون جاوااسکریپت هم با PRG معمولی کار
   می‌کند (فرم <noscript> پایین‌تر). */
$psetSettleLabels = [
    'cash'   => 'پرداخت نقدی',
    'card'   => 'کارت به کارت',
    'cheque' => 'چک',
    'month'  => 'پرداخت اول ماه',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pset_settle_id'], $_POST['pset_settle_method'])) {
    $settleId  = (int)$_POST['pset_settle_id'];
    $settleKey = (string)$_POST['pset_settle_method'];
    $isAjax    = !empty($_POST['pset_ajax']);
    $settleOk  = false;
    if ($settleId > 0 && isset($psetSettleLabels[$settleKey])) {
        try {
            $so = $pdo->prepare("SELECT total_amount FROM orders WHERE id = ?");
            $so->execute([$settleId]);
            $so = $so->fetch();
            if ($so) {
                paymentMarkPaid($settleId, (int)$so['total_amount'], 'دستی-ادمین', '', false);
                $pdo->prepare("UPDATE orders SET payment_note = ? WHERE id = ?")
                    ->execute(['تسویهٔ دستی توسط مدیر: ' . $psetSettleLabels[$settleKey], $settleId]);
                /* اگر روش چک بود و هنوز «دریافت چک» ثبت نشده، همین‌جا هم ثبت شود —
                   یک کلیک به‌جای دو کلیک؛ روی سفارش‌های غیرچکی اثری ندارد. */
                if ($settleKey === 'cheque' && function_exists('paymentChequeReceive')) {
                    paymentChequeReceive($settleId);
                }
                paymentLog("ADMIN pset-settle order=$settleId method=$settleKey by=" . ($_SESSION['admin_username'] ?? 'admin'));
                $settleOk = true;
            }
        } catch (Throwable $e) {}
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $settleOk]);
        exit;
    }
    $backPm2 = preg_replace('/[^a-z_]/', '', (string)($_POST['pset_back_pm'] ?? 'all'));
    header('Location: partner-settlements.php?pm=' . ($backPm2 !== '' ? $backPm2 : 'all') . '#tsviyeh');
    exit;
}

$groups = [];      // customer_id => ['customer' => row, 'orders' => [...], 'debt' => int]
$grandDebt = 0;
$methodTotals = []; // payment_method => ['count'=>n, 'debt'=>n]

if ($payOn && $hasPnrCol) {
    try {
        $rows = $pdo->query("
            SELECT o.*, c.full_name, c.mobile AS cust_mobile, c.partner_company, c.partner_status
            FROM orders o
            JOIN customers c ON c.id = o.customer_id
            WHERE c.customer_type = 'partner'
              AND o.status <> 'cancelled'
              AND o.payment_status IN ('unpaid', 'pending', 'failed')
            ORDER BY c.full_name, o.created_at DESC
        ")->fetchAll();
    } catch (Throwable $e) { $rows = []; }

    foreach ($rows as $o) {
        $cid = (int)$o['customer_id'];
        $debt = max(0, (int)$o['total_amount'] - (int)($o['paid_amount'] ?? 0));
        if (!isset($groups[$cid])) {
            $groups[$cid] = [
                'customer' => [
                    'id' => $cid, 'full_name' => $o['full_name'], 'mobile' => $o['cust_mobile'],
                    'partner_company' => $o['partner_company'], 'partner_status' => $o['partner_status'],
                ],
                'orders' => [], 'debt' => 0,
            ];
        }
        $groups[$cid]['orders'][] = $o;
        $groups[$cid]['debt'] += $debt;
        $grandDebt += $debt;

        $pm = (string)($o['payment_method'] ?? 'cod');
        if (!isset($methodTotals[$pm])) $methodTotals[$pm] = ['count' => 0, 'debt' => 0];
        $methodTotals[$pm]['count']++;
        $methodTotals[$pm]['debt'] += $debt;
    }
    /* بدهکارترین همکار اول دیده شود */
    uasort($groups, function ($a, $b) { return $b['debt'] <=> $a['debt']; });
}

/* ---------- تب فعال ---------- */
$activePm = (string)($_GET['pm'] ?? 'all');
if ($activePm !== 'all' && !isset($methodTotals[$activePm])) $activePm = 'all';

/* ترتیب تب‌ها: «اول ماه» و «چک» بلافاصله بعد «همه» (خواستهٔ کاربر)، بقیه به
   ترتیب بدهی نزولی. */
$tabKeys = array_keys($methodTotals);
usort($tabKeys, function ($a, $b) {
    $priority = ['partner_month' => 0, 'cheque' => 1];
    $pa = $priority[$a] ?? 99;
    $pb = $priority[$b] ?? 99;
    if ($pa !== $pb) return $pa <=> $pb;
    global $methodTotals;
    return $methodTotals[$b]['debt'] <=> $methodTotals[$a]['debt'];
});

/* گروه‌های فیلترشده روی روش فعال — همان ساختار $groups، فقط با نگه‌داشتن
   فقط فاکتورهایی که روش‌شان با تب انتخاب‌شده یکی است (و بدهی هر همکار هم
   دوباره فقط از همان فاکتورها جمع می‌زند). */
function psetFilterGroups($groups, $pm) {
    if ($pm === 'all') return $groups;
    $out = [];
    foreach ($groups as $cid => $g) {
        $orders = array_values(array_filter($g['orders'], function ($o) use ($pm) {
            return (string)($o['payment_method'] ?? 'cod') === $pm;
        }));
        if (!$orders) continue;
        $debt = 0;
        foreach ($orders as $o) $debt += max(0, (int)$o['total_amount'] - (int)($o['paid_amount'] ?? 0));
        $out[$cid] = ['customer' => $g['customer'], 'orders' => $orders, 'debt' => $debt];
    }
    uasort($out, function ($a, $b) { return $b['debt'] <=> $a['debt']; });
    return $out;
}
$viewGroups = psetFilterGroups($groups, $activePm);

/* ---------- محتوای ویژهٔ «پرداخت اول ماه» ----------
   شمارش‌معکوس خودکار تا پایان ماه شمسی جاری — همه فاکتورهای همین ماه یک
   مهلت مشترک دارند. فاکتورهایی که از ماه قبل مانده‌اند «عقب‌افتاده»اند،
   چون مهلت تسویه‌شان (پایان همان ماه) از قبل گذشته است. */
$pmToday = null; $pmDaysLeft = null; $pmMonthLabel = '';
if ($activePm === 'partner_month') {
    $pmToday = jalaliToday();
    $pmDaysInMonth = jalaliMonthDays($pmToday[0], $pmToday[1]);
    $pmDaysLeft = $pmDaysInMonth - $pmToday[2];
    $pmMonthLabel = jalaliMonthName($pmToday[1]) . ' ' . $pmToday[0];
}

/* ---------- محتوای ویژهٔ «چک» ----------
   سه دستهٔ جدا از روی داده‌های ثبت‌شدهٔ چک هر سفارش (checkout.php آن‌ها را
   می‌گیرد، admin/order-detail.php تک‌تک نشان می‌دهد — اینجا همه‌شان یک‌جا و
   دسته‌بندی‌شده‌اند). */
$chqSendList = []; $chqWaitList = []; $chqDoneList = [];
$chqReady2 = function_exists('paymentChequeReady') && paymentChequeReady();
if ($activePm === 'cheque' && $chqReady2) {
    foreach ($viewGroups as $g) {
        foreach ($g['orders'] as $o) {
            $entry = ['customer' => $g['customer'], 'order' => $o];
            if (!empty($o['cheque_received_at'])) {
                $chqDoneList[] = $entry;
            } elseif (trim((string)($o['cheque_number'] ?? '')) !== '') {
                $chqWaitList[] = $entry;
            } else {
                $chqSendList[] = $entry;
            }
        }
    }
}

/* متن مهلت فیزیکی چک — از تاریخ ثبت چک (یا تاریخ سفارش، اگر هنوز ثبت
   نشده) + عدد روزهای مهلت تنظیم‌شده در پنل (پرداخت ← چک). */
function psetChequeDeadline($baseDateStr) {
    $days = function_exists('paymentChequeDeadlineDays') ? paymentChequeDeadlineDays() : 10;
    $baseTs = strtotime((string)$baseDateStr);
    if (!$baseTs) return ['text' => '', 'over' => false];
    $deadlineTs = strtotime('+' . (int)$days . ' days', $baseTs);
    $diffDays = (int)ceil(($deadlineTs - time()) / 86400);
    if ($diffDays > 0)  return ['text' => $diffDays . ' روز تا مهلت',        'over' => false];
    if ($diffDays === 0) return ['text' => 'امروز آخرین مهلت است',           'over' => false];
    return ['text' => 'مهلت ' . abs($diffDays) . ' روز پیش گذشت', 'over' => true];
}

require_once __DIR__ . '/layout-top.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
    <h2 style="font-size:1rem;color:var(--text-primary);"><?= icon('scale', 'ic-sm') ?> پیگیری تسویهٔ همکاران</h2>
    <a href="customers.php?type=partner" class="btn btn-secondary btn-sm">فهرست همکاران</a>
</div>

<?php if (!$payOn || !$hasPnrCol): ?>
<div class="flash flash-error" style="margin-bottom:1rem;">
    <?= icon('alert', 'ic-sm') ?>
    <?= !$payOn ? 'ستون‌های پرداخت هنوز ساخته نشده‌اند.' : 'ستون‌های حساب همکار هنوز ساخته نشده‌اند.' ?>
</div>
<?php else: ?>

<p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1.25rem;">
    این صفحه فقط سفارش‌های همکاران را نشان می‌دهد که هنوز <b>پرداخت‌نشده</b>، <b>در انتظار</b> یا <b>ناموفق</b> هستند
    (سفارش‌های لغوشده یا پرداخت‌شده اینجا نمی‌آیند). با تب‌های زیر، فهرست را روی یک روش تسویهٔ خاص فیلتر کنید —
    «پرداخت اول ماه» مهلت خودکار پایان ماه را نشان می‌دهد و «چک» همکاران را بر اساس اینکه چک را فرستاده‌اند یا نه دسته‌بندی می‌کند.
</p>

<div class="dash-cards" style="margin-bottom:1.5rem;">
    <div class="dc dc-red">
        <span class="dc-lbl">جمع کل بدهی همکاران</span>
        <span class="dc-val"><?= formatPrice($grandDebt) ?></span>
    </div>
    <div class="dc dc-orange">
        <span class="dc-lbl">همکاران بدهکار</span>
        <span class="dc-val"><?= number_format(count($groups)) ?> نفر</span>
    </div>
    <div class="dc dc-gold">
        <span class="dc-lbl">فاکتورهای بی‌تسویه</span>
        <span class="dc-val"><?= number_format(array_sum(array_map(function ($g) { return count($g['orders']); }, $groups))) ?> فاکتور</span>
    </div>
</div>

<?php if ($methodTotals): ?>
<div class="cust-tabs" id="tsviyeh">
    <a href="?pm=all#tsviyeh" class="cust-tab <?= $activePm === 'all' ? 'active' : '' ?>">
        <?= icon('layers', 'ic-sm') ?> همه روش‌ها <span class="cust-tab-n"><?= array_sum(array_column($methodTotals, 'count')) ?></span>
    </a>
    <?php foreach ($tabKeys as $pm): $mt = $methodTotals[$pm]; ?>
    <a href="?pm=<?= h($pm) ?>#tsviyeh" class="cust-tab <?= $activePm === $pm ? 'active' : '' ?>">
        <?= icon(paymentIcon($pm), 'ic-sm') ?> <?= h(paymentLabel($pm)) ?> <span class="cust-tab-n"><?= $mt['count'] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($activePm !== 'all'): $mt = $methodTotals[$activePm]; $pmDone = getSettingRaw('pset_status_' . $activePm, 'progress') === 'done'; ?>
<div class="dg-box" style="margin-bottom:1.5rem;<?= $pmDone ? 'border-color:rgba(34,197,94,0.35);' : '' ?>">
    <div class="dg-box-bd" style="padding:0.75rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;">
        <span style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;color:var(--text-secondary);">
            <?= icon(paymentIcon($activePm), 'ic-sm') ?> <b style="color:var(--text-primary);"><?= h(paymentLabel($activePm)) ?></b>
            <span style="color:var(--text-muted);font-size:0.72rem;">(<?= $mt['count'] ?> فاکتور از <?= count($viewGroups) ?> همکار)</span>
            <b style="margin-inline-start:0.25rem;color:var(--red-light);"><?= formatPrice($mt['debt']) ?></b>
        </span>
        <span style="display:flex;align-items:center;gap:0.5rem;">
            <span class="pay-badge <?= $pmDone ? 'pay-paid' : 'pay-pending' ?>">
                <?= icon($pmDone ? 'check-circle' : 'clock', 'ic-sm') ?> <?= $pmDone ? 'تسویه شد' : 'در حال پیگیری' ?>
            </span>
            <form method="POST" action="partner-settlements.php#tsviyeh">
                <input type="hidden" name="pset_toggle_method" value="<?= h($activePm) ?>">
                <input type="hidden" name="pset_back_pm" value="<?= h($activePm) ?>">
                <button type="submit" class="btn <?= $pmDone ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
                    <?= $pmDone ? 'بازگرداندن به در حال پیگیری' : 'ثبت تسویه‌شدن' ?>
                </button>
            </form>
        </span>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php /* ---------- بنر شمارش‌معکوس «پرداخت اول ماه» ---------- */ ?>
<?php if ($activePm === 'partner_month'): ?>
<div class="dg-box" style="margin-bottom:1.5rem;border-color:rgba(234,179,8,0.35);">
    <div class="dg-box-bd" style="padding:0.9rem 1.1rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
        <span style="font-size:1.4rem;line-height:1;color:#FBBF24;"><?= icon('calendar') ?></span>
        <span style="flex:1 1 260px;">
            <b style="color:var(--text-primary);font-size:0.9rem;">
                <?php if ($pmDaysLeft > 0): ?>
                تا پایان <?= h($pmMonthLabel) ?> <?= (int)$pmDaysLeft ?> روز مانده
                <?php else: ?>
                امروز آخرین روز <?= h($pmMonthLabel) ?> است
                <?php endif; ?>
            </b>
            <div style="color:var(--text-muted);font-size:0.75rem;margin-top:0.2rem;">
                مهلت تسویهٔ خریدهای همین ماه، پایان همین ماه است. فاکتورهایی که از ماه‌های قبل مانده‌اند، پایین با نشان «عقب‌افتاده» جدا شده‌اند.
            </div>
        </span>
    </div>
</div>
<?php endif; ?>

<?php /* ================================================================
        محتوای اصلی: برای «چک» سه جدول دسته‌بندی‌شده؛ برای بقیهٔ تب‌ها
        (همه/اول ماه/سایر روش‌ها) همان فهرست تفکیکی‌شدهٔ اکاردئونی.
        ================================================================ */ ?>

<?php if ($activePm === 'cheque' && $chqReady2): ?>

    <?php if (!$chqSendList && !$chqWaitList && !$chqDoneList): ?>
    <div class="no-results" style="padding:2rem 1rem;">
        <div class="no-results-icon"><?= icon('check-circle') ?></div>
        <p style="font-size:0.95rem;">هیچ فاکتور چکی باز نیست.</p>
    </div>
    <?php else: ?>

    <?php
    /* یک جدول دستهٔ چک — سه بار با پارامترهای متفاوت صدا زده می‌شود */
    function psetChequeTable($title, $iconName, $list, $tone, $showReceiveBtn, $activePm) {
        if (!$list) return;
        $debtSum = 0;
        foreach ($list as $e) $debtSum += max(0, (int)$e['order']['total_amount'] - (int)($e['order']['paid_amount'] ?? 0));
        ?>
        <div class="dg-box" style="margin-bottom:1.25rem;<?= $tone ?>">
            <div class="dg-box-hd"><div class="dg-hd-t"><?= icon($iconName, 'ic-sm') ?> <?= h($title) ?>
                <span style="color:var(--text-muted);font-weight:400;font-size:0.75rem;margin-inline-start:0.4rem;">(<?= count($list) ?> فاکتور — <?= formatPrice($debtSum) ?>)</span>
            </div></div>
            <div class="dg-box-bd" style="padding:0;overflow-x:auto;">
                <table class="admin-table" style="margin:0;min-width:720px;">
                    <thead>
                        <tr>
                            <th>همکار</th>
                            <th>سفارش</th>
                            <th>تاریخ سفارش</th>
                            <th>جزئیات چک</th>
                            <th>مهلت</th>
                            <th>مبلغ بدهی</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $e): $cu = $e['customer']; $o = $e['order'];
                            $odebt = max(0, (int)$o['total_amount'] - (int)($o['paid_amount'] ?? 0));
                            $baseDate = trim((string)($o['cheque_reported_at'] ?? '')) !== '' ? $o['cheque_reported_at'] : $o['created_at'];
                            $dl = psetChequeDeadline($baseDate);
                        ?>
                        <tr>
                            <td>
                                <b><?= h($cu['full_name'] ?: 'بدون نام') ?></b>
                                <div style="color:var(--text-muted);font-size:0.72rem;direction:ltr;"><?= h($cu['mobile']) ?></div>
                            </td>
                            <td dir="ltr"><?= h(orderNumber($o)) ?></td>
                            <td><?= h(jDate($o['created_at'], true)) ?></td>
                            <td style="font-size:0.75rem;color:var(--text-secondary);">
                                <?php if (trim((string)($o['cheque_bank'] ?? '')) !== ''): ?>
                                <div>بانک: <b><?= h($o['cheque_bank']) ?></b></div>
                                <?php endif; ?>
                                <?php if (trim((string)($o['cheque_number'] ?? '')) !== ''): ?>
                                <div>سریال: <b dir="ltr"><?= h($o['cheque_number']) ?></b></div>
                                <?php endif; ?>
                                <?php if (trim((string)($o['cheque_date'] ?? '')) !== ''): ?>
                                <div>تاریخ چک: <b><?= h($o['cheque_date']) ?></b></div>
                                <?php endif; ?>
                                <?php if ((int)($o['cheque_amount'] ?? 0) > 0): ?>
                                <div>مبلغ چک: <b><?= formatPrice((int)$o['cheque_amount']) ?></b></div>
                                <?php endif; ?>
                                <?php if (trim((string)($o['cheque_payee'] ?? '')) !== ''): ?>
                                <div>در وجه: <b><?= h($o['cheque_payee']) ?></b></div>
                                <?php endif; ?>
                                <?php if (trim((string)($o['cheque_sayad'] ?? '')) !== ''): ?>
                                <div>شناسهٔ صیاد: <b dir="ltr"><?= h($o['cheque_sayad']) ?></b></div>
                                <?php endif; ?>
                                <?php if (trim((string)($o['cheque_number'] ?? '')) === ''): ?>
                                <span style="color:var(--text-muted);">هنوز ثبت نشده</span>
                                <?php endif; ?>
                                <?php if (!empty($o['cheque_received_at'])): ?>
                                <div style="color:#4ADE80;margin-top:2px;"><?= icon('check-circle', 'ic-sm') ?> دریافت: <?= h(jDate($o['cheque_received_at'], true)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($o['cheque_received_at'])): ?>
                                <span style="color:#4ADE80;font-size:0.78rem;">رسیده</span>
                                <?php elseif ($dl['text'] !== ''): ?>
                                <span style="font-size:0.78rem;color:<?= $dl['over'] ? '#F87171' : 'var(--text-secondary)' ?>;"><?= h($dl['text']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><b><?= formatPrice($odebt) ?></b></td>
                            <td style="white-space:nowrap;">
                                <a href="order-detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm">جزئیات</a>
                                <?php if ($showReceiveBtn): ?>
                                <form method="POST" action="partner-settlements.php?pm=cheque#tsviyeh" style="display:inline;" onsubmit="return confirm('دریافت این چک ثبت شود؟');">
                                    <input type="hidden" name="pset_cheque_receive" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('check-circle', 'ic-sm') ?> ثبت دریافت</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    psetChequeTable('باید چک ارسال کنند', 'send', $chqSendList, 'border-color:rgba(239,68,68,0.3);', false, $activePm);
    psetChequeTable('چک ارسال شده — منتظر دریافت هستیم', 'clock', $chqWaitList, 'border-color:rgba(234,179,8,0.35);', true, $activePm);
    psetChequeTable('چک دریافت شد — در انتظار تکمیل وضعیت پرداخت', 'check-circle', $chqDoneList, 'border-color:rgba(34,197,94,0.3);', false, $activePm);
    ?>
    <?php endif; ?>

<?php elseif (!$viewGroups): ?>
<div class="no-results" style="padding:2rem 1rem;">
    <div class="no-results-icon"><?= icon('check-circle') ?></div>
    <p style="font-size:0.95rem;">هیچ همکاری در حال حاضر بدهی باز ندارد.</p>
</div>
<?php else: ?>

<div class="dg-box">
    <div class="dg-box-hd"><h3><?= icon('users', 'ic-sm') ?> همکاران بدهکار (<?= count($viewGroups) ?>)</h3></div>
    <div class="dg-box-bd" style="padding:0;">
        <?php foreach ($viewGroups as $g): $cu = $g['customer']; $pid = 'pset-' . (int)$cu['id']; ?>
        <div class="pset-row">
            <input type="checkbox" id="<?= $pid ?>" class="pset-toggle" hidden>
            <div class="pset-sum-wrap">
                <label for="<?= $pid ?>" class="pset-sum">
                    <span class="pset-name">
                        <?= icon('user', 'ic-sm') ?> <b><?= h($cu['full_name'] ?: 'بدون نام') ?></b>
                        <?php if (trim((string)($cu['partner_company'] ?? '')) !== ''): ?>
                        <span class="pset-company"><?= h($cu['partner_company']) ?></span>
                        <?php endif; ?>
                        <span class="badge-<?= $cu['partner_status'] === 'approved' ? 'partner' : 'pending' ?>"><?= $cu['partner_status'] === 'approved' ? 'همکار تأییدشده' : 'در انتظار تأیید' ?></span>
                    </span>
                    <span class="pset-mobile" dir="ltr"><?= h($cu['mobile']) ?></span>
                    <span class="pset-count"><?= count($g['orders']) ?> فاکتور</span>
                    <span class="pset-debt"><?= formatPrice($g['debt']) ?></span>
                    <span class="pset-caret"><?= icon('chevron-down', 'ic-sm') ?></span>
                </label>
                <a href="customer-detail.php?id=<?= (int)$cu['id'] ?>" class="btn btn-secondary btn-sm">پروندهٔ همکار</a>
            </div>

            <div class="pset-body">
            <table class="admin-table" style="margin:0 0 0.75rem;">
                <thead>
                    <tr>
                        <th>شماره سفارش</th>
                        <th>تاریخ</th>
                        <th>روش تسویه</th>
                        <?php if ($activePm === 'partner_month'): ?>
                        <th>مهلت تسویه</th>
                        <?php else: ?>
                        <th>وضعیت</th>
                        <?php endif; ?>
                        <th>مبلغ بدهی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($g['orders'] as $o):
                        $pm = (string)($o['payment_method'] ?? 'cod');
                        $ps = (string)($o['payment_status'] ?? 'unpaid');
                        $odebt = max(0, (int)$o['total_amount'] - (int)($o['paid_amount'] ?? 0));
                    ?>
                    <tr id="pset-ord-<?= (int)$o['id'] ?>">
                        <td dir="ltr"><?= h(orderNumber($o)) ?></td>
                        <td><?= h(jDate($o['created_at'], true)) ?></td>
                        <td><?= icon(paymentIcon($pm), 'ic-sm') ?> <?= h(paymentLabel($pm)) ?></td>
                        <?php if ($activePm === 'partner_month'):
                            $oj = gregorianToJalali(date('Y', strtotime($o['created_at'])), date('n', strtotime($o['created_at'])), date('j', strtotime($o['created_at'])));
                            $sameMonth = ($pmToday && $oj[0] === $pmToday[0] && $oj[1] === $pmToday[1]);
                        ?>
                        <td>
                            <?php if ($sameMonth): ?>
                            <span style="color:var(--text-secondary);font-size:0.78rem;"><?= (int)$pmDaysLeft ?> روز تا پایان ماه</span>
                            <?php else: ?>
                            <span style="color:#F87171;font-size:0.78rem;"><?= icon('alert', 'ic-sm') ?> عقب‌افتاده — <?= h(jalaliMonthName($oj[1])) ?> <?= $oj[0] ?></span>
                            <?php endif; ?>
                        </td>
                        <?php else: ?>
                        <td>
                            <?= paymentStatusBadgeFor($ps, $pm) ?>
                            <?php if ($pm === 'cheque' && function_exists('paymentChequeReady') && paymentChequeReady()): ?>
                                <?php if (!empty($o['cheque_received_at'])): ?>
                                <div style="color:#4ADE80;font-size:0.7rem;margin-top:2px;"><?= icon('check-circle', 'ic-sm') ?> چک دریافت شد</div>
                                <?php elseif (trim((string)($o['cheque_number'] ?? '')) !== ''): ?>
                                <div style="color:#FBBF24;font-size:0.7rem;margin-top:2px;"><?= icon('clock', 'ic-sm') ?> چک ثبت شد — دریافت نشده</div>
                                <?php else: ?>
                                <div style="color:var(--text-muted);font-size:0.7rem;margin-top:2px;">چک هنوز ثبت نشده</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><b><?= formatPrice($odebt) ?></b></td>
                        <td class="pset-acts" style="white-space:nowrap;">
                            <div style="display:flex;align-items:center;gap:0.3rem;flex-wrap:wrap;">
                                <a href="order-detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm">جزئیات</a>
                                <a href="invoice.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm" target="_blank"><?= icon('printer', 'ic-sm') ?></a>
                                <?php /* چهار کلید سریع «تسویه شد» (خواستهٔ کاربر ۲۰۲۶-۰۹-۰۳: «توی همون سطر
                                        هر شماره سفارش ... هر کدوم رو که زدم اون سطر رنگش سبز بشه») — فقط
                                        آیکون، بدون متن، تا سطر به‌هم نریزد؛ عنوان (title) هرکدام می‌گوید چیست.
                                        جاوااسکریپت پایین صفحه با fetch بی‌صدا صدا می‌زند و بدون رفرش، همین
                                        سطر (id="pset-ord-N") را سبز می‌کند؛ بدون جاوااسکریپت هم فرم <noscript>
                                        پایین‌تر همان کار را با PRG معمولی انجام می‌دهد. */ ?>
                                <span class="pset-settle-group" data-order="<?= (int)$o['id'] ?>" style="display:inline-flex;gap:0.2rem;padding-inline-start:0.3rem;border-inline-start:1px solid var(--border-color);">
                                    <button type="button" class="btn btn-secondary btn-sm pset-settle-btn" data-method="cash" title="پرداخت نقدی انجام شد"><?= icon('package', 'ic-sm') ?></button>
                                    <button type="button" class="btn btn-secondary btn-sm pset-settle-btn" data-method="card" title="کارت به کارت انجام شد"><?= icon('credit-card', 'ic-sm') ?></button>
                                    <button type="button" class="btn btn-secondary btn-sm pset-settle-btn" data-method="cheque" title="چک دریافت شد"><?= icon('receipt', 'ic-sm') ?></button>
                                    <button type="button" class="btn btn-secondary btn-sm pset-settle-btn" data-method="month" title="پرداخت اول ماه انجام شد"><?= icon('calendar', 'ic-sm') ?></button>
                                </span>
                                <noscript>
                                <span style="display:inline-flex;gap:0.2rem;">
                                    <?php foreach ($psetSettleLabels as $pmk => $pml): ?>
                                    <form method="POST" action="partner-settlements.php?pm=<?= h($activePm) ?>#tsviyeh" onsubmit="return confirm('<?= h($pml) ?> — این سفارش تسویه‌شده ثبت شود؟');" style="display:inline;">
                                        <input type="hidden" name="pset_settle_id" value="<?= (int)$o['id'] ?>">
                                        <input type="hidden" name="pset_settle_method" value="<?= h($pmk) ?>">
                                        <input type="hidden" name="pset_back_pm" value="<?= h($activePm) ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="<?= h($pml) ?>"><?= h(mb_substr($pml, 0, 1)) ?></button>
                                    </form>
                                    <?php endforeach; ?>
                                </span>
                                </noscript>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>
<?php endif; ?>

<style>
/* سطر تسویه‌شده — خواستهٔ کاربر: «هر کدوم رو که زدم اون سطر رنگش سبز بشه» */
.pset-order-settled{background:rgba(34,197,94,0.14) !important;transition:background 0.4s ease;}
.pset-order-settled .pset-settle-btn{display:none;}
.pset-settle-done{display:inline-flex;align-items:center;gap:0.25rem;color:#4ADE80;font-size:0.78rem;white-space:nowrap;}
</style>
<script>
/* چهار کلید سریع تسویهٔ سفارش، توی سطر خود «همکاران بدهکار» — بدون رفرش،
   با fetch؛ موفق که شد فقط همین سطر سبز می‌شود و دکمه‌ها جای خود را به یک
   نشان «تسویه شد» می‌دهند. خطای شبکه/نشست هم با یک پیام کوتاه گفته می‌شود،
   نه سکوت. */
(function () {
    var groups = document.querySelectorAll('.pset-settle-group');
    if (!groups.length) return;
    var LABELS = {
        cash: 'پرداخت نقدی', card: 'کارت به کارت',
        cheque: 'چک', month: 'پرداخت اول ماه'
    };
    /* آیکون SVG «تیک» — از همان کتابخانهٔ icon() سرور می‌آید، نه ایموجی خام
       (قاعدهٔ سایت)، چون این نشان بعد از fetch در جاوااسکریپت ساخته می‌شود. */
    var CHECK_ICON = <?= json_encode(icon('check-circle', 'ic-sm'), JSON_UNESCAPED_UNICODE) ?>;
    groups.forEach(function (grp) {
        var orderId = grp.getAttribute('data-order');
        grp.querySelectorAll('.pset-settle-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var method = btn.getAttribute('data-method');
                var label = LABELS[method] || method;
                if (!confirm(label + ' — این سفارش تسویه‌شده ثبت شود؟')) return;
                grp.querySelectorAll('.pset-settle-btn').forEach(function (b) { b.disabled = true; });
                var fd = new FormData();
                fd.append('pset_settle_id', orderId);
                fd.append('pset_settle_method', method);
                fd.append('pset_ajax', '1');
                fetch('partner-settlements.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            var row = document.getElementById('pset-ord-' + orderId);
                            if (row) row.classList.add('pset-order-settled');
                            var done = document.createElement('span');
                            done.className = 'pset-settle-done';
                            done.innerHTML = CHECK_ICON + ' تسویه شد (' + label + ')';
                            grp.parentNode.appendChild(done);
                        } else {
                            alert('ثبت انجام نشد. دوباره تلاش کنید.');
                            grp.querySelectorAll('.pset-settle-btn').forEach(function (b) { b.disabled = false; });
                        }
                    })
                    .catch(function () {
                        alert('ارتباط برقرار نشد. دوباره تلاش کنید.');
                        grp.querySelectorAll('.pset-settle-btn').forEach(function (b) { b.disabled = false; });
                    });
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/layout-bottom.php'; ?>
