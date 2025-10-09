<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 May 2025 19:56:14 Central Indonesia Time, Sanur, Plane KL-Shanghai
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterProductsInMasterCollection extends OrgAction
{
    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_assets.name', $value)
                    ->orWhereStartWith('master_assets.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterAsset::class);

        $queryBuilder->join('master_collection_has_models', function ($join) {
            $join->on('master_assets.id', '=', 'master_collection_has_models.model_id')
                ->where('master_collection_has_models.model_type', '=', 'MasterAsset');

        });
        $queryBuilder->where('master_collection_has_models.master_collection_id', '=', $masterCollection->id);


        $queryBuilder
            ->defaultSort('master_assets.code')
            ->select([
                'master_assets.id',
                'master_assets.code',
                'master_assets.name',
                'master_assets.price',
                'master_assets.created_at',
                'master_assets.updated_at',
                'master_assets.slug',
            ])
            ->leftJoin('master_asset_stats', 'master_assets.id', 'master_asset_stats.master_asset_id');

        return $queryBuilder->allowedSorts(['code', 'name'])
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
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("Collection doesn't have any families"),
                        'count' => 0,
                    ]
                );

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);

            if ($action) {
                $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }



}
