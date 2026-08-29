<?php
file_put_contents(__DIR__ . '/assets/css/style.css', file_get_contents('style_css_content'));
echo "CSS updated. <a href='/'>Go to site</a>";