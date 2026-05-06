<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing PosLogService with username...\n";

// Simulate SetDatabase middleware
\Illuminate\Support\Facades\Config::set('database.connections.mysql.database', 'test_db');

// Test writeLog
\App\Services\PosLogService::writeLog(
    'usuarios',
    'user_enabled',
    'Usuario admin (ID: 1) habilitado',
    1
);

echo "writeLog executed\n";

// Test readLogs
$logs = \App\Services\PosLogService::readLogs('usuarios');
echo "readLogs returned " . count($logs) . " entries\n";

if (count($logs) > 0) {
    $lastLog = $logs[0];
    echo "Last entry details: " . $lastLog['details'] . "\n";
    echo "Last entry username: " . ($lastLog['username'] ?? 'N/A') . "\n";
    echo "Last entry user_id: " . $lastLog['user_id'] . "\n";
}

// Test getAvailableModules
$modules = \App\Services\PosLogService::getAvailableModules();
echo "Available modules: " . implode(', ', $modules) . "\n";

echo "\nTest completed!\n";
