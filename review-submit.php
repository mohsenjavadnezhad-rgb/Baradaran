<?php
/* ------------------------------------------------------------------
   ثبت نظر / پرسش / پاسخ  (POST → PRG → بازگشت به صفحهٔ محصول)
   ------------------------------------------------------------------
   این فایل عمدا مستقل است و includes/header.php را لود نمی‌کند، چون
   handleCartAction() سراسری هدر روی POSTهای این فرم‌ها اثر می‌گذاشت و
   الگوی «ارسال → ریدایرکت» را می‌شکست.

   دسترسی: نظر و پرسش فقط برای مشتری واردشده؛ پاسخ برای مشتری واردشده
   یا ادمین (پاسخ ادمین خودکار تأیید می‌شود و با نشان «فروشگاه» می‌آید).
   هر ردیف مشتری به‌صورت pending ذخیره می‌شود و پس از تأیید ادمین دیده می‌شود.
   ------------------------------------------------------------------ */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

/* فقط POST؛ هر ورود دیگری به فروشگاه برمی‌گردد */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') redirect('shop.php');

$action    = $_POST['action'] ?? '';
$productId = (int)($_POST['product_id'] ?? 0);
$backHash  = ($action === 'review') ? '#reviews' : '#qa';

if (!$productId || !in_array($action, ['review', 'question', 'answer'], true)) redirect('shop.php');
if (!reviewsReady()) redirect('product.php?id=' . $productId);

/* محصول باید واقعا وجود داشته باشد (جلوگیری از ثبت روی شناسهٔ دلخواه) */
if (!getProductById($productId)) redirect('shop.php');

$back = 'product.php?id=' . $productId;
function goBack($code, $hash) {
    global $back;
    redirect($back . '&msg=' . $code . $hash);
}

/* ---------- احراز هویت ---------- */
$isAdmin  = isLoggedIn();
$customer = currentCustomer();

/* برای پاسخ، ادمین هم مجاز است؛ برای نظر و پرسش فقط مشتری */
if (!$customer && !($action === 'answer' && $isAdmin)) {
    redirect('login.php?return=' . urlencode($back . $backHash));
}

/* ---------- متن ورودی ---------- */
$body = trim((string)($_POST['body'] ?? ''));
$squeezed = preg_replace('/\R{3,}/u', "\n\n", $body);      // خط خالی پشت‌سرهم کوتاه شود
if (is_string($squeezed)) $body = $squeezed;               // خطای preg → متن دست‌نخورده بماند
if (function_exists('mb_substr')) $body = mb_substr($body, 0, 2000, 'UTF-8');
else                              $body = substr($body, 0, 2000);

$len = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);

$authorName = $customer ? trim((string)($customer['full_name'] ?? '')) : 'فروشگاه';
if ($authorName === '') $authorName = 'مشتری';
$customerId = $customer ? (int)$customer['id'] : null;

try {
    if ($action === 'review') {
        /* ---------- نظر + امتیاز ستاره‌ای ---------- */
        $rating = (int)($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) goBack('badrating', '#reviews');
        if ($len < 3) goBack('short', '#reviews');

        /* هر مشتری برای هر محصول یک نظر — جلوگیری از تکرار امتیاز */
        $dup = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE product_id = ? AND customer_id = ?");
        $dup->execute([$productId, $customerId]);
        if ((int)$dup->fetchColumn() > 0) goBack('dup', '#reviews');

        $pdo->prepare("INSERT INTO product_reviews
                (product_id, customer_id, author_name, rating, body, status)
                VALUES (?,?,?,?,?, 'pending')")
            ->execute([$productId, $customerId, $authorName, $rating, $body]);
        goBack('review', '#reviews');

    } elseif ($action === 'question') {
        /* ---------- پرسش تازه ---------- */
        if ($len < 3) goBack('short', '#qa');
        $pdo->prepare("INSERT INTO product_qa
                (product_id, parent_id, customer_id, is_admin, author_name, body, status)
                VALUES (?, NULL, ?, 0, ?, ?, 'pending')")
            ->execute([$productId, $customerId, $authorName, $body]);
        goBack('question', '#qa');

    } else {
        /* ---------- پاسخ به یک پرسش تأییدشدهٔ همین محصول ---------- */
        if ($len < 3) goBack('short', '#qa');
        $parentId = (int)($_POST['parent_id'] ?? 0);

        $chk = $pdo->prepare("SELECT COUNT(*) FROM product_qa
            WHERE id = ? AND product_id = ? AND parent_id IS NULL AND status = 'approved'");
        $chk->execute([$parentId, $productId]);
        if ((int)$chk->fetchColumn() < 1) goBack('noq', '#qa');

        /* پاسخ فروشگاه بی‌درنگ منتشر می‌شود؛ پاسخ مشتری در صف تأیید می‌ماند */
        $asAdmin = ($isAdmin && !$customer) || ($isAdmin && ($_POST['as_admin'] ?? '') === '1');
        $pdo->prepare("INSERT INTO product_qa
                (product_id, parent_id, customer_id, is_admin, author_name, body, status)
                VALUES (?,?,?,?,?,?,?)")
            ->execute([
                $productId, $parentId,
                $asAdmin ? null : $customerId,
                $asAdmin ? 1 : 0,
                $asAdmin ? 'فروشگاه' : $authorName,
                $body,
                $asAdmin ? 'approved' : 'pending',
            ]);
        goBack($asAdmin ? 'answered' : 'answer', '#qa');
    }
} catch (Throwable $e) {
    goBack('failed', $backHash);
}
