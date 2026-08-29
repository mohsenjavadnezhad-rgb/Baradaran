<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$ordersCount = getOrdersCount();
$productsCount = getProductsCount();
$pendingCount = getPendingOrdersCount();
$brandCount = $pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NULL")->fetchColumn();
$partCatCount = $pdo->query("SELECT COUNT(*) FROM part_categories")->fetchColumn();
$revenue = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();

/* آمار مشتریان و همکاران */
$custCount = 0; $partnerCount = 0; $pendingPartners = 0;
try {
    $custCount = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $partnerCount = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE COALESCE(customer_type,'retail') = 'partner'")->fetchColumn();
    $pendingPartners = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE COALESCE(partner_status,'none') = 'pending'")->fetchColumn();
} catch (Throwable $e) {}

/* نمودار مجموع مبلغ خرید در هر ماه (۱۲ ماه شمسی اخیر) */
$salesSeries = jalaliMonthlySales(null, 12);
$seriesSum = 0; $seriesOrders = 0; $bestMonth = null;
foreach ($salesSeries as $s) {
    $seriesSum += $s['total'];
    $seriesOrders += $s['count'];
    if ($bestMonth === null || $s['total'] > $bestMonth['total']) $bestMonth = $s;
}

$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();
$status = ['pending'=>'در انتظار','confirmed'=>'تأیید شده','shipped'=>'ارسال شده','cancelled'=>'لغو شده'];

require_once __DIR__ . '/layout-top.php';
?>

<div class="dash-cards">
  <div class="dc dc-red"><div class="dc-val"><?= $productsCount ?></div><div class="dc-lbl">محصولات فعال</div></div>
  <div class="dc dc-blue"><div class="dc-val"><?= $ordersCount ?></div><div class="dc-lbl">کل سفارشات</div></div>
  <div class="dc dc-green"><div class="dc-val"><?= formatPrice($revenue) ?></div><div class="dc-lbl">فروش کل</div></div>
  <div class="dc dc-orange"><div class="dc-val"><?= $pendingCount ?></div><div class="dc-lbl">سفارشات معلق</div></div>
  <div class="dc dc-purple"><div class="dc-val"><?= $custCount ?></div><div class="dc-lbl">مشتریان (<?= $partnerCount ?> همکار)</div></div>
  <div class="dc dc-gold"><div class="dc-val"><?= $pendingPartners ?></div><div class="dc-lbl">همکار در انتظار تأیید</div></div>
</div>

<div class="dg-box" style="margin-bottom:1.25rem;">
  <div class="dg-box-hd">
    <h3><?= icon('chart') ?> نمودار خرید در ماه — مجموع مبلغ (۱۲ ماه اخیر)</h3>
    <a href="customers.php">مشتریان و همکاران &#8592;</a>
  </div>
  <div class="dg-box-bd">
    <?= renderSalesBarChart($salesSeries) ?>
    <div class="cust-sum" style="margin:0.75rem 0.5rem 0.25rem;">
      <div><span>مجموع ۱۲ ماه</span><b><?= formatPrice($seriesSum) ?></b></div>
      <div><span>تعداد سفارش ۱۲ ماه</span><b><?= number_format($seriesOrders) ?></b></div>
      <div><span>پرفروش‌ترین ماه</span><b><?= $bestMonth && $bestMonth['total'] > 0 ? h($bestMonth['full']) : '—' ?></b></div>
    </div>
  </div>
</div>

<div class="dash-grid-2">
  <div class="dg-box">
    <div class="dg-box-hd"><h3>آخرین سفارشات</h3><a href="orders.php">مشاهده همه &#8592;</a></div>
    <div class="dg-box-bd">
      <table style="width:100%;font-size:0.78rem;border-collapse:collapse;">
        <tr style="color:var(--text-muted);font-size:0.7rem;border-bottom:1px solid var(--border-color);">
          <td style="padding:0.4rem;">#</td><td style="padding:0.4rem;">مشتری</td><td style="padding:0.4rem;">مبلغ</td><td style="padding:0.4rem;">تاریخ</td><td style="padding:0.4rem;">وضعیت</td>
        </tr>
        <?php foreach($recentOrders as $o): ?>
        <tr style="border-bottom:1px solid var(--border-color);">
          <td style="padding:0.4rem;">#<?= $o['id'] ?></td>
          <td style="padding:0.4rem;"><?= h($o['customer_name']) ?></td>
          <td style="padding:0.4rem;"><?= formatPrice($o['total_amount']) ?></td>
          <td style="padding:0.4rem;white-space:nowrap;"><?= h(jDate($o['created_at'])) ?></td>
          <td style="padding:0.4rem;"><span class="status-badge status-<?= $o['status'] ?>"><?= $status[$o['status']] ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$recentOrders): ?>
        <tr><td colspan="5" style="padding:1rem;text-align:center;color:var(--text-muted);">سفارشی ثبت نشده</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>
  <div class="dg-box">
    <div class="dg-box-hd"><h3>دسترسی سریع</h3></div>
    <div class="dg-box-bd" style="padding:1rem;display:flex;flex-direction:column;gap:0.5rem;">
      <a href="products.php" class="btn btn-primary btn-sm btn-block">+ افزودن محصول جدید</a>
      <a href="customers.php?type=pending" class="btn btn-secondary btn-sm btn-block">همکاران در انتظار تأیید<?= $pendingPartners > 0 ? ' (' . $pendingPartners . ')' : '' ?></a>
      <a href="categories.php" class="btn btn-secondary btn-sm btn-block">مدیریت برندها و مدل‌ها</a>
      <a href="orders.php" class="btn btn-secondary btn-sm btn-block">مدیریت سفارشات</a>
      <a href="../shop.php" target="_blank" class="btn btn-secondary btn-sm btn-block">&#8598; پیش‌نمایش فروشگاه</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/layout-bottom.php';