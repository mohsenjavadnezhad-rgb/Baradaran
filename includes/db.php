<?php
require_once __DIR__ . '/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    die('<div style="text-align:center;margin-top:100px;font-family:Tahoma;direction:rtl;">'
        . '<h2>خطا در اتصال به دیتابیس</h2>'
        . '<p>لطفاً فایل database.sql را در phpMyAdmin اجرا کنید.</p>'
        . '<p>سپس اطلاعات اتصال را در includes/config.php بررسی نمایید.</p>'
        . '</div>');
}