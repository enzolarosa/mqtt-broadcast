<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Models\Brokers;

trait ServiceBindings
{
    /**
     * All the service bindings.
     *
     * @var array
     */
    public $serviceBindings = [
        // General services...
        Brokers::class,

    ];
}
