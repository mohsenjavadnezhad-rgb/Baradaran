<?php
/* گام ۲ از ۳ (سبد → بررسی عکس → ثبت سفارش): «ارسال عکس نمونهٔ قطعه».
   مشتری اینجا آپلود می‌کند یا رد می‌کند و به checkout.php می‌رود؛ اگر
   «بررسی موجودی» هم لازم باشد، خود checkout.php آن وضعیت را زیر «مبلغ
   قابل پرداخت» زنده نشان می‌دهد (۲۰۲۶-۰۹-۰۳: صفحهٔ جداگانهٔ stock-check.php
   که این کار را می‌کرد حذف شد — خواستهٔ کاربر: «این صفحه دیگه اضافه است»،
   چون همان اطلاعات از قبل در خود checkout.php هم نشان داده می‌شد).
   این صفحه خودش را کاملا بسته نمی‌کند: تا وقتی نتیجه «approved» نشده،
   مشتری می‌تواند دوباره به همین‌جا برگردد و نظرش را عوض کند (مثلا بعد از
   «رد کردن»، بالأخره عکس بفرستد) — ثبت دوبارهٔ عکس همان درخواست در-انتظار
   را جایگزین می‌کند، ردیف تازه‌ای نمی‌سازد. فقط «approved» بی‌قیدوشرط از
   اینجا به checkout.php می‌رود، چون دیگر چیزی برای تغییر نمانده.
   POST پیش از include هدر انجام می‌شود تا ریدایرکت PRG ممکن باشد؛ نام فیلدها
   pc_* است تا با handleCartAction() سراسری هدر تلاقی نکند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';

requireCustomerLogin('part-check.php');

$cartItems = getCartItems();
if (empty($cartItems)) redirect('cart.php');
if (!partCheckOn())    redirect('checkout.php');   /* مدیر این مرحله را خاموش کرده */

$c      = currentCustomer();
$minPh  = partCheckMinPhotos();
$sigNow = partCheckCartSig($cartItems);
$errors = [];

/* شناسهٔ محصول‌های سبد — انتخاب مشتری باید یکی از همین‌ها باشد */
$cartPids = [];
foreach ($cartItems as $it) $cartPids[(int)$it['product']['id']] = $it['product'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['pc_action'] ?? '');

    /* رد کردن مرحله — از ۲۰۲۶-۰۸-۳۰ دیگر بایپس بی‌درنگ نیست (خواستهٔ کاربر:
       گیت «بررسی موجودی» کاملا مسدودکننده باشد). همان‌قدر که آپلود عکس یک
       ردیف part_checks می‌سازد، رد کردن هم یکی می‌سازد — فقط بدون عکس —
       و مشتری به checkout.php می‌رود تا در صف ادمین بماند؛ کارشناس دیگر
       مطابقت عکس را نمی‌سنجد (چیزی نفرستاده)، فقط موجودی را تأیید می‌کند. */
    if ($act === 'skip') {
        /* اگر همین الان یک درخواست «در انتظار» برای همین سبد هست (چه با عکس،
           چه رد‌شدهٔ قبلی)، ردیف تازه نساز — وگرنه هر کلیک «رد کردن» یک ردیف
           تکراری می‌ساخت. */
        $cur = partCheckCurrent((int)$c['id']);
        $already = $cur && (string)$cur['status'] === 'pending'
                 && ((string)$cur['cart_sig'] === $sigNow || (string)$cur['cart_sig'] === '');
        if (!$already) {
            try {
                $pdo->prepare("INSERT INTO part_checks
                        (customer_id, product_id, cart_sig, car_info, note, status)
                        VALUES (?,?,?,?,?, 'pending')")
                    ->execute([(int)$c['id'], (int)array_key_first($cartPids) ?: null, $sigNow, '',
                               '(مشتری مرحلهٔ بررسی عکس را رد کرد؛ عکسی ارسال نشده — فقط موجودی را بررسی کنید)']);
            } catch (Throwable $e) {}
        }
        redirect('checkout.php');
    }

    if ($act === 'upload') {
        $pid = (int)($_POST['pc_product'] ?? 0);
        if (!isset($cartPids[$pid])) $pid = (int)array_key_first($cartPids);
        $car  = trim((string)($_POST['pc_car'] ?? ''));
        $note = trim((string)($_POST['pc_note'] ?? ''));

        /* چند فایل واقعا فرستاده شد (برای پیام خطای گویا) */
        $tried = 0;
        if (!empty($_FILES['pc_photos']) && is_array($_FILES['pc_photos']['error'] ?? null)) {
            foreach ($_FILES['pc_photos']['error'] as $e) {
                if ($e !== UPLOAD_ERR_NO_FILE) $tried++;
            }
        }

        $files = partCheckSaveUploads('pc_photos', 8);

        if (count($files) < $minPh) {
            /* عکس‌های پذیرفته‌شده را بی‌صاحب رها نکن */
            foreach ($files as $f) {
                $p = __DIR__ . '/uploads/partchecks/' . $f;
                if (is_file($p)) @unlink($p);
            }
            if ($tried === 0) {
                $errors[] = 'هیچ عکسی انتخاب نشده بود. لطفا حداقل ' . $minPh . ' عکس از زوایای مختلف قطعه بفرستید.';
            } elseif (count($files) > 0) {
                $errors[] = 'از ' . $tried . ' فایل، فقط ' . count($files) . ' عکس پذیرفته شد و این کمتر از حداقل '
                          . $minPh . ' عکس است. هر عکس باید jpg/png/webp و کمتر از ۲ مگابایت باشد.';
            } else {
                $errors[] = 'هیچ‌یک از فایل‌ها پذیرفته نشد. عکس‌ها باید با پسوند jpg، png یا webp و هر یک کمتر از ۲ مگابایت باشند.';
            }
        } else {
            try {
                /* اگر همین الان یک ردیف «در انتظار» بدون نتیجه برای همین سبد هست
                   (مثلا قبلا «رد کردن» زده بود و نظرش عوض شد)، همان را با عکس‌های
                   تازه به‌روزرسانی می‌کنیم، نه یک ردیف تازهٔ جدا — وگرنه ادمین دو
                   درخواست باز همزمان برای یک سبد می‌دید. */
                $curRow = partCheckCurrent((int)$c['id']);
                $reuse  = $curRow && (string)$curRow['status'] === 'pending'
                        && ((string)$curRow['cart_sig'] === $sigNow || (string)$curRow['cart_sig'] === '');
                if ($reuse) {
                    $cid = (int)$curRow['id'];
                    $pdo->prepare("UPDATE part_checks SET product_id=?, cart_sig=?, car_info=?, note=? WHERE id=?")
                        ->execute([$pid ?: null, $sigNow, mb_substr($car, 0, 160), $note, $cid]);
                    foreach (partCheckImages($cid) as $oi) {
                        $op = __DIR__ . '/uploads/partchecks/' . basename((string)$oi['image']);
                        if (is_file($op)) @unlink($op);
                    }
                    $pdo->prepare("DELETE FROM part_check_images WHERE check_id=?")->execute([$cid]);
                } else {
                    $pdo->prepare("INSERT INTO part_checks
                            (customer_id, product_id, cart_sig, car_info, note, status)
                            VALUES (?,?,?,?,?, 'pending')")
                        ->execute([(int)$c['id'], $pid ?: null, $sigNow, mb_substr($car, 0, 160), $note]);
                    $cid = (int)$pdo->lastInsertId();
                }
                $ins = $pdo->prepare("INSERT INTO part_check_images (check_id, image, sort_order) VALUES (?,?,?)");
                foreach ($files as $i => $f) $ins->execute([$cid, $f, $i]);
                redirect('checkout.php?sent=1');
            } catch (Throwable $e) {
                foreach ($files as $f) {
                    $p = __DIR__ . '/uploads/partchecks/' . $f;
                    if (is_file($p)) @unlink($p);
                }
                $errors[] = 'ثبت درخواست انجام نشد. لطفا چند لحظه بعد دوباره تلاش کنید.';
            }
        }
    }
}

/* وضعیت فعلی. تنها حالتی که از این صفحه بی‌قیدوشرط بیرون می‌رود «approved»
   است — آن‌جا دیگر واقعا چیزی برای تغییر نمانده، نتیجه در checkout.php
   است. «pending» عمدا *نمی‌رود* — تجربهٔ واقعی نشان داد اگر مشتری یک‌بار
   «رد کردن» زده باشد و بعد بخواهد نظرش را عوض کند و عکس بفرستد، دیگر هیچ
   راهی به این صفحه نداشت (همیشه پرت می‌شد؛ «چرا نمیاره، میپره به بررسی
   موجودی»). حالا وقتی pending است هم فرم آپلود و هم جعبهٔ ردکردن این‌جا
   می‌مانند؛ ثبت دوبارهٔ عکس همان ردیف در-انتظار را جایگزین می‌کند (بالاتر)،
   نه یک ردیف تکراری. */
$row      = partCheckCurrent((int)$c['id']);
$sameCart = $row && ((string)$row['cart_sig'] === $sigNow || (string)$row['cart_sig'] === '');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $row && $sameCart
    && (string)$row['status'] === 'approved') {
    redirect('checkout.php');
}

$state = ($row && $sameCart) ? (string)$row['status'] : 'form'; /* form | pending | rejected — approved از بالا رد شد */
$imgs     = ($state !== 'form') ? partCheckImages((int)$row['id']) : [];
$rowProd  = null;
if ($row && !empty($row['product_id'])) {
    try {
        $st = $pdo->prepare("SELECT id, name, image, technical_number FROM products WHERE id = ?");
        $st->execute([(int)$row['product_id']]);
        $rowProd = $st->fetch() ?: null;
    } catch (Throwable $e) {}
}

/* فرم آپلود (و کنارش، کلید ردکردن مرحله) فقط در همین حالت‌ها دیده می‌شود؛
   یک متغیر مشترک تا هم بالای صفحه (جعبهٔ ردکردن) هم پایین (خود فرم) از یک
   شرط بخوانند و هیچ‌وقت با هم واگرا نشوند. «pending» هم اینجاست — مشتری اگر
   نظرش عوض شود می‌تواند همین‌جا عکس بفرستد (یا دوباره) بدون گیرکردن در
   یک صفحهٔ انتظار جدا. */
$showUploadForm = ($state === 'form' || $state === 'pending' || $state === 'rejected' || ($row && !$sameCart));

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h1 class="page-title"><?= icon('camera') ?> بررسی عکس قطعه پیش از خرید</h1>

    <?php /* سه‌گام خرید — مشتری بداند کجای مسیر است. ۲۰۲۶-۰۹-۰۳: گام جداگانهٔ
            «بررسی موجودی» حذف شد (صفحهٔ stock-check.php دیگر وجود ندارد)؛
            اگر لازم باشد، همان وضعیت حالا زیر «مبلغ قابل پرداخت» در خود
            گام سوم (ثبت سفارش) نشان داده می‌شود. دو گام اول قابل کلیک‌اند
            تا مشتری بتواند عقب برود؛ گام سوم صرفا نشانگر است (بی‌آنکه از
            گیت رد شده باشد، رفتن به آن بی‌معناست و برمی‌گردد). */ ?>
    <ol class="pchk-steps">
        <li class="is-done"><a href="cart.php"><span>۱</span> سبد خرید</a></li>
        <li class="is-now"><a href="part-check.php"><span>۲</span> بررسی عکس قطعه</a></li>
        <li><span>۳</span> ثبت سفارش و پرداخت</li>
    </ol>

    <?php /* پیغام درشت بالای صفحه — خواستهٔ مدیر: مشتری حتما آن را ببیند */ ?>
    <div class="pchk-notice">
        <div class="pchk-notice-icon"><?= icon('alert') ?></div>
        <div class="pchk-notice-body">
            <b class="pchk-notice-title">لطفا این چند خط را بخوانید — مرجوعی امکان‌پذیر نیست</b>
            <p class="pchk-notice-text"><?= nl2br(h(partCheckNotice())) ?></p>
            <ul class="pchk-notice-list">
                <li><?= icon('camera', 'ic-sm') ?> <b>حداقل <?= $minPh ?> عکس از زوایای مختلف</b> بگیرید: روبه‌رو، پشت، از کنار، و اگر می‌شود محل نصب قطعه روی خودرو.</li>
                <li><?= icon('search', 'ic-sm') ?> اگر شماره فنی روی بدنهٔ قطعه حک شده، یک عکس نزدیک و خوانا از آن بگیرید.</li>
                <li><?= icon('info', 'ic-sm') ?> در نور کافی و بدون لرزش عکس بگیرید؛ قطعه کامل در کادر باشد.</li>
                <li><?= icon('shield-check', 'ic-sm') ?> کارشناس ما عکس‌ها را با کالای سبد خریدتان مقایسه می‌کند و <b>موجودی</b> را هم همان‌جا تأیید می‌کند.</li>
            </ul>
        </div>
    </div>

    <?php /* کلید ردکردن مرحله — بالای صفحه، کنار همان تصمیمی که مشتری همین الان
            باید بگیرد (عکس بفرستم یا نه)، نه بعد از یک فرم کامل که شاید اصلا
            پر نکند. خواستهٔ کاربر: واضح‌تر و بالاتر صفحه. */ ?>
    <?php if ($showUploadForm): ?>
    <?php /* بدون confirm() — همان هشدار (بدون مرجوعی/مسئولیت با خود مشتری)
            توی همین کادر، همین‌جا روی صفحه نوشته شده؛ یک پاپ‌آپ تکراری لازم
            نیست (خواستهٔ کاربر). */ ?>
    <form method="POST" action="" class="pchk-skipbox">
        <input type="hidden" name="pc_action" value="skip">
        <div class="pchk-skipbox-ic"><?= icon('info') ?></div>
        <div>
            <b>نمی‌خواهید عکس بفرستید؟</b>
            <p>می‌توانید این مرحله را رد کنید؛ فقط منتظر تأیید موجودی می‌مانید (نه مطابقت عکس) و بعد از تأیید
               ادمین خودکار به ثبت سفارش می‌روید. بدانید در این حالت مطابقت قطعه بررسی نمی‌شود و چون
               <b>امکان مرجوعی نیست</b>، مسئولیت انتخاب کالا بر عهدهٔ خودتان است.</p>
        </div>
        <button type="submit" class="btn btn-secondary pchk-skip-top">رد کردن این مرحله <?= icon('arrow-left', 'ic-sm') ?></button>
    </form>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
    <div class="flash flash-error"><?= icon('alert', 'ic-sm') ?> <?= h($e) ?></div>
    <?php endforeach; ?>

    <?php if ($row && !$sameCart && $row['status'] !== 'rejected'): ?>
    <div class="flash flash-error"><?= icon('info', 'ic-sm') ?>
        کالاهای سبد خرید شما پس از بررسی قبلی عوض شده‌اند؛ برای قطعهٔ تازه لازم است دوباره عکس بفرستید.
    </div>
    <?php endif; ?>

    <?php /* approved دیگر اینجا رندر نمی‌شود — بالاتر، پیش از require_once
            header.php، به checkout.php ریدایرکت شده. «pending» را اینجا
            نگه داشتیم (بالا توضیح داده شد)، فقط یک یادآوری کوتاه که یک
            درخواست باز دارد و می‌تواند صبر کند یا عکس تازه بفرستد. */ ?>
    <?php if ($state === 'pending'): ?>
    <div class="flash flash-success">
        <?= icon('clock', 'ic-sm') ?> شما یک درخواست «در انتظار بررسی» برای همین سبد دارید.
        می‌توانید صبر کنید (<a href="checkout.php">پیگیری وضعیت</a>) یا اگر نظرتان عوض شده، همین‌جا عکس تازه بفرستید — جایگزین درخواست قبلی می‌شود.
    </div>
    <?php endif; ?>

    <?php if ($state === 'rejected'): ?>
    <div class="pchk-panel is-no">
        <div class="pchk-panel-head">
            <span class="pchk-badge is-no"><?= icon('x-circle', 'ic-sm') ?> قطعه تأیید نشد</span>
            <span class="pchk-when"><?= icon('calendar', 'ic-sm') ?> <?= h(jDate($row['reviewed_at'] ?: $row['created_at'], true)) ?></span>
        </div>
        <p class="pchk-panel-text">
            کارشناس ما عکس‌ها را دید و <b>این قطعه با کالای سبد خرید شما یکی نیست</b> (یا عکس‌ها برای تشخیص کافی نبود).
            لطفا یادداشت زیر را بخوانید و دوباره عکس بفرستید، یا کالای سبد را عوض کنید.
        </p>
        <?php if (trim((string)$row['admin_note']) !== ''): ?>
        <p class="pchk-adminnote is-no"><?= icon('message', 'ic-sm') ?> <b>توضیح کارشناس:</b> <?= nl2br(h($row['admin_note'])) ?></p>
        <?php endif; ?>
        <?php require __DIR__ . '/includes/partcheck-photos.php'; ?>
        <div class="pchk-actions">
            <a href="#pchk-form" class="btn btn-primary"><?= icon('camera') ?>ارسال دوبارهٔ عکس‌ها</a>
            <a href="cart.php" class="btn btn-secondary"><?= icon('arrow-right') ?>اصلاح سبد خرید</a>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ---------- فرم آپلود (حالت اول و حالت ردشده) ---------- */ ?>
    <?php if ($showUploadForm): ?>
    <form method="POST" action="" enctype="multipart/form-data" class="pchk-form" id="pchk-form">
        <input type="hidden" name="pc_action" value="upload">
        <h2 class="pchk-form-title"><?= icon('upload', 'ic-sm') ?> ارسال عکس قطعه</h2>

        <?php if (count($cartPids) > 1): ?>
        <div class="form-group">
            <label for="pc_product">عکس‌ها برای کدام کالای سبد خرید است؟</label>
            <select name="pc_product" id="pc_product" class="form-control">
                <?php foreach ($cartPids as $pid => $p): ?>
                <option value="<?= (int)$pid ?>"><?= h($p['name']) ?> — شماره فنی: <?= h((string)$p['technical_number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: $onlyP = reset($cartPids); ?>
        <input type="hidden" name="pc_product" value="<?= (int)array_key_first($cartPids) ?>">
        <p class="pchk-form-for">
            <?= icon('package', 'ic-sm') ?> کالای سبد خرید شما: <b><?= h($onlyP['name']) ?></b>
            <span class="pchk-tech">شماره فنی: <?= h((string)$onlyP['technical_number']) ?></span>
        </p>
        <?php endif; ?>

        <div class="form-group">
            <label>
                عکس‌های قطعه <span class="pchk-req">* حداقل <?= $minPh ?> عکس از زوایای مختلف</span>
            </label>
            <?php /* به‌جای یک input چندفایلی خام، برای هر عکس الزامی یک کادر مربع
                    آماده می‌گذاریم: کلیک روی کادر، انتخابگر فایل را باز می‌کند و
                    خود عکس (نه فقط نامش) همان‌جا پیش‌نمایش داده می‌شود. کادر آخر
                    برای افزودن عکس اختیاری بیشتر است (تا سقف ۸ عکس). */ ?>
            <div class="pc-shots" id="pcShots">
                <?php for ($i = 1; $i <= $minPh; $i++): ?>
                <div class="pc-shot">
                    <input type="file" name="pc_photos[]" class="pc-shot-input" accept="image/jpeg,image/png,image/webp" required>
                    <div class="pc-shot-view"><div class="pc-shot-ph"><?= icon('camera', 'ic-lg') ?><span>عکس <?= $i ?></span></div></div>
                    <button type="button" class="pc-shot-clear" hidden aria-label="حذف عکس"><?= icon('x') ?></button>
                </div>
                <?php endfor; ?>
                <div class="pc-shot pc-shot-add" id="pcShotAdd">
                    <div class="pc-shot-view"><div class="pc-shot-ph"><?= icon('plus', 'ic-lg') ?><span>عکس بیشتر</span></div></div>
                </div>
            </div>
            <small class="pchk-hint">
                <?= icon('info', 'ic-sm') ?>
                روی هر کادر کلیک کنید و عکس را انتخاب کنید؛ در صورت نیاز تا ۸ عکس می‌توانید بفرستید. پسوندهای مجاز: jpg، png، webp — حجم هر عکس کمتر از ۲ مگابایت.
            </small>
        </div>

        <div class="form-group">
            <label for="pc_car">خودروی شما (اختیاری ولی کمک‌کننده)</label>
            <input type="text" name="pc_car" id="pc_car" class="form-control" maxlength="160"
                   placeholder="مثال: ام‌وی‌ام ۵۵۰ مدل ۱۳۹۶ — موتور ۱۵۰۰">
        </div>

        <div class="form-group">
            <label for="pc_note">توضیح شما (اختیاری)</label>
            <textarea name="pc_note" id="pc_note" class="form-control" rows="3"
                      placeholder="هر نکته‌ای که به تشخیص درست قطعه کمک می‌کند؛ مثلا محل نصب یا ایرادی که دارد."></textarea>
        </div>

        <div class="pchk-actions">
            <button type="submit" class="btn btn-primary btn-lg"><?= icon('upload') ?>ارسال عکس‌ها برای بررسی</button>
            <a href="cart.php" class="btn btn-secondary"><?= icon('arrow-right') ?>بازگشت به سبد</a>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
/* کادرهای عکس قطعه: کلیک روی هر کادر → انتخابگر فایل همان کادر باز می‌شود →
   عکس انتخاب‌شده (نه فقط نامش) همان‌جا پیش‌نمایش داده می‌شود. اعتبارسنجی واقعی
   (پسوند/حجم/تعداد) در سرور است؛ این فقط تجربهٔ کاربری است.
   کادر آخر («عکس بیشتر») تزئینی است: با کلیک، یک کادر اختیاری تازه (تا سقف
   ۸ عکس) پیش از خودش می‌سازد و بلافاصله انتخابگر فایل آن را باز می‌کند. */
(function () {
    var wrap = document.getElementById('pcShots'),
        addTile = document.getElementById('pcShotAdd');
    if (!wrap || !addTile) return;

    var CAM_ICON = '<svg class="ic ic-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 3.5H9L6.8 6.5H4a2 2 0 0 0-2 2V19a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8.5a2 2 0 0 0-2-2h-2.8z"/><circle cx="12" cy="13.5" r="4"/></svg>';
    var X_ICON = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 5 5 19M5 5l14 14"/></svg>';
    var MAX_SHOTS = 8;

    function shotCount() { return wrap.querySelectorAll('.pc-shot:not(.pc-shot-add)').length; }

    function wireShot(box) {
        var input = box.querySelector('.pc-shot-input'),
            view = box.querySelector('.pc-shot-view'),
            clearBtn = box.querySelector('.pc-shot-clear'),
            placeholder = view.innerHTML;

        function reset() {
            view.innerHTML = placeholder;
            box.classList.remove('has-img');
            clearBtn.hidden = true;
        }

        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f) { reset(); return; }
            view.innerHTML = '<img src="' + URL.createObjectURL(f) + '" alt="">';
            box.classList.add('has-img');
            clearBtn.hidden = false;
        });

        clearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            input.value = '';
            reset();
        });
    }

    wrap.querySelectorAll('.pc-shot:not(.pc-shot-add)').forEach(wireShot);

    addTile.addEventListener('click', function () {
        if (shotCount() >= MAX_SHOTS) return;
        var box = document.createElement('div');
        box.className = 'pc-shot';
        box.innerHTML =
            '<input type="file" name="pc_photos[]" class="pc-shot-input" accept="image/jpeg,image/png,image/webp">' +
            '<div class="pc-shot-view"><div class="pc-shot-ph">' + CAM_ICON + '<span>عکس ' + (shotCount() + 1) + '</span></div></div>' +
            '<button type="button" class="pc-shot-clear" hidden aria-label="حذف عکس">' + X_ICON + '</button>';
        wrap.insertBefore(box, addTile);
        wireShot(box);
        box.querySelector('.pc-shot-input').click();
        if (shotCount() >= MAX_SHOTS) addTile.hidden = true;
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
