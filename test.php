<?php
error_reporting(-1);
ini_set('display_errors', 1);
echo "step1 ";
require_once __DIR__.'/includes/config.php';
echo "step2 ";
require_once __DIR__.'/includes/db.php';
echo "step3 ";
require_once __DIR__.'/includes/functions.php';
echo "step4 ";
require_once __DIR__.'/includes/cart-functions.php';
echo "step5 ";
$brands = getAllBrands();
echo "step6 count=".count($brands)." ";
require_once __DIR__.'/includes/header.php';
echo "step7 DONE";