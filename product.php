<?php
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$product = getProductById($id);

if (!$product) {
    echo '<div class="container"><div class="no-results"><div class="no-results-icon">' . icon('alert') . '</div><p>محصول یافت نشد.</p></div></div>';
    require_once 'includes/footer.php';
    exit;
}

$categories = getProductCategories($product['id']);
$images = getProductImages($product['id']);
$variants = getProductVariants($product['id']);

$selectedVariantId = (int)($_GET['v'] ?? 0);
$activeVariant = null;
if ($selectedVariantId && $variants) {
    foreach ($variants as $v) { if ($v['id'] == $selectedVariantId) { $activeVariant = $v; break; } }
}
if (!$activeVariant && $variants) $activeVariant = $variants[0];

$displayRetail = $activeVariant ? $activeVariant['retail_price'] : $product['retail_price'];
$displayWhole  = $activeVariant ? $activeVariant['wholesale_price'] : $product['wholesale_price'];
$displayStock  = $activeVariant ? $activeVariant['stock'] : $product['stock'];
$minWhole      = $product['wholesale_min_qty'];
$retailDisc    = (int)($activeVariant['retail_discount'] ?? $product['retail_discount'] ?? 0);
$wholeDisc     = (int)($activeVariant['wholesale_discount'] ?? $product['wholesale_discount'] ?? 0);

$variantJson = json_encode(array_map(function($v){
    return ['id'=>$v['id'],'country'=>$v['country'],'manufacturer'=>$v['manufacturer'],'retail_price'=>$v['retail_price'],'wholesale_price'=>$v['wholesale_price'],'stock'=>$v['stock'],'retail_discount'=>(int)($v['retail_discount'] ?? 0),'wholesale_discount'=>(int)($v['wholesale_discount'] ?? 0)];
}, $variants), JSON_UNESCAPED_UNICODE);

/* گالری کاروسل: تصویر اصلیِ محصول + تصاویر جدول product_images (بدون تکرار).
   عضو اول همیشه تصویری است که ابتدا در اسلات بزرگ دیده می‌شود. */
$gallery = [];
if (!empty($product['image'])) $gallery[] = $product['image'];
foreach ($images as $img) {
    $f = $img['image'] ?? '';
    if ($f !== '' && !in_array($f, $gallery, true)) $gallery[] = $f;
}
$galleryCount = count($gallery);

/* ===================== نظرها · امتیاز · پرسش‌وپاسخ =====================
   فقط ردیف‌های تأییدشده خوانده می‌شوند؛ ثبت در review-submit.php انجام می‌شود. */
$reviewsOn = reviewsReady();
list($ratingAvg, $ratingCount) = getProductRating($product['id']);
$reviews   = $reviewsOn ? getProductReviews($product['id']) : [];
$questions = $reviewsOn ? getProductQa($product['id']) : [];
$isCustomer    = isCustomerLoggedIn();
$isAdminViewer = isLoggedIn();

/* پیام بازگشتِ PRG از review-submit.php ('ok' یا 'err' + متن) */
$prMsgMap = [
    'review'    => ['ok',  'نظر شما ثبت شد و پس از تأیید فروشگاه نمایش داده می‌شود.'],
    'question'  => ['ok',  'پرسش شما ثبت شد و پس از تأیید فروشگاه نمایش داده می‌شود.'],
    'answer'    => ['ok',  'پاسخ شما ثبت شد و پس از تأیید فروشگاه نمایش داده می‌شود.'],
    'answered'  => ['ok',  'پاسخ فروشگاه ثبت و منتشر شد.'],
    'dup'       => ['err', 'شما پیش‌تر برای این محصول نظر ثبت کرده‌اید.'],
    'short'     => ['err', 'متن نوشته‌شده بسیار کوتاه است.'],
    'badrating' => ['err', 'امتیاز را بین ۱ تا ۵ ستاره انتخاب کنید.'],
    'noq'       => ['err', 'پرسش مورد نظر پیدا نشد.'],
    'failed'    => ['err', 'ثبت انجام نشد؛ لطفاً دوباره تلاش کنید.'],
];
$prMsg = isset($_GET['msg']) ? ($prMsgMap[$_GET['msg']] ?? null) : null;

/* آدرس بازگشت پس از ورود، برای دعوت‌نامهٔ «برای ثبت نظر وارد شوید» */
$loginBack = 'login.php?return=' . urlencode('product.php?id=' . $product['id'] . '#reviews');
?>

<div class="container">
    <div class="product-detail">
        <div class="product-gallery">
            <div class="product-detail-image">
                <?php if ($galleryCount): ?>
                <img src="uploads/products/<?= h($gallery[0]) ?>" alt="<?= h($product['name']) ?>" id="pgMainImg">
                <?php else: ?>
                <?= icon('cog') ?>
                <?php endif; ?>
                <?php if ($galleryCount > 1): ?>
                <button type="button" class="pg-arrow pg-prev" id="pgPrev" aria-label="تصویر قبلی"><?= icon('chevron-right', 'ic-sm') ?></button>
                <button type="button" class="pg-arrow pg-next" id="pgNext" aria-label="تصویر بعدی"><?= icon('chevron-left', 'ic-sm') ?></button>
                <span class="pg-count" id="pgCount">1 / <?= $galleryCount ?></span>
                <?php endif; ?>
            </div>

            <?php if ($galleryCount > 1): ?>
            <?php /* نوار بندانگشتی — کلیک روی هر تصویر فقط src اسلات بالا را عوض می‌کند
                     (ابعاد اسلات ثابت است تا صفحه جابه‌جا نشود). */ ?>
            <div class="pg-thumbs" id="pgThumbs" role="tablist" aria-label="تصاویر محصول">
                <?php foreach ($gallery as $gi => $g): ?>
                <button type="button" class="pg-thumb<?= $gi === 0 ? ' is-active' : '' ?>"
                        data-src="uploads/products/<?= h($g) ?>" role="tab"
                        aria-selected="<?= $gi === 0 ? 'true' : 'false' ?>"
                        aria-label="تصویر <?= $gi + 1 ?> از <?= $galleryCount ?>">
                    <img src="uploads/products/<?= h($g) ?>" alt="" loading="lazy">
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="product-detail-info">
            <h1><?= h($product['name']) ?></h1>
            <div class="product-detail-tech">شماره فنی: <?= h($product['technical_number']) ?></div>

            <?php if ($variants): ?>
            <div class="variant-section" id="variantSection">
                <div class="variant-section-title"><?= icon('tools') ?>انتخاب نوع قطعه</div>
                <div class="variant-selects">
                    <div class="form-group" style="flex:1;">
                        <label><?= icon('globe') ?>کشور سازنده</label>
                        <select id="variantCountry" class="form-control"><option value="">-- انتخاب کشور --</option></select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label><?= icon('factory') ?>شرکت سازنده</label>
                        <select id="variantMaker" class="form-control"><option value="">-- انتخاب شرکت --</option></select>
                    </div>
                </div>
                <div id="variantInfo" style="display:none;margin-top:0.75rem;padding:0.5rem 0.75rem;background:rgba(220,38,38,0.08);border-radius:var(--radius-sm);font-size:0.82rem;color:var(--text-secondary);">
                    <span id="variantStock"></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($categories): ?>
            <div class="product-detail-cats">
                <?php foreach ($categories as $cat): ?>
                <span class="cat-tag"><?= h($cat['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($product['description']): ?>
            <div class="product-detail-desc"><?= nl2br(h($product['description'])) ?></div>
            <?php endif; ?>

            <div class="product-detail-prices">
                <div class="price-box retail" data-type="retail" id="retailBox" role="button" tabindex="0" title="برای انتخاب قیمت جزئی کلیک کنید">
                    <div class="price-box-label">قیمت جزئی (تکی)<span id="retailBadge"><?php if (hasDiscount($retailDisc)): ?> <span class="price-badge"><?= $retailDisc ?>٪ تخفیف</span><?php endif; ?></span></div>
                    <div class="price-box-value" id="retailPrice"><?= priceBoxValue($displayRetail, $retailDisc) ?></div>
                    <div class="price-box-state" id="retailState"></div>
                </div>
                <div class="price-box wholesale" data-type="wholesale" id="wholeBox" role="button" tabindex="0" title="برای رساندن تعداد به حداقل خرید کلی کلیک کنید">
                    <div class="price-box-label">قیمت کلی (عمده)<span id="wholeBadge"><?php if (hasDiscount($wholeDisc)): ?> <span class="price-badge"><?= $wholeDisc ?>٪ تخفیف</span><?php endif; ?></span></div>
                    <div class="price-box-value" id="wholePrice"><?= priceBoxValue($displayWhole, $wholeDisc) ?></div>
                    <div class="price-box-note" id="wholeMinNote">حداقل <?= $minWhole ?> عدد</div>
                    <div class="price-box-state" id="wholeState"></div>
                </div>
            </div>

            <div class="product-detail-stock" id="stockDisplay">
                موجودی: <?= $displayStock > 0 ? $displayStock . ' عدد' : 'ناموجود' ?>
            </div>

            <form method="POST" action="cart.php" class="product-detail-actions" id="add-to-cart-form">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <input type="hidden" name="variant_id" id="variantIdInput" value="<?= $activeVariant ? $activeVariant['id'] : '' ?>">
                <input type="hidden" name="price_type" id="priceTypeInput" value="retail">
                <?php /* type=text + inputmode=numeric: با type=number اگر کاربر ارقام فارسی بزند
                         مرورگر value را خالی برمی‌گرداند و تعداد قابل خواندن نیست. */ ?>
                <div class="qty-stepper">
                    <button type="button" class="qty-step" id="qtyMinus" aria-label="کاهش تعداد" tabindex="-1">&#8722;</button>
                    <input type="text" name="quantity" value="1" id="qtyInput" class="qty-field"
                           inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                           data-max="<?= (int)$displayStock ?>" aria-label="تعداد" title="تعداد">
                    <button type="button" class="qty-step" id="qtyPlus" aria-label="افزایش تعداد" tabindex="-1">+</button>
                </div>
                <button type="submit" class="btn btn-primary" id="addToCartBtn" style="flex:1;">افزودن به سبد خرید</button>
            </form>
            <div class="qty-hint" id="qtyHint">با رسیدن تعداد به <b><?= $minWhole ?></b> عدد، قیمت کلی به‌صورت خودکار اعمال می‌شود.</div>
            <button type="button" class="qty-jump" id="qtyJump" hidden>رساندن تعداد به <b><?= $minWhole ?></b> عدد و گرفتن قیمت کلی</button>
            <div class="qty-total" id="qtyTotal" aria-live="polite"></div>
        </div>
    </div>

    <?php if ($reviewsOn): ?>
    <?php /* ============ نظرها + امتیاز ستاره‌ای و پرسش‌وپاسخ ============ */ ?>
    <div class="pr-wrap">

        <?php if ($prMsg): ?>
        <div class="flash flash-<?= $prMsg[0] === 'ok' ? 'success' : 'error' ?>">
            <?= icon($prMsg[0] === 'ok' ? 'check' : 'alert', 'ic-sm') ?> <?= h($prMsg[1]) ?>
        </div>
        <?php endif; ?>

        <!-- ---------------- نظرها و امتیاز ---------------- -->
        <section class="pr-sec" id="reviews">
            <div class="pr-head">
                <h2><?= icon('star') ?> نظرها و امتیاز مشتریان</h2>
                <div class="pr-score">
                    <?php if ($ratingCount): ?>
                    <span class="pr-score-num"><?= number_format($ratingAvg, 1) ?></span>
                    <span class="pr-score-of">از ۵</span>
                    <?= ratingStars($ratingAvg) ?>
                    <span class="pr-score-n"><?= $ratingCount ?> نظر</span>
                    <?php else: ?>
                    <span class="pr-score-none">هنوز نظری ثبت نشده — اولین نفر باشید</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($reviews): ?>
            <div class="pr-list">
                <?php foreach ($reviews as $rv): ?>
                <article class="pr-item">
                    <div class="pr-item-top">
                        <span class="pr-avatar"><?= icon('user', 'ic-sm') ?></span>
                        <span class="pr-author"><?= h(reviewAuthor($rv)) ?></span>
                        <?= ratingStars((int)$rv['rating'], null, 'rstars-sm') ?>
                        <span class="pr-date"><?= h(jDate($rv['created_at'])) ?></span>
                    </div>
                    <?php if (trim((string)$rv['body']) !== ''): ?>
                    <div class="pr-body"><?= nl2br(h($rv['body'])) ?></div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="pr-empty"><?= icon('message', 'ic-sm') ?> برای این محصول نظر تأییدشده‌ای ثبت نشده است.</div>
            <?php endif; ?>

            <?php if ($isCustomer): ?>
            <?php /* انتخابگر ستاره فقط با CSS کار می‌کند: رادیوها به ترتیب ۵..۱ در DOM
                     هستند و با row-reverse در چیدمان راست‌به‌چپ، ستارهٔ «۱» سمت راست
                     می‌افتد؛ پس بدون هیچ JSای هم انتخاب و هم پیش‌نمایشِ hover کار می‌کند. */ ?>
            <form method="POST" action="review-submit.php" class="pr-form">
                <input type="hidden" name="action" value="review">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <div class="pr-form-title"><?= icon('star', 'ic-sm') ?> نظر و امتیاز شما</div>
                <div class="star-input" role="radiogroup" aria-label="امتیاز شما">
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                    <input type="radio" name="rating" id="rate<?= $s ?>" value="<?= $s ?>"<?= $s === 5 ? ' checked' : '' ?>>
                    <label for="rate<?= $s ?>" title="<?= $s ?> ستاره"><?= icon('star') ?><span class="sr-only"><?= $s ?> ستاره</span></label>
                    <?php endfor; ?>
                </div>
                <textarea name="body" class="form-control pr-textarea" rows="3" maxlength="2000"
                          placeholder="تجربهٔ خود از این قطعه را بنویسید (کیفیت، اصالت، تناسب با خودرو…)" required></textarea>
                <div class="pr-form-foot">
                    <span class="pr-note">نظر شما پس از تأیید فروشگاه منتشر می‌شود.</span>
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('send', 'ic-sm') ?> ثبت نظر</button>
                </div>
            </form>
            <?php else: ?>
            <div class="pr-login"><?= icon('login', 'ic-sm') ?>
                برای ثبت نظر و امتیاز <a href="<?= h($loginBack) ?>">وارد حساب خود شوید</a>.
            </div>
            <?php endif; ?>
        </section>

        <!-- ---------------- پرسش و پاسخ ---------------- -->
        <section class="pr-sec" id="qa">
            <div class="pr-head">
                <h2><?= icon('help') ?> پرسش و پاسخ</h2>
                <span class="pr-score-n"><?= count($questions) ?> پرسش</span>
            </div>

            <?php if ($questions): ?>
            <div class="qa-list">
                <?php foreach ($questions as $q): ?>
                <article class="qa-item">
                    <div class="qa-q">
                        <span class="qa-tag qa-tag-q">پرسش</span>
                        <div class="qa-q-main">
                            <div class="qa-meta"><b><?= h(reviewAuthor($q)) ?></b> · <?= h(jDate($q['created_at'])) ?></div>
                            <div class="qa-body"><?= nl2br(h($q['body'])) ?></div>
                        </div>
                    </div>

                    <?php foreach ($q['answers'] as $an): ?>
                    <div class="qa-a<?= !empty($an['is_admin']) ? ' is-shop' : '' ?>">
                        <span class="qa-tag qa-tag-a"><?= icon('reply', 'ic-sm') ?> پاسخ</span>
                        <div class="qa-q-main">
                            <div class="qa-meta">
                                <b><?= h(reviewAuthor($an)) ?></b>
                                <?php if (!empty($an['is_admin'])): ?><span class="qa-badge-shop"><?= icon('store', 'ic-sm') ?> فروشگاه</span><?php endif; ?>
                                · <?= h(jDate($an['created_at'])) ?>
                            </div>
                            <div class="qa-body"><?= nl2br(h($an['body'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($isCustomer || $isAdminViewer): ?>
                    <details class="qa-reply">
                        <summary><?= icon('reply', 'ic-sm') ?> پاسخ دادن به این پرسش</summary>
                        <form method="POST" action="review-submit.php" class="qa-reply-form">
                            <input type="hidden" name="action" value="answer">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="parent_id" value="<?= (int)$q['id'] ?>">
                            <?php if ($isAdminViewer): ?><input type="hidden" name="as_admin" value="1"><?php endif; ?>
                            <textarea name="body" class="form-control" rows="2" maxlength="2000"
                                      placeholder="پاسخ شما…" required></textarea>
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <?= icon('send', 'ic-sm') ?> <?= $isAdminViewer ? 'ثبت پاسخ فروشگاه' : 'ثبت پاسخ' ?>
                            </button>
                        </form>
                    </details>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="pr-empty"><?= icon('help', 'ic-sm') ?> پرسشی برای این محصول ثبت نشده است.</div>
            <?php endif; ?>

            <?php if ($isCustomer): ?>
            <form method="POST" action="review-submit.php" class="pr-form">
                <input type="hidden" name="action" value="question">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <div class="pr-form-title"><?= icon('help', 'ic-sm') ?> پرسش تازه</div>
                <textarea name="body" class="form-control pr-textarea" rows="2" maxlength="2000"
                          placeholder="سؤال خود را دربارهٔ این قطعه بپرسید…" required></textarea>
                <div class="pr-form-foot">
                    <span class="pr-note">کارشناسان فروشگاه پاسخ می‌دهند. پرسش پس از تأیید نمایش داده می‌شود.</span>
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('send', 'ic-sm') ?> ثبت پرسش</button>
                </div>
            </form>
            <?php elseif (!$isAdminViewer): ?>
            <div class="pr-login"><?= icon('login', 'ic-sm') ?>
                برای پرسیدن سؤال <a href="<?= h('login.php?return=' . urlencode('product.php?id=' . $product['id'] . '#qa')) ?>">وارد حساب خود شوید</a>.
            </div>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
</div>

<?php if ($galleryCount > 1): ?>
<script>
/* ---------- کاروسل تصاویر محصول (بدون کتابخانه) ----------
   فقط src اسلات بزرگ عوض می‌شود؛ ابعاد اسلات با CSS ثابت است تا صفحه نپرد.
   تصاویر از قبل بارگذاری می‌شوند تا جابه‌جایی بدون پرش/سفیدی انجام شود. */
(function () {
    var main   = document.getElementById('pgMainImg');
    var strip  = document.getElementById('pgThumbs');
    var count  = document.getElementById('pgCount');
    if (!main || !strip) return;

    var thumbs = strip.querySelectorAll('.pg-thumb');
    if (thumbs.length < 2) return;

    var idx = 0, i;
    for (i = 0; i < thumbs.length; i++) {
        var pre = new Image();
        pre.src = thumbs[i].getAttribute('data-src');
    }

    function show(n, moveFocus) {
        if (n < 0) n = thumbs.length - 1;
        if (n >= thumbs.length) n = 0;
        idx = n;

        var src = thumbs[n].getAttribute('data-src');
        if (main.getAttribute('src') !== src) main.setAttribute('src', src);

        for (var k = 0; k < thumbs.length; k++) {
            var on = (k === n);
            if (on) thumbs[k].classList.add('is-active');
            else    thumbs[k].classList.remove('is-active');
            thumbs[k].setAttribute('aria-selected', on ? 'true' : 'false');
        }
        if (count) count.textContent = (n + 1) + ' / ' + thumbs.length;

        /* بندانگشتی فعال داخل نوار دیده شود — block:'nearest' مانع اسکرول عمودی صفحه می‌شود */
        if (thumbs[n].scrollIntoView) {
            try { thumbs[n].scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
            catch (e) { /* مرورگرهای قدیمی: بی‌اثر */ }
        }
        if (moveFocus) thumbs[n].focus();
    }

    for (i = 0; i < thumbs.length; i++) {
        (function (k) {
            thumbs[k].addEventListener('click', function () { show(k, false); });
        })(i);
    }

    var prev = document.getElementById('pgPrev');
    var next = document.getElementById('pgNext');
    if (prev) prev.addEventListener('click', function () { show(idx - 1, false); });
    if (next) next.addEventListener('click', function () { show(idx + 1, false); });

    /* در چیدمان راست‌به‌چپ، فلشِ راست به تصویر قبلی می‌رود */
    strip.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft')  { e.preventDefault(); show(idx + 1, true); }
        if (e.key === 'ArrowRight') { e.preventDefault(); show(idx - 1, true); }
    });
})();
</script>
<?php endif; ?>

<script>
(function(){
    var data = <?= $variantJson ?>;
    var minWhole = <?= (int)$minWhole ?>;

    /* قیمت پایهٔ نمایش‌داده‌شده (برای محصول بدون واریانت یا قبل از انتخاب واریانت) */
    var basePrice = {
        retail:    <?= (int)$displayRetail ?>,
        wholesale: <?= (int)$displayWhole ?>,
        rdisc:     <?= (int)$retailDisc ?>,
        wdisc:     <?= (int)$wholeDisc ?>
    };

    /* آیکون‌های SVG برای متن‌هایی که با JS ساخته می‌شوند (از includes/icons.php) */
    var ICON = {
        check: <?= json_encode(icon('check', 'ic-sm'), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>,
        x:     <?= json_encode(icon('x', 'ic-sm'), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
    };

    function num(n){ return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    /* ارقام فارسی/عربی → لاتین. فیلد تعداد از نوع text است تا هرچه کاربر تایپ کند
       خوانده و نرمال شود؛ با type=number مرورگر ارقام فارسی را «نامعتبر» می‌گرفت و
       value خالی برمی‌گشت، پس شرط «تعداد ≥ حداقل کلی» هرگز درست نمی‌شد. */
    var FA = '۰۱۲۳۴۵۶۷۸۹', AR = '٠١٢٣٤٥٦٧٨٩';
    function toLatinDigits(s){
        s = String(s == null ? '' : s);
        var out = '', i, ch, k;
        for (i = 0; i < s.length; i++) {
            ch = s.charAt(i);
            k = FA.indexOf(ch); if (k > -1) { out += k; continue; }
            k = AR.indexOf(ch); if (k > -1) { out += k; continue; }
            out += ch;
        }
        return out;
    }

    /* ---------- بخش مشترک: هم‌گام‌سازی «تعداد» با «نوع قیمت» ----------
       این بخش باید بالای بازگشتِ زودهنگامِ واریانت‌ها باشد تا برای محصولات
       بدون واریانت هم کار کند. قاعده: هرچه کاربر در فیلد تعداد بنویسد،
       اگر ≥ حد کلی باشد جعبهٔ «قیمت کلی» خودکار انتخاب می‌شود. */
    var qtyInput      = document.getElementById('qtyInput');
    var retailBox     = document.getElementById('retailBox');
    var wholeBox      = document.getElementById('wholeBox');
    var retailState   = document.getElementById('retailState');
    var wholeState    = document.getElementById('wholeState');
    var priceTypeInput = document.getElementById('priceTypeInput');
    var qtyHint       = document.getElementById('qtyHint');
    var qtyJump       = document.getElementById('qtyJump');
    var qtyTotal      = document.getElementById('qtyTotal');
    var qtyMinus      = document.getElementById('qtyMinus');
    var qtyPlus       = document.getElementById('qtyPlus');
    var selectedPriceType = 'retail';

    function maxQty(){
        var m = qtyInput ? parseInt(qtyInput.getAttribute('data-max'), 10) : NaN;
        return (isNaN(m) || m < 0) ? 0 : m;
    }

    function paintPriceType(type){
        selectedPriceType = type;
        if (retailBox) retailBox.classList.remove('price-active', 'price-dimmed');
        if (wholeBox)  wholeBox.classList.remove('price-active', 'price-dimmed');
        if (type === 'wholesale') {
            if (wholeBox)  wholeBox.classList.add('price-active');
            if (retailBox) retailBox.classList.add('price-dimmed');
            if (wholeState)  wholeState.innerHTML = ICON.check + ' این قیمت اعمال شد';
            if (retailState) retailState.innerHTML = '';
        } else {
            if (retailBox) retailBox.classList.add('price-active');
            if (wholeBox)  wholeBox.classList.add('price-dimmed');
            if (retailState) retailState.innerHTML = ICON.check + ' این قیمت اعمال شد';
            if (wholeState)  wholeState.innerHTML = '';
        }
        if (priceTypeInput) priceTypeInput.value = type;
    }

    function currentQty(){
        if (!qtyInput) return 1;
        var q = parseInt(toLatinDigits(qtyInput.value).replace(/[^0-9]/g, ''), 10);
        return isNaN(q) ? 0 : q;
    }

    /* هرچه در فیلد است را به ارقام لاتین و بازهٔ مجاز برمی‌گرداند (برای ارسال درست به سرور) */
    function normalizeField(clampLow){
        if (!qtyInput) return 0;
        var clean = toLatinDigits(qtyInput.value).replace(/[^0-9]/g, '').replace(/^0+(?=\d)/, '');
        var q = parseInt(clean, 10);
        if (isNaN(q)) q = clampLow ? 1 : 0;
        var mx = maxQty();
        if (mx > 0 && q > mx) q = mx;
        if (clampLow && q < 1) q = 1;
        var out = (q === 0 && !clampLow) ? '' : String(q);
        if (qtyInput.value !== out) qtyInput.value = out;
        return q;
    }

    function typeForQty(q){
        return (minWhole > 0 && q >= minWhole) ? 'wholesale' : 'retail';
    }

    function unitPrice(type){
        var p = (type === 'wholesale') ? basePrice.wholesale : basePrice.retail;
        var d = (type === 'wholesale') ? basePrice.wdisc : basePrice.rdisc;
        p = parseInt(p, 10) || 0;
        d = parseInt(d, 10) || 0;
        if (d > 0 && d < 100) p = Math.round(p * (100 - d) / 100);
        return p;
    }

    function updateHint(q, type){
        var mx = maxQty();
        var reachable = (minWhole > 0) && (mx === 0 || minWhole <= mx);
        if (qtyHint) {
            if (type === 'wholesale') {
                qtyHint.classList.add('is-wholesale');
                qtyHint.innerHTML = ICON.check + ' قیمت <b>کلی</b> اعمال شد (تعداد ' + q + ' ≥ ' + minWhole + ').';
            } else {
                qtyHint.classList.remove('is-wholesale');
                var need = minWhole - q;
                qtyHint.innerHTML = (q > 0 && need > 0 && reachable)
                    ? 'با <b>' + need + '</b> عدد بیشتر (مجموع ' + minWhole + ' عدد) قیمت <b>کلی</b> خودکار اعمال می‌شود.'
                    : 'با رسیدن تعداد به <b>' + minWhole + '</b> عدد، قیمت کلی به‌صورت خودکار اعمال می‌شود.';
            }
        }
        /* دکمهٔ میان‌بُر فقط وقتی معنی دارد که هنوز جزئی است و آستانه در دسترس است */
        if (qtyJump) qtyJump.hidden = !(type === 'retail' && reachable && q < minWhole);
    }

    function updateTotal(q, type){
        if (!qtyTotal) return;
        var u = unitPrice(type);
        if (!u || q < 1) { qtyTotal.innerHTML = ''; qtyTotal.classList.remove('is-wholesale'); return; }
        if (type === 'wholesale') qtyTotal.classList.add('is-wholesale');
        else qtyTotal.classList.remove('is-wholesale');
        qtyTotal.innerHTML =
            '<span class="qty-total-lbl">مجموع ' + q + ' عدد با قیمت ' +
            (type === 'wholesale' ? '<b>کلی</b>' : '<b>جزئی</b>') + ':</span> ' +
            '<b class="qty-total-num">' + num(u * q) + '</b>' +
            '<span class="qty-total-unit"> تومان &nbsp;·&nbsp; واحدی ' + num(u) + ' تومان</span>';
    }

    /* تعداد را دست نمی‌زند — فقط نوع قیمت را از تعداد نتیجه می‌گیرد */
    function syncFromQty(){
        var q = currentQty();
        var t = typeForQty(q);
        paintPriceType(t);
        updateHint(q, t);
        updateTotal(q, t);
    }

    /* کلیک روی جعبهٔ قیمت: تعداد را به کمترین مقدار لازم برای همان نوع می‌برد */
    function chooseType(type){
        if (!qtyInput) { paintPriceType(type); return; }
        var q = currentQty();
        if (type === 'wholesale') {
            if (q < minWhole) {
                var target = minWhole, mx = maxQty();
                if (mx > 0 && target > mx) target = mx;
                qtyInput.value = String(target);
            }
        } else if (q >= minWhole) {
            qtyInput.value = '1';
        }
        syncFromQty();
    }

    function bump(delta){
        if (!qtyInput) return;
        var q = currentQty() + delta;
        var mx = maxQty();
        if (q < 1) q = 1;
        if (mx > 0 && q > mx) q = mx;
        qtyInput.value = String(q);
        syncFromQty();
    }

    if (qtyInput) {
        qtyInput.addEventListener('input', function(){ normalizeField(false); syncFromQty(); });
        qtyInput.addEventListener('change', function(){ normalizeField(false); syncFromQty(); });
        qtyInput.addEventListener('keyup', syncFromQty);
        qtyInput.addEventListener('blur', function(){ normalizeField(true); syncFromQty(); });
        /* کلید بالا/پایین مثل فیلد عددی کار کند */
        qtyInput.addEventListener('keydown', function(e){
            if (e.key === 'ArrowUp')   { e.preventDefault(); bump(1); }
            if (e.key === 'ArrowDown') { e.preventDefault(); bump(-1); }
        });
    }
    if (qtyMinus) qtyMinus.addEventListener('click', function(){ bump(-1); });
    if (qtyPlus)  qtyPlus.addEventListener('click', function(){ bump(1); });
    if (qtyJump)  qtyJump.addEventListener('click', function(){ chooseType('wholesale'); if (qtyInput) qtyInput.focus(); });

    function boxClick(box, type){
        if (!box) return;
        box.addEventListener('click', function(){ chooseType(type); });
        box.addEventListener('keydown', function(e){
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); chooseType(type); }
        });
    }
    boxClick(retailBox, 'retail');
    boxClick(wholeBox, 'wholesale');

    /* آخرین سنگر: اگر فیلد با ارقام فارسی مانده بود، پیش از ارسال لاتین شود */
    var addForm = document.getElementById('add-to-cart-form');
    if (addForm) addForm.addEventListener('submit', function(){ normalizeField(true); syncFromQty(); });

    syncFromQty();

    /* ---------- از این‌جا به بعد فقط محصولات دارای واریانت ---------- */
    if (!data.length) return;

    var byCountry = {};
    data.forEach(function(v){
        if (!byCountry[v.country]) byCountry[v.country] = [];
        byCountry[v.country].push(v);
    });

    var uniqueCountries = Object.keys(byCountry);

    var cSel = document.getElementById('variantCountry');
    var mSel = document.getElementById('variantMaker');
    var rPrice = document.getElementById('retailPrice');
    var wPrice = document.getElementById('wholePrice');
    var wholeMinNote = document.getElementById('wholeMinNote');
    var stockEl = document.getElementById('stockDisplay');
    var vidInput = document.getElementById('variantIdInput');
    var addBtn = document.getElementById('addToCartBtn');
    var vInfo = document.getElementById('variantInfo');
    var vStock = document.getElementById('variantStock');
    var rBadge = document.getElementById('retailBadge');
    var wBadge = document.getElementById('wholeBadge');

    var currentVariant = null;

    uniqueCountries.forEach(function(c){ cSel.innerHTML += '<option value="'+c+'">'+c+'</option>'; });

    function priceHtml(orig, pct){
        var unit = ' <span style="font-size:0.7em;margin-right:6px;">تومان</span>';
        if (pct > 0 && pct < 100) {
            var np = Math.round(orig * (100 - pct) / 100);
            return '<span class="price-old">' + num(orig) + '</span> '
                 + '<span class="price-new">' + num(np) + unit + '</span>';
        }
        return num(orig) + unit;
    }

    /* قیمت‌ها/موجودی واریانت را به‌روز می‌کند و تعدادِ تایپ‌شده را حفظ می‌کند */
    function syncUI(){
        var v = currentVariant;
        if (!v) return;
        var rd = v.retail_discount || 0, wd = v.wholesale_discount || 0;
        rPrice.innerHTML = priceHtml(v.retail_price, rd);
        wPrice.innerHTML = priceHtml(v.wholesale_price, wd);
        if (rBadge) rBadge.innerHTML = (rd > 0 && rd < 100) ? ' <span class="price-badge">' + rd + '٪ تخفیف</span>' : '';
        if (wBadge) wBadge.innerHTML = (wd > 0 && wd < 100) ? ' <span class="price-badge">' + wd + '٪ تخفیف</span>' : '';
        wholeMinNote.textContent = 'حداقل ' + minWhole + ' عدد';
        stockEl.textContent = 'موجودی: ' + (v.stock > 0 ? v.stock + ' عدد' : 'ناموجود');
        vidInput.value = v.id;
        vInfo.style.display = 'block';
        vStock.innerHTML = (v.stock > 0 ? ICON.check + ' موجود: ' + v.stock + ' عدد' : ICON.x + ' ناموجود');
        addBtn.disabled = v.stock <= 0;
        addBtn.textContent = v.stock > 0 ? 'افزودن به سبد خرید' : 'ناموجود';

        /* قیمت پایهٔ محاسبهٔ «مجموع» هم باید همان واریانت انتخابی باشد */
        basePrice.retail    = v.retail_price;
        basePrice.wholesale = v.wholesale_price;
        basePrice.rdisc     = rd;
        basePrice.wdisc     = wd;

        qtyInput.setAttribute('data-max', v.stock);
        normalizeField(v.stock > 0);
        syncFromQty();
    }

    function updatePrices(){
        var country = cSel.value;
        var maker = mSel.value;
        if (!country || !maker) return;
        for (var i = 0; i < data.length; i++) {
            if (data[i].country === country && data[i].manufacturer === maker) {
                currentVariant = data[i];
                syncUI();
                return;
            }
        }
    }

    function fillMakers(country){
        mSel.innerHTML = '<option value="">-- انتخاب شرکت --</option>';
        if (country && byCountry[country]) {
            byCountry[country].forEach(function(m){
                mSel.innerHTML += '<option value="'+m.manufacturer+'">'+m.manufacturer+'</option>';
            });
        }
    }

    cSel.addEventListener('change', function(){
        fillMakers(this.value);
        updatePrices();
    });

    mSel.addEventListener('change', updatePrices);

    <?php if ($activeVariant): ?>
    /* واریانت فعال از ابتدا انتخاب شود — هم کشور و هم شرکت، وگرنه updatePrices()
       به‌خاطر خالی‌بودن «شرکت سازنده» زودهنگام برمی‌گشت و قیمت/موجودی به‌روز نمی‌شد. */
    cSel.value = <?= json_encode($activeVariant['country'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    fillMakers(cSel.value);
    mSel.value = <?= json_encode($activeVariant['manufacturer'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    updatePrices();
    <?php endif; ?>
})();
</script>

<?php require_once 'includes/footer.php';