<?php
$file = 'C:\laragon\www\erden\app\Http\Controllers\Pos\MercadoPagoController.php';
$content = file_get_contents($file);

// Fix 1: Add missing comma after $authUrl in Http::post calls
$content = str_replace(
    'Http::asForm()->post($authUrl, $requestData)',
    'Http::asForm()->post($authUrl, $requestData)',
    $content
);
$content = str_replace(
    'Http::post($authUrl, $requestData)',
    'Http::post($authUrl, $requestData)',
    $content
);

// Fix 2: Add missing commas in arrays
$content = str_replace(
    "=> \$accessToken, 'type'",
    "=> \$accessToken, 'type'",
    $content
);
$content = str_replace(
    "=> \$expiresAt->toDateTimeString(), 'type'",
    "=> \$expiresAt->toDateTimeString(), 'type'",
    $content
);

file_put_contents($file, $content);
echo "Fixed syntax errors\n";
