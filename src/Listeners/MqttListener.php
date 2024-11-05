<?php

namespace enzolarosa\MqttBroadcast\Listeners;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Listeners\Interfaces\Listener as ListenerInterface;
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

    public function handle(MqttMessageReceived $event)
    {
        $broker = $event->getBroker();
        $topic = $event->getTopic();

        if ($broker != $this->handleBroker) {
            return;
        }

        if ($topic != $this->getTopic() && $this->getTopic() != '*') {
            return;
        }

        if (! $this->preProcessMessage()) {
            return;
        }

        $obj = json_decode($event->getMessage());

        $this->processMessage($topic, $obj);
    }

    public function preProcessMessage(?string $topic = null, ?object $obj = null): bool
    {
        return true;
    }

    protected function getTopic(): string
    {
        $prefix = config("mqtt-broadcast.connections.{$this->handleBroker}.prefix", '');

        return $prefix.$this->topic;
    }
}
