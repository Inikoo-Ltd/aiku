<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 27 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDeliveryItem\UI;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexStockDeliveryUnderOverDeliveredItems extends OrgAction
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
        $query->leftJoin('supplier_products as sp', 'sp.id', '=', 'stock_delivery_items.supplier_product_id');
        $query->whereNotNull('stock_delivery_items.checked_at');
        $query->where('stock_delivery_items.state', '!=', StockDeliveryItemStateEnum::CANCELLED);
        $query->whereColumn('stock_delivery_items.unit_quantity_checked', '!=', 'stock_delivery_items.unit_quantity');

        $query->with(['supplierProduct']);

        return $query
            ->defaultSort('org_stocks.code')
            ->select([
                'stock_delivery_items.id',
                'stock_delivery_items.supplier_product_id',
                'stock_delivery_items.unit_quantity',
                'stock_delivery_items.unit_quantity_checked',
                'stock_delivery_items.org_stock_id',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
            ])
            ->selectRaw('stock_delivery_items.unit_quantity_checked - stock_delivery_items.unit_quantity as difference_units')
            ->selectRaw('round((stock_delivery_items.unit_quantity_checked - stock_delivery_items.unit_quantity) / nullif(sp.units_per_pack, 0), 3) as difference_skos')
            ->selectRaw('round((stock_delivery_items.unit_quantity_checked - stock_delivery_items.unit_quantity) * 100 / nullif(stock_delivery_items.unit_quantity, 0), 1) as difference_percentage')
            ->allowedSorts([
                AllowedSort::field('part', 'org_stocks.code'),
                AllowedSort::field('delivered_quantity', 'stock_delivery_items.unit_quantity'),
                AllowedSort::field('checked_quantity', 'stock_delivery_items.unit_quantity_checked'),
                'difference_units',
                'difference_skos',
                'difference_percentage',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('All items were delivered as expected'),
                    'icon'  => 'fal fa-box-open',
                ])
                ->column(key: 'part', label: __('Part'), canBeHidden: false, sortable: true)
                ->column(key: 'description', label: __('Unit description'), canBeHidden: false)
                ->column(key: 'delivered_quantity', label: __('Delivery Qty'), canBeHidden: false, sortable: true)
                ->column(key: 'checked_quantity', label: __('Actual checked Qty'), canBeHidden: false, sortable: true)
                ->column(key: 'difference_percentage', label: __('Diff'), canBeHidden: false, sortable: true)
                ->column(key: 'difference_units', label: __('Units'), canBeHidden: false, sortable: true)
                ->column(key: 'difference_skos', label: __('SKOs'), canBeHidden: false, sortable: true)
                ->defaultSort('part');
        };
    }
}
