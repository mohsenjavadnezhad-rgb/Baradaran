<?php
file_put_contents(__DIR__.'/assets/css/style.css', '<?php /* replaced by fix2 */ ?>');
$ok = copy(__DIR__.'/style.css.tmp', __DIR__.'/assets/css/style.css');
echo $ok ? 'CSS OK' : 'CSS FAIL';
echo ' | ';
$ok2 = copy(__DIR__.'/main.js.tmp', __DIR__.'/assets/js/main.js');
echo $ok2 ? 'JS OK' : 'JS FAIL';
echo ' | DONE';