<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 10:01:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Actions;

use App\Actions\Traits\UI\WithBucketNavigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithNavigation
{
    use WithBucketNavigation;

    public function getPreviousModel(Model $model, ActionRequest $request): ?array
    {
        return $this->getNavigation(
            $this->getNavigationNeighbour($model, $request, forward: false),
            $request->route()->getName()
        );
    }

    public function getNextModel(Model $model, ActionRequest $request): ?array
    {
        return $this->getNavigation(
            $this->getNavigationNeighbour($model, $request, forward: true),
            $request->route()->getName()
        );
    }

    private function getNavigationNeighbour(Model $model, ActionRequest $request, bool $forward): ?Model
    {
        $query = $model::query();

        $this->applyNavigationFilters($query, $model, $request);

        if ($bucket = $request->input('bucket')) {
            $this->applyNavigationBucket($query, $bucket, $model, $request);
        }

        return $this->getBucketNeighbour(
            query: $query,
            model: $model,
            sort: $request->input('bucket_sort'),
            sortColumns: $this->getNavigationSortColumns($model),
            defaultSort: $this->getNavigationDefaultSort($model),
            forward: $forward
        );
    }

    /**
     * @return array{0: string|array<string>, 1: bool} the index default sort: qualified column(s) and whether descending
     */
    protected function getNavigationDefaultSort(Model $model): array
    {
        return [$model->getTable().'.'.$this->getNavigationComparisonColumn(), false];
    }

    protected function getNavigationComparisonColumn(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string|array<string>> request sort key => qualified column(s)
     */
    protected function getNavigationSortColumns(Model $model): array
    {
        return [];
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        // The default implementation does nothing.
    }

    protected function applyNavigationBucket(Builder $query, string $bucket, Model $model, ActionRequest $request): void
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
