<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "storage_path(): " . storage_path() . "\n";
echo "dirname(storage_path()): " . dirname(storage_path()) . "\n";
echo "Full log dir: " . dirname(storage_path()) . '/logs/test_db' . "\n";

$logDir = dirname(storage_path()) . '/logs/test_db';
echo "is_dir: " . (is_dir($logDir) ? 'YES' : 'NO') . "\n";

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    echo "Directory created\n";
}

$logFile = $logDir . '/usuarios-log.txt';
file_put_contents($logFile, "Test line\n", FILE_APPEND);
echo "File written to: $logFile\n";
echo "file_exists: " . (file_exists($logFile) ? 'YES' : 'NO') . "\n";
