<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 17 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDeliveryItem\UI;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;

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

    public function handle(StockDelivery $parent, ?string $prefix = null, array|StockDeliveryItemStateEnum|null $stateFilter = null, ?int $numberOfRecords = null): LengthAwarePaginator
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
        $query->leftJoin('stocks', 'stocks.id', '=', 'org_stocks.stock_id');
        $query->leftJoin('stock_families', 'stock_families.id', '=', 'stocks.stock_family_id');
        $query->leftJoin('locations', 'locations.id', '=', 'org_stocks.picking_location_id');
        $query->leftJoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id');

        if ($stateFilter) {
            if (is_array($stateFilter)) {
                $query->whereIn('stock_delivery_items.state', $stateFilter);
            } else {
                $query->where('stock_delivery_items.state', $stateFilter);
            }
        }

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
            'organisation.warehouses',
            'orgStock:id,slug,packed_in',
            'stockDelivery.currency',
            'sowings' => fn ($sowings) => $sowings->where('type', SowingTypeEnum::SOW)->orderBy('id')->with('location'),
            'batches.batchCode',
        ]);

        $weight = DB::table('model_has_trade_units as mhtu')
            ->join('trade_units as tu', 'tu.id', '=', 'mhtu.trade_unit_id')
            ->join('org_stocks as pack', 'pack.id', '=', 'mhtu.model_id')
            ->whereColumn('mhtu.model_id', 'stock_delivery_items.org_stock_id')
            ->where('mhtu.model_type', 'OrgStock')
            ->selectRaw('
                case
                    when count(*) = 0 or count(*) filter (where tu.gross_weight is null) > 0 then null
                    else round(sum(tu.gross_weight * mhtu.quantity) * stock_delivery_items.unit_quantity / coalesce(nullif(max(pack.packed_in), 0), 1) / 1000, 1)
                end
            ');

        $paginator = $query
            ->defaultSort('org_stocks.code')
            ->select([
                'stock_delivery_items.id',
                'stock_delivery_items.organisation_id',
                'stock_delivery_items.stock_delivery_id',
                'stock_delivery_items.state',
                'stock_delivery_items.cost_items',
                'stock_delivery_items.cost_extra',
                'stock_delivery_items.cost_shipping',
                'stock_delivery_items.cost_duties',
                'stock_delivery_items.cost_tax',
                'stock_delivery_items.cost_total',
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
                'org_stocks.has_been_in_warehouse',
                DB::raw('coalesce(stock_families.is_batch_tracked, false) as is_batch_tracked'),
                'warehouse_areas.code as warehouse_area_code',
                'warehouse_areas.picking_position as warehouse_area_picking_position',
            ])
            ->selectSub($weight, 'weight')
            ->selectRaw('round(sp.cbm * stock_delivery_items.unit_quantity / nullif(sp.units_per_carton, 0), 2) as volume')
            ->allowedSorts([
                AllowedSort::custom('code', new class () implements Sort {
                    public function __invoke(Builder $query, bool $descending, string $property): void
                    {
                        $query->orderByRaw('coalesce(sp.code, org_stocks.code) '.($descending ? 'desc' : 'asc'));
                    }
                }),
                AllowedSort::field('part', 'org_stocks.code'),
                'org_stock_code',
                'org_stock_name',
                'unit_quantity',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, $numberOfRecords, tableName: request()->route()->getName())
            ->withQueryString();

        $locationsByOrgStock = StockDeliveryItemResource::locationsQuery()
            ->whereIn('location_org_stocks.org_stock_id', $paginator->getCollection()->pluck('org_stock_id')->filter()->unique())
            ->get()
            ->groupBy('org_stock_id');

        foreach ($paginator->getCollection() as $item) {
            $item->setRelation('orgStockLocations', $locationsByOrgStock->get($item->org_stock_id, collect()));
        }

        return $paginator;
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

            $goodsInStates = [
                StockDeliveryStateEnum::RECEIVED,
                StockDeliveryStateEnum::CHECKED,
                StockDeliveryStateEnum::BOOKING_IN,
                StockDeliveryStateEnum::BOOKED_IN,
            ];

            $itemActionStates = [
                StockDeliveryStateEnum::IN_PROCESS,
                StockDeliveryStateEnum::CONFIRMED,
                StockDeliveryStateEnum::READY_TO_SHIP,
            ];

            if ($stockDelivery->state === StockDeliveryStateEnum::PLACED) {
                $this->costingColumns($table, $stockDelivery);

                return;
            }

            $table->column(key: 'state_icon', label: ['fal', 'fa-yin-yang'], canBeHidden: false, type: 'icon');

            if (in_array($stockDelivery->state, $goodsInStates, true)) {
                $table
                    ->column(key: 'part', label: __('Part'), canBeHidden: false, sortable: true)
                    ->column(key: 'description', label: __('Unit description'), canBeHidden: false)
                    ->column(key: 'delivered_quantity', label: __('Delivered Quantity'), canBeHidden: false)
                    ->column(key: 'sowings', label: __('Sowings'), canBeHidden: false)
                    ->column(key: 'checked_unit', label: __('Checked SKOs'), canBeHidden: false, align: 'right')
                    ->column(key: 'placement', label: __('Placement'), canBeHidden: false, align: 'right')
                    ->defaultSort('part');
            } else {
                $table
                    ->column(key: 'code', label: __('S. Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'description', label: __('Unit description'), canBeHidden: false)
                    ->column(key: 'quantity', label: __('Qty'), canBeHidden: false)
                    ->column(key: 'weight', label: __('Weight'), canBeHidden: false)
                    ->column(key: 'volume', label: __('CBM'), canBeHidden: false)
                    ->column(key: 'amount', label: __('Amount'), canBeHidden: false);

                if (in_array($stockDelivery->state, $itemActionStates, true)) {
                    $table->column(key: 'actions', label: __('Actions'), canBeHidden: false, align: 'right');
                }

                $table->defaultSort('code');
            }
        };
    }

    private function costingColumns(InertiaTable $table, StockDelivery $stockDelivery): void
    {
        $currency = $stockDelivery->currency?->code;

        $costLabel = fn (string $label) => $currency ? $label.' ('.$currency.')' : $label;

        $table
            ->column(key: 'part', label: __('Part'), canBeHidden: false, sortable: true)
            ->column(key: 'description', label: __('Unit description'), canBeHidden: false)
            ->column(key: 'units_in', label: __('Units In'), canBeHidden: false)
            ->column(key: 'cost_items', label: $costLabel(__('Items')), canBeHidden: false)
            ->column(key: 'cost_extra', label: $costLabel(__('Extra')), canBeHidden: false)
            ->column(key: 'cost_shipping', label: $costLabel(__('Shipping')), canBeHidden: false)
            ->column(key: 'cost_duties', label: $costLabel(__('Duties')), canBeHidden: false)
            ->column(key: 'cost_tax', label: $costLabel(__('Tax')), canBeHidden: false)
            ->column(key: 'cost_total', label: $costLabel(__('Total')), canBeHidden: false, align: 'right')
            ->column(key: 'cost_per_sko_org', label: __('Landed cost / SKO').' ('.$stockDelivery->organisation->currency->code.')', canBeHidden: false, align: 'right');

        if (!$stockDelivery->is_costed) {
            $table->column(key: 'actions', label: __('Actions'), canBeHidden: false, align: 'right');
        }

        $table->defaultSort('part');
    }
}
