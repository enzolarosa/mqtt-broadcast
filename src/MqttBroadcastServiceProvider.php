<?php

namespace enzolarosa\MqttBroadcast;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Listeners\Logger;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use enzolarosa\MqttBroadcast\Commands\MqttBroadcastCommand;

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
            ->hasMigration('create_mqtt-broadcast_table')
            ->hasCommand(MqttBroadcastCommand::class);
    }

    public function bootingPackage()
    {
        foreach ($this->listen as $event => $listeners) {
            $this->addListener($event, $listeners);
        }
    }

    protected function addListener(string $event, string|array $listeners, string $function = 'handle'): void
    {
        if (is_string($listeners)) {
            Event::listen(MqttMessageReceived::class, [$listeners, $function]);
        }
        foreach ($listeners as $listener) {
            $this->addListener($event, $listener, $function);
        }
    }
}
