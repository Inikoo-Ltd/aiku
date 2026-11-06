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
use Spatie\QueryBuilder\AllowedSort;

class IndexCompetitorProducts extends OrgAction
{
    use WithMastersAuthorisation;

    public function handle(Competitor $competitor, ?string $prefix = null, bool $onSaleOnly = false): LengthAwarePaginator
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
            ->when($onSaleOnly, fn ($query) => $query->whereColumn('competitor_products.was_price', '>', 'competitor_products.price'))
            ->select([
                'competitor_products.id',
                'competitor_products.code',
                'competitor_products.name',
                'competitor_products.url',
                'competitor_products.image_url',
                'competitor_products.price',
                'competitor_products.was_price',
                'competitor_products.rrp',
                'competitor_products.units',
                'competitor_products.minimum_order',
                'competitor_products.fetched_at',
            ])
            ->selectRaw('CASE WHEN competitor_products.was_price > competitor_products.price THEN round(100 * (competitor_products.was_price - competitor_products.price) / competitor_products.was_price) END as discount')
            ->whereElementGroup(
                key: 'on_sale',
                allowedElements: ['yes'],
                engine: fn ($query) => $query->whereColumn('competitor_products.was_price', '>', 'competitor_products.price'),
                prefix: $prefix,
                optional: true
            )
            ->allowedFilters([$globalSearch])
            ->defaultSort($onSaleOnly ? '-discount' : 'name')
            ->allowedSorts(['name', 'code', 'price', 'units', 'fetched_at', AllowedSort::callback('discount', fn ($query, bool $descending) => $query->orderByRaw('discount '.($descending ? 'desc nulls last' : 'asc nulls first')))])
            ->withPaginator($prefix, numberOfRecords: $onSaleOnly ? 10 : null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Competitor $competitor, ?string $prefix = null, bool $onSaleOnly = false): Closure
    {
        return function (InertiaTable $table) use ($competitor, $prefix, $onSaleOnly) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            if ($onSaleOnly) {
                $table
                    ->withEmptyState(['title' => __('Nothing on offer right now'), 'description' => __('Offers show when the shop lists a price before the discount')])
                    ->column(key: 'name', label: __('Product'), sortable: true)
                    ->column(key: 'was_price', label: __('Was'), align: 'right')
                    ->column(key: 'price', label: __('Now'), sortable: true, align: 'right')
                    ->column(key: 'discount', label: __('Off'), sortable: true, align: 'right')
                    ->defaultSort('-discount');

                return;
            }

            $table
                ->withGlobalSearch()
                ->elementGroup(key: 'on_sale', label: __('On sale'), elements: ['yes' => [__('On sale now'), $competitor->products()->whereColumn('was_price', '>', 'price')->count()]], optional: true)
                ->withEmptyState([
                    'title'       => __('Nothing read from this competitor yet'),
                    'description' => __('Product feeds and Shopify or WooCommerce catalogues are read every morning, website searches every week'),
                ])
                ->column(key: 'name', label: __('Product'), sortable: true, searchable: true)
                ->column(key: 'was_price', label: __('Was'), align: 'right')
                ->column(key: 'price', label: __('Price'), sortable: true, align: 'right')
                ->column(key: 'discount', label: __('Off'), sortable: true, align: 'right')
                ->column(key: 'units', label: __('Units'), sortable: true, align: 'right')
                ->column(key: 'fetched_at', label: __('Last read'), sortable: true)
                ->defaultSort('name');
        };
    }
}
