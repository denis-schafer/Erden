<?php
// Test script to debug MercadoPago OAuth

// Simulate what the callback does
$redirectUri = 'https://experience-till-bloggers-facing.trycloudflare.com/mp/callback';

$requestData = [
    'client_id' => 'test',
    'client_secret' => 'test',
    'grant_type' => 'authorization_code',
    'code' => 'test',
    'redirect_uri' => $redirectUri,
    'code_verifier' => 'test',
];

echo "Testing redirect_uri parameter:\n";
echo "Value: " . $redirectUri . "\n";
echo "In request data: " . (isset($requestData['redirect_uri']) ? 'YES' : 'NO') . "\n";
echo "Empty check: " . (empty($requestData['redirect_uri']) ? 'EMPTY' : 'NOT EMPTY') . "\n";
