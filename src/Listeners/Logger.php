<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Listeners;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Logger implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

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
        $rawMessage = $event->getMessage();

        // Try to decode as JSON, but store raw if not valid
        try {
            $message = json_decode($rawMessage, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Not valid JSON - store raw message as-is
            $message = $rawMessage;
        }

        MqttLogger::query()->create([
            'topic' => $topic,
            'message' => $message,
            'broker' => $broker,
        ]);
    }
}
