<?php

namespace enzolarosa\MqttBroadcast\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MqttMessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        protected string $topic,
        protected string $message,
        protected string $broker = 'local',
        protected ?int $pid = null)
    {
    }

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

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setTopic(string $topic): self
    {
        $this->topic = $topic;

        return $this;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function setBroker(string $broker): self
    {
        $this->broker = $broker;

        return $this;
    }

    public function setPid(int $pid): self
    {
        $this->pid = $pid;

        return $this;
    }
}
