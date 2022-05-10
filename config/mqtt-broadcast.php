<?php
// config for enzolarosa/MqttBroadcast
return [
    'logs' => [
        'enable' => env('MQTT_LOG_ENABLE', true),
        'connection' => env('MQTT_LOG_CONNECTION', 'logs'),
'table'=>env('MQTT_LOG_TABLE','mqtt_loggers'),

    ],

    'password' => env('MQTT_MASTER_PASS', \Illuminate\Support\Str\Str::random(32)),

    'connections' => [
        'local' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => env('MQTT_PORT', '1883'),
            'user' => env('MQTT_USER'),
            'password' => env('MQTT_PASSWORD'),
        ],

        'remote' => [
            'host' => env('MQTT_REMOTE_HOST', '127.0.0.1'),
            'port' => env('MQTT_REMOTE_PORT', '1883'),
            'user' => env('MQTT_REMOTE_USER'),
            'password' => env('MQTT_REMOTE_PASSWORD'),
        ],
    ]
];
