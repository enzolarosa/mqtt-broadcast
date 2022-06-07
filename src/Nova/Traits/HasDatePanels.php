<?php

namespace App\Nova\Traits;

use Laravel\Nova\Fields\DateTime;

trait HasDatePanels
{
    protected function datePanels(bool $created = true, bool $updated = true, bool $deleted = true, array $others = [], array $showIndex = []): array
    {
        $obj = [];

        if ($created) {
            $createdAt = DateTime::make(localize('Created At'), 'created_at')
                ->sortable()
                ->hideWhenCreating()
                ->readonly()
                ->default(now())
                ->showOnPreview();

            if (!isset($showIndex['created_at'])) {
                $createdAt->hideFromIndex();
            }

            $obj = array_merge($obj, [$createdAt]);
        }

        if ($updated) {
            $updatedAt = DateTime::make(localize('Updated at'), 'updated_at')
                ->sortable()
                ->hideWhenCreating()
                ->readonly()
                ->default(now())
                ->showOnPreview();

            if (!isset($showIndex['updated_at'])) {
                $updatedAt->hideFromIndex();
            }

            $obj = array_merge($obj, [$updatedAt]);
        }

        if ($deleted) {
            $deteledAt = DateTime::make(localize('Deleted At'), 'deleted_at')
                ->sortable()
                ->hideWhenCreating()
                ->readonly()
                ->showOnPreview();

            if (!isset($showIndex['deleted_at'])) {
                $deteledAt->hideFromIndex();
            }

            $obj = array_merge($obj, [$deteledAt]);
        }

        return array_merge($obj, $others);
    }
}
