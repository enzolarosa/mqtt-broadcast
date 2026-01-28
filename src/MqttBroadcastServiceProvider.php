<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MqttBroadcastServiceProvider extends ServiceProvider
{
    use EventMap, ServiceBindings;

    public function boot(): void
    {
        $this->registerEvents();
        $this->registerRoutes();
        $this->registerGate();
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

    /**
     * Register the MQTT Broadcast HTTP routes.
     *
     * Routes are registered with configurable path prefix and middleware,
     * following the Laravel Horizon pattern. The default path is '/mqtt-broadcast'
     * and can be customized in the config file.
     */
    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::group([
            'domain' => config('mqtt-broadcast.domain'),
            'prefix' => config('mqtt-broadcast.path', 'mqtt-broadcast'),
            'middleware' => config('mqtt-broadcast.middleware', ['web', Http\Middleware\Authorize::class]),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register the MQTT Broadcast authorization gate.
     *
     * This gate is used by the Authorize middleware to control access
     * to MQTT Broadcast endpoints. By default, it denies all access in
     * non-local environments. Users should override this in their own
     * App\Providers\MqttBroadcastServiceProvider.
     */
    protected function registerGate(): void
    {
        // Default gate: deny all in non-local environments
        // Users can override this in their published MqttBroadcastServiceProvider
        app(\Illuminate\Contracts\Auth\Access\Gate::class)->define('viewMqttBroadcast', function ($user = null) {
            // In local environment, Authorize middleware already allows access
            // This gate is only checked in other environments
            // By default, deny access - users must explicitly allow in their provider
            return false;
        });
    }
}
