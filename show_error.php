<?php
// show_error.php — temporary, remove after debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3>Basic PHP info</h3>";
echo "<pre>";
echo "PHP SAPI: " . PHP_SAPI . "\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Disabled functions: " . ini_get('disable_functions') . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n";
echo "</pre>";

// Try to include your main generator to capture thrown error
try {
    // require the same file that returns 500 (adjust path if needed)
    require __DIR__ . '/generate_pdf_chrome.php';
} catch (Throwable $ex) {
    echo "<h3>Caught exception</h3>";
    echo "<pre>" . htmlspecialchars($ex->getMessage()) . "\n\n" . $ex->getTraceAsString() . "</pre>";
}
