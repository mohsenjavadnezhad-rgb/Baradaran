<?php
/* جستجوی زندهٔ نوار بالای سایت — دراپ‌داون assets/js/search.js همین‌جا را
   صدا می‌زند. علاوه بر نام/شمارهٔ فنی/قیمت، حالا برند خودرو (از جدول
   categories، سرشاخه‌اش) و دسته‌بندی قطعه (part_categories) هم برمی‌گردد
   تا دراپ‌داون خودش را نشان بدهد — خواستهٔ کاربر: «برند ماشین و دسته‌بندی
   هم مشخص باشه». */
require_once 'includes/config.php';
require_once 'includes/db.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}

$search = '%' . $q . '%';
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.technical_number, p.retail_price, p.retail_discount, p.image,
           pc.name AS part_cat_name,
           brand.name AS brand_name
    FROM products p
    LEFT JOIN part_categories pc ON pc.id = p.part_category_id
    LEFT JOIN (
        SELECT prc.product_id, MIN(b.name) AS name
        FROM product_categories prc
        JOIN categories b ON b.id = prc.category_id AND b.parent_id IS NULL
        GROUP BY prc.product_id
    ) brand ON brand.product_id = p.id
    WHERE p.is_active = 1 AND (p.name LIKE ? OR p.technical_number LIKE ?)
    ORDER BY p.name
    LIMIT 10
");
$stmt->execute([$search, $search]);
$results = $stmt->fetchAll();

foreach ($results as &$r) {
    $price = (int)$r['retail_price'];
    $disc  = (int)($r['retail_discount'] ?? 0);
    if ($disc > 0 && $disc < 100) $price = (int)round($price * (100 - $disc) / 100);
    $r['price_formatted'] = number_format($price, 0, '.', ',');
    $r['has_discount'] = ($disc > 0 && $disc < 100);
    unset($r['retail_price'], $r['retail_discount']);
}
unset($r);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($results, JSON_UNESCAPED_UNICODE);
