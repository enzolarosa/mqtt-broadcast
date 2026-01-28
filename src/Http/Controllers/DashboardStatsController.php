<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Http\Controllers;

use enzolarosa\MqttBroadcast\Models\MqttLogger;
use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Queue;

class DashboardStatsController extends Controller
{
    /**
     * Get dashboard statistics.
     *
     * Returns aggregated stats for the dashboard overview:
     * - Messages per minute (last hour average)
     * - Active/total brokers count
     * - Memory usage
     * - Queue pending jobs
     * - Master supervisor status
     *
     * @param  BrokerRepository  $brokerRepository
     * @param  MasterSupervisorRepository  $masterRepository
     * @return JsonResponse
     */
    public function index(
        BrokerRepository $brokerRepository,
        MasterSupervisorRepository $masterRepository
    ): JsonResponse {
        $brokers = $brokerRepository->all();
        $activeBrokers = $brokers->filter(function ($broker) {
            return $broker->last_heartbeat_at > now()->subMinutes(2);
        });

        $masterName = config('mqtt-broadcast.master_supervisor.name', 'master');
        $masterSupervisor = $masterRepository->find($masterName);

        // Calculate messages per minute (if logging enabled)
        $messagesPerMinute = 0;
        $messagesLast24h = 0;
        $messagesLastHour = 0;

        if (config('mqtt-broadcast.logs.enable', false)) {
            $messagesLastHour = MqttLogger::where('created_at', '>', now()->subHour())->count();
            $messagesLast24h = MqttLogger::where('created_at', '>', now()->subDay())->count();
            $messagesPerMinute = $messagesLastHour > 0 ? round($messagesLastHour / 60, 2) : 0;
        }

        // Get queue size
        $queueName = config('mqtt-broadcast.queue.name', 'default');
        $queuePending = Queue::size($queueName);

        // Master supervisor data
        $masterData = is_array($masterSupervisor) ? $masterSupervisor : (array) $masterSupervisor;

        return response()->json([
            'data' => [
                'status' => $activeBrokers->isNotEmpty() ? 'running' : 'stopped',

                // Brokers
                'brokers' => [
                    'total' => $brokers->count(),
                    'active' => $activeBrokers->count(),
                    'stale' => $brokers->count() - $activeBrokers->count(),
                ],

                // Messages
                'messages' => [
                    'per_minute' => $messagesPerMinute,
                    'last_hour' => $messagesLastHour,
                    'last_24h' => $messagesLast24h,
                    'logging_enabled' => config('mqtt-broadcast.logs.enable', false),
                ],

                // Queue
                'queue' => [
                    'pending' => $queuePending,
                    'name' => $queueName,
                ],

                // Memory
                'memory' => [
                    'current_mb' => $masterSupervisor ? round(($masterData['memory'] ?? 0) / 1024 / 1024, 2) : 0,
                    'threshold_mb' => config('mqtt-broadcast.memory.threshold_mb', 128),
                    'usage_percent' => $this->calculateMemoryUsagePercent($masterData),
                ],

                // Uptime
                'uptime_seconds' => $this->calculateUptime($masterData),
            ],
        ]);
    }

    /**
     * Calculate memory usage percentage.
     */
    protected function calculateMemoryUsagePercent(array $masterData): float
    {
        $memory = $masterData['memory'] ?? 0;
        $thresholdBytes = config('mqtt-broadcast.memory.threshold_mb', 128) * 1024 * 1024;

        if ($thresholdBytes <= 0) {
            return 0;
        }

        return round(($memory / $thresholdBytes) * 100, 1);
    }

    /**
     * Calculate uptime in seconds.
     */
    protected function calculateUptime(array $masterData): int
    {
        $startedAt = $masterData['started_at'] ?? null;

        if (! $startedAt) {
            return 0;
        }

        return now()->diffInSeconds(\Carbon\Carbon::parse($startedAt), false);
    }
}
