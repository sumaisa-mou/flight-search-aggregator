<?php

return [
    'providers' => [
        'a' => [
            'url' => env('FLIGHT_PROVIDER_A_URL', 'http://localhost:8000/mock/provider-a'),
            'timeout' => env('FLIGHT_PROVIDER_A_TIMEOUT', 2),
        ],
        'b' => [
            'url' => env('FLIGHT_PROVIDER_B_URL', 'http://localhost:8000/mock/provider-b'),
            'timeout' => env('FLIGHT_PROVIDER_B_TIMEOUT', 2),
        ],
        'c' => [
            'url' => env('FLIGHT_PROVIDER_C_URL', 'http://localhost:8000/mock/provider-c'),
            'timeout' => env('FLIGHT_PROVIDER_C_TIMEOUT', 2),
        ]
    ]
];
