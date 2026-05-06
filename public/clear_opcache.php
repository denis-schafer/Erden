<?php
// Limpiar OPcache y verificar archivo
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache limpiado correctamente<br>";
} else {
    echo "opcache_reset() no disponible<br>";
}

// Verificar el archivo actual
$file = 'C:\laragon\www\erden\app\Http\Middleware\VerifyCsrfToken.php';
if (file_exists($file)) {
    $lines = file($file);
    echo "Archivo existe. Líneas: " . count($lines) . "<br>";
    echo "Última línea: " . trim(end($lines)) . "<br>";
} else {
    echo "Archivo NO existe<br>";
}
