<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (isCustomerLoggedIn()) { redirect('account.php'); }

/* مقصد بازگشت پس از ورود (فقط مسیر داخلی نسبی) */
$return = $_REQUEST['return'] ?? '';
$return = str_replace(["\r", "\n"], '', $return);
if ($return !== '' && ($return[0] === '/' || strpos($return, '://') !== false || strpos($return, '\\') !== false)) $return = '';

/* نوع ورود: مشتری (retail) یا همکار (partner).
   روی قیمت‌گذاری اثری ندارد — قیمت کلی برای همه با رسیدن تعداد به آستانه اعمال می‌شود.
   این انتخاب فقط برای شناسایی حساب، تفکیک در پنل مدیریت و آمار است. */
function normalizeCtype($v) { return ($v === 'partner') ? 'partner' : 'retail'; }
$ctype = normalizeCtype($_REQUEST['type'] ?? ($_SESSION['otp_type'] ?? 'retail'));

/* غیرفعال‌کردن یکی از دو تب ورود، از تنظیمات ← ثبت سفارش/ورود.
   وقتی یکی خاموش است، نوع ورود همیشه همان یکی دیگر است — حتی اگر URL/فرم
   ?type= دیگری بخواهد (دور زدن با لینک مستقیم هم بی‌اثر می‌ماند). */
$partnerOff = loginPartnerDisabled();
$retailOff  = loginRetailDisabled();
if ($partnerOff) $ctype = 'retail';
if ($retailOff)  $ctype = 'partner';
$bothTabsOn = !$partnerOff && !$retailOff;

/* آیا ورود به کد تأیید پیامکی نیاز دارد؟ ادمین از «تنظیمات سایت ← پیامک»
   خاموش/روشن می‌کند. با خاموش‌بودن، مرحلهٔ «کد» کلا حذف می‌شود و ورود
   یک‌مرحله‌ای است؛ پس هر مرحلهٔ نیمه‌کارهٔ قبلی هم از نشست پاک می‌شود تا
   کاربری که وسط دریافت کد بوده روی صفحهٔ کد بی‌کاربرد نماند. */
$otpOn = loginOtpRequired();
if (!$otpOn) unset($_SESSION['otp_mobile']);

$step = 'mobile';
$notice = '';
$error = '';
$isTest = false;
$mobilePending = $_SESSION['otp_mobile'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($return === '' && !empty($_SESSION['otp_return'])) $return = $_SESSION['otp_return'];
    if (isset($_POST['ctype'])) $ctype = normalizeCtype($_POST['ctype']);
    /* حتی اگر فرم دستکاری شده باشد، تب خاموش قابل ورود نیست. */
    if ($partnerOff) $ctype = 'retail';
    if ($retailOff)  $ctype = 'partner';

    /* پس از ورود موفق: پاک‌کردن آثار مرحلهٔ ورود و رفتن به مقصد */
    $finish = function () use ($return) {
        $target = $return !== '' ? $return : ($_SESSION['otp_return'] ?? 'account.php');
        unset($_SESSION['otp_mobile'], $_SESSION['otp_type'], $_SESSION['otp_return']);
        if ($target === '') $target = 'account.php';
        redirect($target);
    };

    if ($act === 'change_mobile') {
        unset($_SESSION['otp_mobile']);
        $mobilePending = '';
        $step = 'mobile';
    } elseif ($act === 'send_code' && !$otpOn) {
        /* تأیید پیامکی خاموش است → ورود یک‌مرحله‌ای فقط با شمارهٔ موبایل.
           صحت خود شماره باز هم بررسی می‌شود تا رکورد بی‌ربط ساخته نشود. */
        $m = normalizeMobile($_POST['mobile'] ?? '');
        if (!isValidMobile($m)) {
            $error = 'شماره موبایل نامعتبر است.';
        } else {
            $c = findOrCreateCustomer($m, $ctype);
            if (!$c) {
                $error = 'ورود انجام نشد. لطفا دوباره تلاش کنید.';
            } else {
                loginCustomer($c);
                $finish();
            }
        }
    } elseif ($act === 'send_code') {
        $res = otpGenerateAndSend($_POST['mobile'] ?? '');
        if ($res['ok']) {
            $_SESSION['otp_mobile'] = $res['mobile'];
            $_SESSION['otp_type'] = $ctype;
            if ($return !== '') $_SESSION['otp_return'] = $return;
            $mobilePending = $res['mobile'];
            $step = 'code';
            $notice = 'کد تأیید به شمارهٔ ' . $res['mobile'] . ' ارسال شد.';
            $isTest = !empty($res['test']);
        } else {
            $error = $res['error'];
        }
    } elseif ($act === 'verify') {
        $mobile = $_SESSION['otp_mobile'] ?? '';
        if (!$otpOn) {
            /* ادمین وسط کار تأیید پیامکی را خاموش کرده و این فرم از صفحهٔ
               قدیمی باز ارسال شده — کاربر را به ورود یک‌مرحله‌ای برمی‌گردانیم. */
            $notice = 'ورود با کد تأیید غیرفعال شده است؛ فقط شمارهٔ موبایل خود را وارد کنید.';
            $step = 'mobile';
        } elseif ($mobile === '') {
            $error = 'ابتدا شمارهٔ موبایل را وارد کنید.';
        } else {
            $res = otpVerify($mobile, $_POST['code'] ?? '');
            if ($res['ok']) {
                $c = findOrCreateCustomer($mobile, normalizeCtype($_SESSION['otp_type'] ?? $ctype));
                loginCustomer($c);
                $finish();
            } else {
                $error = $res['error'];
                $step = 'code';
                $mobilePending = $mobile;
            }
        }
    }
} elseif ($mobilePending !== '') {
    $step = 'code';
    $ctype = normalizeCtype($_SESSION['otp_type'] ?? 'retail');
    if ($partnerOff) $ctype = 'retail';
    if ($retailOff)  $ctype = 'partner';
}

$isPartner = ($ctype === 'partner');
$retURL = $return !== '' ? '&return=' . urlencode($return) : '';

require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="auth-card auth-card-wide">
    <h1 class="auth-title"><?= icon('login') ?>ورود / ثبت‌نام</h1>

    <?php if ($step === 'mobile'): ?>
    <p class="auth-sub"><?= $bothTabsOn ? 'نوع حساب خود را انتخاب کنید و با شمارهٔ موبایل وارد شوید.' : 'با شمارهٔ موبایل وارد شوید.' ?> اگر حساب نداشته باشید، بار اول به‌صورت خودکار ساخته می‌شود.<?= $otpOn ? '' : ' در حال حاضر ورود بدون کد تأیید پیامکی انجام می‌شود.' ?></p>

    <?php /* اگر ادمین یکی از دو نوع ورود را از تنظیمات خاموش کرده، دیگر تبی
            برای انتخاب نیست — همان یکی مجاز، مستقیم و بدون سردرگمی. */ ?>
    <?php if ($bothTabsOn): ?>
    <div class="auth-tabs">
      <a class="auth-tab <?= $isPartner ? '' : 'active' ?>" href="login.php?type=retail<?= $retURL ?>">
        <span class="auth-tab-ic"><?= icon('bag') ?></span>
        <span>ورود مشتری</span>
        <span class="auth-tab-sub">خرید عادی</span>
      </a>
      <a class="auth-tab <?= $isPartner ? 'active' : '' ?>" href="login.php?type=partner<?= $retURL ?>">
        <span class="auth-tab-ic"><?= icon('briefcase') ?></span>
        <span>ورود همکار</span>
        <span class="auth-tab-sub">فروشگاه‌ها و تعمیرگاه‌ها</span>
      </a>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <p class="auth-sub">کد تأیید ارسال‌شده را وارد کنید.
      <span class="<?= $isPartner ? 'badge-partner' : 'badge-retail' ?>"><?= $isPartner ? 'ورود همکار' : 'ورود مشتری' ?></span>
    </p>
    <?php endif; ?>

    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="flash flash-success"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($isTest): ?><div class="flash auth-test">حالت آزمایشی فعال است؛ پیامک واقعی ارسال نشد. کد تأیید از پنل مدیریت (تنظیمات سایت) قابل مشاهده است.</div><?php endif; ?>

    <?php if ($step === 'code'): ?>
    <form method="POST" class="auth-form">
      <input type="hidden" name="act" value="verify">
      <input type="hidden" name="ctype" value="<?= $isPartner ? 'partner' : 'retail' ?>">
      <div class="form-group">
        <label>کد تأیید ۵ رقمی</label>
        <input type="text" name="code" class="form-control auth-code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" required autofocus dir="ltr" placeholder="- - - - -">
      </div>
      <button type="submit" class="btn btn-primary btn-block">تأیید و ورود</button>
      <p class="auth-alt">کد به شمارهٔ <b dir="ltr"><?= h($mobilePending) ?></b> ارسال شد.</p>
    </form>
    <form method="POST" style="margin-top:0.5rem;">
      <input type="hidden" name="act" value="change_mobile">
      <input type="hidden" name="ctype" value="<?= $isPartner ? 'partner' : 'retail' ?>">
      <button type="submit" class="btn btn-secondary btn-block">تغییر شماره / درخواست کد جدید</button>
    </form>
    <?php else: ?>
    <div class="auth-note">
      <?php if ($isPartner): ?>
      برای همکاران (فروشگاه‌ها، تعمیرگاه‌ها و خریداران عمده) یک حساب همکار ساخته می‌شود و پس از بررسی توسط پشتیبانی، نشان «همکار تأییدشده» می‌گیرید.
      <b>قیمت کلی</b> برای همهٔ مشتریان با رسیدن تعداد به حد تعیین‌شدهٔ هر کالا به‌صورت خودکار اعمال می‌شود و به تأیید نیاز ندارد.
      <?php else: ?>
      <?= $otpOn
          ? 'ورود با شمارهٔ موبایل و کد پیامکی است؛ نیازی به رمز عبور نیست.'
          : 'ورود فقط با شمارهٔ موبایل است؛ نه رمز عبور لازم است و نه کد پیامکی.' ?>
      اگر تعداد خرید شما از یک کالا به حد کلی آن برسد، <b>قیمت کلی</b> به‌صورت خودکار اعمال می‌شود.
      <?php endif; ?>
    </div>
    <form method="POST" class="auth-form">
      <input type="hidden" name="act" value="send_code">
      <input type="hidden" name="ctype" value="<?= $isPartner ? 'partner' : 'retail' ?>">
      <input type="hidden" name="return" value="<?= h($return) ?>">
      <div class="form-group">
        <label>شمارهٔ موبایل</label>
        <input type="text" name="mobile" class="form-control" inputmode="numeric" placeholder="09xxxxxxxxx" required autofocus dir="ltr" value="<?= h($_POST['mobile'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-block"><?php
        if (!$otpOn)        echo $isPartner ? 'ورود همکار' : 'ورود به حساب';
        else                echo $isPartner ? 'دریافت کد تأیید (همکار)' : 'دریافت کد تأیید';
      ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
