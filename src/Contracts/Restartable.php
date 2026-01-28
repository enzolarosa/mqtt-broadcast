<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Contracts;

interface Restartable
{
    /**
     * Restart the process.
     *
     * @return void
     */
    public function restart(): void;
}
