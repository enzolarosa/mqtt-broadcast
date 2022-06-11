<?php

namespace enzolarosa\MqttBroadcast\Commands;

use Illuminate\Console\Command;

class MqttBroadcastInstallCommand extends Command
{
    protected $signature = 'mqtt-broadcast:install';

    protected $description = 'Install all of the MQTT Broadcasat resources';

    public function handle()
    {
        $this->comment('Publishing MQTT Broadcast configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'mqtt-broadcast-config']);

        $this->comment('Publishing MQTT Broadcast views...');
        $this->callSilent('vendor:publish', ['--tag' => 'mqtt-broadcast-views']);

        $this->comment('Publishing MQTT Broadcast migrations...');
        $this->callSilent('vendor:publish', ['--tag' => 'mqtt-broadcast-migrations']);

        $this->comment('Publishing MQTT Broadcast translations...');
        $this->callSilent('vendor:publish', ['--tag' => 'mqtt-broadcast-translations']);

        $this->comment('Publishing MQTT Broadcast assets...');
        $this->callSilent('vendor:publish', ['--tag' => 'mqtt-broadcast-assets']);

        $this->info('MQTT Broadcast was installed successfully.');
    }
}
