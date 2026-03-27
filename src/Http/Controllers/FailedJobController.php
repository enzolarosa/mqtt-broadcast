<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Http\Controllers;

use enzolarosa\MqttBroadcast\Jobs\MqttMessageJob;
use enzolarosa\MqttBroadcast\Models\FailedMqttJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FailedJobController extends Controller
{
    /**
     * List failed MQTT jobs.
     *
     * Returns paginated list ordered by most recent failure.
     * Filterable by broker and topic.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FailedMqttJob::query()->orderBy('failed_at', 'desc');

        if ($broker = $request->get('broker')) {
            $query->where('broker', $broker);
        }

        if ($topic = $request->get('topic')) {
            $query->where('topic', 'like', "%{$topic}%");
        }

        $limit = min((int) $request->get('limit', 30), 100);
        $jobs = $query->limit($limit)->get();

        return response()->json([
            'data' => $jobs->map(fn ($job) => $this->formatJob($job))->values(),
            'meta' => [
                'count' => $jobs->count(),
                'total' => FailedMqttJob::count(),
                'limit' => $limit,
                'filters' => [
                    'broker' => $request->get('broker'),
                    'topic' => $request->get('topic'),
                ],
            ],
        ]);
    }

    /**
     * Get details for a specific failed job.
     */
    public function show(string $id): JsonResponse
    {
        $job = FailedMqttJob::where('external_id', $id)->firstOrFail();

        return response()->json([
            'data' => [
                ...$this->formatJob($job),
                'exception' => $job->exception,
                'message' => $job->message,
            ],
        ]);
    }

    /**
     * Retry a single failed job.
     *
     * Dispatches a new MqttMessageJob with the original payload,
     * increments retry_count, and sets retried_at timestamp.
     */
    public function retry(string $id): JsonResponse
    {
        $job = FailedMqttJob::where('external_id', $id)->firstOrFail();

        MqttMessageJob::dispatch(
            topic: $job->topic,
            message: $job->message,
            broker: $job->broker,
            qos: $job->qos,
        );

        $job->increment('retry_count');
        $job->update(['retried_at' => now()]);

        return response()->json([
            'data' => $this->formatJob($job->fresh()),
        ]);
    }

    /**
     * Retry all failed jobs that have not been retried yet,
     * or whose last retry was more than 1 minute ago (to avoid spamming).
     */
    public function retryAll(): JsonResponse
    {
        $jobs = FailedMqttJob::query()
            ->where(function ($q) {
                $q->whereNull('retried_at')
                    ->orWhere('retried_at', '<', now()->subMinute());
            })
            ->get();

        foreach ($jobs as $job) {
            MqttMessageJob::dispatch(
                topic: $job->topic,
                message: $job->message,
                broker: $job->broker,
                qos: $job->qos,
            );

            $job->increment('retry_count');
            $job->update(['retried_at' => now()]);
        }

        return response()->json([
            'data' => ['retried' => $jobs->count()],
        ]);
    }

    /**
     * Delete a single failed job.
     */
    public function destroy(string $id): JsonResponse
    {
        FailedMqttJob::where('external_id', $id)->firstOrFail()->delete();

        return response()->json(status: 204);
    }

    /**
     * Delete all failed jobs (flush the DLQ).
     */
    public function flush(): JsonResponse
    {
        $count = FailedMqttJob::count();
        FailedMqttJob::truncate();

        return response()->json([
            'data' => ['flushed' => $count],
        ]);
    }

    protected function formatJob(FailedMqttJob $job): array
    {
        $message = $job->message;
        $preview = is_array($message)
            ? json_encode($message, JSON_UNESCAPED_SLASHES)
            : (string) $message;

        if (strlen($preview) > 100) {
            $preview = substr($preview, 0, 100).'...';
        }

        $exceptionLines = explode("\n", $job->exception);
        $exceptionPreview = $exceptionLines[0] ?? $job->exception;

        return [
            'id' => $job->external_id,
            'broker' => $job->broker,
            'topic' => $job->topic,
            'message_preview' => $preview,
            'exception_preview' => $exceptionPreview,
            'qos' => $job->qos,
            'retain' => $job->retain,
            'failed_at' => $job->failed_at->toIso8601String(),
            'failed_at_human' => $job->failed_at->diffForHumans(),
            'retried_at' => $job->retried_at?->toIso8601String(),
            'retry_count' => $job->retry_count,
        ];
    }
}
