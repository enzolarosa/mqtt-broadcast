<?php

namespace enzolarosa\MqttBroadcast\Nova\Metrics;

use Carbon\Carbon;
use enzolarosa\MqttBroadcast\Models\MqttLogger;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Progress;

class MqttMessageProgress extends Progress
{
    public string $broker = 'local';
    public int $target = 100;
    public ?Carbon $startDate = null;

    public function calculate(NovaRequest $request)
    {
        return $this->count($request, MqttLogger::class, function ($query) {
            $query->where('broker', $this->broker);
            if ($this->startDate) {
                $query->where('created_at', '>=', $this->startDate);
            }

            return $query;
        }, target: $this->target);
    }
}
