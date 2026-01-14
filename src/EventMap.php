<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Listeners\Logger;

trait EventMap
{
    /**
     * All the event / listener mappings.
     *
     * @var array
     */
    protected $events = [
        MqttMessageReceived::class => [
            Logger::class,
        ],
    ];
}
