<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 17 Jul 2026, Bali, Indonesia
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
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexStockDeliveryItems extends OrgAction
{
    protected function getElementGroups(StockDelivery $stockDelivery): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => collect(StockDeliveryItemStateEnum::cases())->mapWithKeys(
                    fn (StockDeliveryItemStateEnum $state) => [
                        $state->value => [
                            StockDeliveryItemStateEnum::labels()[$state->value],
                            $stockDelivery->{'number_stock_delivery_items_state_'.$state->snake()},
                        ],
                    ]
                )->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('stock_delivery_items.state', $elements);
                },
            ],
        ];
    }

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

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
            );
        }

        $query->with([
            'supplierProduct.currency',
            'supplierProduct.supplier',
            'organisation.currency',
        ]);

        $weight = DB::table('model_has_trade_units as mhtu')
            ->join('trade_units as tu', 'tu.id', '=', 'mhtu.trade_unit_id')
            ->whereColumn('mhtu.model_id', 'stock_delivery_items.org_stock_id')
            ->where('mhtu.model_type', 'OrgStock')
            ->selectRaw('
                case
                    when count(*) = 0 or count(*) filter (where tu.gross_weight is null) > 0 then null
                    else round(sum(tu.gross_weight * mhtu.quantity) * stock_delivery_items.unit_quantity / 1000, 1)
                end
            ');

        return $query
            ->defaultSort('org_stocks.code')
            ->select([
                'stock_delivery_items.id',
                'stock_delivery_items.state',
                'stock_delivery_items.supplier_product_id',
                'stock_delivery_items.unit_quantity',
                'stock_delivery_items.unit_quantity_checked',
                'stock_delivery_items.unit_quantity_placed',
                'stock_delivery_items.net_amount',
                'stock_delivery_items.org_net_amount',
                'stock_delivery_items.org_exchange',
                'stock_delivery_items.org_stock_id',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
            ])
            ->selectSub($weight, 'weight')
            ->selectRaw('round(sp.cbm * stock_delivery_items.unit_quantity / nullif(sp.units_per_carton, 0), 2) as volume')
            ->allowedSorts([
                AllowedSort::field('code', 'sp.code'),
                'org_stock_code',
                'org_stock_name',
                'unit_quantity',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(StockDelivery $stockDelivery, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($stockDelivery, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($stockDelivery) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                );
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No items found'),
                    'icon'  => 'fal fa-bars',
                ]);

            $table
                ->column(key: 'code', label: __('S. Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'description', label: __('Unit description'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Qty'), canBeHidden: false)
                ->column(key: 'weight', label: __('Weight'), canBeHidden: false)
                ->column(key: 'volume', label: __('CBM'), canBeHidden: false)
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false)
                ->column(key: 'state', label: __('State'), canBeHidden: false)
                ->defaultSort('code');
        };
    }
}
