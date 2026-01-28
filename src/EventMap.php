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
     * @var array<class-string, array<class-string>>
     */
    protected array $events = [
        MqttMessageReceived::class => [
            Logger::class,
        ],
    ];
}
