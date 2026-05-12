<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=erden_pos', 'root', '');
    $stmt = $pdo->query('SELECT id, name, color FROM order_statuses ORDER BY id');
    foreach ($stmt as $row) {
        echo $row['id'] . ': ' . $row['name'] . ' (' . $row['color'] . ')' . "\n";
    }
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
