<?php

namespace enzolarosa\MqttBroadcast;

trait ServiceBindings
{
    /**
     * All the service bindings.
     *
     * @var array
     */
    public $serviceBindings = [
        // Repository services...
        Contracts\MqttSupervisorRepository::class => Repositories\RedisMqttSupervisorRepository::class,
    ];
}
