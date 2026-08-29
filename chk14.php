<?php
/* بررسی موقت — گزینهٔ «تأیید شماره برای ورود». محلی/یک‌بارمصرف، با کلید محافظت شده. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
if (($_GET['key'] ?? '') !== 'c14otp7731') { http_response_code(404); exit('404'); }
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

function say($k, $v) { echo str_pad($k, 40, '.') . ' ' . $v . "\n"; }
function yn($b) { return $b ? 'YES' : 'no'; }

$TEST_MOBILE = '09000000001';
$m = $_GET['m'] ?? 'state';

echo "=== mode: $m ===\n";

if ($m === 'off') {
    setSetting('login_otp_required', '0');
    getAllSettings(true);
} elseif ($m === 'on') {
    setSetting('login_otp_required', '1');
    getAllSettings(true);
} elseif ($m === 'clean') {
    $st = $pdo->prepare("SELECT id FROM customers WHERE mobile = ?");
    $st->execute([$TEST_MOBILE]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if (!$cid) {
        say('test customer', 'not present — nothing to clean');
    } else {
        $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
        $st->execute([$cid]);
        $orders = (int)$st->fetchColumn();
        if ($orders > 0) {
            say('test customer', "id=$cid has $orders order(s) — NOT deleted");
        } else {
            $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$cid]);
            $pdo->prepare("DELETE FROM customer_otps WHERE mobile = ?")->execute([$TEST_MOBILE]);
            say('test customer', "id=$cid deleted");
        }
    }
}

getAllSettings(true);
say('login_otp_required (raw)', "'" . getSettingRaw('login_otp_required', '<absent>') . "'");
say('loginOtpRequired()', yn(loginOtpRequired()));
say('sms_test_mode (raw)', "'" . getSettingRaw('sms_test_mode', '<absent>') . "'");

$st = $pdo->prepare("SELECT id, mobile, customer_type, created_at FROM customers WHERE mobile = ?");
$st->execute([$TEST_MOBILE]);
$row = $st->fetch();
say('test customer ' . $TEST_MOBILE, $row ? ('id=' . $row['id'] . ' type=' . $row['customer_type'] . ' created=' . $row['created_at']) : 'absent');

$st = $pdo->query("SELECT COUNT(*) FROM customers");
say('customers total', (string)$st->fetchColumn());
