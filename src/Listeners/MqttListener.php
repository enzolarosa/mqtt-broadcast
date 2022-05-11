<?php

namespace enzolarosa\MqttBroadcast\Listeners;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Listeners\Interfaces\Listener as ListenerInterface;

abstract class MqttListener implements ListenerInterface
{
    protected string $handleBroker = 'local';
    protected string $topic = '*';

    abstract public function processMessage(string $topic, object $obj);

    public function handle(MqttMessageReceived $event)
    {
        $broker = $event->getBroker();
        $topic = $event->getTopic();

        if ($broker != $this->handleBroker) {
            return;
        }

        if (
            $topic != '*'
            && $topic != $this->topic
        ) {
            return;
        }

        $obj = json_decode($event->getMessage());

        $this->processMessage($topic, $obj);
    }
}
