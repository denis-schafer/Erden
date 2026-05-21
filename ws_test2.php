<?php
$fp = @fsockopen("127.0.0.1", 8080, $errno, $errstr, 5);
if (!$fp) {
    echo "FAIL direct: $errstr ($errno)\n";
} else {
    echo "CONNECTED direct to 127.0.0.1:8080\n";
    $key = base64_encode(random_bytes(16));
    $request = "GET /app/ihptrpstkum4nvz031lw?protocol=7&client=js&version=7.6.2 HTTP/1.1\r\n";
    $request .= "Host: erden.com.ar\r\n";
    $request .= "Upgrade: websocket\r\n";
    $request .= "Connection: Upgrade\r\n";
    $request .= "Sec-WebSocket-Key: $key\r\n";
    $request .= "Sec-WebSocket-Version: 13\r\n";
    $request .= "\r\n";
    fwrite($fp, $request);
    $response = fread($fp, 1024);
    echo "DIRECT RESPONSE: " . substr($response, 0, 200) . "\n";
    echo strpos($response, "101") !== false ? "DIRECT OK\n" : "DIRECT FAIL\n";
    fclose($fp);
}
echo "---\n";
$fp2 = @fsockopen("ssl://erden.com.ar", 443, $errno, $errstr, 5);
if (!$fp2) {
    echo "FAIL HTTP via Nginx: $errstr ($errno)\n";
} else {
    echo "CONNECTED HTTP via ssl://erden.com.ar:443\n";
    $http_request = "GET /app/ihptrpstkum4nvz031lw?protocol=7&client=js&version=7.6.2 HTTP/1.1\r\n";
    $http_request .= "Host: erden.com.ar\r\n";
    $http_request .= "Accept: */*\r\n";
    $http_request .= "\r\n";
    fwrite($fp2, $http_request);
    $response2 = fread($fp2, 4096);
    echo "HTTP RESPONSE: " . substr($response2, 0, 500) . "\n";
    fclose($fp2);
}
