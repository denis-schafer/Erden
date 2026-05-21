<?php
$fp = fsockopen("ssl://erden.com.ar", 443, $errno, $errstr, 5);
if (!$fp) { echo "FAIL: $errstr ($errno)\n"; exit; }
echo "CONNECTED via ssl://erden.com.ar:443\n";
$key = base64_encode(random_bytes(16));
$request = "GET /app/ihptrpstkum4nvz031lw?protocol=7&client=js&version=7.6.2 HTTP/1.1\r\n";
$request .= "Host: erden.com.ar\r\n";
$request .= "Upgrade: websocket\r\n";
$request .= "Connection: Upgrade\r\n";
$request .= "Sec-WebSocket-Key: $key\r\n";
$request .= "Sec-WebSocket-Version: 13\r\n";
$request .= "\r\n";
fwrite($fp, $request);
$response = fread($fp, 4096);
echo "RESPONSE: " . substr($response, 0, 500) . "\n";
if (strpos($response, "101") !== false) {
    echo "SUCCESS: WebSocket upgrade successful!\n";
} else {
    echo "FAIL: Expected 101, got different response\n";
}
fclose($fp);
