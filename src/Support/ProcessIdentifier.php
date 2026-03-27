<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Support;

use Illuminate\Support\Str;

class ProcessIdentifier
{
    /**
     * Get the current process ID.
     */
    public static function pid(): int
    {
        return getmypid();
    }

    /**
     * Get the slugified hostname of the current machine.
     */
    public static function hostname(): string
    {
        return Str::slug(gethostname());
    }

    /**
     * Generate a unique name for the current process.
     *
     * Format:
     * - Without prefix: "hostname-token"
     * - With prefix: "prefix-hostname-token"
     *
     * The token is generated once per process execution and remains consistent
     * across multiple calls to maintain process identity.
     *
     * @param  string|null  $prefix  Optional prefix for the generated name
     */
    public static function generateName(?string $prefix = null): string
    {
        static $token;

        if (! $token) {
            $token = Str::random(4);
        }

        $hostname = static::hostname();

        // Handle empty string as null (no prefix)
        if ($prefix === '' || $prefix === null) {
            return "{$hostname}-{$token}";
        }

        // Slugify prefix to handle whitespace and special characters
        $slugifiedPrefix = Str::slug($prefix);

        return "{$slugifiedPrefix}-{$hostname}-{$token}";
    }
}
