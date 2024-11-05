<?php

namespace enzolarosa\MqttBroadcast\Repositories;

use Carbon\CarbonImmutable;
use enzolarosa\MqttBroadcast\Contracts\MqttSupervisorRepository;
use enzolarosa\MqttBroadcast\MqttSupervisor;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Arr;
use stdClass;

class RedisMqttSupervisorRepository implements MqttSupervisorRepository
{
    /**
     * The Redis connection instance.
     *
     * @var RedisFactory
     */
    public $redis;

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(RedisFactory $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Get the names of all the master supervisors currently running.
     *
     * @return array
     */
    public function names()
    {
        return $this->connection()->zrevrangebyscore('mqtts', '+inf',
            CarbonImmutable::now()->subSeconds(14)->getTimestamp()
        );
    }

    /**
     * Get information on all the supervisors.
     *
     * @return array
     */
    public function all()
    {
        return $this->get($this->names());
    }

    /**
     * Get information on a master supervisor by name.
     *
     * @param  string  $name
     * @return stdClass|null
     */
    public function find($name)
    {
        return Arr::get($this->get([$name]), 0);
    }

    /**
     * Get information on the given master supervisors.
     *
     * @return array
     */
    public function get(array $names)
    {
        $records = $this->connection()->pipeline(function ($pipe) use ($names) {
            foreach ($names as $name) {
                $pipe->hmget('mqtt:'.$name, ['name', 'pid', 'status', 'supervisors', 'environment']);
            }
        });

        return collect($records)->map(function ($record) {
            $record = array_values($record);

            return ! $record[0] ? null : (object) [
                'name' => $record[0],
                'environment' => $record[4],
                'pid' => $record[1],
                'status' => $record[2],
                'supervisors' => json_decode($record[3], true),
            ];
        })->filter()->all();
    }

    /**
     * Update the information about the given master supervisor.
     *
     * @return void
     */
    public function update(MqttSupervisor $supervisor)
    {
        $supervisors = $supervisor->supervisors->map->name->all();

        $this->connection()->pipeline(function ($pipe) use ($supervisor, $supervisors) {
            $pipe->hmset(
                'mqtt:'.$supervisor->name, [
                    'name' => $supervisor->name,
                    'environment' => $supervisor->environment,
                    'pid' => $supervisor->pid(),
                    'status' => $supervisor->working ? 'running' : 'paused',
                    'supervisors' => json_encode($supervisors),
                ]
            );

            $pipe->zadd('mqtts',
                CarbonImmutable::now()->getTimestamp(), $supervisor->name
            );

            $pipe->expire('mqtt:'.$supervisor->name, 15);
        });
    }

    /**
     * Remove the master supervisor information from storage.
     *
     * @param  string  $name
     * @return void
     */
    public function forget($name)
    {
        if (! $master = $this->find($name)) {
            return;
        }

        app(MqttSupervisorRepository::class)->forget(
            $master->supervisors
        );

        $this->connection()->del('mqtt:'.$name);

        $this->connection()->zrem('mqtts', $name);
    }

    /**
     * Remove expired master supervisors from storage.
     *
     * @return void
     */
    public function flushExpired()
    {
        $this->connection()->zremrangebyscore('mqtts', '-inf',
            CarbonImmutable::now()->subSeconds(14)->getTimestamp()
        );
    }

    /**
     * Get the Redis connection instance.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function connection()
    {
        return $this->redis->connection('horizon');
    }
}
