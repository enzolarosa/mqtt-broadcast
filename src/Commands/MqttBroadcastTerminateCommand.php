<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use enzolarosa\MqttBroadcast\Support\ProcessIdentifier;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mqtt-broadcast:terminate', description: 'Terminate MQTT Broadcast supervisors')]
class MqttBroadcastTerminateCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    public $signature = 'mqtt-broadcast:terminate
                        {broker? : Optional specific broker connection to terminate}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Terminate the MQTT Broadcast supervisors';

    /**
     * Execute the console command.
     *
     * Terminates MQTT Broadcast supervisor processes:
     * - Without argument: terminates ALL brokers on this machine
     * - With argument: terminates only specified broker
     * - Sends SIGTERM signal for graceful shutdown
     * - Cleans up both broker DB records and master cache state
     * - Continues on errors (best-effort cleanup)
     *
     * @param  BrokerRepository  $brokerRepository  Repository for broker persistence
     * @param  MasterSupervisorRepository  $masterRepository  Repository for master state
     * @return int Exit code (always SUCCESS - best-effort operation)
     */
    public function handle(
        BrokerRepository $brokerRepository,
        MasterSupervisorRepository $masterRepository
    ): int {
        $targetBroker = $this->argument('broker');
        $hostname = ProcessIdentifier::hostname();

        // Get all brokers for this machine
        $brokers = collect($brokerRepository->all())
            ->filter(fn ($broker) => Str::startsWith($broker->name, $hostname));

        // Filter by specific broker if requested
        if ($targetBroker) {
            $brokers = $brokers->filter(fn ($broker) => $broker->connection === $targetBroker);

            if ($brokers->isEmpty()) {
                $this->components->warn("No processes found for broker [$targetBroker]");

                return Command::SUCCESS;
            }
        }

        // Extract unique PIDs
        $pids = $brokers->pluck('pid')->unique();

        // Display status
        if ($pids->isEmpty()) {
            $this->components->info('No processes to terminate.');

            return Command::SUCCESS;
        }

        $this->components->info(sprintf(
            'Sending TERM signal to %d process(es)%s.',
            $pids->count(),
            $targetBroker ? " for broker [$targetBroker]" : ''
        ));

        // Terminate each process
        $pids->each(function ($processId) use ($brokerRepository) {
            $result = true;

            $this->components->task("Process: $processId", function () use ($processId, $brokerRepository, &$result) {
                // Cleanup database first (even if kill fails)
                $brokerRepository->deleteByPid($processId);

                // Try to send SIGTERM signal
                $result = posix_kill($processId, SIGTERM);

                return $result;
            });

            // Handle errors (best-effort, continue on failure)
            if (! $result) {
                $errno = posix_get_last_error();
                $error = posix_strerror($errno);

                // ESRCH (3) = "No such process" - already dead, treat as success
                if ($errno === 3) {
                    $this->components->info("Process $processId already terminated");
                } else {
                    $this->components->error("Failed to kill process $processId: $error");
                }
            }
        });

        // Cleanup stale master supervisor cache entries (safety net)
        // This handles cases where process was killed with -9 and couldn't cleanup
        $masters = collect($masterRepository->names())
            ->filter(fn ($name) => Str::startsWith($name, $hostname));

        $masters->each(fn ($name) => $masterRepository->forget($name));

        if ($masters->isNotEmpty()) {
            $this->newLine();
            $this->components->info(sprintf(
                'Cleaned up %d master supervisor cache %s.',
                $masters->count(),
                Str::plural('entry', $masters->count())
            ));
        }

        $this->newLine();

        // Always return success (terminate is best-effort)
        return Command::SUCCESS;
    }
}
