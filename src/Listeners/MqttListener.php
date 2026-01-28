<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Listeners;

use enzolarosa\MqttBroadcast\Contracts\Listener as ListenerInterface;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\MqttBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class MqttListener implements ListenerInterface, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected string $handleBroker = 'local';

    protected string $topic = '*';

    abstract public function processMessage(string $topic, object $obj);

    public function viaQueue(): string
    {
        return config('mqtt-broadcast.queue.listener', 'default');
    }

    public function handle(MqttMessageReceived $event): void
    {
        $broker = $event->getBroker();
        $topic = $event->getTopic();

        if ($broker !== $this->handleBroker) {
            return;
        }

        if ($topic !== $this->getTopic() && $this->getTopic() !== '*') {
            return;
        }

        if (! $this->preProcessMessage()) {
            return;
        }

        $message = $event->getMessage();

        // MqttListener is designed for JSON messages only
        // If message is not valid JSON, log warning and skip processing
        // For non-JSON messages, listen to MqttMessageReceived event directly
        try {
            $obj = json_decode($message, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            logger()->warning('Invalid JSON in MQTT message', [
                'broker' => $broker,
                'topic' => $topic,
                'message' => substr($message, 0, 200), // First 200 chars to avoid log flooding
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Ensure decoded value is an object (JSON objects only)
        if (! is_object($obj)) {
            return;
        }

        $this->processMessage($topic, $obj);
    }

    public function preProcessMessage(?string $topic = null, ?object $obj = null): bool
    {
        return true;
    }

    protected function getTopic(): string
    {
        return MqttBroadcast::getTopic($this->topic, $this->handleBroker);
    }
}
