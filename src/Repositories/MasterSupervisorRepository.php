<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Repository for master supervisor state persistence.
 *
 * Uses cache (Redis/File/Memcached/Array) for state storage with configurable TTL.
 * Inspired by Laravel Horizon's repository pattern.
 *
 * @see \enzolarosa\MqttBroadcast\Supervisors\MasterSupervisor
 */
class MasterSupervisorRepository
{
    /**
     * Cache TTL in seconds.
     */
    protected int $ttl;

    /**
     * Create a new repository instance.
     */
    public function __construct()
    {
        $this->ttl = config('mqtt-broadcast.master_supervisor.cache_ttl', 3600);
    }

    /**
     * Update the master supervisor state in cache.
     */
    public function update(string $name, array $attributes): void
    {
        $data = array_merge($attributes, [
            'updated_at' => now()->toDateTimeString(),
        ]);

        Cache::put($this->cacheKey($name), $data, $this->ttl);
    }

    /**
     * Find a master supervisor by name.
     */
    public function find(string $name): ?array
    {
        return Cache::get($this->cacheKey($name));
    }

    /**
     * Remove a master supervisor from cache.
     */
    public function forget(string $name): void
    {
        Cache::forget($this->cacheKey($name));
    }

    /**
     * Get all master supervisors.
     */
    public function all(): Collection
    {
        $names = $this->names();

        return collect($names)->map(function (string $name) {
            return $this->find($name);
        })->filter();
    }

    /**
     * Get all master supervisor names.
     */
    public function names(): array
    {
        $prefix = 'mqtt-broadcast:master:';
        $keys = $this->getCacheKeys($prefix);

        return array_map(function (string $key) use ($prefix) {
            return str_replace($prefix, '', $key);
        }, $keys);
    }

    /**
     * Get the cache key for a supervisor.
     */
    protected function cacheKey(string $name): string
    {
        return "mqtt-broadcast:master:{$name}";
    }

    /**
     * Get all cache keys matching the prefix.
     *
     * Note: This implementation varies by cache driver.
     * Works with file, redis, and memcached drivers.
     */
    protected function getCacheKeys(string $prefix): array
    {
        $store = Cache::getStore();
        $driver = config('cache.default');

        return match ($driver) {
            'redis' => $this->getRedisKeys($prefix),
            'file' => $this->getFileKeys($prefix),
            'memcached' => $this->getMemcachedKeys($prefix),
            'array' => $this->getArrayKeys($prefix),
            default => [],
        };
    }

    /**
     * Get Redis cache keys.
     */
    protected function getRedisKeys(string $prefix): array
    {
        $store = Cache::getStore();

        if (! method_exists($store, 'connection')) {
            return [];
        }

        $connection = $store->connection();
        $fullPrefix = $store->getPrefix() . $prefix;

        $keys = $connection->keys($fullPrefix . '*');

        return array_map(function ($key) use ($store) {
            return str_replace($store->getPrefix(), '', $key);
        }, $keys);
    }

    /**
     * Get file cache keys.
     */
    protected function getFileKeys(string $prefix): array
    {
        $store = Cache::getStore();

        if (! method_exists($store, 'getDirectory')) {
            return [];
        }

        $directory = $store->getDirectory();
        $files = glob($directory . '/*');

        $keys = [];
        foreach ($files as $file) {
            // Skip directories, only process files
            if (! is_file($file)) {
                continue;
            }

            $key = $this->getKeyFromFile($file);
            if ($key && str_starts_with($key, $prefix)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Get memcached cache keys.
     */
    protected function getMemcachedKeys(string $prefix): array
    {
        // Memcached doesn't support key listing
        // This is a limitation of the driver
        return [];
    }

    /**
     * Get array cache keys (for testing).
     */
    protected function getArrayKeys(string $prefix): array
    {
        $store = Cache::getStore();

        // Use reflection to access the storage array
        try {
            $reflection = new \ReflectionClass($store);
            $property = $reflection->getProperty('storage');
            $property->setAccessible(true);
            $storage = $property->getValue($store);

            $keys = array_keys($storage);

            return array_filter($keys, function ($key) use ($prefix) {
                return str_starts_with($key, $prefix);
            });
        } catch (\ReflectionException $e) {
            return [];
        }
    }

    /**
     * Extract key from cache file path.
     */
    protected function getKeyFromFile(string $file): ?string
    {
        // Skip if not a file (e.g., directory)
        if (! is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        // Laravel file cache format: expiration + serialized data
        try {
            $data = unserialize(substr($contents, 10));

            return $data['key'] ?? null;
        } catch (\Throwable $e) {
            // Log warning for corrupted cache files but continue gracefully
            logger()->warning('Failed to deserialize cache file', [
                'file' => basename($file),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
