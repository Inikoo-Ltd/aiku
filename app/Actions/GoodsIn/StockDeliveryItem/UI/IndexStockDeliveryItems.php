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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;

class IndexStockDeliveryItems extends OrgAction
{
    protected function getElementGroups(StockDelivery $stockDelivery, bool $byProduct = false): array
    {
        $productCounts = $byProduct ? self::productCountsByState($stockDelivery) : null;

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => collect(StockDeliveryItemStateEnum::cases())->mapWithKeys(
                    fn (StockDeliveryItemStateEnum $state) => [
                        $state->value => [
                            StockDeliveryItemStateEnum::labels()[$state->value],
                            $productCounts ? $productCounts->get($state->value, 0) : $stockDelivery->{'number_stock_delivery_items_state_'.$state->snake()},
                        ],
                    ]
                )->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('stock_delivery_items.state', $elements);
                },
            ],
        ];
    }

    public static function productCountsByState(StockDelivery $stockDelivery): Collection
    {
        return $stockDelivery->items()
            ->groupBy('state')
            ->selectRaw("state, count(distinct coalesce(org_stock_id::text, 'line-'||id)) as products")
            ->pluck('products', 'state');
    }

    public static function numberOfProducts(StockDelivery $stockDelivery): int
    {
        return (int) $stockDelivery->items()->selectRaw("count(distinct coalesce(org_stock_id::text, 'line-'||id)) as products")->value('products');
    }

    /**
     * With $groupByProduct each row is a product: a product shipped from two purchase orders sits on two lines of the
     * delivery, and the page lists, sorts and pages it once, with its lines loaded as productLines.
     */
    public function handle(StockDelivery $parent, ?string $prefix = null, array|StockDeliveryItemStateEnum|null $stateFilter = null, ?int $numberOfRecords = null, bool $groupByProduct = false): LengthAwarePaginator
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

        $query = $this->joinItemDetails(QueryBuilder::for(StockDeliveryItem::class));
        $query->where('stock_delivery_items.stock_delivery_id', $parent->id);

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

        if ($groupByProduct) {
            return $this->productsPaginator($query, $globalSearch, $prefix, $numberOfRecords);
        }

        $paginator = $this->withItemColumns($query)
            ->defaultSort('org_stocks.code')
            ->allowedSorts([
                AllowedSort::custom('code', $this->sortSql('coalesce(sp.code, org_stocks.code)')),
                AllowedSort::field('part', 'org_stocks.code'),
                'org_stock_code',
                'org_stock_name',
                'unit_quantity',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, $numberOfRecords, tableName: request()->route()->getName())
            ->withQueryString();

        $this->setOrgStockLocations($paginator->getCollection());

        return $paginator;
    }

    private function productsPaginator(QueryBuilder $query, AllowedFilter $globalSearch, ?string $prefix, ?int $numberOfRecords): LengthAwarePaginator
    {
        $paginator = $query
            ->groupByRaw("coalesce(stock_delivery_items.org_stock_id::text, 'line-'||stock_delivery_items.id)")
            ->selectRaw('min(stock_delivery_items.id) as id')
            ->selectRaw("string_agg(stock_delivery_items.id::text, ',' order by stock_delivery_items.id) as product_line_ids")
            ->defaultSort($codeSort = AllowedSort::custom('code', $this->sortSql('min(coalesce(sp.code, org_stocks.code))')))
            ->allowedSorts([
                $codeSort,
                AllowedSort::custom('part', $this->sortSql('min(org_stocks.code)')),
                AllowedSort::custom('org_stock_code', $this->sortSql('min(org_stocks.code)')),
                AllowedSort::custom('org_stock_name', $this->sortSql('min(org_stocks.name)')),
                AllowedSort::custom('unit_quantity', $this->sortSql('sum(stock_delivery_items.unit_quantity)')),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, $numberOfRecords, tableName: request()->route()->getName())
            ->withQueryString();

        $lineIds = $paginator->getCollection()->mapWithKeys(fn (StockDeliveryItem $product) => [$product->id => array_map('intval', explode(',', $product->product_line_ids))]);

        $lines = $this->withItemColumns($this->joinItemDetails(QueryBuilder::for(StockDeliveryItem::class)))
            ->whereIn('stock_delivery_items.id', $lineIds->flatten())
            ->orderBy('stock_delivery_items.id')
            ->get()
            ->keyBy('id');
        $this->setOrgStockLocations($lines);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (StockDeliveryItem $product) => $lines[$product->id]->setRelation('productLines', collect($lineIds[$product->id])->map(fn (int $id) => $lines[$id])))
        );

        return $paginator;
    }

    private function joinItemDetails(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->leftJoin('org_stocks', 'stock_delivery_items.org_stock_id', 'org_stocks.id')
            ->leftJoin('supplier_products as sp', 'sp.id', '=', 'stock_delivery_items.supplier_product_id')
            ->leftJoin('stocks', 'stocks.id', '=', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', '=', 'stocks.stock_family_id')
            ->leftJoin('locations', 'locations.id', '=', 'org_stocks.picking_location_id')
            ->leftJoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id');
    }

    private function withItemColumns(QueryBuilder $query): QueryBuilder
    {
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

        return $query
            ->leftJoin('purchase_order_transactions as line_order_transaction', 'line_order_transaction.id', '=', DB::raw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint"))
            ->leftJoin('purchase_orders as line_purchase_order', 'line_purchase_order.id', '=', 'line_order_transaction.purchase_order_id')
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
                'stock_delivery_items.cbm_in_container',
                'stock_delivery_items.packed_inside_stock_delivery_item_id',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.has_been_in_warehouse',
                DB::raw('coalesce(stock_families.is_batch_tracked, false) as is_batch_tracked'),
                'warehouse_areas.code as warehouse_area_code',
                'warehouse_areas.picking_position as warehouse_area_picking_position',
                'line_purchase_order.reference as purchase_order_reference',
                'line_purchase_order.slug as purchase_order_slug',
            ])
            ->selectSub($weight, 'weight')
            ->selectRaw('round(sp.cbm * stock_delivery_items.unit_quantity / nullif(sp.units_per_carton, 0), 2) as volume');
    }

    private function sortSql(string $sql): Sort
    {
        return new class ($sql) implements Sort {
            public function __construct(private readonly string $sql)
            {
            }

            public function __invoke(Builder $query, bool $descending, string $property): void
            {
                $query->orderByRaw($this->sql.' '.($descending ? 'desc' : 'asc'));
            }
        };
    }

    private function setOrgStockLocations(Collection $items): void
    {
        $locationsByOrgStock = StockDeliveryItemResource::locationsQuery()
            ->whereIn('location_org_stocks.org_stock_id', $items->pluck('org_stock_id')->filter()->unique())
            ->get()
            ->groupBy('org_stock_id');

        foreach ($items as $item) {
            $item->setRelation('orgStockLocations', $locationsByOrgStock->get($item->org_stock_id, collect()));
        }
    }

    /**
     * Without booking in (the procurement page) a delivery in the warehouse lists what was ordered, checked
     * and placed, with nothing to type; the counting and placing columns belong to goods in.
     */
    public function tableStructure(StockDelivery $stockDelivery, ?string $prefix = null, bool $withCosting = true, bool $bookingIn = true): Closure
    {
        return function (InertiaTable $table) use ($stockDelivery, $prefix, $withCosting, $bookingIn) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $byProduct = !$bookingIn && !($withCosting && $stockDelivery->state === StockDeliveryStateEnum::PLACED);

            foreach ($this->getElementGroups($stockDelivery, $byProduct) as $key => $elementGroup) {
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

            if ($withCosting && $stockDelivery->state === StockDeliveryStateEnum::PLACED) {
                $this->costingColumns($table, $stockDelivery);

                return;
            }

            $table->column(key: 'state_icon', label: ['fal', 'fa-yin-yang'], canBeHidden: false, type: 'icon');

            if (!$bookingIn && in_array($stockDelivery->state, $goodsInStates, true)) {
                $table
                    ->column(key: 'code', label: __('S. Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'description', label: __('Unit description'), canBeHidden: false)
                    ->column(key: 'quantity', label: __('Qty'), canBeHidden: false)
                    ->column(key: 'purchase_orders', label: __('Purchase orders'), canBeHidden: false)
                    ->column(key: 'checked_quantity', label: __('Checked'), canBeHidden: false)
                    ->column(key: 'placed_quantity', label: __('Placed'), canBeHidden: false)
                    ->column(key: 'amount', label: __('Amount'), canBeHidden: false)
                    ->defaultSort('code');
            } elseif (in_array($stockDelivery->state, $goodsInStates, true)) {
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
                    ->column(key: 'quantity', label: __('Qty'), canBeHidden: false);

                if (!$bookingIn) {
                    $table->column(key: 'purchase_orders', label: __('Purchase orders'), canBeHidden: false);
                }

                $table
                    ->column(key: 'weight', label: __('Weight'), canBeHidden: false)
                    ->column(key: 'volume', label: __('CBM / in container'), canBeHidden: false)
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
