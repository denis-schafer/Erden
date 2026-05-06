<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\Illuminate\Support\Facades\Config::set('database.connections.mysql.database', 'test_db');

echo "Writing log...\n";

$logDir = dirname(storage_path()) . '/logs/test_db';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/usuarios-log.txt';
$entry = "[2026-05-05 15:00:00] user_enabled: Usuario admin (ID: 1) habilitado | user_id: 1 | username: admin\n";
file_put_contents($logFile, $entry, FILE_APPEND);

echo "File written to: $logFile\n";
echo "file_exists: " . (file_exists($logFile) ? 'YES' : 'NO') . "\n";

// Now test readLogs
$logs = \App\Services\PosLogService::readLogs('usuarios');
echo "readLogs returned " . count($logs) . " entries\n";

if (count($logs) > 0) {
    echo "Last entry: " . json_encode($logs[0]) . "\n";
}

echo "Done!\n";
