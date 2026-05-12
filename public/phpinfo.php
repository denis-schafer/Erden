<?php
header('Content-Type: text/plain; charset=utf-8');
echo "output_buffering: " . ini_get('output_buffering') . "\n";
echo "output_handler: " . ini_get('output_handler') . "\n";
echo "default_charset: " . ini_get('default_charset') . "\n";
echo "mbstring.http_output: " . ini_get('mbstring.http_output') . "\n";
echo "mbstring.internal_encoding: " . ini_get('mbstring.internal_encoding') . "\n";
echo "zlib.output_compression: " . ini_get('zlib.output_compression') . "\n";
echo "zlib.output_compression_level: " . ini_get('zlib.output_compression_level') . "\n";

$handlers = ob_list_handlers();
echo "ob handlers: " . (empty($handlers) ? 'none' : implode(', ', $handlers)) . "\n";
echo "ob level: " . ob_get_level() . "\n";

echo "\nTest binary output:\n";
echo bin2hex("PK\x03\x04") . "\n";
