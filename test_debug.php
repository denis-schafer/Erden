<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\Illuminate\Support\Facades\Config::set('database.connections.mysql.database', 'test_db');

echo "Checking log file...\n";

$logDir = dirname(storage_path()) . '/logs/test_db';
$logFile = $logDir . '/usuarios-log.txt';

echo "Log file: $logFile\n";
echo "file_exists: " . (file_exists($logFile) ? 'YES' : 'NO') . "\n";

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    echo "Content:\n$content\n";
    
    $lines = explode("\n", trim($content));
    echo "Lines: " . count($lines) . "\n";
    
    foreach ($lines as $i => $line) {
        echo "Line $i: $line\n";
        $parsed = \App\Services\PosLogService::parseLogLine($line);
        if ($parsed) {
            echo "  Parsed: " . json_encode($parsed) . "\n";
        } else {
            echo "  Not parsed\n";
        }
    }
}

echo "\nDone!\n";
