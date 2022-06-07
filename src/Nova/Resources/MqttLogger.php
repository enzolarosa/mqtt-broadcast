<?php

namespace enzolarosa\MqttBroadcast\Nova\Resources;

use App\Models\User;
use App\Nova\Filters\MqttBrokerFilter;
use App\Nova\Traits\HasDatePanels;
use enzolarosa\MqttBroadcast\Models\MqttLogger as MqttLoggerModel;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

class MqttLogger extends Resource
{
    use HasDatePanels;

    public static $model = MqttLoggerModel::class;
    public static $title = 'topic';

    public static $search = [
        'external_id', 'broker', 'topic',
    ];

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable()->hide(),

            Text::make(localize('External ID'), 'external_id')
                ->readonly()
                ->sortable()
                ->showOnPreview(),

            Text::make(localize('Broker'), 'broker')
                ->sortable()
                ->readonly()
                ->showOnPreview(),

            Text::make(localize('Topic'), 'topic')
                ->sortable()
                ->readonly()
                ->showOnPreview(),

            Code::make(localize('Message'), 'message')
                ->readonly()
                ->json(),

            new Panel(localize('Dates Information'), $this->datePanels(deleted: false, showIndex: [
                'created_at' => true,
                'updated_at' => true,
            ])),
        ];
    }

    public function filters(NovaRequest $request)
    {
        return [
            MqttBrokerFilter::make()->canSeeWhen('users canFilterMqttBroker', User::class),
        ];
    }
}
