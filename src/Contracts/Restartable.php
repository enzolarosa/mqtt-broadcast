<?php

namespace enzolarosa\MqttBroadcast\Contracts;

interface Restartable
{
    /**
     * Restart the process.
     *
     * @return void
     */
    public function restart();
}
