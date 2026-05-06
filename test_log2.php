<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Setting database to test_db...\n";

// Simulate SetDatabase middleware
\Illuminate\Support\Facades\Config::set('database.connections.mysql.database', 'test_db');

echo "Testing PosLogService...\n";

// Test writeLog
\App\Services\PosLogService::writeLog(
    'productos',
    'product_enabled',
    'Test product (ID: 1) enabled',
    1
);

echo "writeLog executed\n";

// Test readLogs
$logs = \App\Services\PosLogService::readLogs('productos');
echo "readLogs returned " . count($logs) . " entries\n";

if (count($logs) > 0) {
    echo "Last entry: " . json_encode($logs[0]) . "\n";
}

// Test getDatabaseName
$dbName = \App\Services\PosLogService::getDatabaseName();
echo "Database name: " . $dbName . "\n";

// Test getAvailableModules
$modules = \App\Services\PosLogService::getAvailableModules();
echo "Available modules: " . implode(', ', $modules) . "\n";

echo "\nTest completed!\n";
