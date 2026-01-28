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
        $this->registerMigrations();
    }

    /**
     * Register package migrations.
     *
     * Migrations are loaded automatically from the package directory,
     * following Laravel Horizon's approach. Users don't need to publish
     * migrations - they run directly from vendor using php artisan migrate.
     */
    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
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

            // Migrations are loaded automatically via loadMigrationsFrom()
            // No need to publish them (following Horizon's approach)
            // Users simply run: php artisan migrate
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
