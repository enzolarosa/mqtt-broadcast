<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class MqttBroadcastServiceProvider extends ServiceProvider
{
    use EventMap, ServiceBindings;

    public function boot(): void
    {
        $this->registerEvents();
        $this->offerPublishing();
        $this->registerCommands();
    }

    public function register(): void
    {
        $this->configure();
        $this->registerServices();
    }

    protected function registerEvents(): void
    {
        $events = $this->app->make(Dispatcher::class);

        foreach ($this->events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    protected function offerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../stubs/MqttBroadcastServiceProvider.stub' => app_path('Providers/MqttBroadcastServiceProvider.php'),
            ], 'mqtt-broadcast-provider');

            $this->publishes([
                __DIR__.'/../config/mqtt-broadcast.php' => config_path('mqtt-broadcast.php'),
            ], 'mqtt-broadcast-config');

            if (method_exists($this, 'publishesMigrations')) {
                $this->publishesMigrations([
                    __DIR__.'/../database/migrations' => database_path('migrations'),
                ], 'mqtt-broadcast-migrations');
            } else {
                $this->publishes([
                    __DIR__.'/../database/migrations' => database_path('migrations'),
                ], 'mqtt-broadcast-migrations');
            }
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\MqttBroadcastCommand::class,
                Commands\MqttBroadcastTerminateCommand::class,
                Commands\MqttBroadcastTestCommand::class,
            ]);
        }
    }

    protected function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/mqtt-broadcast.php', 'mqtt-broadcast',
        );
    }

    protected function registerServices(): void
    {
        foreach ($this->serviceBindings as $key => $value) {
            is_numeric($key)
                ? $this->app->singleton($value)
                : $this->app->singleton($key, $value);
        }
    }
}
