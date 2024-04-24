<?php

use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use Illuminate\Foundation\Bus\PendingClosureDispatch;
use Illuminate\Foundation\Bus\PendingDispatch;

if (! function_exists('mqtt')) {
    function mqtt(string $topic, mixed $message, string $broker = 'local', ?int $qos = 0): PendingDispatch|PendingClosureDispatch
    {
        return dispatch(new MqttMessageJob($topic, $message, $broker, $qos));
    }
}
