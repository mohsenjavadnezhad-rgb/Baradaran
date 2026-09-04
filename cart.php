<?php
require_once 'includes/header.php';

$cartItems = getCartItems();
$cartTotal = getCartTotal();
$cartTax   = itemsTaxTotal($cartItems);

/* ---------- انتخاب روش ارسال و برآورد هزینه، همین‌جا در سبد ----------
   تصمیم مدیر: «امکان انتخاب رو همینجا بذار از توی صفحه بعد ثبت سفارش بردار که
   دیگه صفحه بعد فقط ثبت سفارش نهایی باشه» + «همینجا روش ارسال رو انتخاب کردم با
   مبلغ فاکتور جمع کنه».
   شهر از پروفایل مشتری خوانده می‌شود — کسی که تبریز ثبت‌نام کرده، بی‌آنکه چیزی
   بنویسد نرخ تبریز را همین‌جا می‌بیند — و وزن از مجموع وزن کالاهای سبد ضرب در
   تعداد، پس با هر کم/زیادکردن تعداد عوض می‌شود. محاسبه همان
   shippingResolveCost() صفحهٔ تسویه است، پس رقم سبد با رقم سفارش یکی است.
   اگر شهری ثبت نشده باشد فهرست روش‌ها بی‌رقم می‌آید تا انتخاب همین‌جا ممکن
   بماند؛ رقم پس از ثبت شهر در صفحهٔ تسویه حساب می‌شود. */
$shipOn   = shippingReady() && shippingAvailableMethods();
$shipCity = '';
if ($shipOn && isCustomerLoggedIn()) {
    $shipCity = trim((string)(currentCustomer()['city'] ?? ''));
}
/* «جمع کالاها» که به shippingCartSummary داده می‌شود شامل مالیات هم هست تا
   payable برگشتی (کالا + ارسال) درست باشد؛ ردیف نمایشی «جمع کالاها» پایین‌تر
   همچنان از $cartTotal خالص می‌آید، مالیات ردیف جداگانهٔ خودش را دارد. */
$shipSum = ($shipOn && $cartItems) ? shippingCartSummary($cartItems, $shipCity, $cartTotal + $cartTax) : null;
$shipPick = $shipSum ? $shipSum['pick'] : '';
$needPick = $shipSum && $shipSum['quotes'] && $shipPick === '';

/* صفحهٔ ثبت سفارش وقتی روش ارسالی در نشست نیست به همین‌جا برمی‌گرداند؛
   بی‌آنکه دلیلش گفته شود، کاربر فقط «پرت‌شدن» می‌بیند. */
$pickBack = isset($_GET['pick']) && $needPick;

/* ---------- مرحلهٔ «بررسی عکس نمونهٔ قطعه» ----------
   اگر مدیر این مرحله را روشن گذاشته باشد، کلید «ادامه» به‌جای صفحهٔ ثبت سفارش
   به part-check.php می‌رود؛ آن صفحه خودش کلید «رد کردن این مرحله» را دارد، پس
   مسیر هیچ‌وقت بسته نمی‌شود. با خاموش‌بودن مرحله رفتار قبلی برمی‌گردد. */
$pchkOn   = partCheckOn();
$baseNext = $pchkOn ? 'part-check.php' : 'checkout.php';
/* «خرید بدون ثبت‌نام» (تنظیمات ← ثبت سفارش/ورود): مشتری واردنشده به‌جای
   اجبار ورودی که خود part-check.php/checkout.php می‌گذارند، اول از
   guest-checkout.php رد می‌شود که فقط شمارهٔ موبایل می‌گیرد (بدون کد تأیید)
   و از آن‌جا همان مسیر همیشگی ادامه پیدا می‌کند. مشتری واردشده و حالت
   خاموش، رفتار قبلی را دارند.
   ۲۰۲۶-۰۹-۰۳ (خواستهٔ کاربر): «ثبت سفارش بدون موبایل» سخت‌گیرتر و
   کم‌اصطکاک‌تر است — حتی همان یک قدم guest-checkout.php هم برداشته می‌شود؛
   مشتری مستقیم وارد $baseNext می‌شود (part-check.php/checkout.php خودشان
   حساب «مهمان» را بی‌صدا می‌سازند، ensureAnonymousCustomer()). با روشن‌بودن
   هردو کلید، این یکی برتری دارد. */
$nextHref = (!isCustomerLoggedIn() && checkoutNoMobileEnabled())
    ? $baseNext
    : ((!isCustomerLoggedIn() && guestCheckoutEnabled())
        ? ('guest-checkout.php?next=' . urlencode($baseNext))
        : $baseNext);
$nextText = 'ادامه ثبت سفارش';
?>

<div class="container">
    <h1 class="page-title">سبد خرید</h1>

    <?php if ($pickBack): ?>
    <div class="flash flash-error"><?= icon('alert', 'ic-sm') ?> برای ادامه، ابتدا روش ارسال را در همین صفحه انتخاب کنید.</div>
    <?php endif; ?>

    <?php if ($cartItems): ?>
    <table class="cart-table">
        <thead>
            <tr>
                <th>محصول</th>
                <th>قیمت واحد</th>
                <th>تعداد</th>
                <th>جمع</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cartItems as $item): ?>
            <?php $p = $item['product']; ?>
            <tr class="cart-row" data-id="<?= $p['id'] ?>">
                <td data-label="محصول">
                    <div class="cart-product-info">
                        <a href="product.php?id=<?= $p['id'] ?>" class="cart-product-img">
                            <?php if ($p['image']): ?>
                            <img src="uploads/products/<?= h($p['image']) ?>" alt="">
                            <?php else: ?>
                            <?= icon('cog') ?>
                            <?php endif; ?>
                        </a>
                        <div>
                            <a href="product.php?id=<?= $p['id'] ?>" style="color:var(--text-primary);font-weight:500;">
                                <?= h($p['name']) ?>
                            </a>
                            <div style="font-size:0.75rem;color:var(--text-muted);">
                                شماره فنی: <?= h($p['technical_number']) ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td data-label="قیمت واحد">
                    <div class="cart-price-wrap">
                        <span class="cart-unit-price"><?= formatPriceUnit($item['price']) ?></span>
                        <span class="cart-price-type <?= $item['price_type'] ?>">
                            <?= $item['price_type'] === 'wholesale' ? 'کلی' : 'جزئی' ?>
                        </span>
                    </div>
                </td>
                <td data-label="تعداد">
                    <?php /* دکمه‌ها submit واقعی‌اند تا بدون JS هم تعداد کم/زیاد شود؛
                             cart.js کلیک را می‌گیرد و همان کار را زنده انجام می‌دهد. */ ?>
                    <form method="POST" action="" class="cart-update-form" data-id="<?= $p['id'] ?>"
                          data-min-whole="<?= (int)$p['wholesale_min_qty'] ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <?php /* دکمهٔ پیش‌فرض فرم: تا Enter زدن داخل فیلد، همان تعداد
                                 تایپ‌شده را اعمال کند و نه دکمهٔ «−» را. */ ?>
                        <button type="submit" class="cart-enter-submit" tabindex="-1" aria-hidden="true"></button>
                        <div class="qty-stepper cart-stepper">
                            <button type="submit" name="step" value="-1" class="qty-step"
                                    aria-label="کاهش تعداد" title="کاهش تعداد">&#8722;</button>
                            <input type="text" name="quantity" value="<?= (int)$item['quantity'] ?>"
                                   class="qty-field cart-qty-field" inputmode="numeric" pattern="[0-9]*"
                                   autocomplete="off" data-max="<?= (int)$p['stock'] ?>"
                                   aria-label="تعداد" title="تعداد — با رسیدن به <?= (int)$p['wholesale_min_qty'] ?> عدد قیمت کلی اعمال می‌شود">
                            <button type="submit" name="step" value="1" class="qty-step"
                                    aria-label="افزایش تعداد" title="افزایش تعداد">+</button>
                        </div>
                        <noscript><button type="submit" class="btn btn-secondary btn-sm cart-apply">اعمال</button></noscript>
                    </form>
                </td>
                <td data-label="جمع" class="cart-subtotal"><?= formatPriceUnit($item['subtotal']) ?></td>
                <td data-label="">
                    <form method="POST" action="" class="cart-remove-form" data-id="<?= $p['id'] ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($shipSum): ?>
    <?php /* انتخاب روش ارسال + برآورد هزینه. هزینهٔ روش انتخاب‌شده در همین صفحه
            به مبلغ فاکتور اضافه می‌شود و مشتری با انتخاب ثبت‌شده به صفحهٔ ثبت
            سفارش می‌رود. «پیک مشهد» همیشه در فهرست است و پنهان نمی‌شود، ولی اگر
            شهر پروفایل خوانده شود و مشهد نباشد، ردیفش کم‌رنگ و رادیویش disabled
            می‌شود (خواستهٔ مدیر: «غیر فعال نشون داده بشه») — شهر خالی هیچ روشی
            را نمی‌بندد. روش‌های «پس‌کرایه» هیچ رقمی نمی‌گیرند و فقط همین کلمه را
            نشان می‌دهند. */ ?>
    <div class="cart-ship" id="cart-ship">
        <div class="cart-ship-head">
            <b><?= icon('truck', 'ic-sm') ?> روش و هزینهٔ ارسال <span class="cart-ship-req">*</span></b>
            <?php if ($shipCity !== ''): ?>
            <span class="cart-ship-city"><?= icon('map-pin', 'ic-sm') ?> <?= h($shipCity) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($shipCity === ''): ?>
        <p class="cart-ship-note">
            <?= icon('info', 'ic-sm') ?>
            <?php if (isCustomerLoggedIn()): ?>
            شهر خود را در <a href="account.php">حساب کاربری</a> ثبت کنید تا هزینهٔ ارسال همین‌جا خودکار حساب شود.
            <?php else: ?>
            برای محاسبهٔ خودکار هزینهٔ ارسال <a href="login.php?return=cart.php">وارد شوید</a> تا شهر پروفایل‌تان خوانده شود.
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <?php if ($shipSum['quotes']): ?>
        <?php /* بدون جاوااسکریپت، تغییر رادیو با دکمهٔ «اعمال» ثبت می‌شود؛ با
                جاوااسکریپت همان تغییر بی‌رفرش به سرور می‌رود و مبلغ‌ها به‌روز
                می‌شوند. انتخاب در نشست می‌ماند تا صفحهٔ ثبت سفارش همان را بخواند. */ ?>
        <form method="POST" action="" class="cart-ship-form" id="cart-ship-form">
            <input type="hidden" name="action" value="ship">
            <div class="cart-ship-rows">
                <?php foreach ($shipSum['quotes'] as $q):
                    $qOnly = shippingQuoteBadgeOnly($q);
                    $qOff  = !empty($q['blocked']);
                    $qCls  = ($shipPick === $q['key'] ? ' is-on' : '')
                           . (($shipSum['best'] && $shipSum['best']['key'] === $q['key']) ? ' is-best' : '')
                           . ($qOnly ? ' is-nocost' : '')
                           . ($qOff ? ' is-off' : '');
                ?>
                <label class="cart-ship-row<?= $qCls ?>" data-key="<?= h($q['key']) ?>">
                    <input type="radio" name="shipping_method" value="<?= h($q['key']) ?>"
                           <?= $shipPick === $q['key'] ? 'checked' : '' ?> <?= $qOff ? 'disabled' : '' ?>>
                    <?= icon($q['icon'], 'ic-sm') ?>
                    <b><?= h($q['label']) ?></b>
                    <?php if ($q['badge'] !== ''): ?>
                    <i class="csr-badge"><?= icon('map-pin', 'ic-sm') ?> <?= h($q['badge']) ?></i>
                    <?php endif; ?>
                    <em class="csr-price<?= empty($q['known']) ? ' is-soft' : '' ?>"><?= $qOnly ? '' : h($q['text']) ?></em>
                </label>
                <?php endforeach; ?>
            </div>
            <noscript><button type="submit" class="btn btn-secondary btn-sm cart-apply">اعمال روش ارسال</button></noscript>
        </form>

        <?php /* روش محدود به یک شهر که با شهر پروفایل نمی‌خواند غیرفعال است؛ این
                جمله دلیلش را می‌گوید. تغییر تعداد این وضعیت را عوض نمی‌کند (شهر
                عوض نمی‌شود)، پس اسکریپت زنده کاری با آن ندارد. */ ?>
        <?php if (($shipOff = shippingBlockedNote($shipSum['quotes'], $shipCity)) !== ''): ?>
        <p class="cart-ship-note is-off"><?= icon('alert', 'ic-sm') ?> <span><?= h($shipOff) ?></span></p>
        <?php endif; ?>

        <?php /* وزن و کم‌ترین هزینه با تغییر تعداد زنده به‌روز می‌شوند (خواستهٔ
                مدیر). هر دو رشته را سرور می‌سازد تا با مبلغ فاکتور یکی بمانند. */ ?>
        <p class="cart-ship-note" id="cart-ship-weight">
            <?= icon('scale', 'ic-sm') ?>
            <span><span class="csw-line"><?= h($shipSum['weight_line']) ?></span><span class="csw-best"><?php
                if ($shipSum['best']): ?> — کم‌ترین هزینه: <b><?= h(formatPrice((int)$shipSum['best']['cost'])) ?></b> با «<?= h($shipSum['best']['label']) ?>».<?php
                endif; ?></span></span>
        </p>
        <?php else: ?>
        <p class="cart-ship-note"><?= icon('info', 'ic-sm') ?> روش ارسالی برای انتخاب فعال نیست؛ هزینهٔ ارسال پس از ثبت سفارش هماهنگ می‌شود.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="cart-summary">
        <?php if ($shipSum && $shipSum['quotes']): ?>
        <?php /* مبلغ قابل پرداخت = جمع کالاها + هزینهٔ روش انتخاب‌شده. رنگ جداگانه
                (طلایی) دارد تا با «جمع کالاها» اشتباه نشود — خواستهٔ مدیر:
                «با مبلغ فاکتور جمع کنه و به رنگ جدیدی نشون بده». */ ?>
        <div class="cart-sum" id="cart-sum">
            <div class="cart-sum-row">
                <span>جمع کالاها</span>
                <span class="cart-total-val"><?= formatPriceUnit($cartTotal) ?></span>
            </div>
            <div class="cart-sum-row" id="cart-tax-row" <?= $cartTax > 0 ? '' : 'hidden' ?>>
                <span><?= icon('receipt', 'ic-sm') ?> مالیات</span>
                <span class="cart-tax-val" id="cart-tax-cell"><?= formatPriceUnit($cartTax) ?></span>
            </div>
            <div class="cart-sum-row">
                <span><?= icon('truck', 'ic-sm') ?> هزینهٔ ارسال</span>
                <span class="cart-ship-val<?= ($shipPick === '' || $shipSum['cost'] <= 0) ? ' is-soft' : '' ?>"><?= h($shipSum['cost_text']) ?></span>
            </div>
            <div class="cart-sum-row is-pay">
                <span><?= icon('receipt', 'ic-sm') ?> مبلغ قابل پرداخت</span>
                <span class="cart-pay-val"><?= formatPriceUnit($shipSum['payable']) ?></span>
            </div>
        </div>
        <?php elseif ($cartTax > 0): ?>
        <div class="cart-sum" id="cart-sum">
            <div class="cart-sum-row">
                <span>جمع کالاها</span>
                <span class="cart-total-val"><?= formatPriceUnit($cartTotal) ?></span>
            </div>
            <div class="cart-sum-row" id="cart-tax-row">
                <span><?= icon('receipt', 'ic-sm') ?> مالیات</span>
                <span class="cart-tax-val" id="cart-tax-cell"><?= formatPriceUnit($cartTax) ?></span>
            </div>
            <div class="cart-sum-row is-pay">
                <span><?= icon('receipt', 'ic-sm') ?> مبلغ کل</span>
                <span class="cart-pay-val" id="cart-alltotal-cell"><?= formatPriceUnit($cartTotal + $cartTax) ?></span>
            </div>
        </div>
        <?php else: ?>
        <div class="cart-total">مبلغ کل: <span class="cart-total-val"><?= formatPriceUnit($cartTotal) ?></span></div>
        <?php endif; ?>

        <div class="cart-go">
            <a href="preinvoice.php" class="btn btn-secondary"><?= icon('printer') ?>پیش‌فاکتور / چاپ و PDF</a>
            <a href="<?= h($nextHref) ?>" class="btn btn-primary<?= $needPick ? ' is-locked' : '' ?>" id="cart-next"
               <?= $needPick ? 'aria-disabled="true"' : '' ?>><?= icon('cart') ?><?= h($nextText) ?></a>
            <?php if ($shipSum && $shipSum['quotes']): ?>
            <p class="cart-go-hint" id="cart-go-hint"<?= $needPick ? '' : ' hidden' ?>>
                <?= icon('alert', 'ic-sm') ?> ابتدا روش ارسال را انتخاب کنید.
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="cart-empty">
        <div class="cart-empty-icon"><?= icon('cart') ?></div>
        <p>سبد خرید شما خالی است.</p>
        <a href="shop.php" class="btn btn-primary" style="margin-top:1rem;">مشاهده محصولات</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>