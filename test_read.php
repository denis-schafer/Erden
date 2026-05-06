<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\Illuminate\Support\Facades\Config::set('database.connections.mysql.database', 'test_db');

echo "Test readLogs()...\n";

$logs = \App\Services\PosLogService::readLogs('usuarios');
echo "Found " . count($logs) . " entries\n";

if (count($logs) > 0) {
    echo "\nLast 3 entries:\n";
    $start = max(0, count($logs) - 3);
    for ($i = $start; $i < count($logs); $i++) {
        echo ($i + 1) . ". " . json_encode($logs[$i]) . "\n";
    }
}

echo "\nDone!\n";
