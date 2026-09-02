<?php
/* بررسی زندهٔ گروه «ه» — نظرها + پرسش‌وپاسخ + امتیاز ستاره‌ای.
   همهٔ درج‌ها داخل یک تراکنش انجام و در پایان ROLLBACK می‌شوند. */
if (($_GET['key'] ?? '') !== 'c11rev8815') { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

function say($k, $v) { echo str_pad($k, 40, '.') . ' ' . $v . "\n"; }
function yn($b)      { return $b ? 'YES' : '*** NO ***'; }
function has($s, $n) { return strpos($s, $n) !== false; }
function dirt($s) {
    foreach (['Warning:', 'Notice:', 'Deprecated:', 'Fatal error:', 'Undefined'] as $w) {
        if (strpos($s, $w) !== false) return $w;
    }
    return '';
}

echo "=== 1) اسکیما و توابع ===\n";
say('reviewsReady()', yn(reviewsReady()));
foreach (['product_reviews' => ['product_id','customer_id','author_name','rating','body','status','created_at'],
          'product_qa'      => ['product_id','parent_id','customer_id','is_admin','author_name','body','status','created_at']] as $tbl => $cols) {
    $st = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $st->execute([$tbl]);
    $have = $st->fetchAll(PDO::FETCH_COLUMN);
    say("$tbl cols", yn(!array_diff($cols, $have)) . ' (' . count($have) . ')');
}
foreach (['ratingsAll','getProductRating','ratingStars','productCardStars',
          'getProductReviews','getProductQa','reviewAuthor','pendingReviewsCount'] as $fn) {
    say("fn $fn", yn(function_exists($fn)));
}
foreach (['message','help','reply','send','star','store','trash','check','x','clock','check-circle'] as $ic) {
    say("icon $ic", yn(icon($ic) !== ''));
}

echo "\n=== 2) دادهٔ آزمایشی (در تراکنش) ===\n";
$zzPid = (int)$pdo->query("SELECT id FROM products WHERE is_active = 1 AND image <> '' ORDER BY id DESC LIMIT 1")->fetchColumn();
$zzName = $pdo->query("SELECT name FROM products WHERE id = $zzPid")->fetchColumn();
say('product', $zzPid . ' — ' . $zzName);

$pdo->beginTransaction();

$zzCid = $pdo->query("SELECT id FROM customers ORDER BY id LIMIT 1")->fetchColumn();
if ($zzCid === false) {
    $pdo->prepare("INSERT INTO customers (mobile, full_name) VALUES ('09000000000','zzchk مشتری')")->execute();
    $zzCid = (int)$pdo->lastInsertId();
}
$zzCid = (int)$zzCid;
say('customer id', (string)$zzCid);

$insR = $pdo->prepare("INSERT INTO product_reviews (product_id, customer_id, author_name, rating, body, status) VALUES (?,?,?,?,?,?)");
$insR->execute([$zzPid, $zzCid, 'zzchk علی', 5, 'zzchk-A کیفیت قطعه بسیار خوب بود.', 'approved']);
$insR->execute([$zzPid, null,   'zzchk رضا', 4, 'zzchk-B اصل بود ولی بسته‌بندی ضعیف.', 'approved']);
$insR->execute([$zzPid, null,   'zzchk سعید', 1, 'zzchk-C این نظر باید در صف بماند.', 'pending']);

$insQ = $pdo->prepare("INSERT INTO product_qa (product_id, parent_id, customer_id, is_admin, author_name, body, status) VALUES (?,?,?,?,?,?,?)");
$insQ->execute([$zzPid, null, null, 0, 'zzchk پرسنده', 'zzchk-Q1 آیا برای مدل ۱۴۰۰ هم مناسب است؟', 'approved']);
$zzQ1 = (int)$pdo->lastInsertId();
$insQ->execute([$zzPid, $zzQ1, null, 1, 'فروشگاه', 'zzchk-A1 بله، روی همهٔ مدل‌ها نصب می‌شود.', 'approved']);
$insQ->execute([$zzPid, $zzQ1, null, 0, 'zzchk مشتری', 'zzchk-A2 پاسخ مشتری که باید در صف بماند.', 'pending']);
$insQ->execute([$zzPid, null, null, 0, 'zzchk پرسندهٔ دوم', 'zzchk-Q2 پرسش در انتظار تأیید.', 'pending']);
say('rows inserted', '3 نظر + 4 پرسش‌وپاسخ');

echo "\n=== 3) توابع محاسبهٔ امتیاز ===\n";
list($zzAvg, $zzCnt) = getProductRating($zzPid);
say('avg (باید 4.5)', (string)$zzAvg);
say('count (باید 2)', (string)$zzCnt);
$zzStars = ratingStars($zzAvg, $zzCnt);
say('stars width:90%', yn(has($zzStars, 'width:90%')));
say('stars متن «2 نظر»', yn(has($zzStars, '2 نظر')));
say('stars base+fill', yn(has($zzStars, 'rstars-base') && has($zzStars, 'rstars-fill')));
$zzCard = productCardStars($zzPid);
say('card pc-stars + (2)', yn(has($zzCard, 'pc-stars') && has($zzCard, '(2)')));
say('card محصول بی‌نظر خالی', yn(productCardStars(999999) === ''));
say('approved reviews = 2', yn(count(getProductReviews($zzPid)) === 2));
say('all reviews = 3', yn(count(getProductReviews($zzPid, '')) === 3));
$zzQa = getProductQa($zzPid);
say('پرسش عمومی = 1', yn(count($zzQa) === 1));
say('پاسخ عمومی = 1', yn(count($zzQa) === 1 && count($zzQa[0]['answers']) === 1));
say('نام پاسخ = فروشگاه', yn(count($zzQa) === 1 && reviewAuthor($zzQa[0]['answers'][0]) === 'فروشگاه'));
say('pendingReviewsCount = 3', (string)pendingReviewsCount());

echo "\n=== 4) product.php — بازدیدکنندهٔ مهمان ===\n";
$_GET = ['id' => $zzPid];
ob_start(); include __DIR__ . '/product.php'; $zzPage = ob_get_clean();
say('pr-wrap', yn(has($zzPage, 'class="pr-wrap"')));
say('id="reviews" / id="qa"', yn(has($zzPage, 'id="reviews"') && has($zzPage, 'id="qa"')));
say('rstars-fill width:90%', yn(has($zzPage, 'rstars-fill') && has($zzPage, 'width:90%')));
say('عدد 4.5 در خلاصه', yn(has($zzPage, 'pr-score-num') && has($zzPage, '4.5')));
say('نظر تأییدشدهٔ A', yn(has($zzPage, 'zzchk-A ')));
say('نظر تأییدشدهٔ B', yn(has($zzPage, 'zzchk-B ')));
say('نظر pending پنهان', yn(!has($zzPage, 'zzchk-C ')));
say('پرسش تأییدشده Q1', yn(has($zzPage, 'zzchk-Q1')));
say('پاسخ فروشگاه A1', yn(has($zzPage, 'zzchk-A1')));
say('نشان qa-badge-shop', yn(has($zzPage, 'qa-badge-shop')));
say('پاسخ pending پنهان', yn(!has($zzPage, 'zzchk-A2')));
say('پرسش pending پنهان', yn(!has($zzPage, 'zzchk-Q2')));
say('مهمان: فرم ستاره ندارد', yn(!has($zzPage, 'class="star-input"')));
say('مهمان: دعوت به ورود', yn(has($zzPage, 'pr-login') && has($zzPage, 'login.php?return=')));
say('style.css?v=16', yn(has($zzPage, 'style.css?v=16')));
say('تصویر اصلی pgMainImg', yn(has($zzPage, 'id="pgMainImg"')));
say('یک‌تصویری: بی‌بندانگشتی', yn(!has($zzPage, 'pg-thumbs') && !has($zzPage, 'pg-arrow')));
say('استپر تعداد سالم', yn(has($zzPage, 'qty-stepper') && has($zzPage, 'qtyPlus')));
$d = dirt($zzPage); say('خروجی پاک', $d === '' ? 'YES' : "*** $d ***");
say('نقشهٔ پیام‌ها = 9', yn(isset($prMsgMap) && count($prMsgMap) === 9));
say('کلیدهای پیام کامل', yn(isset($prMsgMap) && !array_diff(
        ['review','question','answer','answered','dup','short','badrating','noq','failed'], array_keys($prMsgMap))));

echo "\n=== 5) product.php — مشتری واردشده + پیام PRG ===\n";
$_SESSION['customer_id'] = $zzCid;
unset($GLOBALS['__customer_cache']);
$_GET = ['id' => $zzPid, 'msg' => 'review'];
ob_start(); include __DIR__ . '/product.php'; $zzPage2 = ob_get_clean();
say('فرم ستارهٔ CSS-only', yn(has($zzPage2, 'class="star-input"') && has($zzPage2, 'name="rating"')));
say('۵ رادیوی ستاره', substr_count($zzPage2, 'name="rating"') . ' (باید 5)');
say('rate5 پیش‌فرض checked', yn(has($zzPage2, 'id="rate5" value="5" checked')));
say('اکشن فرم نظر', yn(has($zzPage2, 'action="review-submit.php"') && has($zzPage2, 'value="review"')));
say('فرم پرسش', yn(has($zzPage2, 'value="question"')));
say('فرم پاسخ + parent_id', yn(has($zzPage2, 'value="answer"') && has($zzPage2, 'name="parent_id"')));
say('as_admin برای مشتری نیست', yn(!has($zzPage2, 'name="as_admin"')));
say('فلاش «نظر شما ثبت شد»', yn(has($zzPage2, 'نظر شما ثبت شد')));
say('دعوت به ورود نیست', yn(!has($zzPage2, 'pr-login')));
$d = dirt($zzPage2); say('خروجی پاک', $d === '' ? 'YES' : "*** $d ***");

echo "\n=== 6) کارت‌های فهرست (search.php) ===\n";
$_GET = ['q' => $zzName];
ob_start(); include __DIR__ . '/search.php'; $zzList = ob_get_clean();
say('محصول در نتایج', yn(has($zzList, 'product.php?id=' . $zzPid)));
say('pc-stars روی کارت', yn(has($zzList, 'pc-stars')));
say('شمارندهٔ (2)', yn(has($zzList, 'pc-stars-n')));
$d = dirt($zzList); say('خروجی پاک', $d === '' ? 'YES' : "*** $d ***");

echo "\n=== 6b) کاروسل چندتصویری (گروه د) ===\n";
$insG = $pdo->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?,?,?)");
$insG->execute([$zzPid, 'zzchk_g1.jpg', 0]);
$insG->execute([$zzPid, 'zzchk_g2.jpg', 1]);
$_GET = ['id' => $zzPid];
ob_start(); include __DIR__ . '/product.php'; $zzPage3 = ob_get_clean();
say('بندانگشتی‌ها = 3', substr_count($zzPage3, 'class="pg-thumb') . ' (نوار + 3 دکمه = 4)');
say('شمارندهٔ «1 / 3»', yn(has($zzPage3, '>1 / 3<')));
say('دو فلش prev/next', yn(has($zzPage3, 'id="pgPrev"') && has($zzPage3, 'id="pgNext"')));
say('اولین بندانگشتی فعال', yn(has($zzPage3, 'pg-thumb is-active')));
say('نظرها هم‌زمان سالم', yn(has($zzPage3, 'pr-wrap') && has($zzPage3, 'zzchk-A ')));
$d = dirt($zzPage3); say('خروجی پاک', $d === '' ? 'YES' : "*** $d ***");

echo "\n=== 7) admin/reviews.php ===\n";
unset($_SESSION['customer_id']);
unset($GLOBALS['__customer_cache']);
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';
$_GET = [];
chdir(__DIR__ . '/admin');
ob_start(); include __DIR__ . '/admin/reviews.php'; $zzAdm = ob_get_clean();
chdir(__DIR__);
say('عنوان صفحه', yn(has($zzAdm, 'نظرها و پرسش‌وپاسخ محصولات')));
say('صف «در انتظار تأیید (3)»', yn(has($zzAdm, 'در انتظار تأیید (3)')));
say('کارت‌های آمار', yn(has($zzAdm, 'dash-cards') && has($zzAdm, 'نظر در انتظار')));
say('جدول admin-table', yn(substr_count($zzAdm, 'class="admin-table"') === 2));
say('نظر pending در صف', yn(has($zzAdm, 'zzchk-C ')));
say('پاسخ pending در صف', yn(has($zzAdm, 'zzchk-A2')));
say('پرسش pending در صف', yn(has($zzAdm, 'zzchk-Q2')));
say('نظرهای منتشرشده', yn(has($zzAdm, 'zzchk-A ') && has($zzAdm, 'zzchk-B ')));
say('نخ پرسش + پاسخ', yn(has($zzAdm, 'zzchk-Q1') && has($zzAdm, 'zzchk-A1')));
say('لینک تأیید', yn(has($zzAdm, 'set=approved&id=')));
say('لینک رد', yn(has($zzAdm, 'set=rejected&id=')));
say('لینک لغو انتشار', yn(has($zzAdm, 'set=pending&id=')));
say('لینک حذف + confirm', yn(has($zzAdm, 'del=') && has($zzAdm, 'return confirm(')));
say('فرم پاسخ فروشگاه', yn(has($zzAdm, 'name="answer_to"') && has($zzAdm, 'پاسخ رسمی فروشگاه')));
say('لنگر #q<id>', yn(has($zzAdm, 'id="q' . $zzQ1 . '"')));
say('لینک سایدبار reviews', yn(has($zzAdm, "href='reviews.php'") && has($zzAdm, 'نظرات و پرسش‌ها')));
say('نشان am-badge = 3', yn(has($zzAdm, "am-badge'>3<")));
say('style.css?v=16', yn(has($zzAdm, 'style.css?v=16')));
$d = dirt($zzAdm); say('خروجی پاک', $d === '' ? 'YES' : "*** $d ***");

echo "\n=== 8) بازگردانی ===\n";
$pdo->rollBack();
say('ROLLED BACK', yn(!$pdo->inTransaction()));
say('نظرهای zzchk باقی‌مانده', (string)(int)$pdo->query("SELECT COUNT(*) FROM product_reviews WHERE body LIKE 'zzchk%'")->fetchColumn());
say('پرسش‌وپاسخ zzchk باقی‌مانده', (string)(int)$pdo->query("SELECT COUNT(*) FROM product_qa WHERE body LIKE 'zzchk%'")->fetchColumn());
say('مشتری zzchk باقی‌مانده', (string)(int)$pdo->query("SELECT COUNT(*) FROM customers WHERE full_name LIKE 'zzchk%'")->fetchColumn());
say('تصویر گالری zzchk باقی‌مانده', (string)(int)$pdo->query("SELECT COUNT(*) FROM product_images WHERE image LIKE 'zzchk%'")->fetchColumn());
echo "\nDONE\n";
