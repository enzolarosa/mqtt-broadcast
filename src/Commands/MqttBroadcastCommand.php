<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\Factories\MqttClientFactory;
use enzolarosa\MqttBroadcast\Repositories\BrokerRepository;
use enzolarosa\MqttBroadcast\Repositories\MasterSupervisorRepository;
use enzolarosa\MqttBroadcast\Support\ProcessIdentifier;
use enzolarosa\MqttBroadcast\Supervisors\BrokerSupervisor;
use enzolarosa\MqttBroadcast\Supervisors\MasterSupervisor;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mqtt-broadcast', description: 'Start MQTT Broadcast supervisors')]
class MqttBroadcastCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    public $signature = 'mqtt-broadcast
                        {--environment= : The environment name (defaults to config or app.env)}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Start the MQTT Broadcast supervisor';

    /**
     * Execute the console command.
     *
     * Follows Laravel Horizon pattern:
     * - Reads environment from option or config
     * - Loads broker connections from config
     * - Creates one BrokerSupervisor per connection
     * - Orchestrates with MasterSupervisor
     * - Returns proper exit codes
     *
     * @param  MasterSupervisorRepository  $masterRepository  Repository for master state
     * @param  BrokerRepository  $brokerRepository  Repository for broker persistence
     * @param  MqttClientFactory  $clientFactory  Factory for MQTT client creation
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(
        MasterSupervisorRepository $masterRepository,
        BrokerRepository $brokerRepository,
        MqttClientFactory $clientFactory
    ): int {
        // Generate unique name for this master supervisor
        $masterName = ProcessIdentifier::generateName('master');

        // Check if a master supervisor is already running on this machine
        if ($masterRepository->find($masterName)) {
            $this->components->warn('A master supervisor is already running on this machine.');

            return Command::FAILURE;
        }

        // Determine environment (option > config > app.env)
        $environment = $this->option('environment')
            ?? config('mqtt-broadcast.env')
            ?? config('app.env');

        // Get broker connections for this environment
        $connections = $this->getEnvironmentConnections($environment);

        if (empty($connections)) {
            $this->components->error("No broker connections configured for environment [$environment]");
            $this->line('Check config/mqtt-broadcast.php -> environments section');

            return Command::FAILURE;
        }

        // Create master supervisor
        $master = new MasterSupervisor($masterName, $masterRepository);

        // Set output callback for master
        $master->setOutput(function ($type, $line) {
            $this->output->writeln($line);
        });

        // Create broker supervisors for each connection
        foreach ($connections as $connection) {
            $brokerName = $brokerRepository->generateName();

            $supervisor = new BrokerSupervisor(
                $brokerName,
                $connection,
                $brokerRepository,
                $clientFactory,
                function ($type, $line) use ($connection) {
                    $this->output->writeln("[$connection] $line");
                }
            );

            $master->addSupervisor($supervisor);
        }

        // Display startup information
        $this->components->info(sprintf(
            'MQTT Broadcast started successfully for %d broker(s) in [%s] environment.',
            count($connections),
            $environment
        ));

        $this->line('Brokers: '.implode(', ', $connections));
        $this->newLine();

        // Register SIGINT handler for graceful shutdown (Ctrl+C)
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () use ($master) {
            $this->output->writeln('');
            $this->components->info('Shutting down...');

            return $master->terminate();
        });

        // Start monitoring (blocking, never returns normally)
        $master->monitor();
    }

    /**
     * Get broker connections configured for the given environment.
     *
     * Lazy validation approach (Horizon-style):
     * - Returns connections as-is from config
     * - If connection invalid, MqttClientFactory will throw exception
     * - Lets the system fail naturally with descriptive error
     *
     * @param  string  $environment  The environment name
     * @return array<string> Array of connection names
     */
    protected function getEnvironmentConnections(string $environment): array
    {
        $environments = config('mqtt-broadcast.environments', []);

        // Return connections for this environment, or empty array
        return $environments[$environment] ?? [];
    }
}
