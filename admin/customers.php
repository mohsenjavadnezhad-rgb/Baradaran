<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$err = '';
$msg = '';

/* ستون‌های همکار ممکن است هنوز ساخته نشده باشند (dbsetup3) */
function custColExists($pdo, $col) {
    try {
        return (bool)$pdo->query("SHOW COLUMNS FROM customers LIKE " . $pdo->quote($col))->fetch();
    } catch (Throwable $e) { return false; }
}
$hasPartnerCols = custColExists($pdo, 'customer_type');

/* تأیید / رد همکار */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasPartnerCols) {
    $cid = (int)($_POST['customer_id'] ?? 0);
    $act = $_POST['act'] ?? '';
    if ($cid > 0) {
        try {
            if ($act === 'approve') {
                $pdo->prepare("UPDATE customers SET customer_type='partner', partner_status='approved', partner_approved_at=NOW() WHERE id=?")->execute([$cid]);
                $msg = 'حساب همکار تأیید شد.';
            } elseif ($act === 'reject') {
                $pdo->prepare("UPDATE customers SET partner_status='rejected' WHERE id=?")->execute([$cid]);
                $msg = 'درخواست همکاری رد شد.';
            } elseif ($act === 'make_partner') {
                $pdo->prepare("UPDATE customers SET customer_type='partner', partner_status='approved', partner_approved_at=NOW(), partner_requested_at=COALESCE(partner_requested_at, NOW()) WHERE id=?")->execute([$cid]);
                $msg = 'حساب به همکار تأییدشده تغییر یافت.';
            } elseif ($act === 'make_retail') {
                $pdo->prepare("UPDATE customers SET customer_type='retail', partner_status='none' WHERE id=?")->execute([$cid]);
                $msg = 'حساب به مشتری عادی تغییر یافت.';
            }
        } catch (Throwable $e) {
            $err = 'خطای دیتابیس: ' . $e->getMessage();
        }
    }
}

/* تب فعال */
$tab = $_GET['type'] ?? 'all';
if (!in_array($tab, ['all', 'partner', 'retail', 'pending'], true)) $tab = 'all';
$q = trim($_GET['q'] ?? '');

$typeSel = $hasPartnerCols
    ? "COALESCE(c.customer_type,'retail') AS ctype, COALESCE(c.partner_status,'none') AS pstatus, c.partner_company, c.partner_requested_at, c.partner_approved_at"
    : "'retail' AS ctype, 'none' AS pstatus, '' AS partner_company, NULL AS partner_requested_at, NULL AS partner_approved_at";

$customers = [];
$counts = ['all' => 0, 'partner' => 0, 'retail' => 0, 'pending' => 0];
try {
    $where = [];
    $params = [];
    if ($hasPartnerCols) {
        if ($tab === 'partner')      $where[] = "COALESCE(c.customer_type,'retail') = 'partner'";
        elseif ($tab === 'retail')   $where[] = "COALESCE(c.customer_type,'retail') <> 'partner'";
        elseif ($tab === 'pending')  $where[] = "COALESCE(c.partner_status,'none') = 'pending'";
    }
    if ($q !== '') {
        $where[] = "(c.mobile LIKE ? OR c.full_name LIKE ?" . ($hasPartnerCols ? " OR c.partner_company LIKE ?" : "") . ")";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        if ($hasPartnerCols) $params[] = '%' . $q . '%';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("
        SELECT c.*, $typeSel,
               COALESCE(o.cnt, 0)   AS order_count,
               COALESCE(o.total, 0) AS total_spent,
               o.last_order
        FROM customers c
        LEFT JOIN (
            SELECT customer_id, COUNT(*) AS cnt, SUM(total_amount) AS total, MAX(created_at) AS last_order
            FROM orders
            WHERE status <> 'cancelled' AND customer_id IS NOT NULL
            GROUP BY customer_id
        ) o ON o.customer_id = c.id
        $whereSql
        ORDER BY total_spent DESC, c.created_at DESC
    ");
    $stmt->execute($params);
    $customers = $stmt->fetchAll();

    $counts['all'] = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    if ($hasPartnerCols) {
        $counts['partner'] = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE COALESCE(customer_type,'retail') = 'partner'")->fetchColumn();
        $counts['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE COALESCE(partner_status,'none') = 'pending'")->fetchColumn();
    }
    $counts['retail'] = $counts['all'] - $counts['partner'];
} catch (Throwable $e) {
    $err = 'جدول مشتریان هنوز ساخته نشده است یا خطایی رخ داد. (dbsetup2/dbsetup3 را اجرا کنید) — ' . $e->getMessage();
}

/* جمع کل همین لیست */
$sumSpent = 0; $sumOrders = 0;
foreach ($customers as $c) { $sumSpent += (float)$c['total_spent']; $sumOrders += (int)$c['order_count']; }

$tabs = [
    'all'     => 'همه',
    'partner' => 'همکاران',
    'retail'  => 'مشتریان',
    'pending' => 'در انتظار تأیید',
];

require_once __DIR__ . '/layout-top.php';
?>
<h2 style="margin-bottom:1rem;">مشتریان و همکاران</h2>

<?php if ($err): ?><div class="flash flash-error"><?= h($err) ?></div><?php endif; ?>
<?php if ($msg): ?><div class="flash flash-success"><?= h($msg) ?></div><?php endif; ?>
<?php if (!$hasPartnerCols && !$err): ?>
<div class="flash" style="background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.35);color:#FBBF24;">
  ستون‌های تفکیک همکار/مشتری ساخته نشده‌اند. برای فعال‌شدن تب‌ها و تأیید همکار، اسکریپت <code dir="ltr">dbsetup3.php</code> را یک‌بار اجرا کنید.
</div>
<?php endif; ?>

<div class="cust-tabs">
  <?php foreach ($tabs as $key => $label): ?>
  <a class="cust-tab <?= $tab === $key ? 'active' : '' ?>" href="customers.php?type=<?= $key ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">
    <?= h($label) ?> <span class="cust-tab-n"><?= (int)$counts[$key] ?></span>
  </a>
  <?php endforeach; ?>
  <form method="GET" class="cust-search">
    <input type="hidden" name="type" value="<?= h($tab) ?>">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="جستجوی موبایل / نام / فروشگاه" class="form-control">
    <button type="submit" class="btn btn-secondary btn-sm">جستجو</button>
  </form>
</div>

<div class="cust-sum">
  <div><span>تعداد در این لیست</span><b><?= count($customers) ?></b></div>
  <div><span>مجموع سفارش‌ها</span><b><?= number_format($sumOrders) ?></b></div>
  <div><span>مجموع خرید</span><b><?= formatPrice($sumSpent) ?></b></div>
</div>

<?php if ($customers): ?>
<table class="admin-table">
  <thead>
    <tr>
      <th>#</th>
      <th>نوع</th>
      <th>موبایل</th>
      <th>نام / فروشگاه</th>
      <th>شهر</th>
      <th>تعداد خرید</th>
      <th>مجموع خرید</th>
      <th>آخرین خرید</th>
      <th>عضویت</th>
      <th>عملیات</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td>
      <td>
        <?php if ($c['ctype'] === 'partner'): ?>
          <?php if ($c['pstatus'] === 'approved'): ?><span class="badge-partner">همکار <?= icon('check', 'ic-sm') ?></span>
          <?php elseif ($c['pstatus'] === 'rejected'): ?><span class="badge-retail">همکار (رد شده)</span>
          <?php else: ?><span class="badge-pending">همکار (در انتظار)</span><?php endif; ?>
        <?php else: ?>
          <span class="badge-retail">مشتری</span>
        <?php endif; ?>
      </td>
      <td dir="ltr" style="text-align:right;">
        <?php if (isValidMobile((string)$c['mobile'])): ?>
        <?= h($c['mobile']) ?>
        <?php else: ?>
        <span style="color:var(--text-muted);" title="حساب مهمان — بدون شمارهٔ موبایل واقعی؛ شمارهٔ تماس واقعی هر سفارش را در همان سفارش ببینید.">مهمان</span>
        <?php endif; ?>
      </td>
      <td>
        <a href="customer-detail.php?id=<?= (int)$c['id'] ?>" style="color:var(--red-light);"><?= h($c['full_name'] ?: '—') ?></a>
        <?php if (trim((string)$c['partner_company']) !== ''): ?>
        <div style="font-size:0.75rem;color:var(--text-muted);"><?= h($c['partner_company']) ?></div>
        <?php endif; ?>
      </td>
      <td><?= h(trim(($c['province'] ?? '') . ' ' . ($c['city'] ?? '')) ?: '—') ?></td>
      <td><b><?= (int)$c['order_count'] ?></b></td>
      <td style="white-space:nowrap;"><?= (float)$c['total_spent'] > 0 ? formatPrice($c['total_spent']) : '—' ?></td>
      <td style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap;"><?= h($c['last_order'] ? jDate($c['last_order']) : '—') ?></td>
      <td style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap;"><?= h(jDate($c['created_at'])) ?></td>
      <td style="white-space:nowrap;">
        <a href="customer-detail.php?id=<?= (int)$c['id'] ?>" class="btn btn-secondary btn-sm">جزئیات</a>
        <?php if ($hasPartnerCols): ?>
          <?php if ($c['ctype'] === 'partner' && $c['pstatus'] !== 'approved'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
            <input type="hidden" name="act" value="approve">
            <button type="submit" class="btn btn-primary btn-sm">تأیید همکار</button>
          </form>
          <?php elseif ($c['ctype'] !== 'partner'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
            <input type="hidden" name="act" value="make_partner">
            <button type="submit" class="btn btn-secondary btn-sm" data-confirm="این حساب به همکار تأییدشده تغییر کند؟" data-confirm-icon="check" data-confirm-label="تأیید شود" data-confirm-tone="primary">تبدیل به همکار</button>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php elseif (!$err): ?>
<p style="color:var(--text-muted);">موردی در این دسته پیدا نشد.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php';
