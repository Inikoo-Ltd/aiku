<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Web\Announcement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithAnnouncementNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'created_at';
    }

    /**
     * Newest first, matching the default sort of the index the announcement was opened from.
     */
    protected function getNavigationDefaultSort(Model $model): array
    {
        return [$model->getTable().'.'.$this->getNavigationComparisonColumn(), true];
    }

    protected function getNavigationSortColumns(Model $model): array
    {
        return [
            'name'       => $model->getTable().'.name',
            'created_at' => $model->getTable().'.created_at',
            'closed_at'  => $model->getTable().'.closed_at',
        ];
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var Announcement $model */
        $query->where('announcements.website_id', $model->website_id);
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var Announcement $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var Announcement $announcement */
        $announcement = $model;

        return [
            'organisation' => $announcement->website->organisation->slug,
            'shop'         => $announcement->website->shop->slug,
            'website'      => $announcement->website->slug,
            'announcement' => $announcement->ulid,
        ];
    }
}
