<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['2', '3'] as $db) {
    \Config::set('database.connections.mysql.database', $db);
    \DB::purge('mysql');
    \DB::reconnect('mysql');
    $cols = \DB::getSchemaBuilder()->getColumnListing('users');
    echo "DB $db: Has posnet_id = " . (in_array('posnet_id', $cols) ? 'YES' : 'NO') . "\n";
}
