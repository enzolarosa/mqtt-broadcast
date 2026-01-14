<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\MqttBroadcast;

if (! function_exists('mqttMessage')) {
    function mqttMessage(
        string $topic,
        mixed $message,
        string $broker = 'local',
        ?int $qos = 0,
    ) {
        MqttBroadcast::publish($topic, $message, $broker, $qos);
    }
}

if (! function_exists('mqtt_message_sync')) {
    function mqttMessageSync(
        string $topic,
        mixed $message,
        string $broker = 'local',
        ?int $qos = 0,
    ) {
        MqttBroadcast::publishSync($topic, $message, $broker, $qos);
    }
}
