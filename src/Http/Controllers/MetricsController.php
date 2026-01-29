<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Http\Controllers;

use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /**
     * Get message throughput metrics for charting.
     *
     * Returns time-series data of message counts aggregated by time interval:
     * - Last hour: grouped by minute (60 data points)
     * - Last 24 hours: grouped by hour (24 data points)
     * - Last 7 days: grouped by day (7 data points)
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function throughput(Request $request): JsonResponse
    {
        if (! config('mqtt-broadcast.logs.enable', false)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'logging_enabled' => false,
                    'period' => 'hour',
                ],
            ]);
        }

        $period = $request->get('period', 'hour'); // hour, day, week

        $data = match ($period) {
            'day' => $this->getThroughputByHour(),
            'week' => $this->getThroughputByDay(),
            default => $this->getThroughputByMinute(),
        };

        return response()->json([
            'data' => $data,
            'meta' => [
                'logging_enabled' => true,
                'period' => $period,
                'data_points' => count($data),
            ],
        ]);
    }

    /**
     * Get throughput grouped by minute (last hour).
     */
    protected function getThroughputByMinute(): array
    {
        $results = MqttLogger::query()
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i:00") as time_bucket, COUNT(*) as count'))
            ->where('created_at', '>', now()->subHour())
            ->groupBy('time_bucket')
            ->orderBy('time_bucket', 'asc')
            ->get();

        // Fill gaps with zeros
        $data = [];
        $current = now()->subHour()->startOfMinute();
        $end = now()->startOfMinute();

        while ($current <= $end) {
            $timeKey = $current->format('Y-m-d H:i:00');
            $result = $results->firstWhere('time_bucket', $timeKey);

            $data[] = [
                'time' => $current->format('H:i'),
                'timestamp' => $current->toIso8601String(),
                'count' => $result ? (int) $result->count : 0,
            ];

            $current->addMinute();
        }

        return $data;
    }

    /**
     * Get throughput grouped by hour (last 24 hours).
     */
    protected function getThroughputByHour(): array
    {
        $results = MqttLogger::query()
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as time_bucket, COUNT(*) as count'))
            ->where('created_at', '>', now()->subDay())
            ->groupBy('time_bucket')
            ->orderBy('time_bucket', 'asc')
            ->get();

        $data = [];
        $current = now()->subDay()->startOfHour();
        $end = now()->startOfHour();

        while ($current <= $end) {
            $timeKey = $current->format('Y-m-d H:00:00');
            $result = $results->firstWhere('time_bucket', $timeKey);

            $data[] = [
                'time' => $current->format('H:00'),
                'timestamp' => $current->toIso8601String(),
                'count' => $result ? (int) $result->count : 0,
            ];

            $current->addHour();
        }

        return $data;
    }

    /**
     * Get throughput grouped by day (last 7 days).
     */
    protected function getThroughputByDay(): array
    {
        $results = MqttLogger::query()
            ->select(DB::raw('DATE(created_at) as time_bucket, COUNT(*) as count'))
            ->where('created_at', '>', now()->subWeek())
            ->groupBy('time_bucket')
            ->orderBy('time_bucket', 'asc')
            ->get();

        $data = [];
        $current = now()->subWeek()->startOfDay();
        $end = now()->startOfDay();

        while ($current <= $end) {
            $timeKey = $current->format('Y-m-d');
            $result = $results->firstWhere('time_bucket', $timeKey);

            $data[] = [
                'time' => $current->format('M d'),
                'timestamp' => $current->toIso8601String(),
                'count' => $result ? (int) $result->count : 0,
            ];

            $current->addDay();
        }

        return $data;
    }

    /**
     * Get performance metrics summary.
     *
     * Returns aggregated performance data:
     * - Total messages by time period
     * - Average messages per minute/hour
     * - Peak throughput
     *
     * @return JsonResponse
     */
    public function summary(): JsonResponse
    {
        if (! config('mqtt-broadcast.logs.enable', false)) {
            return response()->json([
                'data' => null,
            ]);
        }

        $lastHour = MqttLogger::where('created_at', '>', now()->subHour())->count();
        $last24h = MqttLogger::where('created_at', '>', now()->subDay())->count();
        $last7days = MqttLogger::where('created_at', '>', now()->subWeek())->count();

        // Find peak minute in last hour
        $peakMinute = MqttLogger::query()
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i:00") as time_bucket, COUNT(*) as count'))
            ->where('created_at', '>', now()->subHour())
            ->groupBy('time_bucket')
            ->orderBy('count', 'desc')
            ->first();

        return response()->json([
            'data' => [
                'last_hour' => [
                    'total' => $lastHour,
                    'per_minute' => round($lastHour / 60, 2),
                ],
                'last_24h' => [
                    'total' => $last24h,
                    'per_hour' => round($last24h / 24, 2),
                ],
                'last_7days' => [
                    'total' => $last7days,
                    'per_day' => round($last7days / 7, 2),
                ],
                'peak_minute' => [
                    'time' => $peakMinute?->time_bucket,
                    'count' => $peakMinute?->count ?? 0,
                ],
            ],
        ]);
    }
}
