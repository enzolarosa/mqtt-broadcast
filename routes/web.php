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
    // GET /mqtt-broadcast/api/health
    Route::get('/health', [HealthController::class, 'check'])->name('mqtt-broadcast.health');

    // Future endpoints will be added here:
    // - GET /api/stats - Dashboard statistics
    // - GET /api/brokers - List all brokers
    // - GET /api/messages - Message log browser
    // - POST /api/publish - Manual message publishing
});
