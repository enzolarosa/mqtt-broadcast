<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use enzolarosa\MqttBroadcast\Events\MqttMessageReceived;
use enzolarosa\MqttBroadcast\Models\BrokerProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Keep demo broker heartbeats fresh on every web request so the dashboard
        // shows connected brokers without needing a real MQTT daemon running.
        if (! $this->app->runningInConsole()) {
            try {
                BrokerProcess::query()->update(['last_heartbeat_at' => now()]);
            } catch (\Throwable) {
                // Table may not exist before migrations run
            }
        }

        // Example listener: log every received MQTT message
        Event::listen(MqttMessageReceived::class, function (MqttMessageReceived $event) {
            Log::info('[MQTT] Message received', [
                'topic' => $event->topic,
                'broker' => $event->broker,
            ]);
        });
    }
}
