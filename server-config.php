<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'yadaki_db');
define('DB_USER', 'yadaki_dbuser');
define('DB_PASS', 'R4shAd3AbJnQBJCmfWAq');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'فروشگاه برادران');
define('SITE_URL', 'http://localhost/AutoPartsShop');
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

define('ITEMS_PER_PAGE', 12);

session_start();