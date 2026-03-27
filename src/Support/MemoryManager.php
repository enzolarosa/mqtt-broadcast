<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Support;

use Closure;

/**
 * Memory management service for long-running supervisor processes.
 *
 * Handles:
 * - Periodic garbage collection to prevent memory leaks
 * - Memory threshold monitoring with configurable warnings
 * - Auto-restart capability when memory limits are exceeded
 *
 * Prevents unbounded memory growth from MQTT client internal queues
 * and PHP circular references in long-running processes.
 */
class MemoryManager
{
    /**
     * Loop iteration counter for periodic operations.
     */
    protected int $loopIteration = 0;

    /**
     * Peak memory usage tracking (bytes).
     */
    protected int $peakMemoryUsage = 0;

    /**
     * Timestamp when threshold was first exceeded (for grace period).
     */
    protected ?float $thresholdExceededAt = null;

    /**
     * Create a new memory manager instance.
     *
     * @param  Closure|null  $output  Output callback (type, message) => void
     * @param  Closure|null  $onRestart  Callback to invoke when auto-restart is triggered
     */
    public function __construct(
        protected ?Closure $output = null,
        protected ?Closure $onRestart = null
    ) {
        $this->peakMemoryUsage = memory_get_usage(true);
    }

    /**
     * Perform periodic maintenance on each loop iteration.
     *
     * Should be called every iteration of the supervisor loop.
     * Handles GC and memory monitoring based on configuration.
     *
     * @return bool True if supervisor should continue, false if should terminate
     */
    public function tick(): bool
    {
        $this->loopIteration++;

        $gcInterval = config('mqtt-broadcast.memory.gc_interval', 100);

        if ($this->loopIteration % $gcInterval === 0) {
            $this->performGarbageCollection();
            return $this->checkMemoryThreshold();
        }

        return true;
    }

    /**
     * Perform garbage collection and track cycles collected.
     *
     * Uses gc_collect_cycles() to clean up circular references that
     * accumulate from MQTT client internal queues and signal handlers.
     */
    protected function performGarbageCollection(): void
    {
        $beforeMemory = memory_get_usage(true);
        $cycles = gc_collect_cycles();
        $afterMemory = memory_get_usage(true);

        // Only log if cycles were collected (indicates garbage was present)
        if ($cycles > 0) {
            $freed = max(0, $beforeMemory - $afterMemory);
            $this->output('info', sprintf(
                'GC: Collected %d cycles, freed %.2f MB',
                $cycles,
                $freed / 1024 / 1024
            ));
        }
    }

    /**
     * Check if memory usage exceeds configured thresholds.
     *
     * Implements three-tier warning system:
     * - 80% threshold: Warning (early alert)
     * - 100% threshold: Error + auto-restart countdown
     * - After grace period: Triggers restart callback
     *
     * @return bool True if supervisor should continue, false if should restart
     */
    protected function checkMemoryThreshold(): bool
    {
        $currentMemory = memory_get_usage(true);
        $this->peakMemoryUsage = max($this->peakMemoryUsage, $currentMemory);

        $thresholdMb = config('mqtt-broadcast.memory.threshold_mb');

        if ($thresholdMb === null) {
            return true;
        }

        $thresholdBytes = $thresholdMb * 1024 * 1024;
        $usagePercent = ($currentMemory / $thresholdBytes) * 100;

        // Warning at 80% threshold (early alert)
        if ($currentMemory >= $thresholdBytes * 0.8 && $currentMemory < $thresholdBytes) {
            $this->output('warning', sprintf(
                'Memory at %.0f%%: %.2f MB / %d MB (peak: %.2f MB)',
                $usagePercent,
                $currentMemory / 1024 / 1024,
                $thresholdMb,
                $this->peakMemoryUsage / 1024 / 1024
            ));
        }

        // Error at 100% threshold
        if ($currentMemory >= $thresholdBytes) {
            return $this->handleThresholdExceeded($currentMemory, $thresholdMb);
        }

        // Reset threshold tracking if memory drops below limit
        if ($this->thresholdExceededAt !== null && $currentMemory < $thresholdBytes) {
            $this->thresholdExceededAt = null;
            $this->output('info', 'Memory usage back below threshold');
        }

        return true;
    }

    /**
     * Handle memory threshold exceeded scenario.
     *
     * Implements grace period before restart to allow in-progress
     * operations to complete safely.
     *
     * @param  int  $currentMemory  Current memory usage in bytes
     * @param  int  $thresholdMb  Configured threshold in MB
     * @return bool True to continue, false to trigger restart
     */
    protected function handleThresholdExceeded(int $currentMemory, int $thresholdMb): bool
    {
        $autoRestart = config('mqtt-broadcast.memory.auto_restart', true);
        $gracePeriod = config('mqtt-broadcast.memory.restart_delay_seconds', 10);

        // First time exceeding threshold
        if ($this->thresholdExceededAt === null) {
            $this->thresholdExceededAt = microtime(true);

            $this->output('error', sprintf(
                'Memory threshold exceeded: %.2f MB / %d MB (peak: %.2f MB)',
                $currentMemory / 1024 / 1024,
                $thresholdMb,
                $this->peakMemoryUsage / 1024 / 1024
            ));

            if ($autoRestart) {
                $this->output('warning', sprintf(
                    'Auto-restart will trigger in %d seconds if memory stays above threshold',
                    $gracePeriod
                ));
            }
        }

        // Check if grace period has elapsed
        if ($autoRestart) {
            $elapsed = microtime(true) - $this->thresholdExceededAt;

            if ($elapsed >= $gracePeriod) {
                $this->output('error', 'Grace period elapsed, triggering auto-restart');

                // Invoke restart callback
                if ($this->onRestart !== null) {
                    call_user_func($this->onRestart);
                }

                return false; // Signal to restart
            }
        }

        return true; // Continue running
    }

    /**
     * Get current memory statistics.
     *
     * @return array{current_mb: float, peak_mb: float, current_bytes: int, peak_bytes: int}
     */
    public function getMemoryStats(): array
    {
        $currentMemory = memory_get_usage(true);
        $this->peakMemoryUsage = max($this->peakMemoryUsage, $currentMemory);

        return [
            'current_mb' => round($currentMemory / 1024 / 1024, 2),
            'peak_mb' => round($this->peakMemoryUsage / 1024 / 1024, 2),
            'current_bytes' => $currentMemory,
            'peak_bytes' => $this->peakMemoryUsage,
        ];
    }

    /**
     * Output a message via the callback.
     *
     * @param  string  $type  Message type (info, warning, error)
     * @param  string  $message  Message content
     */
    protected function output(string $type, string $message): void
    {
        if ($this->output !== null) {
            call_user_func($this->output, $type, $message);
        }
    }

    /**
     * Reset the memory manager state.
     *
     * Useful for testing or when re-initializing a supervisor.
     */
    public function reset(): void
    {
        $this->loopIteration = 0;
        $this->peakMemoryUsage = memory_get_usage(true);
        $this->thresholdExceededAt = null;
    }
}
