<?php
$junk = ['api_db.txt','api_dbs.txt','api_post.txt','api_result.txt','cookies.txt',
'db_ajax.txt','db_detail.txt','db_page.txt','db_page_full.txt','dbaction.txt',
'dbjson.txt','dblist.txt','dblist2.txt','login_result.txt','plugins.txt',
'pma.txt','pma2.txt','pma3.txt','pma4.txt','pma5.txt','pma6.txt','pma_final.txt',
'pma_login.txt','pma_sso.txt','pma_sso2.txt','query_test.txt','ftp_home.txt',
'ftp_list.txt','dirs.txt','styles.css','mainjs.js',
'fix.php','fix2.php','fix-all.php','css-upload.php','u.php',
'db-test.php','db-importer.php','generate-samples.php',
'style.css.tmp','main.js.tmp','database.sql.tmp'];

$deleted = [];
$failed = [];
foreach ($junk as $f) {
    if (file_exists($f)) {
        if (unlink($f)) $deleted[] = $f; else $failed[] = $f;
    }
}
echo "Deleted: " . count($deleted) . " files. Failed: " . count($failed);
if ($failed) echo "<br>Failed: " . implode(', ', $failed);
$self = basename(__FILE__);
unlink(__FILE__);
echo "<br>Self-deleted. <a href='/'>Back to site</a>";