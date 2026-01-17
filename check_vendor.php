<?php
echo 'vendor/autoload.php exists: ' . (file_exists(__DIR__.'/vendor/autoload.php') ? 'YES' : 'NO') . "<br>\n";
if (is_dir(__DIR__.'/vendor')) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/vendor', FilesystemIterator::SKIP_DOTS));
    $count = 0; $size = 0;
    foreach ($it as $f) { $count++; $size += $f->getSize(); if ($count>20) break; }
    echo "vendor folder exists; approx files checked: $count; sample total bytes: $size<br>\n";
} else {
    echo "vendor folder not found<br>\n";
}
?>
