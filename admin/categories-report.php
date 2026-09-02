<?php
/* گزارش چاپی/اکسل «برندها و مدل‌ها» — برای هر برند و هر مدل زیر آن، تعداد
   محصولات متصل (از جدول product_categories) نشان داده می‌شود. جمع هر برند،
   محصولات خود برند + همهٔ مدل‌های زیرش است.
   الگوی جعبه/چاپ از admin/invoice.php گرفته شده (toolbar + window.print())،
   با پیشوند کلاس جدا (rp-) تا با استایل فاکتور تداخل نکند. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$parents = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE category_id = ?");
$childStmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");

$tree = [];
$grandCats = 0; $grandProducts = 0;
foreach ($parents as $p) {
    $countStmt->execute([$p['id']]);
    $ownCount = (int)$countStmt->fetchColumn();

    $childStmt->execute([$p['id']]);
    $children = [];
    $childTotal = 0;
    foreach ($childStmt->fetchAll() as $c) {
        $countStmt->execute([$c['id']]);
        $cCount = (int)$countStmt->fetchColumn();
        $children[] = ['row' => $c, 'count' => $cCount];
        $childTotal += $cCount;
        $grandCats++;
    }
    $tree[] = ['row' => $p, 'own' => $ownCount, 'children' => $children, 'total' => $ownCount + $childTotal];
    $grandCats++;
    $grandProducts += $ownCount + $childTotal;
}

/* خروجی اکسل (CSV با BOM برای نمایش صحیح فارسی در اکسل) — پیش از هر خروجی HTML بررسی می‌شود */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="brands-models-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM
    $out = fopen('php://output', 'w');
    fputcsv($out, ['برند', 'مدل', 'نوع', 'تعداد محصولات']);
    foreach ($tree as $t) {
        fputcsv($out, [$t['row']['name'], '', 'برند', $t['own']]);
        foreach ($t['children'] as $c) {
            fputcsv($out, [$t['row']['name'], $c['row']['name'], 'مدل', $c['count']]);
        }
        fputcsv($out, [$t['row']['name'], 'جمع برند', '', $t['total']]);
    }
    fputcsv($out, ['جمع کل', '', '', $grandProducts]);
    fclose($out);
    exit;
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>گزارش برندها و مدل‌ها — <?= h(SITE_NAME) ?></title>
<style>
@font-face{font-family:Peyda;src:url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff2') format('woff2'),url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff') format('woff');font-display:swap}
*{margin:0;padding:0;box-sizing:border-box}
<?= iconBaseCss() ?>
body{font-family:Peyda,Tahoma,Arial,sans-serif;background:#EEF1F5;color:#111827;line-height:1.7;padding:1.5rem 1rem;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rp-toolbar{max-width:820px;margin:0 auto 1rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center}
.rp-btn{display:inline-flex;align-items:center;gap:0.4rem;padding:0.55rem 1.1rem;border:1px solid #D1D5DB;border-radius:8px;background:#fff;color:#111827;font-family:inherit;font-size:0.9rem;font-weight:600;cursor:pointer;text-decoration:none}
.rp-btn:hover{background:#F3F4F6}
.rp-btn-primary{background:#DC2626;border-color:#DC2626;color:#fff}
.rp-btn-primary:hover{background:#B91C1C}
.rp-back{margin-right:auto;color:#374151;font-size:0.85rem;text-decoration:none}
.rp-sheet{max-width:820px;margin:0 auto;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:1.75rem}
.rp-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;border-bottom:2px solid #DC2626;padding-bottom:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.rp-brand{font-size:1.3rem;font-weight:800;color:#DC2626}
.rp-brand small{display:block;font-size:0.78rem;font-weight:400;color:#6B7280;margin-top:0.2rem}
.rp-meta{text-align:left;font-size:0.82rem;color:#374151}
.rp-title{display:inline-block;background:#FEE2E2;color:#991B1B;border-radius:6px;padding:0.2rem 0.7rem;font-size:0.9rem;font-weight:700;margin-bottom:0.5rem}
table.rp-tbl{width:100%;border-collapse:collapse;font-size:0.85rem;margin-bottom:0.75rem}
table.rp-tbl th{background:#F3F4F6;color:#374151;font-size:0.78rem;font-weight:700;padding:0.55rem 0.5rem;border:1px solid #E5E7EB;text-align:right}
table.rp-tbl td{padding:0.5rem 0.5rem;border:1px solid #E5E7EB;vertical-align:middle}
table.rp-tbl td.num{white-space:nowrap;font-variant-numeric:tabular-nums;text-align:center}
tr.rp-brand-row td{background:#FEF2F2;font-weight:700}
tr.rp-sub-row td{background:#F9FAFB;color:#374151;font-weight:600}
.rp-total{max-width:820px;margin:0 auto;display:flex;justify-content:flex-end;gap:1rem;font-size:0.9rem;color:#374151}
.rp-total b{color:#111827}
@media print{
  body{background:#fff;padding:0}
  .rp-toolbar{display:none !important}
  .rp-sheet{max-width:none;border:none;border-radius:0;padding:0;margin:0}
  table.rp-tbl th{background:#F3F4F6 !important}
  tr.rp-brand-row td{background:#FEF2F2 !important}
  tr.rp-sub-row td{background:#F9FAFB !important}
  tr{page-break-inside:avoid}
  @page{size:A4;margin:12mm}
}
</style>
</head>
<body>

<div class="rp-toolbar">
  <button type="button" class="rp-btn rp-btn-primary" onclick="window.print()"><?= icon('printer') ?>چاپ گزارش</button>
  <a href="?export=csv" class="rp-btn"><?= icon('file-down') ?>خروجی اکسل (CSV)</a>
  <a href="categories.php" class="rp-back">بازگشت به دسته‌بندی‌ها &#8592;</a>
</div>

<div class="rp-sheet">
  <div class="rp-head">
    <div>
      <div class="rp-title">گزارش دسته‌بندی‌ها</div>
      <div class="rp-brand"><?= h(SITE_NAME) ?><small>برندها و مدل‌ها — به‌همراه تعداد محصولات هر دسته</small></div>
    </div>
    <div class="rp-meta">
      <div>تاریخ گزارش: <b><?= h(jDateLong(date('Y-m-d H:i:s'))) ?></b></div>
      <div>تعداد برندها: <b><?= count($parents) ?></b></div>
    </div>
  </div>

  <table class="rp-tbl">
    <thead>
      <tr>
        <th style="width:34px;">#</th>
        <th>نام</th>
        <th style="width:110px;">نوع</th>
        <th style="width:130px;">تعداد محصولات</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($tree as $t): ?>
      <tr class="rp-brand-row">
        <td class="num"><?= $i++ ?></td>
        <td><?= h($t['row']['name']) ?></td>
        <td class="num">برند</td>
        <td class="num"><?= number_format($t['own']) ?></td>
      </tr>
      <?php foreach ($t['children'] as $c): ?>
      <tr>
        <td class="num"></td>
        <td style="padding-right:1.5rem;color:#6B7280;">↳ <?= h($c['row']['name']) ?></td>
        <td class="num">مدل</td>
        <td class="num"><?= number_format($c['count']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if ($t['children']): ?>
      <tr class="rp-sub-row">
        <td class="num"></td>
        <td colspan="2">جمع «<?= h($t['row']['name']) ?>»</td>
        <td class="num"><?= number_format($t['total']) ?></td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$tree): ?>
      <tr><td colspan="4" style="text-align:center;color:#6B7280;">دسته‌بندی‌ای ثبت نشده است.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="rp-total">جمع کل محصولات دسته‌بندی‌شده: <b><?= number_format($grandProducts) ?></b></div>

</body>
</html>
