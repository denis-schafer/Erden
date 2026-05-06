<?php
$file = 'C:\laragon\www\erden\app\Http\Controllers\Pos\MercadoPagoController.php';
$content = file_get_contents($file);

// Fix missing comma after $authUrl in Http::post calls
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

// Fix missing commas in arrays: $accessToken, 'type'
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

// Fix missing commas in response()->json calls
$content = str_replace(
    "=> false, 'message'",
    "=> false, 'message'",
    $content
);
$content = str_replace(
    "=> true, 'message'",
    "=> true, 'message'",
    $content
);
$content = str_replace(
    "=> true, 'data'",
    "=> true, 'data'",
    $content
);
$content = str_replace(
    "=> true, 'access_token'",
    "=> true, 'access_token'",
    $content
);

file_put_contents($file, $content);
echo "Fixed syntax errors\n";
