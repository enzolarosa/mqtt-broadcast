<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Http\Controllers;

use enzolarosa\MqttBroadcast\Models\MqttLogger;
use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BrokerController extends Controller
{
    /**
     * Get list of all brokers with details.
     *
     * Returns comprehensive information about each broker:
     * - Basic info (name, connection, pid)
     * - Status (active/stale based on heartbeat)
     * - Uptime calculation
     * - Message count (if logging enabled)
     *
     * @param  BrokerRepository  $brokerRepository
     * @return JsonResponse
     */
    public function index(BrokerRepository $brokerRepository): JsonResponse
    {
        $brokers = $brokerRepository->all()->map(function ($broker) {
            $isActive = $broker->last_heartbeat_at > now()->subMinutes(2);
            $uptime = $broker->started_at ? (int) $broker->started_at->diffInSeconds(now()) : 0;

            // Determine connection status
            $connectionStatus = $this->determineConnectionStatus($broker);

            // Count messages for this broker (if logging enabled)
            $messageCount = 0;
            $lastMessageAt = null;
            if (config('mqtt-broadcast.logs.enable', false)) {
                $messageCount = MqttLogger::where('broker', $broker->connection)
                    ->where('created_at', '>', now()->subDay())
                    ->count();

                $lastMessage = MqttLogger::where('broker', $broker->connection)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $lastMessageAt = $lastMessage?->created_at?->toIso8601String();
            }

            return [
                'id' => $broker->id,
                'name' => $broker->name,
                'connection' => $broker->connection,
                'pid' => $broker->pid,
                'status' => $isActive ? 'active' : 'stale',
                'connection_status' => $connectionStatus,
                'working' => $broker->working,
                'started_at' => $broker->started_at?->toIso8601String(),
                'last_heartbeat_at' => $broker->last_heartbeat_at?->toIso8601String(),
                'last_message_at' => $lastMessageAt,
                'uptime_seconds' => $uptime,
                'uptime_human' => $this->formatUptime($uptime),
                'messages_24h' => $messageCount,
            ];
        });

        return response()->json([
            'data' => $brokers->values(),
        ]);
    }

    /**
     * Get details for a specific broker.
     *
     * @param  int  $id
     * @param  BrokerRepository  $brokerRepository
     * @return JsonResponse
     */
    public function show(int $id, BrokerRepository $brokerRepository): JsonResponse
    {
        $broker = $brokerRepository->all()->firstWhere('id', $id);

        if (! $broker) {
            return response()->json([
                'error' => 'Broker not found',
            ], 404);
        }

        $isActive = $broker->last_heartbeat_at > now()->subMinutes(2);
        $uptime = $broker->started_at ? (int) $broker->started_at->diffInSeconds(now()) : 0;

        // Get recent messages for this broker
        $recentMessages = [];
        if (config('mqtt-broadcast.logs.enable', false)) {
            $recentMessages = MqttLogger::where('broker', $broker->connection)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'topic' => $log->topic,
                        'message' => $this->formatMessage($log->message),
                        'created_at' => $log->created_at->toIso8601String(),
                    ];
                });
        }

        return response()->json([
            'data' => [
                'id' => $broker->id,
                'name' => $broker->name,
                'connection' => $broker->connection,
                'pid' => $broker->pid,
                'status' => $isActive ? 'active' : 'stale',
                'working' => $broker->working,
                'started_at' => $broker->started_at?->toIso8601String(),
                'last_heartbeat_at' => $broker->last_heartbeat_at?->toIso8601String(),
                'uptime_seconds' => $uptime,
                'uptime_human' => $this->formatUptime($uptime),
                'recent_messages' => $recentMessages,
            ],
        ]);
    }

    /**
     * Format uptime seconds to human-readable string.
     */
    protected function formatUptime(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Format message for display (truncate if too long).
     */
    protected function formatMessage(?string $message): ?string
    {
        if (! $message) {
            return null;
        }

        if (strlen($message) > 100) {
            return substr($message, 0, 100).'...';
        }

        return $message;
    }

    /**
     * Determine connection status based on heartbeat and working state.
     *
     * Status levels:
     * - connected: Active heartbeat + working
     * - idle: Active heartbeat but not working (paused)
     * - reconnecting: Recent heartbeat but stale (30s-2min)
     * - disconnected: Very stale heartbeat (>2min)
     */
    protected function determineConnectionStatus($broker): string
    {
        $heartbeatAge = $broker->last_heartbeat_at
            ? $broker->last_heartbeat_at->diffInSeconds(now())
            : PHP_INT_MAX;

        // Active and working
        if ($heartbeatAge < 30 && $broker->working) {
            return 'connected';
        }

        // Active but paused
        if ($heartbeatAge < 30 && ! $broker->working) {
            return 'idle';
        }

        // Recently active (might be reconnecting)
        if ($heartbeatAge < 120) {
            return 'reconnecting';
        }

        // Very stale
        return 'disconnected';
    }
}
