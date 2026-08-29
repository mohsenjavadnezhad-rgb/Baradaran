<?php
/* نمونهٔ فایل تنظیمات — این فایل کپی می‌شود روی includes/config.php (که در
   .gitignore است و هرگز به گیت‌هاب نمی‌رود) و مقدارهای واقعی جایگزین می‌شوند. */
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'فروشگاه برادران');
define('SITE_URL', 'http://yadakii.ir');
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

define('ITEMS_PER_PAGE', 12);

session_start();
