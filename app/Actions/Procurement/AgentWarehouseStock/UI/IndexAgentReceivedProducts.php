<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-15 15:07
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithAgentWarehouseAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilderBase;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * What the agent has received from its suppliers and still holds: the goods in its warehouse, packed or not, and the
 * goods already put in a container that has not been dispatched yet.
 */
class IndexAgentReceivedProducts extends OrgAction
{
    use WithAgentOrganisation;
    use WithAgentWarehouseAuthorisation;
    use WithAgentWarehouseProductRows;

    private const array STAGED_CONTAINER_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    private Agent $agent;

    private function receivedItems(Agent $agent): QueryBuilderBase
    {
        $inWarehouse = DB::table('agent_warehouse_stocks')
            ->leftJoin('agent_goods_receipt_items', 'agent_goods_receipt_items.agent_warehouse_stock_id', 'agent_warehouse_stocks.id')
            ->leftJoin('agent_goods_receipts', 'agent_goods_receipts.id', 'agent_goods_receipt_items.agent_goods_receipt_id')
            ->where('agent_warehouse_stocks.agent_id', $agent->id)
            ->where('agent_warehouse_stocks.quantity', '>', 0)
            ->selectRaw("'warehouse-' || agent_warehouse_stocks.id as row_key")
            ->selectRaw("'warehouse' as location")
            ->selectRaw('agent_warehouse_stocks.state as warehouse_state')
            ->selectRaw('null::varchar as container_reference')
            ->selectRaw('null::varchar as container_slug')
            ->selectRaw('null::varchar as container_state')
            ->selectRaw('agent_warehouse_stocks.organisation_id::int as organisation_id')
            ->selectRaw('agent_warehouse_stocks.supplier_product_id::int as supplier_product_id')
            ->selectRaw('agent_warehouse_stocks.purchase_order_id::int as purchase_order_id')
            ->selectRaw('agent_warehouse_stocks.quantity::numeric as quantity')
            ->selectRaw('(agent_goods_receipt_items.id is not null and agent_goods_receipt_items.checked_at is null) as is_unchecked')
            ->selectRaw('agent_goods_receipts.reference as goods_receipt_reference')
            ->selectRaw('agent_goods_receipts.slug as goods_receipt_slug');

        $inContainers = DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->leftJoin('purchase_order_transactions as container_lines', fn ($join) => $join->on(DB::raw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint"), '=', 'container_lines.id'))
            ->where('stock_deliveries.agent_id', $agent->id)
            ->whereIn('stock_deliveries.state', array_map(fn (StockDeliveryStateEnum $state) => $state->value, self::STAGED_CONTAINER_STATES))
            ->whereNotIn('stock_delivery_items.state', [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value])
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->whereNotNull('stock_delivery_items.supplier_product_id')
            ->selectRaw("'container-' || stock_delivery_items.id as row_key")
            ->selectRaw("'container' as location")
            ->selectRaw('null::varchar as warehouse_state')
            ->selectRaw('stock_deliveries.reference as container_reference')
            ->selectRaw('stock_deliveries.slug as container_slug')
            ->selectRaw('stock_deliveries.state as container_state')
            ->selectRaw('stock_deliveries.organisation_id::int as organisation_id')
            ->selectRaw('stock_delivery_items.supplier_product_id::int as supplier_product_id')
            ->selectRaw('container_lines.purchase_order_id::int as purchase_order_id')
            ->selectRaw('stock_delivery_items.unit_quantity::numeric as quantity')
            ->selectRaw('false as is_unchecked')
            ->selectRaw('null::varchar as goods_receipt_reference')
            ->selectRaw('null::varchar as goods_receipt_slug');

        return $inWarehouse->unionAll($inContainers);
    }

    private function baseQuery(Agent $agent): Builder
    {
        return AgentWarehouseStock::query()->fromSub($this->receivedItems($agent), 'received_items');
    }

    public function handle(Agent $agent): LengthAwarePaginator
    {
        $query = $this->joinProductRowDetails(
            $this->baseQuery($agent)->select([
                'received_items.row_key',
                'received_items.location',
                'received_items.warehouse_state',
                'received_items.container_reference',
                'received_items.container_slug',
                'received_items.container_state',
                'received_items.quantity',
                'received_items.is_unchecked',
                'received_items.goods_receipt_reference',
                'received_items.goods_receipt_slug',
            ]),
            'received_items'
        );

        $filters   = $this->productRowFilters();
        $filters[] = AllowedFilter::callback('location', fn ($query, $value) => $query->where('received_items.location', $value));

        return $this->withProductImages(QueryBuilder::for($query)
            ->defaultSort('code')
            ->allowedSorts([
                AllowedSort::field('code', 'supplier_products.code'),
                AllowedSort::field('name', 'supplier_products.name'),
                AllowedSort::field('supplier_code', 'suppliers.code'),
                AllowedSort::field('organisation_code', 'organisations.code'),
                AllowedSort::field('quantity', 'received_items.quantity'),
                AllowedSort::field('location', 'received_items.location'),
            ])
            ->allowedFilters($filters)
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn (AgentWarehouseStock $row) => array_merge($this->productRow($row), [
                'id'                  => $row->row_key,
                'location'            => $row->location,
                'location_icon'       => $this->locationIcon($row),
                'container_reference' => $row->container_reference,
                'container_slug'      => $this->hideProcurementDetails ? null : $row->container_slug,
                'is_unchecked'        => (bool) $row->is_unchecked,
                'goods_receipt_reference' => $row->goods_receipt_reference,
                'goods_receipt_slug'      => $row->goods_receipt_slug,
            ])));
    }

    private function locationIcon(AgentWarehouseStock $row): array
    {
        if ($row->location === 'container') {
            $state = StockDeliveryStateEnum::from($row->container_state);

            return [
                'tooltip' => __('In container :reference, :state', ['reference' => $row->container_reference, 'state' => $state->labels()[$state->value] ?? $state->value]),
                'icon'    => 'fal fa-truck-container',
                'class'   => 'text-[--app-accent]',
            ];
        }

        return AgentWarehouseStockStateEnum::from($row->warehouse_state)->icon();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Product'), __('Products')])
                ->withEmptyState(['title' => __('Nothing received yet')])
                ->column(key: 'organisation_code', label: __('For'), canBeHidden: false, sortable: true)
                ->column(key: 'location', label: __('Where'), canBeHidden: false, sortable: true)
                ->column(key: 'image_thumbnail', label: __('Image'), canBeHidden: false)
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true)
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true)
                ->column(key: 'purchase_order_reference', label: __('Purchase order'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Units'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'skos', label: __('SKO'), align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        return $this->handle($agent);
    }

    public function htmlResponse(LengthAwarePaginator $rows, ActionRequest $request): Response
    {
        $counts = DB::query()->fromSub($this->receivedItems($this->agent), 'received_items')
            ->join('organisations', 'organisations.id', 'received_items.organisation_id')
            ->leftJoin('countries', 'countries.id', 'organisations.country_id')
            ->selectRaw('organisations.slug, organisations.code, organisations.name, countries.name as country_name, received_items.location, count(*) as total')
            ->groupBy('organisations.slug', 'organisations.code', 'organisations.name', 'countries.name', 'received_items.location')
            ->get();

        $organisationCounts = $counts->groupBy('slug')->map(fn ($rows) => (object) [
            'slug'         => $rows->first()->slug,
            'code'         => $rows->first()->code,
            'name'         => $rows->first()->name,
            'country_name' => $rows->first()->country_name,
            'total'        => $rows->sum('total'),
        ]);

        return $this->productRowsResponse(
            $rows,
            $request,
            __('Received'),
            'fal fa-inbox-in',
            'grp.org.agent.warehouse.received.index',
            'received',
            $this->organisationOptions($organisationCounts),
            [
                'location' => [
                    ['label' => __('In the warehouse'), 'value' => 'warehouse', 'count' => (int) $counts->where('location', 'warehouse')->sum('total')],
                    ['label' => __('In a container not dispatched'), 'value' => 'container', 'count' => (int) $counts->where('location', 'container')->sum('total')],
                ],
            ]
        )->with('receive', $this->canEdit ? IndexAgentProductsInProduction::receiveScopes($this->agent) : null)->table($this->tableStructure());
    }
}
