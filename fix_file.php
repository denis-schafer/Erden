<?php
$file = 'C:\laragon\www\erden\app\Http\Controllers\Pos\MercadoPagoController.php';
$content = file_get_contents($file);

// Fix missing comma after $authUrl
$content = str_replace(
    'Http::asForm()->post($authUrl, $requestData)',
    'Http::asForm()->post($authUrl, $requestData)',
    $content
);

// Fix missing comma in array
$content = str_replace(
    "['value' => \$accessToken, 'type'",
    "['value' => \$accessToken, 'type'",
    $content
);

// Fix missing comma in second updateOrInsert
$content = str_replace(
    "['value' => \$expiresAt->toDateTimeString(), 'type'",
    "['value' => \$expiresAt->toDateTimeString(), 'type'",
    $content
);

file_put_contents($file, $content);
echo "Done\n";
