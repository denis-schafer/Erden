<?php
$files = [
    'app/Http/Controllers/Pos/PosStatisticsController.php',
    'routes/web.php',
];
foreach ($files as $file) {
    $content = file_get_contents(__DIR__ . '/' . $file);
    $hasBom = substr($content, 0, 3) === "\xEF\xBB\xBF";
    $first4 = bin2hex(substr($content, 0, 4));
    echo "$file: BOM=" . ($hasBom ? 'YES' : 'NO') . " first4=$first4" . PHP_EOL;
}

// Check common framework files
$framework = [
    'vendor/laravel/framework/src/Illuminate/Http/Response.php',
    'vendor/symfony/http-foundation/BinaryFileResponse.php',
    'vendor/symfony/http-foundation/Response.php',
];
foreach ($framework as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) continue;
    $content = file_get_contents(__DIR__ . '/' . $file);
    $hasBom = substr($content, 0, 3) === "\xEF\xBB\xBF";
    echo "$file: BOM=" . ($hasBom ? 'YES' : 'NO') . PHP_EOL;
}
