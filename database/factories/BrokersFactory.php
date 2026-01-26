<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Database\Factories;

use enzolarosa\MqttBroadcast\Models\Brokers;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrokersFactory extends Factory
{
    protected $model = Brokers::class;

    public function definition(): array
    {
        return [
            'name' => Str::slug(fake()->word()).'-'.Str::random(4),
            'connection' => 'default',
            'pid' => fake()->numberBetween(1000, 99999),
            'started_at' => now(),
            'last_heartbeat_at' => now(),
            'working' => true,
        ];
    }

    /**
     * Indicate that the broker is not working.
     */
    public function stopped(): static
    {
        return $this->state(fn (array $attributes) => [
            'working' => false,
        ]);
    }

    /**
     * Indicate that the broker has a stale heartbeat.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);
    }
}
