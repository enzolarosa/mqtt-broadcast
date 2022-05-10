<?php

namespace enzolarosa\MqttBroadcast\Traits\Models;

use Illuminate\Database\Eloquent\Builder;

trait ColumnEmpty
{
    public function scopeEmpty(Builder $query, string $column)
    {
        return $query->where(function (Builder $query) use ($column) {
            $query->whereNull($column)
                ->orWhere($column, '');
        });
    }
}
