<?php
echo 'output_buffering: ' . ini_get('output_buffering') . PHP_EOL;
echo 'output_handler: ' . ini_get('output_handler') . PHP_EOL;
echo 'mbstring.internal_encoding: ' . ini_get('mbstring.internal_encoding') . PHP_EOL;
echo 'mbstring.http_output: ' . ini_get('mbstring.http_output') . PHP_EOL;
echo 'default_charset: ' . ini_get('default_charset') . PHP_EOL;
echo 'zlib.output_compression: ' . ini_get('zlib.output_compression') . PHP_EOL;

$handlers = ob_list_handlers();
echo 'ob handlers: ' . (empty($handlers) ? 'none' : implode(', ', $handlers)) . PHP_EOL;
echo 'ob level: ' . ob_get_level() . PHP_EOL;
