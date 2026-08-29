<?php
$dbHost='localhost';$dbName='yadaki_db';$dbUser='yadaki_dbuser';$dbPass='R4shAd3AbJnQBJCmfWAq';
$pdo=new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// Check if admin exists
$admin = $pdo->query("SELECT * FROM admins WHERE username = 'admin'")->fetch();
if ($admin) {
    echo "Admin exists: " . $admin['username'] . "<br>";
    // Reset password to admin123
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = 'admin'")->execute([$hash]);
    echo "Password reset to: <strong>admin123</strong><br>";
} else {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)")->execute(['admin', $hash]);
    echo "Admin created: admin / admin123<br>";
}

echo "<hr>";
echo "<a href='/admin/login.php' style='color:red;font-size:1.2rem;'>ورود به پنل مدیریت</a><br>";
echo "نام کاربری: <strong>admin</strong><br>";
echo "رمز عبور: <strong>admin123</strong><br>";
@unlink(__FILE__);