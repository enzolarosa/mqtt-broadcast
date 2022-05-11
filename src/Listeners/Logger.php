<?php

namespace enzolarosa\MqttBroadcast\Listeners;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Models\MqttLogger;

class Logger
{
    public function handle(MqttMessageReceived $event): void
    {
        if (!config('mqtt-broadcast.logs.enable')) {
            return;
        }

        $broker = $event->getBroker();
        $topic = $event->getTopic();
        $message = $event->getMessage();

        MqttLogger::query()->create([
            'topic' => $topic,
            'message' => $message,
            'broker' => $broker,
        ]);
    }
}
