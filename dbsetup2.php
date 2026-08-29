<?php
/* Run-once DB setup for: per-variant discounts, customer accounts (OTP),
   orders.customer_id, and settings table. Neutralized to 404 after running. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

function colExists($pdo, $table, $col) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    // 1) product_variants: per-variant discount columns
    if (!colExists($pdo, 'product_variants', 'retail_discount')) {
        $pdo->exec("ALTER TABLE product_variants ADD COLUMN retail_discount INT NOT NULL DEFAULT 0");
        echo "OK product_variants.retail_discount added\n";
    } else { echo "SKIP product_variants.retail_discount exists\n"; }

    if (!colExists($pdo, 'product_variants', 'wholesale_discount')) {
        $pdo->exec("ALTER TABLE product_variants ADD COLUMN wholesale_discount INT NOT NULL DEFAULT 0");
        echo "OK product_variants.wholesale_discount added\n";
    } else { echo "SKIP product_variants.wholesale_discount exists\n"; }

    // 2) customers (passwordless — phone OTP)
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mobile VARCHAR(15) NOT NULL UNIQUE,
        full_name VARCHAR(150) NOT NULL DEFAULT '',
        province VARCHAR(100) NOT NULL DEFAULT '',
        city VARCHAR(100) NOT NULL DEFAULT '',
        address TEXT NULL,
        postal_code VARCHAR(20) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK customers table ready\n";

    // 3) customer_otps (one active row per mobile)
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mobile VARCHAR(15) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mobile (mobile)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK customer_otps table ready\n";

    // 4) orders.customer_id (nullable — preserve guest orders)
    if (!colExists($pdo, 'orders', 'customer_id')) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN customer_id INT NULL, ADD INDEX idx_customer (customer_id)");
        echo "OK orders.customer_id added\n";
    } else { echo "SKIP orders.customer_id exists\n"; }

    // 5) settings (key/value)
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(64) PRIMARY KEY,
        setting_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK settings table ready\n";

    $seed = [
        'footer_about'      => 'فروشگاه اینترنتی لوازم یدکی خودرو - فروش کلی و جزئی',
        'footer_copyright'  => '',
        'contact_phone'     => '۰۲۱-۱۲۳۴۵۶۷۸',
        'contact_mobile'    => '۰۹۱۲۳۴۵۶۷۸۹',
        'contact_email'     => '',
        'contact_address'   => '',
        'working_hours'     => 'شنبه تا پنجشنبه ۹ تا ۱۸',
        'social_instagram'  => '',
        'social_telegram'   => '',
        'social_whatsapp'   => '',
        'sms_provider'      => 'smsir',
        'sms_api_key'       => '',
        'sms_template_id'   => '',
        'sms_param_name'    => 'CODE',
        'sms_test_mode'     => '1',
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($seed as $k => $v) { $ins->execute([$k, $v]); }
    echo "OK settings seeded (" . count($seed) . " keys)\n";

    echo "\nALL DONE\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
