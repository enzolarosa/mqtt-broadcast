<?php

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\Brokers;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mqtt-broadcast', description: 'Mqtt Broadcast Command')]
class MqttBroadcastCommand extends Command
{
    public $signature = 'mqtt-broadcast {broker}';

    protected $description = 'Listener for mqtt message';

    public function handle(Brokers $brokers)
    {
        if ($brokers->find(Brokers::name())) {
            return $this->components->warn('A master supervisor is already running on this machine.');
        }

        $broker = $this->argument('broker');

        $master = (new Brokers)
            ->make($broker)
            ->handleOutputUsing(function ($type, $line) {
                $this->output->writeln($line);
            });

        $this->components->info(sprintf('Mqtt Broadcast for %s broker started successfully.', $broker));

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () use ($master) {
            $this->output->writeln('');

            $this->components->info('Shutting down.');

            return $master->terminate();
        });

        $master->monitor();
    }
}
