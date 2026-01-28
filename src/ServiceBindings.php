<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;

trait ServiceBindings
{
    /**
     * All the service bindings for MQTT Broadcast.
     *
     * @var array
     */
    public $serviceBindings = [
        // Factory services...
        MqttClientFactory::class,

        // Repository services...
        BrokerRepository::class,
        MasterSupervisorRepository::class,
    ];
}
