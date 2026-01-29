<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Http\Controllers;

use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class HealthController extends Controller
{
    /**
     * Check the health status of MQTT Broadcast system.
     *
     * This endpoint provides a comprehensive health check of all MQTT brokers
     * and the master supervisor process. It's designed for monitoring tools,
     * load balancers, and Kubernetes health checks.
     *
     * Returns HTTP 200 if healthy, 503 if unhealthy.
     *
     * @param  BrokerRepository  $brokerRepository
     * @param  MasterSupervisorRepository  $masterRepository
     * @return JsonResponse
     */
    public function check(
        BrokerRepository $brokerRepository,
        MasterSupervisorRepository $masterRepository
    ): JsonResponse {
        $brokers = $brokerRepository->all();
        $activeBrokers = $brokers->filter(function ($broker) {
            return $broker->last_heartbeat_at > now()->subMinutes(2);
        });

        // Find master supervisor using configured name
        $masterName = config('mqtt-broadcast.master_supervisor.name', 'master');
        $masterSupervisor = $masterRepository->find($masterName);

        // System is healthy if:
        // 1. At least one broker is active
        // 2. Master supervisor is running (found in cache/repository)
        $isHealthy = $activeBrokers->isNotEmpty() && $masterSupervisor !== null;

        $data = [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                // Broker information
                'brokers' => [
                    'total' => $brokers->count(),
                    'active' => $activeBrokers->count(),
                    'stale' => $brokers->count() - $activeBrokers->count(),
                ],

                // Master supervisor information
                'master_supervisor' => $this->getMasterSupervisorData($masterSupervisor),

                // Queue health
                'queues' => [
                    'pending' => Queue::size(config('mqtt-broadcast.queue.name', 'default')),
                ],
            ],

            // Individual health checks for debugging
            'checks' => [
                'brokers_active' => [
                    'status' => $activeBrokers->isNotEmpty() ? 'pass' : 'fail',
                    'message' => $activeBrokers->count().' active broker(s)',
                ],
                'master_running' => [
                    'status' => $masterSupervisor !== null ? 'pass' : 'fail',
                    'message' => $masterSupervisor ? 'Master supervisor running' : 'Master supervisor not found',
                ],
                'memory_ok' => $this->checkMemoryStatus($masterSupervisor),
            ],
        ];

        return response()->json($data, $isHealthy ? 200 : 503);
    }

    /**
     * Extract master supervisor data for response.
     *
     * @param  mixed  $masterSupervisor
     * @return array|null
     */
    protected function getMasterSupervisorData($masterSupervisor): ?array
    {
        if (! $masterSupervisor) {
            return null;
        }

        // Handle both array and object formats
        $data = is_array($masterSupervisor) ? $masterSupervisor : (array) $masterSupervisor;

        $memory = $data['memory'] ?? 0;
        $startedAt = $data['started_at'] ?? null;

        return [
            'pid' => $data['pid'] ?? null,
            'uptime_seconds' => $startedAt ? \Carbon\Carbon::parse($startedAt)->diffInSeconds(now(), false) : 0,
            'memory_mb' => round($memory / 1024 / 1024, 2),
            'supervisors_count' => $data['supervisors_count'] ?? 0,
        ];
    }

    /**
     * Check memory status against configured threshold.
     *
     * @param  mixed  $masterSupervisor
     * @return array
     */
    protected function checkMemoryStatus($masterSupervisor): array
    {
        if (! $masterSupervisor) {
            return [
                'status' => 'unknown',
                'message' => 'Master supervisor not running',
            ];
        }

        $data = is_array($masterSupervisor) ? $masterSupervisor : (array) $masterSupervisor;
        $memory = $data['memory'] ?? 0;
        $thresholdBytes = config('mqtt-broadcast.memory.threshold_mb', 128) * 1024 * 1024;

        $usagePercent = $thresholdBytes > 0 ? ($memory / $thresholdBytes) * 100 : 0;

        if ($usagePercent >= 100) {
            return [
                'status' => 'critical',
                'message' => sprintf('Memory usage at %.1f%% of threshold', $usagePercent),
            ];
        }

        if ($usagePercent >= 80) {
            return [
                'status' => 'warn',
                'message' => sprintf('Memory usage at %.1f%% of threshold', $usagePercent),
            ];
        }

        return [
            'status' => 'pass',
            'message' => sprintf('Memory usage at %.1f%% of threshold', $usagePercent),
        ];
    }
}
