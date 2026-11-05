<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\Competitor;
use App\Models\Masters\CompetitorProduct;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCompetitorProducts extends OrgAction
{
    use WithMastersAuthorisation;

    public function handle(Competitor $competitor, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('competitor_products.code', $value)
                    ->orWhereAnyWordStartWith('competitor_products.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(CompetitorProduct::class)
            ->where('competitor_products.competitor_id', $competitor->id)
            ->select([
                'competitor_products.id',
                'competitor_products.code',
                'competitor_products.name',
                'competitor_products.url',
                'competitor_products.image_url',
                'competitor_products.price',
                'competitor_products.rrp',
                'competitor_products.units',
                'competitor_products.minimum_order',
                'competitor_products.fetched_at',
            ])
            ->allowedFilters([$globalSearch])
            ->defaultSort('name')
            ->allowedSorts(['name', 'code', 'price', 'units', 'fetched_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('Nothing read from this competitor yet'),
                    'description' => __('Product feeds and Shopify catalogues are read every morning, website searches every week'),
                ])
                ->column(key: 'name', label: __('Product'), sortable: true, searchable: true)
                ->column(key: 'price', label: __('Price'), sortable: true, align: 'right')
                ->column(key: 'units', label: __('Units'), sortable: true, align: 'right')
                ->column(key: 'fetched_at', label: __('Last read'), sortable: true)
                ->defaultSort('name');
        };
    }
}
