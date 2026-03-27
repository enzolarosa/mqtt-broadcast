<?php

declare(strict_types=1);

namespace enzolarosa\MqttBroadcast\Models\Concerns;

use Illuminate\Support\Str;

trait HasExternalId
{
    public static function booted(): void
    {
        static::creating(function ($model) {
            $model->external_id ??= Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }
}
