<?php
/* نقطهٔ پایانی JSON برای تولید شمارهٔ فنی خودکار (فقط برای ادمین وارد‌شده) */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string)($_POST['name'] ?? $_GET['name'] ?? ''));
$cats = $_POST['categories'] ?? $_GET['categories'] ?? [];
if (!is_array($cats)) $cats = array_filter(explode(',', (string)$cats));
$pid  = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'empty_name'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $code = generateTechnicalNumber($name, $cats, $pid);
    echo json_encode([
        'ok'     => true,
        'code'   => $code,
        'prefix' => partCodeFromName($name) . '-' . modelCodeFor($cats, $name),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server'], JSON_UNESCAPED_UNICODE);
}
