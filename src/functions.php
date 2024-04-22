<?php

use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use Illuminate\Foundation\Bus\PendingClosureDispatch;
use Illuminate\Foundation\Bus\PendingDispatch;

if (! function_exists('mqttMessage')) {
    function mqttMessage(string $topic, mixed $message, string $target, ?string $source = null, ?string $system_id = null, string $broker = 'local', ?int $qos = 0): MqttMessageJob
    {
        return new MqttMessageJob($topic, [
            'source' => $source ?? config('domes.type').' server',
            'target' => $target,
            'data' => $message,
            'system_uuid' => $system_id,
        ], $broker, $qos);
    }
}

if (! function_exists('mqtt')) {
    function mqtt(string $topic, mixed $message, string $target, ?string $source = null, ?string $system_id = null, string $broker = 'local', ?int $qos = 0): PendingDispatch|PendingClosureDispatch
    {
        return dispatch(mqttMessage($topic, $message, $target, $source, $system_id, $broker, $qos));
    }
}
