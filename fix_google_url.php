<?php
$file = 'C:\laragon\www\erden\app\Http\Controllers\Pos\MercadoPagoController.php';
$content = file_get_contents($file);

// Fix 1: Google Charts URL - change "chrt" to "cht"
$content = str_replace(
    'chs=300x300&chrt=qr',
    'chs=300x300&cht=qr',
    $content
);

// Fix 2: Ensure we use api.mercadopago.com (not sandbox URL)
$content = str_replace(
    'https://api.sandbox.mercadopago.com/checkout/preferences',
    'https://api.mercadopago.com/checkout/preferences',
    $content
);

if (file_put_contents($file, $content)) {
    echo "File fixed successfully\n";
} else {
    echo "Error writing file\n";
}
