<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        redirect('index.php');
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل مدیریت - <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=62">
</head>
<body class="login-page">
    <form method="POST" class="login-form">
        <h2><?= icon('lock') ?> ورود به پنل مدیریت</h2>
        <?php if (isset($error)): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
        <?php endif; ?>
        <div class="form-group">
            <label for="username">نام کاربری</label>
            <input type="text" name="username" id="username" class="form-control" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">رمز عبور</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">ورود</button>
    </form>
</body>
</html>