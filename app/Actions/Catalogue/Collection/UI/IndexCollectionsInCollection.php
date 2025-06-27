<?php

/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-11h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCollectionsInCollection extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Collection $collection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Collection::class);

        $queryBuilder->join('collection_has_models', function ($join) {
            $join->on('collections.id', '=', 'collection_has_models.model_id')
                ->where('collection_has_models.model_type', '=', 'Collection');

        });
        $queryBuilder->where('collection_has_models.collection_id', '=', $collection->id);

        return $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.slug',
                'collections.code',
                'collections.name',
            ])
            ->allowedSorts(['code', 'name',])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Collection $collection, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($collection, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->defaultSort('code')
                ->withEmptyState(
                    [
                        'title' => __("Collection doesn't have any collections"),
                        'count' => $collection->stats->number_collections,
                    ]
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);


        };
    }
}
