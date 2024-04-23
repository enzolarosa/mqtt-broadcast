<?php

namespace enzolarosa\MqttBroadcast\Listeners;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Listeners\Interfaces\Listener;
use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Logger implements ShouldQueue
{
    use Queueable, SerializesModels,InteractsWithQueue;

    public function viaQueue(): string
    {
        return config('mqtt-broadcast.logs.queue');
    }

    public function handle(MqttMessageReceived $event): void
    {
        if (! config('mqtt-broadcast.logs.enable')) {
            return;
        }

        $broker = $event->getBroker();
        $topic = $event->getTopic();
        $message = json_decode($event->getMessage());

        $obj = json_decode($event->getMessage());

        MqttLogger::query()->create([
            'topic' => $topic,
            'message' => $message,
            'broker' => $broker,
        ]);
    }
}
