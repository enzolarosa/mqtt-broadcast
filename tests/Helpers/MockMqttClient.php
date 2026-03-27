<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Tests\Helpers;

class MockMqttClient
{
    protected bool $connected = false;

    public array $publishedMessages = [];

    public array $subscribedTopics = [];

    public function connect($settings = null, bool $cleanSession = true): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void
    {
        if (! $this->connected) {
            throw new \RuntimeException('Not connected to MQTT broker');
        }

        $this->publishedMessages[] = [
            'topic' => $topic,
            'message' => $message,
            'qos' => $qos,
            'retain' => $retain,
            'timestamp' => time(),
        ];
    }

    public function subscribe(string $topic, callable $callback, int $qos = 0): void
    {
        if (! $this->connected) {
            throw new \RuntimeException('Not connected to MQTT broker');
        }

        $this->subscribedTopics[] = [
            'topic' => $topic,
            'callback' => $callback,
            'qos' => $qos,
        ];
    }

    public function loopOnce(int $timeout = 0): void
    {
        // No-op for testing
    }

    public function getPublishedMessage(int $index = 0): ?array
    {
        return $this->publishedMessages[$index] ?? null;
    }

    public function getLastPublishedMessage(): ?array
    {
        return end($this->publishedMessages) ?: null;
    }

    public function assertPublished(string $topic, ?string $message = null, ?int $qos = null): void
    {
        $found = false;

        foreach ($this->publishedMessages as $published) {
            if ($published['topic'] === $topic) {
                if ($message !== null && $published['message'] !== $message) {
                    continue;
                }

                if ($qos !== null && $published['qos'] !== $qos) {
                    continue;
                }

                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new \RuntimeException("Message not published to topic: {$topic}");
        }
    }

    public function assertNotPublished(string $topic): void
    {
        foreach ($this->publishedMessages as $published) {
            if ($published['topic'] === $topic) {
                throw new \RuntimeException("Message was published to topic: {$topic}");
            }
        }
    }

    public function clearPublished(): void
    {
        $this->publishedMessages = [];
    }

    public function getPublishedCount(): int
    {
        return count($this->publishedMessages);
    }
}
