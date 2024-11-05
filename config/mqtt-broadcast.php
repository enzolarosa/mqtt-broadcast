<?php

return [
    'logs' => [
        'enable' => env('MQTT_LOG_ENABLE', false),
        'queue' => env('MQTT_LOG_JOB_QUEUE', 'default'),
        'connection' => env('MQTT_LOG_CONNECTION', 'mysql'),
        'table' => env('MQTT_LOG_TABLE', 'mqtt_loggers'),
    ],

    'password' => env('MQTT_MASTER_PASS', Illuminate\Support\Str::random(32)),

    'queue' => [
        'name' => env('MQTT_JOB_QUEUE', 'default'),
        'listener' => env('MQTT_LISTENER_QUEUE', 'default'),
        'connection' => env('MQTT_JOB_CONNECTION', 'redis'),
        'middleware' => env('MQTT_JOB_MIDDLEWARE'),
    ],

    'connections' => [
        'local' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => env('MQTT_PORT', '1883'),
            'user' => env('MQTT_USER'),
            'password' => env('MQTT_PASSWORD'),
            'qos' => env('MQTT_QOS'),
            'prefix' => env('MQTT_PREFIX', ''),
        ],

        //        'remote' => [
        //            'host' => env('MQTT_REMOTE_HOST', '127.0.0.1'),
        //            'port' => env('MQTT_REMOTE_PORT', '1883'),
        //            'user' => env('MQTT_REMOTE_USER'),
        //            'password' => env('MQTT_REMOTE_PASSWORD'),
        //            'qos' => env('MQTT_REMOTE_QOS'),
        //            'prefix' => env('MQTT_REMOTE_PREFIX', ''),
        //        ],
    ],
];
