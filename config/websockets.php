<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'host' => '0.0.0.0',
        'port' => 6001,
    ],

    'apps' => [
        [
            'key' => 'erden-key',
            'secret' => '',
            'name' => 'Erden POS',
            'enable_client_messages' => true,
            'enable_statistics' => true,
        ],
    ],

    'hosts' => [
        '127.0.0.1',
    ],
];