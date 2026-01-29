<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Http\Controllers;

use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MessageLogController extends Controller
{
    /**
     * Get recent MQTT messages.
     *
     * Returns paginated list of recent messages with filtering options:
     * - Filter by broker connection
     * - Filter by topic (partial match)
     * - Limit results (default: 30, max: 100)
     * - Order by created_at desc
     *
     * Only works if logging is enabled in config.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Check if logging is enabled
        if (! config('mqtt-broadcast.logs.enable', false)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'logging_enabled' => false,
                    'message' => 'Message logging is disabled. Enable it in config/mqtt-broadcast.php',
                ],
            ]);
        }

        $query = MqttLogger::query()
            ->orderBy('created_at', 'desc');

        // Filter by broker
        if ($broker = $request->get('broker')) {
            $query->where('broker', $broker);
        }

        // Filter by topic (partial match)
        if ($topic = $request->get('topic')) {
            $query->where('topic', 'like', "%{$topic}%");
        }

        // Limit results (default: 30, max: 100)
        $limit = min((int) $request->get('limit', 30), 100);

        $messages = $query->limit($limit)->get();

        return response()->json([
            'data' => $messages->map(function ($log) {
                return [
                    'id' => $log->id,
                    'broker' => $log->broker,
                    'topic' => $log->topic,
                    'message' => $this->formatMessage($log->message),
                    'message_preview' => $this->getMessagePreview($log->message),
                    'created_at' => $log->created_at->toIso8601String(),
                    'created_at_human' => $log->created_at->diffForHumans(),
                ];
            })->values(),
            'meta' => [
                'logging_enabled' => true,
                'count' => $messages->count(),
                'limit' => $limit,
                'filters' => [
                    'broker' => $request->get('broker'),
                    'topic' => $request->get('topic'),
                ],
            ],
        ]);
    }

    /**
     * Get details for a specific message.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        if (! config('mqtt-broadcast.logs.enable', false)) {
            return response()->json([
                'error' => 'Message logging is disabled',
            ], 404);
        }

        $log = MqttLogger::find($id);

        if (! $log) {
            return response()->json([
                'error' => 'Message not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $log->id,
                'broker' => $log->broker,
                'topic' => $log->topic,
                'message' => $log->message, // Full message
                'is_json' => $this->isJson($log->message),
                'message_parsed' => $this->parseMessage($log->message),
                'created_at' => $log->created_at->toIso8601String(),
                'created_at_human' => $log->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Get available topics.
     *
     * Returns list of unique topics with message counts.
     *
     * @return JsonResponse
     */
    public function topics(): JsonResponse
    {
        if (! config('mqtt-broadcast.logs.enable', false)) {
            return response()->json([
                'data' => [],
            ]);
        }

        $topics = MqttLogger::query()
            ->selectRaw('topic, COUNT(*) as count')
            ->where('created_at', '>', now()->subDay())
            ->groupBy('topic')
            ->orderBy('count', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $topics->map(function ($item) {
                return [
                    'topic' => $item->topic,
                    'count' => $item->count,
                ];
            })->values(),
        ]);
    }

    /**
     * Format message for API response.
     */
    protected function formatMessage(?string $message): ?string
    {
        if (! $message) {
            return null;
        }

        // Try to parse as JSON for better formatting
        if ($this->isJson($message)) {
            $decoded = json_decode($message, true);

            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $message;
    }

    /**
     * Get message preview (first 100 chars).
     */
    protected function getMessagePreview(?string $message): ?string
    {
        if (! $message) {
            return null;
        }

        $preview = $this->isJson($message)
            ? json_encode(json_decode($message), JSON_UNESCAPED_SLASHES)
            : $message;

        if (strlen($preview) > 100) {
            return substr($preview, 0, 100).'...';
        }

        return $preview;
    }

    /**
     * Check if string is valid JSON.
     */
    protected function isJson(?string $string): bool
    {
        if (! $string) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Parse message (JSON or raw).
     */
    protected function parseMessage(?string $message): mixed
    {
        if (! $message) {
            return null;
        }

        if ($this->isJson($message)) {
            return json_decode($message, true);
        }

        return $message;
    }
}
