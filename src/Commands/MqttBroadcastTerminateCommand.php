<?php

namespace enzolarosa\MqttBroadcast\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MqttBroadcastTerminateCommand extends Command
{
    public $signature = 'mqtt-broadcast:terminate {broker}';
    protected $description = 'Terminate the master supervisor so it can be restarted';

    public function handle()
    {
        $broker = $this->argument('broker');

        $processId = Cache::get("mqtt_listener_$broker");

        if (!posix_kill($processId, SIGTERM)) {
            $this->error("Failed to kill process: {$processId} (" . posix_strerror(posix_get_last_error()) . ')');
        }
    }
}
