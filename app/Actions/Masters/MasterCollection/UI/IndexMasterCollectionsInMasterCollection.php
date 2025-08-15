<?php

/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-11h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterCollection;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterCollectionsInMasterCollection extends OrgAction
{
    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_collections.name', $value)
                    ->orWhereStartWith('master_collections.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterCollection::class);

        $queryBuilder->join('master_collection_has_models', function ($join) {
            $join->on('master_collections.id', '=', 'master_collection_has_models.model_id')
                ->where('master_collection_has_models.model_type', '=', 'MasterCollection');

        });
        $queryBuilder->where('master_collection_has_models.master_collection_id', '=', $masterCollection->id);

        return $queryBuilder
            ->defaultSort('master_collections.code')
            ->select([
                'master_collections.id',
                'master_collections.slug',
                'master_collections.code',
                'master_collections.name',
            ])
            ->allowedSorts(['code', 'name',])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterCollection $masterCollection, $prefix = null, $action = true): Closure
    {
        return function (InertiaTable $table) use ($masterCollection, $prefix, $action) {
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
                        'count' => 0,
                    ]
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            if ($action) {
                $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);
            }


        };
    }
}
