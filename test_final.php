<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Final test of PosLogService...\n\n";

// Set database
\Illuminate\Support\Facades\Config::set('database.connections.mysql.database', 'test_db');

// Test 1: Write log for user
echo "1. Writing user log...\n";
\App\Services\PosLogService::writeLog(
    'usuarios',
    'user_enabled',
    'Usuario admin (ID: 1) habilitado',
    1
);

// Test 2: Write log for product
echo "2. Writing product log...\n";
\App\Services\PosLogService::writeLog(
    'productos',
    'product_enabled',
    'Producto Pizza (ID: 5) habilitado',
    1
);

// Test 3: Read logs
echo "\n3. Reading usuarios logs...\n";
$logs = \App\Services\PosLogService::readLogs('usuarios');
echo "Found " . count($logs) . " entries\n";
if (count($logs) > 0) {
    $last = $logs[0];
    echo "Last entry:\n";
    echo "  Timestamp: " . $last['timestamp'] . "\n";
    echo "  Action: " . $last['action'] . "\n";
    echo "  Details: " . $last['details'] . "\n";
    echo "  User ID: " . $last['user_id'] . "\n";
    echo "  Username: " . ($last['username'] ?? 'N/A') . "\n";
}

// Test 4: Get available modules
echo "\n4. Available modules...\n";
$modules = \App\Services\PosLogService::getAvailableModules();
echo "Modules: " . implode(', ', $modules) . "\n";

echo "\nTest completed!\n";
