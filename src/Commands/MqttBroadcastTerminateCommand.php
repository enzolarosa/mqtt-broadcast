<?php

namespace enzolarosa\MqttBroadcast\Commands;

use enzolarosa\MqttBroadcast\Contracts\MqttSupervisorRepository;
use enzolarosa\MqttBroadcast\MqttSupervisor;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mqtt-broadcast:terminate')]
class MqttBroadcastTerminateCommand extends Command
{
    public $signature = 'mqtt-broadcast:terminate';

    protected $description = 'Terminate the master supervisor so it can be restarted';

    public function handle(MqttSupervisorRepository $supervisor)
    {
        $mqtts = collect($supervisor->all())->filter(function ($master) {
            return Str::startsWith($master->name, MqttSupervisor::basename());
        })->all();

        collect(Arr::pluck($mqtts, 'pid'))
            ->whenNotEmpty(fn () => $this->components->info('Sending TERM signal to processes.'))
            ->whenEmpty(fn () => $this->components->info('No processes to terminate.'))
            ->each(function ($processId) {
                $result = true;

                $this->components->task("Process: $processId", function () use ($processId, &$result) {
                    return $result = posix_kill($processId, SIGTERM);
                });

                if (! $result) {
                    $this->components->error("Failed to kill process: {$processId} (".posix_strerror(posix_get_last_error()).')');
                }
            })->whenNotEmpty(fn () => $this->output->writeln(''));
    }
}
