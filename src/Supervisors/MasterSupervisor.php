<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Supervisors;

use Closure;
use enzolarosa\MqttBroadcast\Contracts\Pausable;
use enzolarosa\MqttBroadcast\Contracts\Restartable;
use enzolarosa\MqttBroadcast\Contracts\Terminable;
use enzolarosa\MqttBroadcast\ListensForSignals;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use enzolarosa\MqttBroadcast\Support\MemoryManager;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Master supervisor for orchestrating multiple broker supervisors.
 *
 * Inspired by Laravel Horizon's MasterSupervisor pattern, this class:
 * - Manages the main monitoring loop with signal handling
 * - Coordinates multiple BrokerSupervisor instances
 * - Persists state to cache for health monitoring
 * - Handles graceful pause/resume/termination
 * - Removes dead supervisors from the pool
 *
 * The master supervisor runs an infinite loop (blocking) that:
 * 1. Processes pending UNIX signals (TERM, USR1, USR2, CONT)
 * 2. Calls monitor() on each active supervisor
 * 3. Removes supervisors that have terminated
 * 4. Persists current state to repository
 * 5. Sleeps for 1 second
 *
 * @see \enzolarosa\MqttBroadcast\Supervisors\BrokerSupervisor
 */
class MasterSupervisor implements Terminable, Pausable, Restartable
{
    use ListensForSignals;

    /**
     * Collection of broker supervisors being managed.
     */
    protected Collection $supervisors;

    /**
     * Indicates if the supervisor is currently working.
     */
    protected bool $working = true;

    /**
     * The output handler callback.
     */
    protected ?Closure $output = null;

    /**
     * Memory manager for GC and threshold monitoring.
     */
    protected MemoryManager $memoryManager;

    /**
     * Create a new master supervisor instance.
     *
     * @param  string  $name  Unique identifier for this master supervisor
     * @param  MasterSupervisorRepository  $repository  Repository for state persistence
     */
    public function __construct(
        protected string $name,
        protected MasterSupervisorRepository $repository
    ) {
        $this->supervisors = collect();
        $this->memoryManager = new MemoryManager(
            output: fn (string $type, string $message) => $this->output($type, $message),
            onRestart: fn () => $this->restart()
        );
    }

    /**
     * Monitor the supervisors in an infinite loop.
     *
     * This is a blocking method that runs forever until terminated.
     * It performs the following operations:
     * 1. Registers UNIX signal listeners (TERM, USR1, USR2, CONT)
     * 2. Persists initial state
     * 3. Enters infinite loop with 1-second sleep interval
     *
     * @return never This method never returns normally
     */
    public function monitor(): never
    {
        $this->listenForSignals();
        $this->persist();

        while (true) {
            sleep(1);
            $this->loop();
        }
    }

    /**
     * Perform a single monitoring iteration.
     *
     * This method is called every second by monitor() and:
     * 1. Processes pending UNIX signals
     * 2. Monitors all active supervisors (if working)
     * 3. Removes dead supervisors from the pool
     * 4. Persists current state
     *
     * All exceptions are caught and logged via the output callback
     * to prevent the master supervisor from crashing.
     */
    public function loop(): void
    {
        try {
            $this->processPendingSignals();

            if ($this->working) {
                $this->monitorSupervisors();

                // Remove supervisors that have terminated
                $this->supervisors = $this->supervisors->filter(
                    fn (BrokerSupervisor $supervisor) => $supervisor->isWorking()
                );
            }

            // Periodic garbage collection and memory monitoring
            // Returns false if auto-restart should be triggered
            if (! $this->memoryManager->tick()) {
                return; // restart() will be called by memoryManager callback
            }

            $this->persist();
        } catch (Throwable $e) {
            $this->output('error', sprintf(
                'Error in master supervisor loop: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Add a broker supervisor to the pool.
     *
     * The supervisor will be monitored starting from the next loop iteration.
     *
     * @param  BrokerSupervisor  $supervisor  The supervisor to add
     */
    public function addSupervisor(BrokerSupervisor $supervisor): void
    {
        $this->supervisors->push($supervisor);
    }

    /**
     * Monitor all active supervisors.
     *
     * Calls monitor() on each supervisor to handle connection,
     * message processing, and heartbeat updates.
     */
    protected function monitorSupervisors(): void
    {
        $this->supervisors->each(fn (BrokerSupervisor $supervisor) => $supervisor->monitor());
    }

    /**
     * Persist the current state to the repository.
     *
     * Saves:
     * - Process ID (PID)
     * - Current status (running/paused)
     * - Number of active supervisors
     * - Current and peak memory usage
     *
     * Called once during initialization and then every loop iteration.
     */
    public function persist(): void
    {
        $memoryStats = $this->memoryManager->getMemoryStats();

        $this->repository->update($this->name, [
            'pid' => getmypid(),
            'status' => $this->working ? 'running' : 'paused',
            'supervisors' => $this->supervisors->count(),
            'memory_mb' => $memoryStats['current_mb'],
            'peak_memory_mb' => $memoryStats['peak_mb'],
        ]);
    }

    /**
     * Pause the master supervisor.
     *
     * Stops monitoring supervisors but keeps the loop running.
     * All active supervisors are also paused.
     */
    public function pause(): void
    {
        $this->working = false;
        $this->supervisors->each->pause();
    }

    /**
     * Continue the master supervisor.
     *
     * Resumes monitoring after being paused.
     * All supervisors are also resumed.
     */
    public function continue(): void
    {
        $this->working = true;
        $this->supervisors->each->continue();
    }

    /**
     * Restart the master supervisor.
     *
     * Following Horizon's approach, restart means terminating the process
     * and letting the process manager (systemd, supervisor, etc.) restart it.
     *
     * This ensures a clean slate and prevents state corruption.
     *
     * @return never This method never returns
     */
    public function restart(): never
    {
        $this->terminate(0);
    }

    /**
     * Terminate the master supervisor.
     *
     * Performs graceful shutdown:
     * 1. Stops the monitoring loop
     * 2. Terminates all active supervisors (disconnect, cleanup)
     * 3. Removes state from repository
     * 4. Exits with the given status code
     *
     * Supervisor termination errors are caught and logged to ensure
     * all supervisors are terminated even if some fail.
     *
     * @param  int  $status  Exit status code (0 = success, 1+ = error)
     * @return never This method never returns
     */
    public function terminate($status = 0): never
    {
        $this->working = false;

        // Terminate all supervisors
        foreach ($this->supervisors as $supervisor) {
            try {
                $supervisor->terminate($status);
            } catch (Throwable $e) {
                $this->output('error', sprintf(
                    'Error terminating supervisor: %s',
                    $e->getMessage()
                ));
            }
        }

        // Remove state from repository
        $this->repository->forget($this->name);

        exit($status);
    }

    /**
     * Set the output handler callback.
     *
     * The callback receives two parameters: type (string) and line (string).
     * Used for logging without coupling to specific logging implementations.
     *
     * @param  Closure|null  $output  The output callback (type, line) => void
     */
    public function setOutput(?Closure $output): void
    {
        $this->output = $output;
    }

    /**
     * Output a message via the callback.
     *
     * @param  string  $type  Message type (info, error, etc.)
     * @param  string  $line  Message content
     */
    protected function output(string $type, string $line): void
    {
        if ($this->output) {
            call_user_func($this->output, $type, $line);
        }
    }

    /**
     * Get the master supervisor name.
     *
     * @return string The supervisor name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the count of active supervisors.
     *
     * @return int Number of supervisors in the pool
     */
    public function getSupervisorsCount(): int
    {
        return $this->supervisors->count();
    }

    /**
     * Check if the master supervisor is currently working.
     *
     * @return bool True if active and monitoring supervisors
     */
    public function isWorking(): bool
    {
        return $this->working;
    }
}
