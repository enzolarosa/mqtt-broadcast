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
        // General services...
        Lock::class,

        // Repository services...
        Contracts\MqttSupervisorRepository::class => Repositories\RedisMqttSupervisorRepository::class,
    ];
}
