<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\MqttBroadcast;

if (! function_exists('mqttMessage')) {
    function mqttMessage(
        string $topic,
        mixed $message,
        string $broker = 'local',
        int $qos = 0,
    ): void {
        MqttBroadcast::publish($topic, $message, $broker, $qos);
    }
}

if (! function_exists('mqttMessageSync')) {
    function mqttMessageSync(
        string $topic,
        mixed $message,
        string $broker = 'local',
        int $qos = 0,
    ): void {
        MqttBroadcast::publishSync($topic, $message, $broker, $qos);
    }
}
