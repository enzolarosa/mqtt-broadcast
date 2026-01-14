<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Contracts;

interface Terminable
{
    /**
     * Terminate the process.
     *
     * @param  int  $status
     * @return void
     */
    public function terminate($status = 0);
}
