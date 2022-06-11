<?php

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Commands\MqttBroadcastCommand;
use enzolarosa\MqttBroadcast\Commands\MqttBroadcastInstallCommand;
use enzolarosa\MqttBroadcast\Commands\MqttBroadcastTestCommand;
use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Listeners\Logger;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MqttBroadcastServiceProvider extends PackageServiceProvider
{
    protected array $listen = [
        MqttMessageReceived::class => [
            Logger::class,
        ],
    ];

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('mqtt-broadcast')
            ->hasConfigFile()
            ->hasMigration('create_mqtt_broadcast_table')
            ->hasCommand(MqttBroadcastCommand::class)
            ->hasCommand(MqttBroadcastTestCommand::class)
            ->hasCommand(MqttBroadcastInstallCommand::class);
    }

    public function packageRegistered()
    {
        $this->addListenerToEvent();
    }

    protected function addListenerToEvent()
    {
        foreach ($this->listen as $event => $listeners) {
            foreach (array_unique($listeners) as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
