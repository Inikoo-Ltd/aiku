<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Masters\MasterCollection;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCollectionsInMasterCollection extends OrgAction
{
    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('collections.name', $value)
                    ->orWhereStartWith('collections.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->where('collections.master_collection_id', $masterCollection->id);
        $queryBuilder->leftjoin('collection_stats', 'collections.id', 'collection_stats.collection_id');


        $queryBuilder
            ->leftJoin('webpages', function ($join) {
                $join->on('collections.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Collection')
                    ->whereNull('webpages.deleted_at');
            });

        $queryBuilder
            ->leftJoin('organisations', 'collections.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'collections.shop_id', '=', 'shops.id')
            ->leftJoin('websites', 'websites.shop_id', '=', 'shops.id');
        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.state',
                'collections.products_status',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
                'collection_stats.number_families',
                'collection_stats.number_products',
                'collection_stats.number_parents',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'webpages.id as webpage_id',
                'webpages.state as webpage_state',
                'webpages.url as webpage_url',
                'webpages.slug as webpage_slug',
                'websites.slug as website_slug',
            ])
            ->selectRaw(
                '(
        SELECT concat(string_agg(product_categories.slug,\',\'),\'|\',string_agg(product_categories.type,\',\'),\'|\',string_agg(product_categories.code,\',\'),\'|\',string_agg(product_categories.name,\',\')) FROM model_has_collections
        left join product_categories on model_has_collections.model_id = product_categories.id
        WHERE model_has_collections.collection_id = collections.id
   
        AND model_has_collections.model_type = ?
    ) as parents_data',
                ['ProductCategory',]
            );


        return $queryBuilder
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['code', 'name', 'number_parents', 'number_families', 'number_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __("No collections found"),
                        'description' => __('Get started by creating a new collection. ✨'),
                        'count'       => 0
                    ]
                );

            $table
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false);

            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
