<?php

namespace enzolarosa\MqttBroadcast\Contracts;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;

interface Listener
{
    public function handle(MqttMessageReceived $event);

    public function processMessage(string $topic, object $obj);
}
