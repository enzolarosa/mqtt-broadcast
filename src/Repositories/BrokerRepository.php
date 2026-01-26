<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Repositories;

use enzolarosa\MqttBroadcast\Models\Brokers;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Repository for broker persistence operations.
 *
 * Handles CRUD operations for MQTT broker records in the database.
 * Inspired by Laravel Horizon's repository pattern.
 *
 * @see \enzolarosa\MqttBroadcast\Models\Brokers
 */
class BrokerRepository
{
    /**
     * Create a new broker record.
     *
     * @param  string  $name  Unique broker identifier
     * @param  string  $connection  MQTT connection name
     * @return Brokers The created broker instance
     */
    public function create(string $name, string $connection): Brokers
    {
        return Brokers::create([
            'name' => $name,
            'connection' => $connection,
            'pid' => getmypid(),
            'started_at' => now(),
            'last_heartbeat_at' => now(),
            'working' => true,
        ]);
    }

    /**
     * Find a broker by name.
     *
     * @param  string  $name  Broker name to search for
     * @return Brokers|null The broker instance or null if not found
     */
    public function find(string $name): ?Brokers
    {
        return Brokers::where('name', $name)->first();
    }

    /**
     * Get all brokers.
     *
     * @return Collection<int, Brokers> Collection of all broker instances
     */
    public function all(): Collection
    {
        return Brokers::all();
    }

    /**
     * Update broker heartbeat timestamp.
     *
     * Updates the last_heartbeat_at field to indicate the broker is still alive.
     * Used for health monitoring and stale process detection.
     * Silent fail if broker doesn't exist (Horizon pattern).
     *
     * @param  string  $name  Broker name
     */
    public function touch(string $name): void
    {
        Brokers::where('name', $name)
            ->update(['last_heartbeat_at' => now()]);
    }

    /**
     * Delete broker record by name.
     *
     * Silent fail if broker doesn't exist (Horizon pattern).
     *
     * @param  string  $name  Broker name to delete
     */
    public function delete(string $name): void
    {
        Brokers::where('name', $name)->delete();
    }

    /**
     * Delete broker record by process ID.
     *
     * Useful for cleanup when terminating by PID.
     * May delete multiple records if multiple brokers share the same PID (edge case).
     * Silent fail if no broker with that PID exists (Horizon pattern).
     *
     * @param  int  $pid  Process ID to delete
     */
    public function deleteByPid(int $pid): void
    {
        Brokers::where('pid', $pid)->delete();
    }

    /**
     * Generate a unique broker name.
     *
     * Format: {hostname-slug}-{random-token}
     * Example: "johns-macbook-a3f2"
     *
     * Maintains compatibility with existing Brokers::name() logic.
     *
     * @return string Generated broker name
     */
    public function generateName(): string
    {
        $hostname = Str::slug(gethostname());
        $token = Str::lower(Str::random(4));

        return "{$hostname}-{$token}";
    }
}
