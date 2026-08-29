<?php
/* هندلرِ ثبت واریز پیش از هدر اجرا می‌شود، وگرنه ریدایرکتِ PRG بعد از چاپ HTML
   کار نمی‌کند (هیچ بافر خروجی‌ای در این پروژه روشن نیست). */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$orderId = (int)($_GET['id'] ?? 0);

/* ---------- ثبت واریزِ کارت به کارت (PRG) ----------
   فقط صاحب سفارش می‌تواند ثبت کند و فقط روی سفارشِ کارت‌به‌کارتِ پرداخت‌نشده. */
$c2cErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['c2c_report']) && $orderId > 0) {
    $own = false;
    try {
        $st = $pdo->prepare("SELECT customer_id, payment_method, payment_status FROM orders WHERE id=?");
        $st->execute([$orderId]);
        $row = $st->fetch();
        $own = $row && isCustomerLoggedIn() && (int)($row['customer_id'] ?? 0) === (int)$_SESSION['customer_id']
               && (string)($row['payment_method'] ?? '') === 'card'
               && (string)($row['payment_status'] ?? '') !== 'paid';
    } catch (Throwable $e) { $own = false; }

    if (!$own) {
        $c2cErr = 'این سفارش قابل ثبت واریز نیست.';
    } else {
        $c2cErr = paymentC2cSave($orderId, [
            'ref'       => $_POST['c2c_ref']       ?? '',
            'amount'    => $_POST['c2c_amount']    ?? '',
            'last4'     => $_POST['c2c_last4']     ?? '',
            'paid_text' => $_POST['c2c_paid_text'] ?? '',
        ]);
        if ($c2cErr === '') redirect('order-success.php?id=' . $orderId . '&c2c=ok');
    }
}

/* ---------- ثبت اطلاعاتِ چک (PRG) ---------- همان قاعده: فقط صاحبِ سفارشِ
   چکیِ پرداخت‌نشده. بانک/شماره/تاریخ/مبلغ الزامی؛ شناسهٔ صیاد اختیاری. */
$chqErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cheque_report']) && $orderId > 0) {
    $own = false;
    try {
        $st = $pdo->prepare("SELECT customer_id, payment_method, payment_status FROM orders WHERE id=?");
        $st->execute([$orderId]);
        $row = $st->fetch();
        $own = $row && isCustomerLoggedIn() && (int)($row['customer_id'] ?? 0) === (int)$_SESSION['customer_id']
               && (string)($row['payment_method'] ?? '') === 'cheque'
               && (string)($row['payment_status'] ?? '') !== 'paid';
    } catch (Throwable $e) { $own = false; }

    if (!$own) {
        $chqErr = 'این سفارش قابل ثبتِ چک نیست.';
    } else {
        $chqErr = paymentChequeSave($orderId, [
            'bank'   => $_POST['cheque_bank']   ?? '',
            'number' => $_POST['cheque_number'] ?? '',
            'date'   => $_POST['cheque_date']   ?? '',
            'amount' => $_POST['cheque_amount'] ?? '',
            'sayad'  => $_POST['cheque_sayad']  ?? '',
        ]);
        if ($chqErr === '') redirect('order-success.php?id=' . $orderId . '&chq=ok');
    }
}

require_once __DIR__ . '/includes/header.php';

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

/* یک مشتری فقط سفارش خودش را ببیند (سفارش‌های مهمان بدون customer_id باقی می‌مانند) */
if ($order && isCustomerLoggedIn() && ($order['customer_id'] ?? null) !== null
    && (int)$order['customer_id'] !== (int)$_SESSION['customer_id']) {
    $order = false;
}

if (!$order) {
    echo '<div class="container"><div class="no-results"><div class="no-results-icon">' . icon('alert') . '</div><p>سفارش یافت نشد.</p></div></div>';
    require_once 'includes/footer.php';
    exit;
}

/* وضعیت پرداخت (فقط اگر مهاجرت پرداخت اجرا شده باشد) */
$payOn     = paymentReady();
$payMethod = $payOn ? (string)($order['payment_method'] ?? 'cod') : '';
$payStatus = $payOn ? (string)($order['payment_status'] ?? 'unpaid') : '';
$payIsPaid = ($payStatus === 'paid');
$payOnline = $payOn && paymentIsOnline($payMethod);
/* روش ارسالی که مشتری انتخاب کرده (فقط اگر مهاجرت ارسال اجرا شده باشد) */
$shipOn     = shippingReady();
$shipMethod = $shipOn ? (string)($order['shipping_method'] ?? '') : '';
$shipCost   = $shipOn ? (int)($order['shipping_cost'] ?? 0) : 0;
$orderTax   = (int)($order['tax_total'] ?? 0);

/* نتیجهٔ بازگشت از درگاه (payment-callback.php این پارامتر را می‌گذارد) */
$payFlag   = (string)($_GET['pay'] ?? '');

/* گیتِ «بررسی موجودی» — اولین مرحلهٔ روند ارسال. تا مدیر آن را تیک نزند (یا
   خودکار از «بررسی عکس نمونهٔ قطعه» طی نشده باشد)، هیچ اقدامِ پرداختی —
   دکمهٔ درگاه، گزارشِ واریزِ کارت‌به‌کارت، ثبتِ اطلاعاتِ چک — در دسترس نیست. */
$stockUnlocked = orderPaymentUnlocked($order);

/* کارت به کارت: آیا مشتری واریز را ثبت کرده؟
   نکته: اطلاعاتِ کارت/چک عمداً به $stockUnlocked گره نمی‌خورد — این دو حالا
   خودِ checkout.php جمع‌شان می‌کند (پیش از این‌که سفارش، و پس‌ازآن امکانِ
   بررسیِ موجودی، اصلاً وجود داشته باشد)، پس مخفی‌کردنِ چیزی که مشتری همین
   الان فرستاده گمراه‌کننده بود. گیت فقط جلوی «پرداختِ آنیِ» درگاهِ بانکی را
   می‌گیرد (پایین‌تر). */
$c2cOn     = $payOn && $payMethod === 'card' && !$payIsPaid && paymentC2cReady();
$c2cDone   = $c2cOn && trim((string)($order['c2c_ref'] ?? '')) !== '';
$c2cSaved  = ((string)($_GET['c2c'] ?? '') === 'ok');

/* همان الگو برای چک */
$chqOn     = $payOn && $payMethod === 'cheque' && !$payIsPaid && paymentChequeReady();
$chqDone   = $chqOn && trim((string)($order['cheque_number'] ?? '')) !== '';
$chqSaved  = ((string)($_GET['chq'] ?? '') === 'ok');
?>

<div class="container">
    <div class="success-page">
        <div class="success-icon"><?= icon($payFlag === 'fail' || $payFlag === 'cancel' ? 'alert' : 'check-circle') ?></div>
        <h2><?php if ($payFlag === 'ok' || ($payIsPaid && $payFlag === '')): ?>سفارش ثبت و پرداخت شد<?php elseif ($payFlag === 'cancel'): ?>سفارش ثبت شد — پرداخت لغو شد<?php elseif ($payFlag === 'fail'): ?>سفارش ثبت شد — پرداخت ناموفق بود<?php else: ?>سفارش شما با موفقیت ثبت شد<?php endif; ?></h2>
        <p>شماره سفارش: <strong dir="ltr"><?= h(orderNumber($order)) ?></strong></p>
        <?php
        $incNotes = [];
        if ($shipCost > 0) $incNotes[] = 'هزینهٔ ارسال';
        if ($orderTax > 0) $incNotes[] = 'مالیات';
        ?>
        <p>مبلغ سفارش: <strong><?= formatPrice($order['total_amount']) ?></strong><?= $incNotes ? ' <span style="color:var(--text-muted);font-size:0.85rem;">(شامل ' . h(implode(' و ', $incNotes)) . ')</span>' : '' ?></p>

        <?php if ($shipMethod !== ''): ?>
        <div class="pay-recap">
            <div><span>روش ارسال</span><b><?= icon(shippingIcon($shipMethod), 'ic-sm') ?> <?= h(shippingLabel($shipMethod)) ?></b></div>
            <div><span>هزینهٔ ارسال</span><b><?= h(shippingCostText($shipCost, $shipMethod)) ?></b></div>
            <?php if (($sb = shippingBadge($shipMethod)) !== ''): ?>
            <div><span>یادآوری</span><b><?= icon('map-pin', 'ic-sm') ?> <?= h($sb) ?></b></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($payOn && !$stockUnlocked && !$payIsPaid && $order['status'] !== 'cancelled'): ?>
        <div style="max-width:380px;margin:0 auto 1rem;padding:0.75rem 0.9rem;border-radius:var(--radius);background:rgba(234,179,8,0.1);border:1px solid rgba(234,179,8,0.3);color:#FCD34D;font-size:0.82rem;line-height:1.9;text-align:right;">
            <?= icon('shield-check', 'ic-sm') ?> <b>سفارش شما در انتظار «بررسی موجودی» است.</b>
            کارشناسان ما ابتدا موجودی کالا را بررسی می‌کنند؛ به‌محضِ تأیید، امکانِ پرداخت (آنلاین یا دیگر روش‌ها) برای شما فعال می‌شود.
        </div>
        <?php endif; ?>

        <?php if ($payOn): ?>
        <div class="pay-recap">
            <div><span>روش پرداخت</span><b><?= icon(paymentIcon($payMethod), 'ic-sm') ?> <?= h(paymentLabel($payMethod)) ?></b></div>
            <div><span>وضعیت پرداخت</span><b><?= paymentStatusBadgeFor($payStatus, $payMethod) ?></b></div>
            <?php if (!empty($order['payment_ref'])): ?>
            <div><span>شمارهٔ پیگیری بانک</span><b dir="ltr"><?= h($order['payment_ref']) ?></b></div>
            <?php endif; ?>
            <?php if ($payIsPaid && (int)$order['paid_amount'] > 0): ?>
            <div><span>مبلغ پرداخت‌شده</span><b><?= formatPrice($order['paid_amount']) ?></b></div>
            <?php endif; ?>
        </div>

        <?php if ($payMethod === 'card' && !$payIsPaid && trim((string)getSettingRaw('pay_card_number', '')) !== ''): ?>
        <div class="pay-card-info">
            <div class="pay-card-info-t"><?= icon('credit-card', 'ic-sm') ?> اطلاعات کارت برای واریز</div>
            <div><span>شمارهٔ کارت</span><b dir="ltr" class="pay-pan"><?= h(getSettingRaw('pay_card_number', '')) ?></b></div>
            <?php if (($ch = trim((string)getSettingRaw('pay_card_holder', ''))) !== ''): ?>
            <div><span>به نام</span><b><?= h($ch) ?></b></div>
            <?php endif; ?>
            <?php if (($cb = trim((string)getSettingRaw('pay_card_bank', ''))) !== ''): ?>
            <div><span>بانک</span><b><?= h($cb) ?></b></div>
            <?php endif; ?>
            <?php if (($cn = trim((string)getSetting('pay_card_note', ''))) !== ''): ?>
            <p class="pay-card-note"><?= h($cn) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($c2cOn): ?>
        <?php /* ثبت واریزِ کارت به کارت — چهار موردی که مدیر خواسته: شناسهٔ واریز،
                مبلغ، چهار رقم آخر کارت مبدأ و زمان واریز. سفارش تا تأیید مدیر
                «در انتظار تأیید واریز» می‌ماند (هیچ پرداختی خودکار تأیید نمی‌شود). */ ?>
        <div class="c2c-box">
            <div class="c2c-t"><?= icon('receipt', 'ic-sm') ?> ثبت واریز</div>

            <?php if ($c2cErr !== ''): ?>
            <p class="c2c-msg is-bad"><?= icon('alert', 'ic-sm') ?> <?= h($c2cErr) ?></p>
            <?php elseif ($c2cSaved): ?>
            <p class="c2c-msg is-ok"><?= icon('check-circle', 'ic-sm') ?> اطلاعات واریز ثبت شد. سفارش شما تا تأیید فروشگاه «در انتظار تأیید واریز» است.</p>
            <?php endif; ?>

            <?php if (($c2cNote = trim((string)getSetting('pay_c2c_note', ''))) !== ''): ?>
            <p class="c2c-note"><?= icon('info', 'ic-sm') ?> <?= h($c2cNote) ?></p>
            <?php else: ?>
            <p class="c2c-note"><?= icon('info', 'ic-sm') ?> پس از واریز، چهار مورد زیر را پر کنید تا سفارش‌تان بررسی و تأیید شود.</p>
            <?php endif; ?>

            <?php if ($c2cDone): ?>
            <div class="c2c-recap">
                <div><span>شناسهٔ واریز</span><b dir="ltr"><?= h((string)$order['c2c_ref']) ?></b></div>
                <div><span>مبلغ واریزی</span><b><?= formatPrice((int)($order['c2c_amount'] ?? 0)) ?></b></div>
                <div><span>چهار رقم آخر کارت شما</span><b dir="ltr"><?= h((string)($order['c2c_last4'] ?? '')) ?></b></div>
                <div><span>زمان واریز</span><b><?= h((string)($order['c2c_paid_text'] ?? '')) ?></b></div>
            </div>
            <p class="c2c-note"><?= icon('clock', 'ic-sm') ?> اگر اشتباهی وارد کرده‌اید، فرم زیر را دوباره پر کنید تا اطلاعات جایگزین شود.</p>
            <?php endif; ?>

            <form method="POST" action="order-success.php?id=<?= (int)$order['id'] ?>" class="c2c-form">
                <input type="hidden" name="c2c_report" value="1">
                <div class="c2c-grid">
                    <div class="form-group">
                        <label for="c2c_ref">شناسهٔ واریز / شمارهٔ پیگیری *</label>
                        <input type="text" name="c2c_ref" id="c2c_ref" class="form-control" dir="ltr" required
                               value="<?= h((string)($order['c2c_ref'] ?? '')) ?>" placeholder="مثلاً 123456789">
                    </div>
                    <div class="form-group">
                        <label for="c2c_amount">مبلغ واریزی (تومان) *</label>
                        <input type="text" name="c2c_amount" id="c2c_amount" class="form-control" dir="ltr"
                               inputmode="numeric" required
                               value="<?= (int)($order['c2c_amount'] ?? 0) > 0 ? (int)$order['c2c_amount'] : (int)$order['total_amount'] ?>">
                    </div>
                    <div class="form-group">
                        <label for="c2c_last4">چهار رقم آخر کارت شما *</label>
                        <input type="text" name="c2c_last4" id="c2c_last4" class="form-control" dir="ltr"
                               inputmode="numeric" maxlength="4" required
                               value="<?= h((string)($order['c2c_last4'] ?? '')) ?>" placeholder="1234">
                    </div>
                    <div class="form-group">
                        <label for="c2c_paid_text">زمان واریز *</label>
                        <input type="text" name="c2c_paid_text" id="c2c_paid_text" class="form-control" required
                               value="<?= h((string)($order['c2c_paid_text'] ?? '')) ?>" placeholder="مثلاً امروز ساعت ۱۸:۳۰">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><?= icon('check-circle', 'ic-sm') ?> <?= $c2cDone ? 'به‌روزرسانی اطلاعات واریز' : 'ثبت اطلاعات واریز' ?></button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($chqOn): ?>
        <?php /* ثبت اطلاعات چک — پنج مورد: بانک، شماره، تاریخ، مبلغ، شناسهٔ صیاد
                (اختیاری). سفارش تا «دریافت چک» مدیر «در انتظار بررسی چک» می‌ماند. */ ?>
        <div class="c2c-box">
            <div class="c2c-t"><?= icon('receipt', 'ic-sm') ?> ثبت اطلاعات چک</div>

            <?php if ($chqErr !== ''): ?>
            <p class="c2c-msg is-bad"><?= icon('alert', 'ic-sm') ?> <?= h($chqErr) ?></p>
            <?php elseif ($chqSaved): ?>
            <p class="c2c-msg is-ok"><?= icon('check-circle', 'ic-sm') ?> اطلاعات چک ثبت شد. سفارش شما تا بررسیِ فروشگاه «در انتظار بررسی چک» است.</p>
            <?php endif; ?>

            <p class="c2c-note"><?= icon('info', 'ic-sm') ?> مشخصات چک را دقیق وارد کنید تا کارشناس ما بررسی و دریافت را تأیید کند.</p>
            <?php if (($chqSampleImg = trim((string)getSettingRaw('pay_cheque_sample', ''))) !== ''): ?>
            <div class="pc2-sample">
                <img src="uploads/settings/<?= h($chqSampleImg) ?>" alt="نمونهٔ چک">
                <span><?= icon('info', 'ic-sm') ?> نمونهٔ یک چکِ خوانا — مشخصاتِ خواسته‌شده را از روی چکِ خودتان همین‌طور واضح بنویسید</span>
            </div>
            <?php endif; ?>

            <?php if ($chqDone): ?>
            <div class="c2c-recap">
                <div><span>بانک</span><b><?= h((string)$order['cheque_bank']) ?></b></div>
                <div><span>شمارهٔ چک</span><b dir="ltr"><?= h((string)$order['cheque_number']) ?></b></div>
                <div><span>تاریخ چک</span><b><?= h((string)$order['cheque_date']) ?></b></div>
                <div><span>مبلغ چک</span><b><?= formatPrice((int)$order['cheque_amount']) ?></b></div>
                <?php if (trim((string)($order['cheque_sayad'] ?? '')) !== ''): ?>
                <div><span>شناسهٔ صیاد</span><b dir="ltr"><?= h((string)$order['cheque_sayad']) ?></b></div>
                <?php endif; ?>
                <div><span>وضعیتِ دریافت</span><b><?= !empty($order['cheque_received_at']) ? icon('check-circle', 'ic-sm') . ' چک دریافت شد' : icon('clock', 'ic-sm') . ' هنوز دریافت نشده' ?></b></div>
            </div>
            <?php if (empty($order['cheque_received_at'])): ?>
            <?php /* ثبتِ آنلاینِ مشخصات چک با تحویلِ فیزیکیِ خودِ چک فرق دارد — این
                    یادآوریِ صریح همان چیزی است که کاربر خواسته: «همکار ببیند که باید
                    چک را ارسال کند»، نه فقط یک وضعیتِ خنثی. */ ?>
            <p class="c2c-msg is-bad" style="margin-top:0.6rem;"><?= icon('alert', 'ic-sm') ?>
                ثبتِ این مشخصات کافی نیست — <b>اصلِ چک</b> را هم باید برایمان ارسال یا تحویل دهید؛
                سفارش تا رسیدنِ فیزیکیِ چک «در انتظار دریافت چک» می‌ماند.
            </p>
            <?php endif; ?>
            <p class="c2c-note"><?= icon('clock', 'ic-sm') ?> اگر اشتباهی وارد کرده‌اید، فرم زیر را دوباره پر کنید تا اطلاعات جایگزین شود.</p>
            <?php endif; ?>

            <form method="POST" action="order-success.php?id=<?= (int)$order['id'] ?>" class="c2c-form">
                <input type="hidden" name="cheque_report" value="1">
                <div class="c2c-grid">
                    <div class="form-group">
                        <label for="cheque_bank">نام بانک *</label>
                        <input type="text" name="cheque_bank" id="cheque_bank" class="form-control" required
                               value="<?= h((string)($order['cheque_bank'] ?? '')) ?>" placeholder="مثلاً ملی">
                    </div>
                    <div class="form-group">
                        <label for="cheque_number">شمارهٔ چک *</label>
                        <input type="text" name="cheque_number" id="cheque_number" class="form-control" dir="ltr" required
                               value="<?= h((string)($order['cheque_number'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="cheque_date">تاریخ چک *</label>
                        <input type="text" name="cheque_date" id="cheque_date" class="form-control" required
                               value="<?= h((string)($order['cheque_date'] ?? '')) ?>" placeholder="مثلاً ۱۴۰۵/۰۷/۰۱">
                    </div>
                    <div class="form-group">
                        <label for="cheque_amount">مبلغ چک (تومان) *</label>
                        <input type="text" name="cheque_amount" id="cheque_amount" class="form-control" dir="ltr"
                               inputmode="numeric" required
                               value="<?= (int)($order['cheque_amount'] ?? 0) > 0 ? (int)$order['cheque_amount'] : (int)$order['total_amount'] ?>">
                    </div>
                    <div class="form-group">
                        <label for="cheque_sayad">شناسهٔ صیاد (اختیاری)</label>
                        <input type="text" name="cheque_sayad" id="cheque_sayad" class="form-control" dir="ltr"
                               value="<?= h((string)($order['cheque_sayad'] ?? '')) ?>" placeholder="اگر در سامانه صیاد ثبت شده">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><?= icon('check-circle', 'ic-sm') ?> <?= $chqDone ? 'به‌روزرسانی اطلاعات چک' : 'ثبت اطلاعات چک' ?></button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($payMethod === 'cod' && ($cod = trim((string)getSetting('pay_cod_note', ''))) !== ''): ?>
        <p style="color:var(--text-muted);font-size:0.85rem;"><?= icon('info', 'ic-sm') ?> <?= h($cod) ?></p>
        <?php endif; ?>
        <?php endif; ?>

        <p>همکاران ما در اسرع وقت با شما تماس خواهند گرفت.</p>
        <div style="display:flex;gap:0.6rem;justify-content:center;flex-wrap:wrap;">
            <?php if ($payOnline && !$payIsPaid && $order['status'] !== 'cancelled' && $stockUnlocked): ?>
            <a href="payment-start.php?order=<?= (int)$order['id'] ?>" class="btn btn-primary"><?= icon('credit-card', 'ic-sm') ?> پرداخت آنلاین سفارش</a>
            <a href="shop.php" class="btn btn-secondary">بازگشت به فروشگاه</a>
            <?php else: ?>
            <a href="shop.php" class="btn btn-primary">بازگشت به فروشگاه</a>
            <?php endif; ?>
            <?php if (isCustomerLoggedIn()): ?><a href="account.php" class="btn btn-secondary">سفارش‌های من</a><?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>