<?php

return [
    'default' => env('BROADCAST_DRIVER', 'pusher'),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('REVERB_APP_KEY', 'ihptrpstkum4nvz031lw'),
            'secret' => env('REVERB_APP_SECRET', 'gjxacjhhjs2q8hfe0shl'),
            'app_id' => env('REVERB_APP_ID', '346097'),
            'options' => [
                'useTLS' => false,
                'encrypted' => false,
                'host' => env('REVERB_HOST', 'localhost'),
                'port' => env('REVERB_PORT', '8080'),
                'scheme' => 'http',
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];