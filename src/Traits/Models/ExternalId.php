<?php

namespace enzolarosa\MqttBroadcast\Traits\Models;

use Illuminate\Support\Str;

trait ExternalId
{
    public static function booted()
    {
        static::creating(function ($model) {
            $model->external_id ??= Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'external_id';
    }
}
