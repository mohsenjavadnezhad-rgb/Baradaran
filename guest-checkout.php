<?php
/* «خرید بدون ثبت‌نام» — پنل مدیریت ← تنظیمات ← ثبت سفارش/ورود.
   مشتریِ واردنشده به‌جای صفحهٔ ورود (که کد تأیید/انتخاب نوع حساب می‌خواهد)
   فقط شمارهٔ موبایلش را اینجا می‌گذارد؛ بدون کدِ تأیید، بی‌صدا برایش حساب
   ساخته/پیدا و واردش می‌کنیم (همان الگوی findOrCreateCustomer+loginCustomer
   که در login.php برای حالتِ «پیامک خاموش» استفاده می‌شود) و به همان مسیری
   می‌رود که cart.php بدونِ این قابلیت مستقیم می‌فرستاد. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';

if (isCustomerLoggedIn() || !guestCheckoutEnabled()) { redirect('cart.php'); }
if (empty(getCartItems())) { redirect('cart.php'); }

/* مقصدِ بعدی فقط از میانِ مسیرهای شناخته‌شده پذیرفته می‌شود — نه هر آدرسی که
   در URL بیاید — تا این صفحه به‌عنوانِ ریدایرکتِ باز سوءاستفاده نشود. */
$allowedNext = ['checkout.php', 'part-check.php'];
$next = $_REQUEST['next'] ?? 'checkout.php';
if (!in_array($next, $allowedNext, true)) $next = 'checkout.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = normalizeMobile($_POST['mobile'] ?? '');
    if (!isValidMobile($mobile)) {
        $error = 'شمارهٔ موبایل نامعتبر است.';
    } else {
        $c = findOrCreateCustomer($mobile, 'retail');
        if (!$c) {
            $error = 'مشکلی پیش آمد. لطفاً دوباره تلاش کنید.';
        } else {
            loginCustomer($c);
            redirect($next);
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="auth-card">
    <h1 class="auth-title"><?= icon('bag') ?>ادامهٔ خرید بدونِ ثبت‌نام</h1>
    <p class="auth-sub">فقط شمارهٔ موبایلتان را بگذارید — نه کدِ تأیید لازم است و نه ثبت‌نامِ جداگانه‌ای؛ مستقیم به مراحلِ بعدی می‌روید.</p>

    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <div class="form-group">
        <label>شمارهٔ موبایل</label>
        <input type="text" name="mobile" class="form-control" inputmode="numeric" placeholder="09xxxxxxxxx" required autofocus dir="ltr" value="<?= h($_POST['mobile'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-block">ادامهٔ خرید</button>
    </form>

    <p class="auth-alt">حساب دارید؟ <a href="login.php?return=<?= urlencode($next) ?>">وارد شوید</a></p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
