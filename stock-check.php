<?php
/* گامِ ۳ از ۴ (سبد → بررسی عکس → بررسی موجودی → ثبت سفارش): «در انتظار
   بررسی موجودی». مشتری فقط پس از یک اقدام در part-check.php (آپلود یا رد
   کردن) به اینجا می‌رسد — این صفحه خودش هیچ اقدامی نمی‌گیرد، فقط وضعیتِ
   ردیفِ part_checks را نشان می‌دهد و صبر می‌کند.
   ۲۰۲۶-۰۸-۳۰: پیش‌تر این حالت‌ها داخلِ part-check.php رندر می‌شدند؛ به
   خواستِ کاربر («امکان آپلود عکس رو بذار توی یک صفحه، صفحه سوم بررسی
   موجودی میشه») این‌جا صفحهٔ مستقلِ خودش را گرفت. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';

requireCustomerLogin('stock-check.php');

$cartItems = getCartItems();
if (empty($cartItems)) redirect('cart.php');
if (!partCheckOn())    redirect('checkout.php');   /* مدیر این مرحله را خاموش کرده */

$c      = currentCustomer();
$sigNow = partCheckCartSig($cartItems);
$sent   = isset($_GET['sent']);

$row      = partCheckCurrent((int)$c['id']);
$sameCart = $row && ((string)$row['cart_sig'] === $sigNow || (string)$row['cart_sig'] === '');

/* بدون ردیفِ منطبق، یا ردیفی که رد شده: اینجا معنا ندارد — برگرد به گامِ ۲
   تا مشتری آپلود کند یا رد کند. */
if (!$row || !$sameCart || (string)$row['status'] === 'rejected') {
    redirect('part-check.php');
}

$stockReq = partCheckStockRequired();
$state = (string)$row['status'];                              /* pending | approved */
if ($stockReq && $state === 'approved' && empty($row['stock_ok'])) $state = 'pending';

$imgs    = partCheckImages((int)$row['id']);
$rowProd = null;
if (!empty($row['product_id'])) {
    try {
        $st = $pdo->prepare("SELECT id, name, image, technical_number FROM products WHERE id = ?");
        $st->execute([(int)$row['product_id']]);
        $rowProd = $st->fetch() ?: null;
    } catch (Throwable $e) {}
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h1 class="page-title"><?= icon('shield-check') ?> بررسی موجودی</h1>

    <ol class="pchk-steps">
        <li class="is-done"><span>۱</span> سبد خرید</li>
        <li class="is-done"><span>۲</span> بررسی عکس قطعه</li>
        <li class="is-now"><span>۳</span> بررسی موجودی</li>
        <li><span>۴</span> ثبت سفارش و پرداخت</li>
    </ol>

    <?php if ($sent): ?>
    <div class="flash flash-success"><?= icon('check-circle', 'ic-sm') ?> عکس‌های شما ثبت شد و برای بررسی به کارشناس ما رفت.</div>
    <?php endif; ?>

    <?php /* ---------- در انتظار بررسیِ موجودی ----------
            کادرِ بزرگِ مسدودکننده: خواستهٔ کاربر ۲۰۲۶-۰۸-۳۰. تا وقتی ادمین در
            پنل تیکِ «موجودی کالا را تأیید می‌کنم» را نزند (همراه با تأییدِ
            مطابقتِ عکس، اگر عکسی بوده)، مشتری همین‌جا می‌ماند — نه دکمهٔ فرار،
            نه timeout. صفحه هر چند ثانیه خودش تازه می‌شود تا با تأییدِ ادمین،
            بدونِ کاری از سمتِ مشتری به تسویه‌حساب برود. */ ?>
    <?php if ($state === 'pending'): ?>
    <?php $photoReviewed = ((string)$row['status'] === 'approved'); /* عکس تأیید شده، فقط موجودی مانده */ ?>
    <div class="pchk-panel is-wait pchk-panel-big">
        <div class="pchk-wait-glow" aria-hidden="true"></div>
        <div class="pchk-wait-icon"><?= icon('package', 'ic-lg') ?></div>
        <div class="pchk-panel-head" style="justify-content:center;">
            <span class="pchk-badge is-wait pchk-badge-blink"><?= icon('clock', 'ic-sm') ?> در انتظار بررسی موجودی</span>
            <span class="pchk-when"><?= icon('calendar', 'ic-sm') ?> <?= h(jDate($row['created_at'], true)) ?></span>
        </div>
        <p class="pchk-panel-text" style="text-align:center;">
            <?php if ($photoReviewed): ?>
            کارشناسِ ما مطابقتِ عکسِ قطعه را تأیید کرد؛ فقط <b>تأییدِ موجودیِ انبار</b> باقی مانده.
            <?php else: ?>
            درخواستِ شما رسید و در نوبتِ <b>بررسیِ موجودیِ انبار</b> است.
            <?php endif; ?>
            به‌محضِ تأییدِ کارشناسِ ما، همین صفحه خودکار سبز می‌شود و مستقیم به
            <b>ثبتِ سفارش و پرداخت</b> می‌روید — کاری لازم نیست بکنید، فقط این صفحه را باز نگه دارید.
        </p>
        <?php require __DIR__ . '/includes/partcheck-photos.php'; ?>
        <div class="pchk-actions" style="justify-content:center;">
            <a href="stock-check.php" class="btn btn-secondary"><?= icon('refresh') ?>بررسی وضعیت</a>
        </div>
    </div>
    <script>
    /* در حالت انتظار، صفحه خودش تازه می‌شود تا مشتری مجبور نباشد رفرش کند */
    setTimeout(function () { location.replace('stock-check.php'); }, 10000);
    </script>

    <?php /* ---------- تأیید شد — خودکار به تسویه‌حساب ---------- */ ?>
    <?php elseif ($state === 'approved'): ?>
    <div class="pchk-panel is-ok pchk-panel-big pchk-panel-pop">
        <div class="pchk-wait-icon is-ok"><?= icon('check-circle', 'ic-lg') ?></div>
        <div class="pchk-panel-head" style="justify-content:center;">
            <span class="pchk-badge is-ok"><?= icon('check-circle', 'ic-sm') ?> موجودی تأیید شد</span>
            <span class="pchk-when"><?= icon('calendar', 'ic-sm') ?> <?= h(jDate($row['reviewed_at'] ?: $row['created_at'], true)) ?></span>
        </div>
        <p class="pchk-panel-text" style="text-align:center;">
            کارشناسِ ما موجودیِ کالا را تأیید کرد. در حالِ انتقال به <b>ثبتِ سفارش و پرداخت</b>…
        </p>
        <div class="pchk-confirm">
            <?php if ($imgs): ?>
            <div class="pchk-confirm-row is-ok">
                <?= icon('check-circle', 'ic-sm') ?> <b>مطابقت قطعه:</b> تأیید شد
            </div>
            <?php else: ?>
            <div class="pchk-confirm-row is-soft">
                <?= icon('info', 'ic-sm') ?> <b>مطابقت قطعه:</b> بررسی نشد (عکسی ارسال نشده بود)
            </div>
            <?php endif; ?>
            <div class="pchk-confirm-row is-ok">
                <?= icon('package', 'ic-sm') ?>
                <b>موجودی کالا:</b> موجود است و برای شما کنار گذاشته شد
                <?php if (trim((string)$row['stock_note']) !== ''): ?>
                <span class="pchk-confirm-note">— <?= h($row['stock_note']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (trim((string)$row['admin_note']) !== ''): ?>
        <p class="pchk-adminnote"><?= icon('message', 'ic-sm') ?> <b>یادداشت کارشناس:</b> <?= nl2br(h($row['admin_note'])) ?></p>
        <?php endif; ?>
        <?php require __DIR__ . '/includes/partcheck-photos.php'; ?>
        <div class="pchk-actions" style="justify-content:center;">
            <a href="checkout.php" class="btn btn-primary btn-lg"><?= icon('cart') ?>ادامه به ثبتِ سفارش و پرداخت</a>
            <a href="cart.php" class="btn btn-secondary"><?= icon('arrow-right') ?>بازگشت به سبد خرید</a>
        </div>
    </div>
    <script>
    /* خودکار وارد تسویه‌حساب می‌شویم — کلیدِ بالا فقط برای وقتی جاوااسکریپت
       خاموش است می‌ماند. کمی مکث تا انیمیشنِ تیکِ سبز دیده شود. */
    setTimeout(function () { location.href = 'checkout.php'; }, 1800);
    </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
