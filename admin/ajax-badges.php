<?php
/* شمارندهٔ زندهٔ نشان‌های منوی کناری ادمین (admin/layout-top.php) — خواستهٔ
   کاربر ۲۰۲۶-۰۹-۰۳: «هرکاری برای تأیید یا انجام است باید جلوی اون بخش با عدد
   مشخص باشه ... اون اعداد بدون رفرش کردن اتومات اضافه بشه». همان توابعی که
   خود layout-top.php برای رندر اول نشان‌ها صدا می‌زند، اینجا هم صدا زده
   می‌شوند تا هیچ‌وقت با هم اختلاف نداشته باشند. کلیدهای این JSON دقیقا همان
   data-badge روی هر <span class="am-badge"> است. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { http_response_code(403); exit; }

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'orders'      => (int)getPendingOrdersCount(),
    'partners'    => (int)partnerPendingCount(),
    'reviews'     => (int)pendingReviewsCount(),
    'partchecks'  => (int)partCheckPendingCount(),
    'stockchecks' => (int)stockCheckPendingCount(),
], JSON_UNESCAPED_UNICODE);
