<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Web\WebsiteDialog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithWebsiteDialogNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'created_at';
    }

    protected function getNavigationDefaultSort(Model $model): array
    {
        return [$model->getTable().'.'.$this->getNavigationComparisonColumn(), true];
    }

    protected function getNavigationSortColumns(Model $model): array
    {
        return [
            'name'       => $model->getTable().'.name',
            'created_at' => $model->getTable().'.created_at',
            'live_at'    => $model->getTable().'.live_at',
            'closed_at'  => $model->getTable().'.closed_at',
        ];
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var WebsiteDialog $model */
        $query->where('website_dialogs.website_id', $model->website_id);
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var WebsiteDialog $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var WebsiteDialog $model */
        return [
            'organisation'  => $model->website->organisation->slug,
            'shop'          => $model->website->shop->slug,
            'website'       => $model->website->slug,
            'websiteDialog' => $model->ulid,
        ];
    }
}
