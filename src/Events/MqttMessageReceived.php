<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MqttMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        protected readonly string $topic,
        protected readonly string $message,
        protected readonly string $broker = 'local',
        protected readonly ?int $pid = null) {}

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getBroker(): string
    {
        return $this->broker;
    }

    public function getPid(): ?int
    {
        return $this->pid;
    }
}
