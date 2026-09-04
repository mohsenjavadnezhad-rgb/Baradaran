<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: customers.php'); exit; }

$err = '';
$msg = '';

function custColExists2($pdo, $col) {
    try {
        return (bool)$pdo->query("SHOW COLUMNS FROM customers LIKE " . $pdo->quote($col))->fetch();
    } catch (Throwable $e) { return false; }
}
$hasPartnerCols = custColExists2($pdo, 'customer_type');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasPartnerCols) {
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'approve') {
            $pdo->prepare("UPDATE customers SET customer_type='partner', partner_status='approved', partner_approved_at=NOW(), partner_requested_at=COALESCE(partner_requested_at, NOW()) WHERE id=?")->execute([$id]);
            $msg = 'حساب همکار تأیید شد.';
        } elseif ($act === 'reject') {
            $pdo->prepare("UPDATE customers SET partner_status='rejected' WHERE id=?")->execute([$id]);
            $msg = 'درخواست همکاری رد شد.';
        } elseif ($act === 'make_retail') {
            $pdo->prepare("UPDATE customers SET customer_type='retail', partner_status='none' WHERE id=?")->execute([$id]);
            $msg = 'حساب به مشتری عادی تغییر یافت.';
        }
    } catch (Throwable $e) {
        $err = 'خطای دیتابیس: ' . $e->getMessage();
    }
}

$c = null;
$orders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if ($c) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $orders = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $err = 'خطا در خواندن اطلاعات: ' . $e->getMessage();
}

if (!$c) {
    require_once __DIR__ . '/layout-top.php';
    echo '<div class="flash flash-error">مشتری یافت نشد.</div><a href="customers.php" class="btn btn-secondary">بازگشت به لیست</a>';
    require_once __DIR__ . '/layout-bottom.php';
    exit;
}

$ctype   = $hasPartnerCols ? ($c['customer_type'] ?? 'retail') : 'retail';
$pstatus = $hasPartnerCols ? ($c['partner_status'] ?? 'none') : 'none';

/* آمار خرید (سفارش لغوشده حساب نمی‌شود) */
$buyCount = 0; $buySum = 0; $cancelled = 0; $lastOrder = null; $firstOrder = null;
foreach ($orders as $o) {
    if ($o['status'] === 'cancelled') { $cancelled++; continue; }
    $buyCount++;
    $buySum += (float)$o['total_amount'];
    if ($lastOrder === null) $lastOrder = $o['created_at'];
    $firstOrder = $o['created_at'];
}
$avg = $buyCount > 0 ? ($buySum / $buyCount) : 0;

$series = jalaliMonthlySales($id, 12);

$statusLabels = ['pending' => 'در انتظار بررسی', 'confirmed' => 'تأیید شده', 'shipped' => 'ارسال شده', 'cancelled' => 'لغو شده'];

require_once __DIR__ . '/layout-top.php';
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
  <h2 style="margin:0;">
    <?= h($c['full_name'] ?: 'مشتری #' . (int)$c['id']) ?>
    <?php if ($ctype === 'partner'): ?>
      <?php if ($pstatus === 'approved'): ?><span class="badge-partner">همکار تأییدشده <?= icon('check', 'ic-sm') ?></span>
      <?php elseif ($pstatus === 'rejected'): ?><span class="badge-retail">همکار (رد شده)</span>
      <?php else: ?><span class="badge-pending">همکار — در انتظار تأیید</span><?php endif; ?>
    <?php else: ?>
      <span class="badge-retail">مشتری</span>
    <?php endif; ?>
  </h2>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <?php if ($hasPartnerCols): ?>
      <?php if ($pstatus !== 'approved'): ?>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="act" value="approve">
        <button type="submit" class="btn btn-primary btn-sm">تأیید به‌عنوان همکار</button>
      </form>
      <?php endif; ?>
      <?php if ($ctype === 'partner' && $pstatus === 'pending'): ?>
      <form method="POST" style="display:inline;" onsubmit="return confirm('درخواست همکاری رد شود؟');">
        <input type="hidden" name="act" value="reject">
        <button type="submit" class="btn btn-secondary btn-sm">رد درخواست</button>
      </form>
      <?php endif; ?>
      <?php if ($ctype === 'partner'): ?>
      <form method="POST" style="display:inline;" onsubmit="return confirm('به مشتری عادی تغییر کند؟');">
        <input type="hidden" name="act" value="make_retail">
        <button type="submit" class="btn btn-secondary btn-sm">تبدیل به مشتری عادی</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
    <a href="customers.php" class="btn btn-secondary btn-sm">بازگشت به لیست</a>
  </div>
</div>

<?php if ($err): ?><div class="flash flash-error"><?= h($err) ?></div><?php endif; ?>
<?php if ($msg): ?><div class="flash flash-success"><?= h($msg) ?></div><?php endif; ?>

<div class="dash-cards" style="margin-bottom:1.25rem;">
  <div class="dc dc-blue"><div class="dc-val"><?= number_format($buyCount) ?></div><div class="dc-lbl">تعداد خرید</div></div>
  <div class="dc dc-green"><div class="dc-val" style="font-size:1.1rem;"><?= number_format($buySum) ?></div><div class="dc-lbl">مجموع خرید (تومان)</div></div>
  <div class="dc dc-orange"><div class="dc-val" style="font-size:1.1rem;"><?= number_format(round($avg)) ?></div><div class="dc-lbl">میانگین هر خرید (تومان)</div></div>
  <div class="dc dc-red"><div class="dc-val"><?= number_format($cancelled) ?></div><div class="dc-lbl">سفارش لغوشده</div></div>
</div>

<div class="dash-grid-2">
  <div class="dg-box">
    <div class="dg-box-hd"><span class="dg-hd-t"><?= icon('chart') ?>نمودار خرید در ماه (۱۲ ماه اخیر)</span></div>
    <div class="dg-box-bd">
      <?= renderSalesBarChart($series) ?>
    </div>
  </div>
  <div class="dg-box">
    <div class="dg-box-hd"><span class="dg-hd-t"><?= icon('user') ?>مشخصات</span></div>
    <div class="dg-box-bd">
      <table class="admin-table cust-info">
        <tr><th>موبایل</th><td dir="ltr" style="text-align:right;">
          <?php if (isValidMobile((string)$c['mobile'])): ?>
          <?= h($c['mobile']) ?>
          <?php else: ?>
          <span style="color:var(--text-muted);" dir="rtl">حساب مهمان — بدون شمارهٔ موبایل واقعی؛ شمارهٔ تماس واقعی هر سفارش را در همان سفارش ببینید.</span>
          <?php endif; ?>
        </td></tr>
        <tr><th>نام</th><td><?= h($c['full_name'] ?: '—') ?></td></tr>
        <?php if ($hasPartnerCols): ?>
        <tr><th>فروشگاه / تعمیرگاه</th><td><?= h(($c['partner_company'] ?? '') !== '' ? $c['partner_company'] : '—') ?></td></tr>
        <?php endif; ?>
        <tr><th>استان / شهر</th><td><?= h(trim(($c['province'] ?? '') . ' ' . ($c['city'] ?? '')) ?: '—') ?></td></tr>
        <tr><th>آدرس</th><td><?= h($c['address'] ?: '—') ?></td></tr>
        <tr><th>کد پستی</th><td dir="ltr" style="text-align:right;"><?= h($c['postal_code'] ?: '—') ?></td></tr>
        <tr><th>تاریخ عضویت</th><td><?= h(jDate($c['created_at'], true)) ?></td></tr>
        <tr><th>آخرین ورود</th><td><?= h(jDate($c['last_login_at'] ?? null, true)) ?></td></tr>
        <tr><th>اولین خرید</th><td><?= h($firstOrder ? jDate($firstOrder) : '—') ?></td></tr>
        <tr><th>آخرین خرید</th><td><?= h($lastOrder ? jDate($lastOrder) : '—') ?></td></tr>
        <?php if ($hasPartnerCols && $ctype === 'partner'): ?>
        <tr><th>درخواست همکاری</th><td><?= h(jDate($c['partner_requested_at'] ?? null)) ?></td></tr>
        <tr><th>تأیید همکاری</th><td><?= h(jDate($c['partner_approved_at'] ?? null)) ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<div class="dg-box" style="margin-top:1.25rem;">
  <div class="dg-box-hd"><span class="dg-hd-t"><?= icon('clipboard-list') ?>سفارش‌ها (<?= count($orders) ?>)</span></div>
  <div class="dg-box-bd">
    <?php if ($orders): ?>
    <table class="admin-table">
      <thead><tr><th>#</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= (int)$o['id'] ?></td>
          <td style="white-space:nowrap;"><?= h(jDate($o['created_at'], true)) ?></td>
          <td style="white-space:nowrap;"><?= formatPrice($o['total_amount']) ?></td>
          <td><span class="order-status status-<?= h($o['status']) ?>"><?= h($statusLabels[$o['status']] ?? $o['status']) ?></span></td>
          <td><a href="order-detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm">مشاهده</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="color:var(--text-muted);">این مشتری هنوز سفارشی ثبت نکرده است.</p>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/layout-bottom.php';
