<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 17 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDeliveryItem\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStockDeliveryItems extends OrgAction
{
    public function handle(StockDelivery $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(StockDeliveryItem::class);
        $query->where('stock_delivery_items.stock_delivery_id', $parent->id);

        $query->leftJoin('org_stocks', 'stock_delivery_items.org_stock_id', 'org_stocks.id');

        return $query
            ->defaultSort('org_stocks.code')
            ->select([
                'stock_delivery_items.id',
                'stock_delivery_items.state',
                'stock_delivery_items.unit_quantity',
                'stock_delivery_items.unit_quantity_checked',
                'stock_delivery_items.unit_quantity_placed',
                'stock_delivery_items.org_stock_id',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
            ])
            ->allowedSorts(['org_stock_code', 'org_stock_name', 'unit_quantity'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(StockDelivery $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No items found'),
                    'icon'  => 'fal fa-bars',
                ]);

            $table
                ->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unit_quantity', label: __('Quantity'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('org_stock_code');
        };
    }
}
