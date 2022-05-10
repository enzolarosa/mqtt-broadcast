<?php

namespace enzolarosa\MqttBroadcast;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use enzolarosa\MqttBroadcast\Commands\MqttBroadcastCommand;

class MqttBroadcastServiceProvider extends PackageServiceProvider
{
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
            ->hasViews()
            ->hasMigration('create_mqtt-broadcast_table')
            ->hasCommand(MqttBroadcastCommand::class);
    }
}
