<?php
/**
 * AutoPartsShop Setup Script
 * Run once to create database tables, insert sample data, and create admin user.
 * After setup is complete, DELETE this file from the server for security.
 */

$dbHost = $_POST['db_host'] ?? 'localhost';
$dbUser = $_POST['db_user'] ?? 'root';
$dbPass = $_POST['db_pass'] ?? '';
$dbName = $_POST['db_name'] ?? 'autoparts_shop';

$step = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 1) {
    try {
        $dsn = "mysql:host=$dbHost;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");

        $sql = file_get_contents(__DIR__ . '/database.sql');
        $pdo->exec($sql);

        $step = 2;
        $success = 'دیتابیس با موفقیت ایجاد و داده‌های اولیه درج شد.';
    } catch (Exception $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? '';

    if (strlen($adminPass) < 6) {
        $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
    } else {
        try {
            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET username = ?, password_hash = ? WHERE id = 1");
            $stmt->execute([$adminUser, $hash]);

            $configContent = "<?php
define('DB_HOST', '" . addslashes($dbHost) . "');
define('DB_NAME', '" . addslashes($dbName) . "');
define('DB_USER', '" . addslashes($dbUser) . "');
define('DB_PASS', '" . addslashes($dbPass) . "');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'فروشگاه برادران');
define('SITE_URL', 'http" . (isset($_SERVER['HTTPS']) ? 's' : '') . "://" . $_SERVER['HTTP_HOST'] . "');
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ITEMS_PER_PAGE', 12);

session_start();";

            file_put_contents(__DIR__ . '/includes/config.php', $configContent);

            $success = 'نصب با موفقیت انجام شد. می‌توانید وارد پنل مدیریت شوید.';
            $step = 3;
        } catch (Exception $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب فروشگاه برادران</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; background: #111827; color: #F9FAFB; direction: rtl; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .setup-box { background: #1F2937; border: 1px solid #374151; border-radius: 12px; padding: 2rem; width: 100%; max-width: 500px; }
        h2 { color: #DC2626; margin-bottom: 1.5rem; text-align: center; font-size: 1.5rem; }
        h3 { color: #F9FAFB; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.35rem; color: #D1D5DB; font-size: 0.85rem; }
        input { width: 100%; padding: 0.6rem; background: #374151; border: 1px solid #4B5563; border-radius: 4px; color: #F9FAFB; font-family: inherit; font-size: 0.9rem; }
        input:focus { border-color: #DC2626; outline: none; }
        button { width: 100%; padding: 0.75rem; background: #DC2626; border: none; border-radius: 4px; color: #fff; font-family: inherit; font-size: 0.95rem; cursor: pointer; margin-top: 0.5rem; }
        button:hover { background: #B91C1C; }
        .msg { padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; text-align: center; font-size: 0.85rem; }
        .msg-error { background: rgba(220,38,38,0.15); border: 1px solid #DC2626; color: #EF4444; }
        .msg-success { background: rgba(16,185,129,0.15); border: 1px solid #10B981; color: #10B981; }
        .btn-link { display: inline-block; background: #374151; color: #F9FAFB; padding: 0.6rem 1.25rem; border-radius: 4px; text-decoration: none; margin: 0.5rem; }
        .btn-link:hover { background: #4B5563; }
        .danger { color: #EF4444; font-weight: bold; }
    </style>
</head>
<body>
    <div class="setup-box">
        <h2>&#9881; نصب فروشگاه برادران</h2>

        <?php if ($error): ?>
        <div class="msg msg-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="msg msg-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <form method="POST">
            <input type="hidden" name="step" value="1">
            <h3>اطلاعات دیتابیس</h3>
            <div class="form-group">
                <label>هاست دیتابیس</label>
                <input type="text" name="db_host" value="localhost">
            </div>
            <div class="form-group">
                <label>نام کاربری</label>
                <input type="text" name="db_user" value="root">
            </div>
            <div class="form-group">
                <label>رمز عبور</label>
                <input type="text" name="db_pass" placeholder="در هاست‌های اشتراکی ایران معمولاً خالی است">
            </div>
            <div class="form-group">
                <label>نام دیتابیس</label>
                <input type="text" name="db_name" value="autoparts_shop">
            </div>
            <button type="submit">ایجاد دیتابیس و جداول</button>
        </form>

        <?php elseif ($step == 2): ?>
        <form method="POST">
            <input type="hidden" name="step" value="2">
            <h3>اطلاعات مدیر</h3>
            <div class="form-group">
                <label>نام کاربری مدیر</label>
                <input type="text" name="admin_user" value="admin">
            </div>
            <div class="form-group">
                <label>رمز عبور (حداقل ۶ کاراکتر)</label>
                <input type="password" name="admin_pass" required minlength="6">
            </div>
            <button type="submit">تکمیل نصب</button>
        </form>

        <?php elseif ($step == 3): ?>
        <div style="text-align:center;">
            <h3 style="color:#10B981;">&#10004; نصب با موفقیت انجام شد</h3>
            <p style="margin:1rem 0;color:#D1D5DB;">
                فایل <code>setup.php</code> را از سرور <span class="danger">حذف کنید</span>.
            </p>
            <a href="index.php" class="btn-link">&#127968; فروشگاه</a>
            <a href="admin/login.php" class="btn-link">&#128274; پنل مدیریت</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>