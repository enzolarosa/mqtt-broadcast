<?php

namespace enzolarosa\MqttBroadcast\Commands;

use Illuminate\Console\Command;

class MqttBroadcastCommand extends Command
{
    public $signature = 'mqtt-broadcast';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
