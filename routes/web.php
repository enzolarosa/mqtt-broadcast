<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MQTT Broadcast Routes
|--------------------------------------------------------------------------
|
| These routes are automatically registered by the MqttBroadcastServiceProvider
| when the package is installed. The path prefix and middleware are configurable
| via config/mqtt-broadcast.php
|
| Default path: /mqtt-broadcast
| Default middleware: ['web', Authorize::class]
|
*/

Route::prefix('api')->group(function () {
    // Health check endpoint
    Route::get('/health', [HealthController::class, 'check'])->name('mqtt-broadcast.health');

    // Dashboard statistics
    Route::get('/stats', [\enzolarosa\MqttBroadcast\Http\Controllers\DashboardStatsController::class, 'index'])
        ->name('mqtt-broadcast.stats');

    // Brokers management
    Route::get('/brokers', [\enzolarosa\MqttBroadcast\Http\Controllers\BrokerController::class, 'index'])
        ->name('mqtt-broadcast.brokers.index');
    Route::get('/brokers/{id}', [\enzolarosa\MqttBroadcast\Http\Controllers\BrokerController::class, 'show'])
        ->name('mqtt-broadcast.brokers.show');

    // Message logs (if logging enabled)
    Route::get('/messages', [\enzolarosa\MqttBroadcast\Http\Controllers\MessageLogController::class, 'index'])
        ->name('mqtt-broadcast.messages.index');
    Route::get('/messages/{id}', [\enzolarosa\MqttBroadcast\Http\Controllers\MessageLogController::class, 'show'])
        ->name('mqtt-broadcast.messages.show');
    Route::get('/topics', [\enzolarosa\MqttBroadcast\Http\Controllers\MessageLogController::class, 'topics'])
        ->name('mqtt-broadcast.topics');

    // Metrics for charts
    Route::get('/metrics/throughput', [\enzolarosa\MqttBroadcast\Http\Controllers\MetricsController::class, 'throughput'])
        ->name('mqtt-broadcast.metrics.throughput');
    Route::get('/metrics/summary', [\enzolarosa\MqttBroadcast\Http\Controllers\MetricsController::class, 'summary'])
        ->name('mqtt-broadcast.metrics.summary');
});

// Dashboard UI (React SPA)
Route::get('/', function () {
    return view('mqtt-broadcast::dashboard');
})->name('mqtt-broadcast.dashboard');
