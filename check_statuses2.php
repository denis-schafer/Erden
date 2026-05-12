<?php
$pwd = '20dejulio';
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', $pwd);
    $stmt = $pdo->query('SHOW DATABASES');
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbs as $db) {
        if ($db == 'information_schema' || $db == 'mysql' || $db == 'performance_schema' || $db == 'sys') continue;
        echo "$db:\n";
        $pdo2 = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$db", 'root', $pwd);
        $tables = $pdo2->query("SHOW TABLES LIKE 'status_orders'");
        if ($tables && $tables->rowCount() > 0) {
            $rows = $pdo2->query('SELECT * FROM status_orders ORDER BY id');
            foreach ($rows as $r) {
                echo "  status_orders: id={$r['id']}, name={$r['name']}\n";
            }
        } else {
            echo "  (no status_orders table)\n";
        }
    }
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
