<?php
if(file_exists(__DIR__.'/p_latest.php')){copy(__DIR__.'/p_latest.php',__DIR__.'/product.php');echo'OK';}
if(function_exists('opcache_reset'))opcache_reset();
@unlink(__FILE__);