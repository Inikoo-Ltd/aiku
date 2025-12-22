<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 10:01:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithNavigation
{
    public function getPreviousModel(Model $model, ActionRequest $request): ?array
    {
        $previous = $model::where($this->getNavigationComparisonColumn(), '<', $model->{$this->getNavigationComparisonColumn()})
            ->when(true, function (Builder $query) use ($model, $request) {
                $this->applyNavigationFilters($query, $model, $request);
            })
            ->orderBy($this->getNavigationComparisonColumn(), 'desc')
            ->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNextModel(Model $model, ActionRequest $request): ?array
    {
        $next = $model::where($this->getNavigationComparisonColumn(), '>', $model->{$this->getNavigationComparisonColumn()})
            ->when(true, function (Builder $query) use ($model, $request) {
                $this->applyNavigationFilters($query, $model, $request);
            })
            ->orderBy($this->getNavigationComparisonColumn())
            ->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    protected function getNavigationComparisonColumn(): string
    {
        return 'slug';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        // The default implementation does nothing.
    }

    protected function getNavigation(?Model $model, string $routeName): ?array
    {
        if (!$model) {
            return null;
        }

        return [
            'label' => $this->getNavigationLabel($model),
            'route' => [
                'name'       => $routeName,
                'parameters' => $this->getNavigationRouteParameters($model, $routeName),
            ],
        ];
    }



    protected function getNavigationLabel(Model $model): string
    {
        return $model->slug;
    }

    abstract protected function getNavigationRouteParameters(Model $model, string $routeName): array;
}
