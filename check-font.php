<?php
// save as checkfont.php in project root and open in browser
var_dump(is_readable(__DIR__ . '/fonts/NotoSansBengali-Regular.ttf'));
echo "<p>realpath: " . realpath(__DIR__ . '/fonts/NotoSansBengali-Regular.ttf') . "</p>";
?>
