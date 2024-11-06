<?php

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
